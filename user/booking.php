<?php
require_once __DIR__ . '/../config/koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'user') {
    header("Location: " . BASE_URL . "auth/login.php?err=akses_ditolak");
    exit;
}

$id_user = $_SESSION['id_user'];
$error = '';
$success = '';

// Ambil foto profil terbaru langsung dari database (biar selalu sinkron)
$stmtFoto = $koneksi->prepare("SELECT foto FROM tb_user WHERE id_user = ?");
$stmtFoto->execute([$id_user]);
$fotoProfil = $stmtFoto->fetch()['foto'] ?? null;

$stmtKendaraan = $koneksi->prepare("SELECT * FROM tb_kendaraan WHERE id_user = ? ORDER BY plat_nomor");
$stmtKendaraan->execute([$id_user]);
$daftarKendaraan = $stmtKendaraan->fetchAll();

$area = $koneksi->query("SELECT * FROM tb_area_parkir ORDER BY nama_area")->fetchAll();

// ==== TAMBAH BOOKING ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    $mode_kendaraan     = $_POST['mode_kendaraan'] ?? 'lama';
    $id_area            = $_POST['id_area'] ?? '';
    $tanggal_booking    = $_POST['tanggal_booking'] ?? '';
    $jam_booking_masuk  = $_POST['jam_booking_masuk'] ?? '';
    $jam_booking_keluar = trim($_POST['jam_booking_keluar'] ?? '');
    $catatan            = trim($_POST['catatan'] ?? '');

    if ($id_area === '' || $tanggal_booking === '' || $jam_booking_masuk === '') {
        $error = 'Area, tanggal, dan jam masuk booking wajib diisi.';
    } elseif (strtotime($tanggal_booking) < strtotime(date('Y-m-d'))) {
        $error = 'Tanggal booking tidak boleh di masa lalu.';
    } elseif ($jam_booking_keluar !== '' && $jam_booking_keluar <= $jam_booking_masuk) {
        $error = 'Jam keluar harus lebih besar dari jam masuk.';
    } else {
        // ==== CEK KAPASITAS AREA (area terbatas) ====
        $stmtArea = $koneksi->prepare("SELECT nama_area, kapasitas FROM tb_area_parkir WHERE id_area = ?");
        $stmtArea->execute([$id_area]);
        $areaInfo = $stmtArea->fetch();

        $stmtHitung = $koneksi->prepare(
            "SELECT COUNT(*) AS jumlah FROM tb_booking
             WHERE id_area = ? AND tanggal_booking = ? AND status IN ('menunggu','dikonfirmasi')"
        );
        $stmtHitung->execute([$id_area, $tanggal_booking]);
        $jumlahTerpakai = (int) $stmtHitung->fetch()['jumlah'];

        if ($areaInfo && $jumlahTerpakai >= (int) $areaInfo['kapasitas']) {
            $error = 'Maaf, area "' . htmlspecialchars($areaInfo['nama_area']) . '" sudah penuh pada tanggal tersebut. Silakan pilih area atau tanggal lain.';
        } else {
            $id_kendaraan = null;

            if ($mode_kendaraan === 'baru') {
                $plat_nomor      = strtoupper(trim($_POST['plat_nomor_baru'] ?? ''));
                $jenis_kendaraan = $_POST['jenis_kendaraan_baru'] ?? '';
                $warna           = trim($_POST['warna_baru'] ?? '');

                if ($plat_nomor === '' || $jenis_kendaraan === '') {
                    $error = 'Plat nomor dan jenis kendaraan wajib diisi.';
                } else {
                    $cekPlat = $koneksi->prepare("SELECT id_kendaraan FROM tb_kendaraan WHERE plat_nomor = ?");
                    $cekPlat->execute([$plat_nomor]);
                    $existing = $cekPlat->fetch();

                    if ($existing) {
                        $id_kendaraan = $existing['id_kendaraan'];
                    } else {
                        $stmtK = $koneksi->prepare(
                            "INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user)
                             VALUES (?, ?, ?, ?, ?)"
                        );
                        $stmtK->execute([$plat_nomor, $jenis_kendaraan, $warna ?: null, $_SESSION['nama_lengkap'], $id_user]);
                        $id_kendaraan = $koneksi->lastInsertId();
                    }
                }
            } else {
                $id_kendaraan = $_POST['id_kendaraan'] ?? '';
                if ($id_kendaraan === '') {
                    $error = 'Silakan pilih kendaraan.';
                } else {
                    $cekKendaraan = $koneksi->prepare("SELECT id_kendaraan FROM tb_kendaraan WHERE id_kendaraan = ? AND id_user = ?");
                    $cekKendaraan->execute([$id_kendaraan, $id_user]);
                    if (!$cekKendaraan->fetch()) {
                        $error = 'Kendaraan tidak valid.';
                    }
                }
            }

            if (!$error && $id_kendaraan) {
                // cek ulang kapasitas sesaat sebelum insert (mencegah race condition sederhana)
                $stmtHitung->execute([$id_area, $tanggal_booking]);
                $jumlahTerpakaiFinal = (int) $stmtHitung->fetch()['jumlah'];

                if ($areaInfo && $jumlahTerpakaiFinal >= (int) $areaInfo['kapasitas']) {
                    $error = 'Maaf, area "' . htmlspecialchars($areaInfo['nama_area']) . '" baru saja penuh. Silakan pilih area atau tanggal lain.';
                } else {
                    $stmt = $koneksi->prepare(
                        "INSERT INTO tb_booking (id_user, id_kendaraan, id_area, tanggal_booking, jam_booking_masuk, jam_booking_keluar, catatan, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'menunggu')"
                    );
                    $stmt->execute([$id_user, $id_kendaraan, $id_area, $tanggal_booking, $jam_booking_masuk, $jam_booking_keluar ?: null, $catatan ?: null]);
                    $success = 'Booking berhasil dibuat. Menunggu konfirmasi dari petugas.';

                    // refresh daftar kendaraan kalau baru ditambahkan
                    $stmtKendaraan->execute([$id_user]);
                    $daftarKendaraan = $stmtKendaraan->fetchAll();
                }
            }
        }
    }
}

// ==== BATALKAN BOOKING ====
if (isset($_GET['batal'])) {
    $id_booking = $_GET['batal'];
    $stmt = $koneksi->prepare("UPDATE tb_booking SET status = 'dibatalkan' WHERE id_booking = ? AND id_user = ? AND status = 'menunggu'");
    $stmt->execute([$id_booking, $id_user]);
    header("Location: booking.php?sukses=Booking berhasil dibatalkan");
    exit;
}

if (isset($_GET['sukses'])) $success = $_GET['sukses'];
if (isset($_GET['gagal'])) $error = $_GET['gagal'];

$stmtBooking = $koneksi->prepare(
    "SELECT b.*, k.plat_nomor, k.jenis_kendaraan, k.warna, a.nama_area
     FROM tb_booking b
     JOIN tb_kendaraan k ON b.id_kendaraan = k.id_kendaraan
     LEFT JOIN tb_area_parkir a ON b.id_area = a.id_area
     WHERE b.id_user = ?
     ORDER BY b.id_booking DESC"
);
$stmtBooking->execute([$id_user]);
$daftarBooking = $stmtBooking->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Booking Parkir - Parkir Terminal Parangtritis System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        /* ===== Komponen khusus halaman Booking — tema Senja Dermaga ===== */
        .page-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 26px 0 22px;
        }
        .page-head .ph-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--tp-blue-dark), var(--tp-blue));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            box-shadow: 0 8px 18px rgba(190,78,44,.3);
            flex-shrink: 0;
        }
        .page-head h4 { margin: 0; font-family: 'Fraunces', serif; font-weight: 600; }
        .page-head p { margin: 2px 0 0; color: var(--tp-muted); font-size: .88rem; }

        /* Toggle mode kendaraan — pengganti nav-pills Bootstrap biru */
        .mode-toggle { display: flex; gap: 8px; margin-bottom: 18px; }
        .mode-toggle button {
            flex: 1;
            border: 1px solid var(--tp-blue);
            background: transparent;
            color: var(--tp-blue);
            font-family: 'Space Mono', monospace;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
            padding: 9px 10px;
            border-radius: 6px;
            transition: all .15s ease;
        }
        .mode-toggle button.active {
            background: var(--tp-blue);
            color: #fff;
        }

        .form-label { font-size: .82rem; font-weight: 600; color: #1C2B27; }

        #infoSlotArea { font-size: .8rem; margin-top: 6px; }

        /* Badge status booking — palet Senja Dermaga */
        .badge-status {
            font-size: .72rem;
            font-weight: 600;
            padding: 5px 11px;
            border-radius: 20px;
            letter-spacing: .3px;
        }
        .badge-menunggu     { background: var(--tp-cyan); color: #16262B; }
        .badge-dikonfirmasi { background: var(--tp-blue); color: #fff; }
        .badge-selesai      { background: var(--tp-sea); color: #fff; }
        .badge-dibatalkan   { background: #d8d2c4; color: #6b6355; }

        /* Toast notifikasi — samakan warna dengan tema, bukan bg-primary biru */
        .toast.toast-tp {
            background: linear-gradient(120deg, var(--tp-dark) 0%, var(--tp-blue-dark) 100%);
            color: #fff;
        }

        .table thead th { white-space: nowrap; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-user">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>user/index.php">
            <i class="bi bi-p-circle"></i> TERMINAL PARANGTRITIS PARKING
        </a>
        <div class="d-flex align-items-center">
            <a href="<?= BASE_URL ?>user/index.php" class="btn btn-outline-light btn-sm me-2">
                <i class="bi bi-house"></i> Dashboard
            </a>
            <a href="<?= BASE_URL ?>user/edit_profil.php" class="btn btn-outline-light btn-sm me-2">
                <i class="bi bi-person-gear"></i> Edit Profil
            </a>
            <span class="text-light me-3 d-flex align-items-center">
                <?php if (!empty($fotoProfil)): ?>
                    <span class="avatar-mini avatar-mini-img me-2" style="width:28px;height:28px;">
                        <img src="<?= BASE_URL ?>uploads/profil/<?= htmlspecialchars($fotoProfil) ?>" alt="Foto Profil">
                    </span>
                <?php else: ?>
                    <i class="bi bi-person-circle me-1"></i>
                <?php endif; ?>
                <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>
            </span>
            <a href="<?= BASE_URL ?>auth/logout.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right"></i> Keluar
            </a>
        </div>
    </div>
</nav>

<!-- Toast Notifikasi -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
    <div id="toastNotif" class="toast toast-tp align-items-center border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toastNotifPesan">Notifikasi</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<div class="container pb-5">

    <div class="page-head">
        <div class="ph-icon"><i class="bi bi-calendar-plus"></i></div>
        <div>
            <h4>Booking Parkir</h4>
            <p>Reservasi slot parkir kendaraan anda sebelum datang ke Terminal Parangtritis.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card card-tp">
                <div class="card-header">
                    <i class="bi bi-plus-circle"></i> Buat Booking Baru
                </div>
                <div class="card-body">
                    <form method="POST" id="formBooking">
                        <input type="hidden" name="aksi" value="tambah">
                        <input type="hidden" name="mode_kendaraan" id="mode_kendaraan" value="<?= count($daftarKendaraan) > 0 ? 'lama' : 'baru' ?>">

                        <?php if (count($daftarKendaraan) > 0): ?>
                        <div class="mode-toggle">
                            <button type="button" id="tab-lama" class="active" onclick="pilihModeKendaraan('lama')">Kendaraan Saya</button>
                            <button type="button" id="tab-baru" onclick="pilihModeKendaraan('baru')">Kendaraan Baru</button>
                        </div>
                        <?php endif; ?>

                        <!-- Pilih kendaraan terdaftar -->
                        <div id="blok-kendaraan-lama" class="mb-3" style="<?= count($daftarKendaraan) === 0 ? 'display:none;' : '' ?>">
                            <label class="form-label">Kendaraan</label>
                            <select name="id_kendaraan" class="form-select">
                                <option value="" selected disabled>Pilih kendaraan</option>
                                <?php foreach ($daftarKendaraan as $k): ?>
                                    <option value="<?= $k['id_kendaraan'] ?>">
                                        <?= htmlspecialchars($k['plat_nomor']) ?> — <?= htmlspecialchars($k['jenis_kendaraan']) ?><?= $k['warna'] ? ' (' . htmlspecialchars($k['warna']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Tambah kendaraan baru langsung -->
                        <div id="blok-kendaraan-baru" style="<?= count($daftarKendaraan) > 0 ? 'display:none;' : '' ?>">
                            <div class="mb-3">
                                <label class="form-label">Plat Nomor</label>
                                <input type="text" name="plat_nomor_baru" class="form-control text-uppercase" placeholder="Contoh: B 1234 XYZ">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Jenis Kendaraan</label>
                                <select name="jenis_kendaraan_baru" class="form-select">
                                    <option value="" selected disabled>Pilih jenis</option>
                                    <option value="Motor">Motor</option>
                                    <option value="Mobil">Mobil</option>
                                    <option value="Truk/Bus">Truk/Bus</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Warna (opsional)</label>
                                <input type="text" name="warna_baru" class="form-control" maxlength="20">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Area Parkir</label>
                            <select name="id_area" id="id_area" class="form-select" required>
                                <option value="" selected disabled>Pilih area</option>
                                <?php foreach ($area as $a): ?>
                                    <option value="<?= $a['id_area'] ?>"><?= htmlspecialchars($a['nama_area']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="infoSlotArea" class="form-text"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal Booking</label>
                            <input type="date" name="tanggal_booking" id="tanggal_booking" class="form-control"
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Jam Masuk</label>
                                <input type="time" name="jam_booking_masuk" id="jam_booking_masuk" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Jam Keluar (estimasi)</label>
                                <input type="time" name="jam_booking_keluar" id="jam_booking_keluar" class="form-control">
                            </div>
                        </div>
                        <div class="form-text mb-3">
                            <i class="bi bi-exclamation-triangle" style="color: var(--tp-cyan);"></i>
                            Kalau datang <strong>5 jam atau lebih</strong> dari jam booking di atas, akan dikenakan <strong>denda telat flat</strong> (sekali kena, tidak berkali-lipat).
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan (opsional)</label>
                            <textarea name="catatan" class="form-control" rows="2" placeholder="Contoh: butuh slot dekat pintu masuk"></textarea>
                        </div>
                        <button type="submit" id="btnAjukanBooking" class="btn btn-tp w-100">
                            <i class="bi bi-calendar-check"></i> Ajukan Booking
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-tp">
                <div class="card-header">
                    <i class="bi bi-list-check"></i> Riwayat Booking Saya
                </div>
                <div class="card-body p-0">
                    <?php if (count($daftarBooking) === 0): ?>
                        <p class="text-muted text-center py-4 mb-0">Belum ada booking yang dibuat.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Kendaraan</th>
                                    <th>Area</th>
                                    <th>Tanggal</th>
                                    <th>Jam Masuk</th>
                                    <th>Jam Keluar</th>
                                    <th>Catatan</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daftarBooking as $b): ?>
                                <tr id="booking-row-<?= $b['id_booking'] ?>">
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($b['plat_nomor']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($b['jenis_kendaraan']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($b['nama_area'] ?: '-') ?></td>
                                    <td><?= date('d M Y', strtotime($b['tanggal_booking'])) ?></td>
                                    <td><?= date('H:i', strtotime($b['jam_booking_masuk'])) ?></td>
                                    <td><?= $b['jam_booking_keluar'] ? date('H:i', strtotime($b['jam_booking_keluar'])) : '<span class="text-muted">-</span>' ?></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($b['catatan'] ?: '-') ?></small></td>
                                    <td>
                                        <?php
                                        $badgeClass = ['menunggu' => 'badge-menunggu', 'dikonfirmasi' => 'badge-dikonfirmasi', 'selesai' => 'badge-selesai', 'dibatalkan' => 'badge-dibatalkan'];
                                        $label = ['menunggu' => 'Menunggu', 'dikonfirmasi' => 'Dikonfirmasi', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
                                        ?>
                                        <span class="badge badge-status <?= $badgeClass[$b['status']] ?>" data-id="<?= $b['id_booking'] ?>"><?= $label[$b['status']] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($b['status'] === 'menunggu'): ?>
                                        <a href="?batal=<?= $b['id_booking'] ?>" class="btn btn-sm btn-outline-tp btn-batal-konfirmasi">
                                            <i class="bi bi-x-circle"></i> Batalkan
                                        </a>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/sound-effect.js"></script>
<script>
function pilihModeKendaraan(mode) {
    document.getElementById('mode_kendaraan').value = mode;
    document.getElementById('blok-kendaraan-lama').style.display = (mode === 'lama') ? '' : 'none';
    document.getElementById('blok-kendaraan-baru').style.display = (mode === 'baru') ? '' : 'none';
    document.getElementById('tab-lama').classList.toggle('active', mode === 'lama');
    document.getElementById('tab-baru').classList.toggle('active', mode === 'baru');
}

document.querySelectorAll('.btn-batal-konfirmasi').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        if (!confirm('Yakin ingin membatalkan booking ini?')) e.preventDefault();
    });
});

// ==== NOTIFIKASI SUARA + STATUS UNTUK USER ====
function bunyikanNotifikasi() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.2, ctx.currentTime);
        osc.start();
        osc.stop(ctx.currentTime + 0.3);
    } catch (e) { /* browser belum mengizinkan audio */ }
}

function tampilkanToast(pesan) {
    document.getElementById('toastNotifPesan').textContent = pesan;
    const toast = new bootstrap.Toast(document.getElementById('toastNotif'));
    toast.show();
}

const labelStatus = { menunggu: 'Menunggu', dikonfirmasi: 'Dikonfirmasi', selesai: 'Selesai', dibatalkan: 'Dibatalkan' };
const kelasBadge = { menunggu: 'badge-menunggu', dikonfirmasi: 'badge-dikonfirmasi', selesai: 'badge-selesai', dibatalkan: 'badge-dibatalkan' };

function cekStatusBooking() {
    fetch('cek_status_booking.php')
        .then(res => res.json())
        .then(data => {
            let statusLama = JSON.parse(localStorage.getItem('statusBookingSaya') || '{}');
            let statusBaru = {};

            data.forEach(function (b) {
                statusBaru[b.id_booking] = b.status;

                if (statusLama[b.id_booking] && statusLama[b.id_booking] !== b.status) {
                    bunyikanNotifikasi();
                    if (b.status === 'dikonfirmasi') {
                        tampilkanToast('Booking anda telah dikonfirmasi petugas!');
                    } else if (b.status === 'dibatalkan') {
                        tampilkanToast('Booking anda dibatalkan/ditolak petugas.');
                    }
                    const badgeEl = document.querySelector('.badge-status[data-id="' + b.id_booking + '"]');
                    if (badgeEl) {
                        badgeEl.className = 'badge badge-status ' + kelasBadge[b.status];
                        badgeEl.textContent = labelStatus[b.status];
                    }
                }
            });

            localStorage.setItem('statusBookingSaya', JSON.stringify(statusBaru));
        })
        .catch(err => console.error(err));
}

setInterval(cekStatusBooking, 8000);

// ==== CEK SISA SLOT AREA (real-time saat pilih area/tanggal) ====
const selectArea   = document.getElementById('id_area');
const inputTanggal = document.getElementById('tanggal_booking');
const infoSlotArea = document.getElementById('infoSlotArea');
const btnAjukan    = document.getElementById('btnAjukanBooking');

let areaSudahPenuh = false;

function cekSlotArea() {
    const idArea = selectArea.value;
    const tanggal = inputTanggal.value;

    if (!idArea || !tanggal) {
        infoSlotArea.innerHTML = '';
        areaSudahPenuh = false;
        btnAjukan.disabled = false;
        return;
    }

    infoSlotArea.innerHTML = '<span class="text-muted">Mengecek ketersediaan slot...</span>';

    fetch('cek_slot_area.php?id_area=' + encodeURIComponent(idArea) + '&tanggal_booking=' + encodeURIComponent(tanggal))
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                infoSlotArea.innerHTML = '';
                areaSudahPenuh = false;
                btnAjukan.disabled = false;
                return;
            }

            if (data.penuh) {
                infoSlotArea.innerHTML = '<span style="color:var(--tp-blue-dark);font-weight:600;"><i class="bi bi-exclamation-triangle-fill"></i> Area "' + data.nama_area + '" sudah PENUH pada tanggal ini (' + data.terpakai + '/' + data.kapasitas + ' slot terpakai).</span>';
                areaSudahPenuh = true;
                btnAjukan.disabled = true;
                bunyikanNotifikasi();
                tampilkanToast('Area "' + data.nama_area + '" sudah penuh pada tanggal yang dipilih!');
            } else {
                infoSlotArea.innerHTML = '<span style="color:var(--tp-sea);"><i class="bi bi-check-circle-fill"></i> Sisa ' + data.sisa + ' dari ' + data.kapasitas + ' slot tersedia.</span>';
                areaSudahPenuh = false;
                btnAjukan.disabled = false;
            }
        })
        .catch(err => console.error(err));
}

if (selectArea && inputTanggal) {
    selectArea.addEventListener('change', cekSlotArea);
    inputTanggal.addEventListener('change', cekSlotArea);
}

document.getElementById('formBooking').addEventListener('submit', function (e) {
    if (areaSudahPenuh) {
        e.preventDefault();
        tampilkanToast('Tidak bisa mengajukan booking, area sudah penuh.');
    }
});
</script>

</body>
</html>
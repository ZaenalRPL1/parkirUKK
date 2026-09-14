<?php
require_once __DIR__ . '/../config/koneksi.php';

// Hanya boleh diakses oleh yang sudah login dan berrole user
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'user') {
    header("Location: " . BASE_URL . "auth/login.php?err=akses_ditolak");
    exit;
}

$id_user = $_SESSION['id_user'];

// Ambil foto profil terbaru langsung dari database (biar selalu sinkron)
$stmtFoto = $koneksi->prepare("SELECT foto FROM tb_user WHERE id_user = ?");
$stmtFoto->execute([$id_user]);
$fotoProfil = $stmtFoto->fetch()['foto'] ?? null;

// Ambil daftar kendaraan milik user ini
$stmtKendaraan = $koneksi->prepare("SELECT * FROM tb_kendaraan WHERE id_user = ? ORDER BY id_kendaraan DESC");
$stmtKendaraan->execute([$id_user]);
$daftarKendaraan = $stmtKendaraan->fetchAll();

// Ambil riwayat parkir terbaru (join ke kendaraan milik user ini)
$stmtRiwayat = $koneksi->prepare(
    "SELECT t.*, k.plat_nomor, k.jenis_kendaraan
     FROM tb_transaksi t
     JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
     WHERE k.id_user = ?
     ORDER BY t.waktu_masuk DESC
     LIMIT 10"
);
$stmtRiwayat->execute([$id_user]);
$riwayat = $stmtRiwayat->fetchAll();

// Cek apakah ada kendaraan yang sedang parkir (status masuk, belum keluar)
$stmtAktif = $koneksi->prepare(
    "SELECT t.*, k.plat_nomor, k.jenis_kendaraan
     FROM tb_transaksi t
     JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
     WHERE k.id_user = ? AND t.status = 'masuk'
     ORDER BY t.waktu_masuk DESC"
);
$stmtAktif->execute([$id_user]);
$parkirAktif = $stmtAktif->fetchAll();

// Total riwayat parkir (untuk statistik)
$stmtTotal = $koneksi->prepare(
    "SELECT COUNT(*) AS total FROM tb_transaksi t
     JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
     WHERE k.id_user = ?"
);
$stmtTotal->execute([$id_user]);
$totalParkir = $stmtTotal->fetch()['total'] ?? 0;

// Ikon kendaraan berdasar jenis (untuk grid & tiket)
function iconJenis($jenis) {
    $j = strtolower($jenis);
    if (strpos($j, 'motor') !== false) return 'bi-scooter';
    if (strpos($j, 'truk') !== false || strpos($j, 'truck') !== false) return 'bi-truck';
    if (strpos($j, 'bus') !== false) return 'bi-bus-front';
    return 'bi-car-front-fill';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard User - Parkir Terminal Parangtritis System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        /* =========================================================
           Komponen khusus dashboard user — struktur "Senja Dermaga"
           sengaja dibuat berbeda dari layout kartu statistik generik
           (tiket horizontal, grid kendaraan, timeline riwayat).
           Idealnya dipindah ke assets/css/style.css bila sudah pas.
           ========================================================= */
        .welcome-hero {
            background: linear-gradient(120deg, var(--tp-dark) 0%, var(--tp-blue-dark) 55%, var(--tp-blue) 85%, var(--tp-cyan) 130%);
            border-radius: 16px;
            padding: 34px 32px;
            color: #fff;
            position: relative;
            overflow: hidden;
            margin-top: 26px;
            box-shadow: 0 16px 40px rgba(22,38,43,.22);
        }
        .welcome-hero::after {
            content: "";
            position: absolute;
            right: -60px; top: -60px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(221,164,58,.35), transparent 70%);
        }
        .welcome-hero h4 { font-family: 'Fraunces', serif; font-weight: 600; margin-bottom: 4px; }
        .welcome-hero p.sub { color: rgba(255,255,255,.8); margin-bottom: 22px; }

        .hero-stats {
            display: flex;
            align-items: center;
            gap: 0;
            flex-wrap: wrap;
            border-top: 1px dashed rgba(255,255,255,.25);
            padding-top: 20px;
        }
        .hero-stat {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 4px 28px;
            border-right: 1px dashed rgba(255,255,255,.25);
        }
        .hero-stat:last-child { border-right: none; }
        .hero-stat .hero-icon {
            width: 42px; height: 42px;
            border-radius: 10px;
            background: rgba(255,255,255,.14);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .hero-stat .hero-num { font-family: 'Space Mono', monospace; font-weight: 700; font-size: 1.3rem; line-height: 1; }
        .hero-stat .hero-label { font-size: .7rem; color: rgba(255,255,255,.75); text-transform: uppercase; letter-spacing: .5px; }

        @media (max-width: 767.98px) {
            .hero-stat { padding: 4px 16px; border-right: none; border-bottom: 1px dashed rgba(255,255,255,.2); width: 100%; padding-bottom: 14px; }
            .hero-stat:last-child { border-bottom: none; }
        }

        /* Tiket kendaraan aktif — scroll horizontal */
        .active-scroll {
            display: flex;
            gap: 14px;
            overflow-x: auto;
            padding: 4px 2px 14px;
            margin-bottom: 6px;
        }
        .active-scroll::-webkit-scrollbar { height: 6px; }
        .active-scroll::-webkit-scrollbar-thumb { background: rgba(190,78,44,.3); border-radius: 10px; }

        .ticket-mini {
            flex: 0 0 auto;
            width: 220px;
            background: var(--tp-paper);
            border: 1px dashed rgba(190,78,44,.4);
            border-radius: 12px;
            padding: 16px;
            position: relative;
        }
        .ticket-mini .ti-badge {
            position: absolute; top: -9px; right: 14px;
            background: var(--tp-cyan);
            color: #16262B;
            font-size: .62rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            font-family: 'Space Mono', monospace;
            letter-spacing: .5px;
        }
        .ticket-mini .ti-icon {
            width: 36px; height: 36px;
            border-radius: 8px;
            background: rgba(190,78,44,.1);
            color: var(--tp-blue);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 10px;
        }
        .ticket-mini .ti-plat { font-weight: 700; font-family: 'Space Mono', monospace; font-size: 1rem; }
        .ticket-mini .ti-jenis { font-size: .72rem; color: var(--tp-muted); margin-bottom: 8px; }
        .ticket-mini .ti-time { font-size: .72rem; color: #1C2B27; display: flex; align-items: center; gap: 5px; }

        /* Grid kendaraan */
        .vehicle-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 575.98px) { .vehicle-grid { grid-template-columns: 1fr; } }
        .vehicle-chip {
            background: var(--tp-paper);
            border: 1px solid rgba(22,38,43,.08);
            border-radius: 10px;
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .vehicle-chip:hover { transform: translateY(-3px); box-shadow: 0 8px 18px rgba(22,38,43,.1); }
        .vehicle-chip .vc-icon {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: rgba(62,110,107,.12);
            color: var(--tp-sea);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .vehicle-chip .vc-plat { font-weight: 700; font-family: 'Space Mono', monospace; font-size: .92rem; }
        .vehicle-chip .vc-jenis { font-size: .72rem; color: var(--tp-muted); }

        /* Timeline riwayat */
        .rw-timeline { position: relative; padding: 4px 0 4px 6px; }
        .rw-timeline::before {
            content: "";
            position: absolute;
            left: 20px; top: 6px; bottom: 6px;
            width: 2px;
            background: repeating-linear-gradient(180deg, rgba(190,78,44,.35) 0 6px, transparent 6px 12px);
        }
        .rw-item { position: relative; display: flex; gap: 16px; padding: 0 16px 22px 0; }
        .rw-item:last-child { padding-bottom: 4px; }
        .rw-dot {
            position: relative; z-index: 1;
            width: 40px; height: 40px;
            border-radius: 50%;
            background: var(--tp-paper);
            border: 2px solid var(--tp-blue);
            color: var(--tp-blue);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            font-size: .95rem;
        }
        .rw-item.selesai .rw-dot { border-color: var(--tp-sea); color: var(--tp-sea); }
        .rw-content {
            background: var(--tp-paper);
            border: 1px solid rgba(22,38,43,.07);
            border-radius: 10px;
            padding: 12px 16px;
            flex: 1;
        }
        .rw-content .rw-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .rw-content .rw-plat { font-weight: 700; font-family: 'Space Mono', monospace; }
        .rw-content .rw-meta { font-size: .76rem; color: var(--tp-muted); display: flex; flex-wrap: wrap; gap: 4px 14px; }
        .rw-content .rw-meta span { display: flex; align-items: center; gap: 5px; }
        .rw-content .rw-biaya { font-family: 'Space Mono', monospace; font-weight: 700; color: var(--tp-blue-dark); }

        .section-heading {
            display: flex; align-items: center; gap: 10px;
            margin: 30px 0 14px;
        }
        .section-heading .sh-icon {
            width: 34px; height: 34px;
            border-radius: 8px;
            background: var(--tp-dark);
            color: var(--tp-cyan);
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem;
        }
        .section-heading h5 { margin: 0; font-family: 'Fraunces', serif; font-weight: 600; }
    </style>
</head>
<body>
<?php tampilkanNotifikasiLogin(); ?>

<nav class="navbar navbar-expand-lg navbar-dark navbar-user">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>user/index.php">
            <i class="bi bi-p-circle"></i> TERMINAL PARANGTRITIS PARKING
        </a>
        <div class="d-flex align-items-center">
            <a href="<?= BASE_URL ?>user/booking.php" class="btn btn-outline-light btn-sm me-2">
                <i class="bi bi-calendar-plus"></i> Booking Parkir
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

<div class="container pb-5">

    <!-- Hero sambutan + statistik ringkas dalam satu banner gradasi -->
    <div class="welcome-hero">
        <h4>Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?> 👋</h4>
        <p class="sub">Berikut ringkasan aktivitas parkir kendaraan anda di Terminal Parangtritis.</p>
        <div class="hero-stats">
            <div class="hero-stat">
                <div class="hero-icon"><i class="bi bi-car-front-fill"></i></div>
                <div>
                    <div class="hero-num"><?= count($daftarKendaraan) ?></div>
                    <div class="hero-label">Kendaraan Terdaftar</div>
                </div>
            </div>
            <div class="hero-stat">
                <div class="hero-icon"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="hero-num"><?= (int)$totalParkir ?></div>
                    <div class="hero-label">Total Riwayat Parkir</div>
                </div>
            </div>
            <div class="hero-stat">
                <div class="hero-icon"><i class="bi bi-p-square-fill"></i></div>
                <div>
                    <div class="hero-num"><?= count($parkirAktif) ?></div>
                    <div class="hero-label">Sedang Parkir</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kendaraan sedang parkir — tiket mini, scroll horizontal -->
    <?php if (count($parkirAktif) > 0): ?>
    <div class="section-heading">
        <div class="sh-icon"><i class="bi bi-p-square"></i></div>
        <h5>Kendaraan Sedang Parkir</h5>
    </div>
    <div class="active-scroll">
        <?php foreach ($parkirAktif as $p): ?>
        <div class="ticket-mini">
            <span class="ti-badge">AKTIF</span>
            <div class="ti-icon"><i class="bi <?= iconJenis($p['jenis_kendaraan']) ?>"></i></div>
            <div class="ti-plat"><?= htmlspecialchars($p['plat_nomor']) ?></div>
            <div class="ti-jenis"><?= htmlspecialchars($p['jenis_kendaraan']) ?></div>
            <div class="ti-time"><i class="bi bi-box-arrow-in-right"></i> <?= date('d M, H:i', strtotime($p['waktu_masuk'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Kendaraan saya — grid chip -->
        <div class="col-lg-5">
            <div class="section-heading">
                <div class="sh-icon"><i class="bi bi-car-front"></i></div>
                <h5>Kendaraan Saya</h5>
            </div>
            <?php if (count($daftarKendaraan) === 0): ?>
                <p class="text-muted">Belum ada kendaraan terdaftar.</p>
            <?php else: ?>
                <div class="vehicle-grid">
                    <?php foreach ($daftarKendaraan as $k): ?>
                    <div class="vehicle-chip">
                        <div class="vc-icon"><i class="bi <?= iconJenis($k['jenis_kendaraan']) ?>"></i></div>
                        <div>
                            <div class="vc-plat"><?= htmlspecialchars($k['plat_nomor']) ?></div>
                            <div class="vc-jenis"><?= htmlspecialchars($k['jenis_kendaraan']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Riwayat parkir — timeline vertikal -->
        <div class="col-lg-7">
            <div class="section-heading">
                <div class="sh-icon"><i class="bi bi-clock-history"></i></div>
                <h5>Riwayat Parkir Terbaru</h5>
            </div>
            <?php if (count($riwayat) === 0): ?>
                <p class="text-muted">Belum ada riwayat parkir.</p>
            <?php else: ?>
                <div class="rw-timeline">
                    <?php foreach ($riwayat as $r): ?>
                    <?php $selesai = $r['status'] !== 'masuk'; ?>
                    <div class="rw-item <?= $selesai ? 'selesai' : '' ?>">
                        <div class="rw-dot"><i class="bi <?= iconJenis($r['jenis_kendaraan']) ?>"></i></div>
                        <div class="rw-content">
                            <div class="rw-top">
                                <span class="rw-plat"><?= htmlspecialchars($r['plat_nomor']) ?></span>
                                <?php if ($selesai): ?>
                                    <span class="badge badge-status-keluar">Selesai</span>
                                <?php else: ?>
                                    <span class="badge badge-status-masuk">Sedang Parkir</span>
                                <?php endif; ?>
                            </div>
                            <div class="rw-meta">
                                <span><i class="bi bi-box-arrow-in-right"></i> <?= date('d M Y, H:i', strtotime($r['waktu_masuk'])) ?></span>
                                <span><i class="bi bi-box-arrow-right"></i> <?= $r['waktu_keluar'] ? date('d M Y, H:i', strtotime($r['waktu_keluar'])) : '-' ?></span>
                                <?php if (!empty($r['biaya_total'])): ?>
                                    <span class="rw-biaya">Rp <?= number_format($r['biaya_total'], 0, ',', '.') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/sound-effect.js"></script>
</body>
</html>
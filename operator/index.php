<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['petugas']);
$page_title = 'Dashboard Petugas';

// ==== AKSI KONFIRMASI / TOLAK BOOKING ====
if (isset($_GET['konfirmasi_booking'])) {
    $id_booking = $_GET['konfirmasi_booking'];
    $stmt = $koneksi->prepare("UPDATE tb_booking SET status = 'dikonfirmasi' WHERE id_booking = ? AND status = 'menunggu'");
    $stmt->execute([$id_booking]);
    header("Location: index.php?sukses=Booking berhasil dikonfirmasi");
    exit;
}

if (isset($_GET['tolak_booking'])) {
    $id_booking = $_GET['tolak_booking'];
    $stmt = $koneksi->prepare("UPDATE tb_booking SET status = 'dibatalkan' WHERE id_booking = ? AND status = 'menunggu'");
    $stmt->execute([$id_booking]);
    header("Location: index.php?sukses=Booking berhasil ditolak");
    exit;
}

$notif = $_GET['sukses'] ?? '';

$kendaraanMasuk = $koneksi->query("SELECT COUNT(*) c FROM tb_transaksi WHERE status='masuk'")->fetch()['c'];
$transaksiSayaHariIni = $koneksi->prepare("SELECT COUNT(*) c FROM tb_transaksi WHERE id_user = ? AND DATE(waktu_masuk) = CURDATE()");
$transaksiSayaHariIni->execute([$_SESSION['id_user']]);
$transaksiSayaHariIni = $transaksiSayaHariIni->fetch()['c'];

$pendapatanHariIni = $koneksi->query("SELECT COALESCE(SUM(biaya_total),0) t FROM tb_transaksi WHERE status='keluar' AND DATE(waktu_keluar) = CURDATE()")->fetch()['t'];

$area = $koneksi->query("SELECT * FROM tb_area_parkir ORDER BY id_area")->fetchAll();

$sedangParkir = $koneksi->query("
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.pemilik, a.nama_area
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
    JOIN tb_area_parkir a ON a.id_area = t.id_area
    WHERE t.status = 'masuk'
    ORDER BY t.waktu_masuk DESC LIMIT 8
")->fetchAll();

// ==== BOOKING MENUNGGU KONFIRMASI ====
$bookingMenunggu = $koneksi->query("
    SELECT b.*, k.plat_nomor, k.jenis_kendaraan, k.warna, u.nama_lengkap
    FROM tb_booking b
    JOIN tb_kendaraan k ON k.id_kendaraan = b.id_kendaraan
    JOIN tb_user u ON u.id_user = b.id_user
    WHERE b.status = 'menunggu'
    ORDER BY b.tanggal_booking ASC, b.jam_booking_masuk ASC
")->fetchAll();
$jumlahBookingMenunggu = count($bookingMenunggu);

include __DIR__ . '/components/header.php';
?>

<?php if ($notif): ?>
    <div class="alert alert-success py-2"><?= htmlspecialchars($notif) ?></div>
<?php endif; ?>

<div class="row g-3 mb-2">
    <div class="col-md-3">
        <div class="stat-card bg-grad-red">
            <div class="stat-icon"><i class="bi bi-p-circle"></i></div>
            <h3><?= $kendaraanMasuk ?></h3>
            <span class="label">Kendaraan Sedang Parkir</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-grad-orange">
            <div class="stat-icon"><i class="bi bi-clipboard-check"></i></div>
            <h3><?= $transaksiSayaHariIni ?></h3>
            <span class="label">Transaksi Saya Hari Ini</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-grad-green">
            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            <h3><?= rupiah($pendapatanHariIni) ?></h3>
            <span class="label">Total Pendapatan Hari Ini</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-grad-blue">
            <div class="stat-icon"><i class="bi bi-bell"></i></div>
            <h3><?= $jumlahBookingMenunggu ?></h3>
            <span class="label">Booking Menunggu Konfirmasi</span>
        </div>
    </div>
</div>

<?php if ($jumlahBookingMenunggu > 0): ?>
<div class="row g-3 mb-2">
    <div class="col-12">
        <div class="card card-tp border-warning">
            <div class="card-header bg-warning-subtle d-flex align-items-center justify-content-between">
                <span><i class="bi bi-bell-fill text-warning"></i> Booking Menunggu Konfirmasi</span>
                <span class="badge bg-warning text-dark"><?= $jumlahBookingMenunggu ?> booking</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Kendaraan</th>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th>Jam Keluar</th>
                            <th>Catatan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bookingMenunggu as $b): ?>
                        <tr>
                            <td><?= htmlspecialchars($b['nama_lengkap']) ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($b['plat_nomor']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($b['jenis_kendaraan']) ?><?= $b['warna'] ? ' - ' . htmlspecialchars($b['warna']) : '' ?></small>
                            </td>
                            <td><?= date('d M Y', strtotime($b['tanggal_booking'])) ?></td>
                            <td><?= date('H:i', strtotime($b['jam_booking_masuk'])) ?></td>
                            <td><?= $b['jam_booking_keluar'] ? date('H:i', strtotime($b['jam_booking_keluar'])) : '<span class="text-muted small">-</span>' ?></td>
                            <td><small class="text-muted"><?= htmlspecialchars($b['catatan'] ?: '-') ?></small></td>
                            <td class="text-end">
                                <a href="?konfirmasi_booking=<?= $b['id_booking'] ?>" class="btn btn-sm btn-success">
                                    <i class="bi bi-check-circle"></i> Konfirmasi
                                </a>
                                <a href="?tolak_booking=<?= $b['id_booking'] ?>" class="btn btn-sm btn-outline-danger btn-tolak-konfirmasi">
                                    <i class="bi bi-x-circle"></i> Tolak
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4 d-flex flex-column gap-3">
        <a href="transaksi_masuk.php" class="card card-tp text-decoration-none">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-grad-red text-white" style="width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-box-arrow-in-right fs-4"></i></div>
                <div>
                    <h6 class="mb-0 text-dark">Input Kendaraan Masuk</h6>
                    <small class="text-muted">Catat kendaraan baru masuk area parkir</small>
                </div>
            </div>
        </a>
        <a href="transaksi_keluar.php" class="card card-tp text-decoration-none">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-grad-dark text-white" style="width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-box-arrow-right fs-4"></i></div>
                <div>
                    <h6 class="mb-0 text-dark">Proses Kendaraan Keluar</h6>
                    <small class="text-muted">Hitung biaya & cetak struk parkir</small>
                </div>
            </div>
        </a>
        <div class="card card-tp">
            <div class="card-header">Kapasitas Area</div>
            <div class="card-body">
                <?php foreach ($area as $a):
                    $persen = $a['kapasitas'] > 0 ? round(($a['terisi'] / $a['kapasitas']) * 100) : 0;
                    $warna = $persen >= 90 ? 'bg-danger' : ($persen >= 60 ? 'bg-warning' : 'bg-success');
                ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-semibold"><?= htmlspecialchars($a['nama_area']) ?></span>
                        <span class="small text-muted"><?= $a['terisi'] ?>/<?= $a['kapasitas'] ?></span>
                    </div>
                    <div class="progress" style="height:6px;"><div class="progress-bar <?= $warna ?>" style="width:<?= $persen ?>%"></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card card-tp">
            <div class="card-header">Kendaraan Sedang Parkir</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Plat Nomor</th><th>Jenis</th><th>Pemilik</th><th>Area</th><th>Waktu Masuk</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($sedangParkir as $s): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($s['plat_nomor']) ?></td>
                            <td class="text-capitalize"><?= $s['jenis_kendaraan'] ?></td>
                            <td><?= htmlspecialchars($s['pemilik']) ?></td>
                            <td><?= htmlspecialchars($s['nama_area']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($s['waktu_masuk'])) ?></td>
                            <td><a href="transaksi_keluar.php?id=<?= $s['id_parkir'] ?>" class="btn btn-sm btn-tp">Proses Keluar</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($sedangParkir)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada kendaraan yang sedang parkir</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-tolak-konfirmasi').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        if (!confirm('Yakin ingin menolak booking ini?')) e.preventDefault();
    });
});
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
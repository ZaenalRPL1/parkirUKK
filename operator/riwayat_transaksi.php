<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['petugas']);
$page_title = 'Riwayat Transaksi';

$mode    = $_GET['mode'] ?? 'riwayat';       // riwayat | rekap
$tanggal = $_GET['tanggal'] ?? '';
$bulan   = $_GET['bulan'] ?? date('Y-m');    // format YYYY-MM

// ============================
// EXPORT CSV REKAP BULANAN
// ============================
if ($mode === 'export' && preg_match('/^\d{4}-\d{2}$/', $bulan)) {
    $sql = "
        SELECT t.id_parkir, k.plat_nomor, k.jenis_kendaraan, k.pemilik, a.nama_area,
               t.waktu_masuk, t.waktu_keluar, t.status, t.biaya_total
        FROM tb_transaksi t
        JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
        JOIN tb_area_parkir a ON a.id_area = t.id_area
        WHERE t.id_user = ? AND DATE_FORMAT(t.waktu_masuk, '%Y-%m') = ?
        ORDER BY t.waktu_masuk ASC
    ";
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([$_SESSION['id_user'], $bulan]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=rekap_transaksi_' . $bulan . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Plat Nomor', 'Jenis Kendaraan', 'Pemilik', 'Area', 'Waktu Masuk', 'Waktu Keluar', 'Status', 'Biaya']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id_parkir'],
            $r['plat_nomor'],
            $r['jenis_kendaraan'],
            $r['pemilik'],
            $r['nama_area'],
            $r['waktu_masuk'],
            $r['waktu_keluar'] ?? '-',
            ucfirst($r['status']),
            $r['biaya_total'] ?? 0,
        ]);
    }
    fclose($out);
    exit;
}

// ============================
// DATA: REKAP BULANAN
// ============================
if ($mode === 'rekap') {
    // Ringkasan per hari dalam bulan terpilih
    $sqlHarian = "
        SELECT
            DATE(t.waktu_masuk) AS tgl,
            COUNT(*) AS jumlah_transaksi,
            SUM(CASE WHEN t.status = 'selesai' THEN t.biaya_total ELSE 0 END) AS pendapatan
        FROM tb_transaksi t
        WHERE t.id_user = ? AND DATE_FORMAT(t.waktu_masuk, '%Y-%m') = ?
        GROUP BY DATE(t.waktu_masuk)
        ORDER BY tgl ASC
    ";
    $stmt = $koneksi->prepare($sqlHarian);
    $stmt->execute([$_SESSION['id_user'], $bulan]);
    $rekapHarian = $stmt->fetchAll();

    // Breakdown per area
    $sqlArea = "
        SELECT a.nama_area,
               COUNT(*) AS jumlah_transaksi,
               SUM(CASE WHEN t.status = 'selesai' THEN t.biaya_total ELSE 0 END) AS pendapatan
        FROM tb_transaksi t
        JOIN tb_area_parkir a ON a.id_area = t.id_area
        WHERE t.id_user = ? AND DATE_FORMAT(t.waktu_masuk, '%Y-%m') = ?
        GROUP BY a.id_area, a.nama_area
        ORDER BY pendapatan DESC
    ";
    $stmt = $koneksi->prepare($sqlArea);
    $stmt->execute([$_SESSION['id_user'], $bulan]);
    $rekapArea = $stmt->fetchAll();

    // Total keseluruhan bulan itu
    $totalTransaksi  = array_sum(array_column($rekapHarian, 'jumlah_transaksi'));
    $totalPendapatan = array_sum(array_column($rekapHarian, 'pendapatan'));
    $jumlahHariAda   = count($rekapHarian);
    $rataRataHarian  = $jumlahHariAda > 0 ? $totalPendapatan / $jumlahHariAda : 0;

// ============================
// DATA: RIWAYAT HARIAN (existing)
// ============================
} else {
    $sql = "
        SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.pemilik, a.nama_area
        FROM tb_transaksi t
        JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
        JOIN tb_area_parkir a ON a.id_area = t.id_area
        WHERE t.id_user = ?
    ";
    $params = [$_SESSION['id_user']];
    if ($tanggal !== '') {
        $sql .= " AND DATE(t.waktu_masuk) = ?";
        $params[] = $tanggal;
    }
    $sql .= " ORDER BY t.id_parkir DESC LIMIT 200";
    $stmt = $koneksi->prepare($sql);
    $stmt->execute($params);
    $riwayat = $stmt->fetchAll();
}

include __DIR__ . '/components/header.php';
?>

<div class="card card-tp">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <ul class="nav nav-pills mb-0">
            <li class="nav-item">
                <a class="nav-link <?= $mode === 'riwayat' ? 'active' : '' ?>" href="riwayat_transaksi.php">Riwayat</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $mode === 'rekap' ? 'active' : '' ?>" href="riwayat_transaksi.php?mode=rekap&bulan=<?= htmlspecialchars($bulan) ?>">Rekap Bulanan</a>
            </li>
        </ul>

        <?php if ($mode === 'rekap'): ?>
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="mode" value="rekap">
                <input type="month" name="bulan" class="form-control form-control-sm" value="<?= htmlspecialchars($bulan) ?>">
                <button class="btn btn-sm btn-tp">Tampilkan</button>
                <a href="riwayat_transaksi.php?mode=export&bulan=<?= htmlspecialchars($bulan) ?>" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-download"></i> Export CSV
                </a>
            </form>
        <?php else: ?>
            <form method="GET" class="d-flex gap-2">
                <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= htmlspecialchars($tanggal) ?>">
                <button class="btn btn-sm btn-tp">Filter</button>
                <?php if ($tanggal !== ''): ?><a href="riwayat_transaksi.php" class="btn btn-sm btn-outline-secondary">Reset</a><?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <div class="card-body <?= $mode === 'rekap' ? '' : 'p-0' ?>">
    <?php if ($mode === 'rekap'): ?>

        <!-- Ringkasan -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="p-3 border rounded-3 h-100">
                    <div class="text-muted small">Total Transaksi</div>
                    <div class="fs-4 fw-bold"><?= number_format($totalTransaksi) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded-3 h-100">
                    <div class="text-muted small">Total Pendapatan</div>
                    <div class="fs-4 fw-bold"><?= rupiah($totalPendapatan) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded-3 h-100">
                    <div class="text-muted small">Rata-rata Pendapatan / Hari</div>
                    <div class="fs-4 fw-bold"><?= rupiah($rataRataHarian) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Rekap per hari -->
            <div class="col-lg-7">
                <h6 class="mb-2">Rekap Harian — <?= date('F Y', strtotime($bulan . '-01')) ?></h6>
                <div class="table-responsive" style="max-height:420px;">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="sticky-top bg-white">
                            <tr><th>Tanggal</th><th>Jumlah Transaksi</th><th>Pendapatan</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rekapHarian as $r): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($r['tgl'])) ?></td>
                                <td><?= number_format($r['jumlah_transaksi']) ?></td>
                                <td><?= rupiah($r['pendapatan']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rekapHarian)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Tidak ada transaksi pada bulan ini</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Rekap per area -->
            <div class="col-lg-5">
                <h6 class="mb-2">Rekap per Area Parkir</h6>
                <div class="table-responsive" style="max-height:420px;">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="sticky-top bg-white">
                            <tr><th>Area</th><th>Jumlah</th><th>Pendapatan</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rekapArea as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['nama_area']) ?></td>
                                <td><?= number_format($r['jumlah_transaksi']) ?></td>
                                <td><?= rupiah($r['pendapatan']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rekapArea)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Tidak ada data</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php else: ?>

        <!-- Tabel riwayat harian (existing) -->
        <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Plat Nomor</th><th>Pemilik</th><th>Area</th><th>Masuk</th><th>Keluar</th><th>Status</th><th>Biaya</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($riwayat as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($r['plat_nomor']) ?></td>
                    <td><?= htmlspecialchars($r['pemilik']) ?></td>
                    <td><?= htmlspecialchars($r['nama_area']) ?></td>
                    <td><?= date('d/m/y H:i', strtotime($r['waktu_masuk'])) ?></td>
                    <td><?= $r['waktu_keluar'] ? date('d/m/y H:i', strtotime($r['waktu_keluar'])) : '-' ?></td>
                    <td><span class="badge badge-status-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                    <td><?= $r['biaya_total'] ? rupiah($r['biaya_total']) : '-' ?></td>
                    <td><a href="cetak_struk.php?id=<?= $r['id_parkir'] ?>&mode=<?= $r['status'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($riwayat)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Belum ada riwayat transaksi</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

    <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/components/footer.php'; ?>
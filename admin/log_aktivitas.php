<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['admin']);
$page_title = 'Log Aktivitas';

$tanggal = $_GET['tanggal'] ?? '';
$sql = "SELECT l.*, u.nama_lengkap, u.role FROM tb_log_aktivitas l JOIN tb_user u ON u.id_user = l.id_user";
$params = [];
if ($tanggal !== '') {
    $sql .= " WHERE DATE(l.waktu_aktivitas) = ?";
    $params[] = $tanggal;
}
$sql .= " ORDER BY l.id_log DESC LIMIT 200";
$stmt = $koneksi->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

include __DIR__ . '/template/header.php';
?>

<div class="card card-tp">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Log Aktivitas Pengguna</span>
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= htmlspecialchars($tanggal) ?>">
            <button class="btn btn-sm btn-tp">Filter</button>
            <?php if ($tanggal !== ''): ?><a href="log_aktivitas.php" class="btn btn-sm btn-outline-secondary">Reset</a><?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Waktu</th><th>Nama</th><th>Role</th><th>Aktivitas</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $i => $l): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= date('d/m/Y H:i:s', strtotime($l['waktu_aktivitas'])) ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($l['nama_lengkap']) ?></td>
                    <td><span class="badge bg-secondary text-uppercase"><?= $l['role'] ?></span></td>
                    <td><?= htmlspecialchars($l['aktivitas']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada log aktivitas</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/template/footer.php'; ?>

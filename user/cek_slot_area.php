<?php
require_once __DIR__ . '/../config/koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'user') {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');

$id_area         = $_GET['id_area'] ?? '';
$tanggal_booking = $_GET['tanggal_booking'] ?? '';

if ($id_area === '' || $tanggal_booking === '') {
    echo json_encode(['error' => 'Parameter tidak lengkap']);
    exit;
}

$stmtArea = $koneksi->prepare("SELECT nama_area, kapasitas FROM tb_area_parkir WHERE id_area = ?");
$stmtArea->execute([$id_area]);
$areaInfo = $stmtArea->fetch();

if (!$areaInfo) {
    echo json_encode(['error' => 'Area tidak ditemukan']);
    exit;
}

$stmtHitung = $koneksi->prepare(
    "SELECT COUNT(*) AS jumlah FROM tb_booking
     WHERE id_area = ? AND tanggal_booking = ? AND status IN ('menunggu','dikonfirmasi')"
);
$stmtHitung->execute([$id_area, $tanggal_booking]);
$jumlahTerpakai = (int) $stmtHitung->fetch()['jumlah'];

$kapasitas = (int) $areaInfo['kapasitas'];
$sisa      = max(0, $kapasitas - $jumlahTerpakai);
$penuh     = $jumlahTerpakai >= $kapasitas;

echo json_encode([
    'nama_area'  => $areaInfo['nama_area'],
    'kapasitas'  => $kapasitas,
    'terpakai'   => $jumlahTerpakai,
    'sisa'       => $sisa,
    'penuh'      => $penuh,
]);
<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['petugas']);

$id = $_GET['id'] ?? 0;
$mode = $_GET['mode'] ?? 'masuk';

$stmt = $koneksi->prepare("
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.pemilik, k.warna, a.nama_area, u.nama_lengkap AS petugas, tf.tarif_per_jam,
           bk.jam_booking_masuk AS booking_jam_masuk, bk.jam_booking_keluar AS booking_jam_keluar
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
    JOIN tb_area_parkir a ON a.id_area = t.id_area
    JOIN tb_user u ON u.id_user = t.id_user
    JOIN tb_tarif tf ON tf.id_tarif = t.id_tarif
    LEFT JOIN tb_booking bk ON bk.id_booking = t.id_booking
    WHERE t.id_parkir = ?
");
$stmt->execute([$id]);
$trx = $stmt->fetch();

if (!$trx) {
    header("Location: index.php?gagal=Transaksi tidak ditemukan");
    exit;
}

$page_title = 'Cetak Struk';
include __DIR__ . '/components/header.php';
?>
<script>
// Halaman ini hanya muncul kalau transaksi masuk/keluar berhasil diproses
document.addEventListener('DOMContentLoaded', function () {
    if (typeof mainkanSuaraBenar === 'function') mainkanSuaraBenar();
});
</script>

<div class="text-center mb-3 no-print">
    <button class="btn btn-tp" onclick="cetakStruk()"><i class="bi bi-printer me-1"></i> Cetak Struk</button>
    <a href="<?= $mode === 'masuk' ? 'index.php' : 'transaksi_keluar.php' ?>" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="struk-box">
    <div class="struk-head">
        <div class="struk-logo"><i class="bi bi-p-circle-fill"></i></div>
        <h5>TERMINAL PARANGTRITIS</h5>
        <p class="sub">Struk Parkir Digital</p>
        <span class="struk-badge"><?= $mode === 'masuk' ? 'TIKET MASUK' : 'BUKTI PEMBAYARAN' ?></span>
    </div>
    <div class="struk-notch"></div>

    <div class="struk-body">
        <div class="row-line"><span><i class="bi bi-hash"></i>No. Transaksi</span><span>#<?= str_pad($trx['id_parkir'], 6, '0', STR_PAD_LEFT) ?></span></div>
        <div class="row-line"><span><i class="bi bi-credit-card-2-front"></i>Plat Nomor</span><span><?= htmlspecialchars($trx['plat_nomor']) ?></span></div>
        <div class="row-line"><span><i class="bi bi-car-front"></i>Jenis</span><span class="text-capitalize"><?= $trx['jenis_kendaraan'] ?></span></div>
        <div class="row-line"><span><i class="bi bi-person"></i>Pemilik</span><span><?= htmlspecialchars($trx['pemilik']) ?></span></div>
        <div class="row-line"><span><i class="bi bi-geo-alt"></i>Area</span><span><?= htmlspecialchars($trx['nama_area']) ?></span></div>
        <div class="row-line"><span><i class="bi bi-person-badge"></i>Petugas</span><span><?= htmlspecialchars($trx['petugas']) ?></span></div>
        <hr>
        <div class="row-line"><span><i class="bi bi-box-arrow-in-right"></i>Waktu Masuk</span><span><?= date('d/m/Y H:i', strtotime($trx['waktu_masuk'])) ?></span></div>
        <?php if ($trx['booking_jam_masuk']): ?>
            <div class="row-line small text-muted"><span><i class="bi bi-calendar-check"></i>Jadwal Booking Masuk</span><span><?= date('H:i', strtotime($trx['booking_jam_masuk'])) ?></span></div>
        <?php endif; ?>
        <?php if ((float) $trx['denda_telat'] > 0): ?>
            <div class="row-line text-danger"><span><i class="bi bi-exclamation-triangle"></i>Denda Telat Booking (<?= BATAS_TELAT_BOOKING_JAM ?> jam)</span><span><?= rupiah($trx['denda_telat']) ?></span></div>
        <?php endif; ?>
        <?php if ($trx['status'] === 'keluar'): ?>
            <div class="row-line"><span><i class="bi bi-box-arrow-right"></i>Waktu Keluar</span><span><?= date('d/m/Y H:i', strtotime($trx['waktu_keluar'])) ?></span></div>
            <?php if ($trx['booking_jam_keluar']): ?>
                <div class="row-line small text-muted"><span><i class="bi bi-calendar-check"></i>Jadwal Booking Keluar</span><span><?= date('H:i', strtotime($trx['booking_jam_keluar'])) ?></span></div>
            <?php endif; ?>
            <div class="row-line"><span><i class="bi bi-clock-history"></i>Durasi</span><span><?= $trx['durasi_jam'] ?> jam</span></div>
            <div class="row-line"><span><i class="bi bi-cash-coin"></i>Tarif/Jam</span><span><?= rupiah($trx['tarif_per_jam']) ?></span></div>
            <div class="row-line"><span><i class="bi bi-wallet2"></i>Biaya Parkir</span><span><?= rupiah($trx['durasi_jam'] * $trx['tarif_per_jam']) ?></span></div>
            <div class="row-line"><span><i class="bi bi-credit-card"></i>Metode Bayar</span><span><?= strtoupper($trx['metode_bayar'] ?? 'TUNAI') ?></span></div>

            <div class="struk-total-box">
                <div class="total-line"><span>TOTAL BAYAR</span><span><?= rupiah($trx['biaya_total']) ?></span></div>
            </div>
        <?php else: ?>
            <div class="struk-total-box">
                <div class="total-line"><span><i class="bi bi-info-circle"></i> INFO</span><span style="font-size:.78rem;">Simpan struk ini</span></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="struk-footer">
        Terima kasih telah menggunakan jasa parkir<br>
        <strong>Terminal Parangtritis</strong> &middot; <?= date('d/m/Y H:i') ?>
    </div>
</div>

<?php include __DIR__ . '/components/footer.php'; ?>
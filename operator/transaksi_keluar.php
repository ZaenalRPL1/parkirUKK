<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['petugas']);
$page_title = 'Kendaraan Keluar';
$error = '';

// Proses kendaraan keluar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_parkir'])) {
    $id_parkir = $_POST['id_parkir'];
    $metode_bayar = in_array($_POST['metode_bayar'] ?? '', ['tunai', 'qris']) ? $_POST['metode_bayar'] : 'tunai';

    $stmt = $koneksi->prepare("
        SELECT t.*, tf.tarif_per_jam
        FROM tb_transaksi t
        JOIN tb_tarif tf ON tf.id_tarif = t.id_tarif
        WHERE t.id_parkir = ?
    ");
    $stmt->execute([$id_parkir]);
    $trx = $stmt->fetch();

    if ($trx && $trx['status'] === 'masuk') {
        $waktu_masuk = new DateTime($trx['waktu_masuk']);
        $waktu_keluar = new DateTime();
        $selisih = $waktu_masuk->diff($waktu_keluar);
        $jam = ($selisih->days * 24) + $selisih->h + ($selisih->i > 0 || $selisih->s > 0 ? 1 : 0);
        $jam = max(1, $jam); // minimal 1 jam
        $biayaParkir = $jam * $trx['tarif_per_jam'];

        // Denda telat booking (flat, sekali kena) sudah dihitung & disimpan saat
        // kendaraan masuk — tidak ada denda tambahan lagi saat keluar.
        $denda_telat = (float) $trx['denda_telat'];
        $biaya = $biayaParkir + $denda_telat;

        $koneksi->beginTransaction();
        try {
            $upd = $koneksi->prepare("UPDATE tb_transaksi SET waktu_keluar = NOW(), durasi_jam = ?, biaya_total = ?, status = 'keluar', metode_bayar = ? WHERE id_parkir = ?");
            $upd->execute([$jam, $biaya, $metode_bayar, $id_parkir]);

            $updArea = $koneksi->prepare("UPDATE tb_area_parkir SET terisi = GREATEST(terisi - 1, 0) WHERE id_area = ?");
            $updArea->execute([$trx['id_area']]);

            $ketDenda = '';
            if ($denda_telat > 0) $ketDenda .= " + denda telat booking " . rupiah($denda_telat);
            catatLog($koneksi, $_SESSION['id_user'], "Memproses kendaraan keluar transaksi #$id_parkir (bayar: " . strtoupper($metode_bayar) . ")" . $ketDenda);

            $koneksi->commit();
            header("Location: cetak_struk.php?id=$id_parkir&mode=keluar");
            exit;
        } catch (Exception $e) {
            $koneksi->rollBack();
            $error = 'Gagal memproses kendaraan keluar: ' . $e->getMessage();
        }
    } else {
        $error = 'Transaksi tidak ditemukan atau kendaraan sudah keluar.';
    }
}

$sedangParkir = $koneksi->query("
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.pemilik, a.nama_area, tf.tarif_per_jam
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
    JOIN tb_area_parkir a ON a.id_area = t.id_area
    JOIN tb_tarif tf ON tf.id_tarif = t.id_tarif
    WHERE t.status = 'masuk'
    ORDER BY t.waktu_masuk ASC
")->fetchAll();

include __DIR__ . '/components/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card card-tp">
    <div class="card-header"><i class="bi bi-box-arrow-right me-1"></i> Kendaraan yang Sedang Parkir</div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Plat Nomor</th><th>Jenis</th><th>Pemilik</th><th>Area</th><th>Waktu Masuk</th><th>Durasi</th><th>Tarif/Jam</th><th>Denda</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($sedangParkir as $s): ?>
                <?php
                    $dendaRow = (float) $s['denda_telat'];
                    $waktuMasukDt = new DateTime($s['waktu_masuk']);
                    $selisihRow = $waktuMasukDt->diff(new DateTime());
                    $jamBerjalanRow = ($selisihRow->days * 24) + $selisihRow->h;
                ?>
                <tr class="<?= (isset($_GET['id']) && $_GET['id'] == $s['id_parkir']) ? 'table-warning' : '' ?>">
                    <td class="fw-semibold"><?= htmlspecialchars($s['plat_nomor']) ?></td>
                    <td class="text-capitalize"><?= $s['jenis_kendaraan'] ?></td>
                    <td><?= htmlspecialchars($s['pemilik']) ?></td>
                    <td><?= htmlspecialchars($s['nama_area']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($s['waktu_masuk'])) ?></td>
                    <td><?= $jamBerjalanRow ?> jam berjalan</td>
                    <td><?= rupiah($s['tarif_per_jam']) ?></td>
                    <td>
                        <?php if ($dendaRow > 0): ?>
                            <span class="badge bg-danger d-block">Telat booking <?= rupiah($dendaRow) ?></span>
                        <?php else: ?>
                            <span class="text-muted small">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-tp" data-bs-toggle="modal" data-bs-target="#modalBayar<?= $s['id_parkir'] ?>">
                            <i class="bi bi-flag me-1"></i>Proses Keluar
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($sedangParkir)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada kendaraan yang sedang parkir</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Modal pilih metode pembayaran (per kendaraan) -->
<?php foreach ($sedangParkir as $s): ?>
<?php
    $jamEst = max(1, (new DateTime($s['waktu_masuk']))->diff(new DateTime())->days * 24 + (new DateTime($s['waktu_masuk']))->diff(new DateTime())->h + 1);
    $biayaParkirEst = $jamEst * $s['tarif_per_jam'];

    $dendaTelatEst = (float) $s['denda_telat'];

    $totalEst = $biayaParkirEst + $dendaTelatEst;
?>
<div class="modal fade" id="modalBayar<?= $s['id_parkir'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="id_parkir" value="<?= $s['id_parkir'] ?>">
            <div class="modal-header">
                <h5 class="modal-title">Pembayaran &middot; <?= htmlspecialchars($s['plat_nomor']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Estimasi Durasi: <strong><?= $jamEst ?> jam</strong></p>
                <p class="mb-1">Biaya Parkir: <strong><?= rupiah($biayaParkirEst) ?></strong></p>

                <?php if ($dendaTelatEst > 0): ?>
                    <p class="mb-1 text-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Denda telat booking: <strong><?= rupiah($dendaTelatEst) ?></strong>
                    </p>
                <?php endif; ?>

                <p class="mb-3">Estimasi Total: <strong><?= rupiah($totalEst) ?></strong> <span class="small text-muted">(dihitung ulang saat diproses)</span></p>

                <label class="form-label">Metode Pembayaran</label>
                <div class="btn-group w-100 mb-3" role="group">
                    <input type="radio" class="btn-check" name="metode_bayar" id="tunai<?= $s['id_parkir'] ?>" value="tunai" checked
                           onchange="document.getElementById('qrisBox<?= $s['id_parkir'] ?>').classList.add('d-none')">
                    <label class="btn btn-outline-tp" for="tunai<?= $s['id_parkir'] ?>"><i class="bi bi-cash-stack me-1"></i>Tunai</label>

                    <input type="radio" class="btn-check" name="metode_bayar" id="qris<?= $s['id_parkir'] ?>" value="qris"
                           onchange="document.getElementById('qrisBox<?= $s['id_parkir'] ?>').classList.remove('d-none')">
                    <label class="btn btn-outline-tp" for="qris<?= $s['id_parkir'] ?>"><i class="bi bi-qr-code me-1"></i>QRIS</label>
                </div>

                <div id="qrisBox<?= $s['id_parkir'] ?>" class="text-center d-none">
                    <img src="<?= BASE_URL ?>img/qris.jpeg" alt="QRIS Terminal Parangtritis" class="img-fluid border rounded p-2" style="max-width:220px;">
                    <p class="small text-muted mt-2 mb-0">Minta pelanggan scan QRIS di atas menggunakan e-wallet / m-banking, lalu tekan tombol di bawah setelah pembayaran berhasil.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-tp" onclick="return confirm('Proses kendaraan <?= htmlspecialchars($s['plat_nomor']) ?> keluar sekarang?')">
                    <i class="bi bi-check2-circle me-1"></i>Konfirmasi &amp; Proses Keluar
                </button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/components/footer.php'; ?>
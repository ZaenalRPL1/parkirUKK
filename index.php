<?php
require_once __DIR__ . '/config/koneksi.php';

// Jika sudah login, langsung arahkan ke dashboard sesuai role
if (isset($_SESSION['id_user'])) {
    switch ($_SESSION['role']) {
        case 'admin': header("Location: " . BASE_URL . "admin/index.php"); exit;
        case 'petugas': header("Location: " . BASE_URL . "operator/index.php"); exit;
        case 'owner': header("Location: " . BASE_URL . "owner/index.php"); exit;
    }
}

// ===== Ambil testimoni yang sudah disetujui (approved) dari database =====
$daftarTestimoni = [];
try {
    $stmtTesti = $koneksi->prepare(
        "SELECT nama, role, rating, komentar, created_at
         FROM testimoni
         WHERE status = 'approved'
         ORDER BY created_at DESC
         LIMIT 6"
    );
    $stmtTesti->execute();
    $daftarTestimoni = $stmtTesti->fetchAll();
} catch (PDOException $e) {
    // Tabel testimoni mungkin belum dibuat -> tampilkan data contoh (fallback) di bawah
    $daftarTestimoni = [];
}

// ===== Ambil data kepadatan parkir per jam untuk grafik =====
// Ganti query di bawah sesuai struktur tabel transaksi Anda,
// misalnya menghitung jumlah kendaraan masuk per jam hari ini.
$labelJam = ['08:00','10:00','12:00','14:00','16:00','18:00','20:00'];
$dataKepadatan = [12, 28, 45, 38, 52, 67, 30]; // data contoh (fallback)

try {
    $stmtGrafik = $koneksi->prepare(
        "SELECT DATE_FORMAT(waktu_masuk, '%H:00') AS jam, COUNT(*) AS jumlah
         FROM transaksi
         WHERE DATE(waktu_masuk) = CURDATE()
         GROUP BY jam
         ORDER BY jam ASC"
    );
    $stmtGrafik->execute();
    $hasilGrafik = $stmtGrafik->fetchAll();

    if (!empty($hasilGrafik)) {
        $labelJam = array_column($hasilGrafik, 'jam');
        $dataKepadatan = array_map('intval', array_column($hasilGrafik, 'jumlah'));
    }
} catch (PDOException $e) {
    // Tabel transaksi mungkin belum tersedia/berbeda struktur -> gunakan data contoh di atas
}

// ===== Ambil data seluruh area parkir beserta sisa slotnya (real-time: kapasitas - terisi) =====
$daftarAreaLanding = [];
try {
    $stmtAreaLanding = $koneksi->query("SELECT * FROM tb_area_parkir ORDER BY id_area");
    $daftarAreaLanding = $stmtAreaLanding->fetchAll();
} catch (PDOException $e) {
    $daftarAreaLanding = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Terminal Parangtritis — Sistem Parkir</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,500&family=Work+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        :root {
            --pt-ink:    #16262B;
            --pt-ink-2:  #1F3B3E;
            --pt-sand:   #F2E9D6;
            --pt-paper:  #FBF7EC;
            --pt-rust:   #BE4E2C;
            --pt-gold:   #DDA43A;
            --pt-sea:    #3E6E6B;
            --pt-text:   #1C2B27;
        }
        html { scroll-behavior: smooth; }
        body { margin: 0; font-family: 'Work Sans', system-ui, -apple-system, sans-serif; background: var(--pt-sand); color: var(--pt-text); }
        h1, h2, h3, .display-face { font-family: 'Fraunces', Georgia, serif; }
        .mono-face { font-family: 'Space Mono', 'Courier New', monospace; }
        section[id] { scroll-margin-top: 84px; }

        .navbar-tp { background: var(--pt-ink); padding: 14px 0; position: sticky; top: 0; z-index: 1030; border-bottom: 3px dashed rgba(221,164,58,.5); }
        .navbar-tp .brand { color: var(--pt-paper); font-weight: 700; font-size: 1.15rem; letter-spacing: .5px; font-family: 'Fraunces', serif; }
        .navbar-logo { height: 34px; width: auto; object-fit: contain; border-radius: 6px; }
        .navbar-tp .nav-link { color: rgba(251,247,236,.78); font-family: 'Space Mono', monospace; font-size: .8rem; text-transform: uppercase; letter-spacing: 1px; padding: 8px 12px !important; border-bottom: 2px solid transparent; }
        .navbar-tp .nav-link:hover, .navbar-tp .nav-link.active { color: var(--pt-gold); border-color: var(--pt-gold); }
        .navbar-tp .navbar-toggler { border-color: rgba(251,247,236,.4); }
        .navbar-tp .navbar-toggler-icon { filter: invert(1) grayscale(100%) brightness(200%); }

        .btn-tp { background: var(--pt-rust); color: var(--pt-paper); border: none; font-weight: 600; border-radius: 3px; font-family: 'Space Mono', monospace; font-size: .82rem; letter-spacing: .5px; text-transform: uppercase; transition: transform .15s ease, background .15s ease; }
        .btn-tp:hover { color: var(--pt-paper); background: #a3421f; transform: translateY(-2px); }
        .btn-outline-tp { border: 2px solid var(--pt-gold); color: var(--pt-paper); font-weight: 600; border-radius: 3px; font-family: 'Space Mono', monospace; font-size: .82rem; letter-spacing: .5px; text-transform: uppercase; }
        .btn-outline-tp:hover { background: var(--pt-gold); color: var(--pt-ink); }

        .hero {
            position: relative; overflow: hidden;
            background: linear-gradient(180deg, #0F1C20 0%, #1B2E30 45%, #3B3327 78%, #16262B 100%);
            color: var(--pt-paper); padding: 84px 0 60px; text-align: center;
        }
        .hero-sun { position: absolute; left: 50%; top: 55%; width: 420px; height: 420px; border-radius: 50%;
            background: radial-gradient(circle, var(--pt-gold) 0%, var(--pt-rust) 60%, transparent 76%);
            opacity: .5; transform: translate(-50%, -50%); filter: blur(2px); pointer-events: none; }
        .hero-eyebrow { display: inline-flex; align-items: center; gap: 10px; font-family: 'Space Mono', monospace; font-size: .72rem; letter-spacing: 3px; text-transform: uppercase; color: var(--pt-gold); border: 1px solid rgba(221,164,58,.5); padding: 6px 14px; border-radius: 50px; }
        .hero h1 { font-weight: 600; font-size: 3rem; line-height: 1.16; margin: 22px auto 18px; max-width: 780px; }
        .hero h1 em { font-style: italic; color: var(--pt-gold); }
        .hero p.lead { color: rgba(251,247,236,.8); max-width: 560px; margin: 0 auto; font-size: 1.05rem; }
        .hero .hero-actions { display: flex; gap: 14px; justify-content: center; margin-top: 30px; flex-wrap: wrap; }

        /* --- Hero dua kolom: teks + foto bergaya tiket/postcard --- */
        .hero-copy { position: relative; z-index: 2; }
        .hero-copy h1, .hero-copy p.lead { margin-left: 0; margin-right: 0; }
        .hero-copy .hero-actions { justify-content: center; }
        @media (min-width: 992px) {
            .hero-copy { text-align: left; }
            .hero-copy .hero-actions { justify-content: flex-start; }
            .hero-copy h1 { font-size: 2.5rem; }
        }
        .hero-photo-wrap {
            position: relative; z-index: 2; max-width: 420px; margin: 0 auto;
            padding: 22px 8px 34px 34px;
        }
        .hero-photo-frame {
            border-radius: 6px; overflow: hidden; border: 6px solid var(--pt-paper);
            box-shadow: 0 26px 60px rgba(0,0,0,.5); background: var(--pt-ink-2);
        }
        .hero-photo-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .hero-photo-frame--front { position: relative; aspect-ratio: 4 / 5; transform: rotate(-3deg); z-index: 2; }
        .hero-photo-frame--back {
            position: absolute; z-index: 1; width: 74%; aspect-ratio: 4 / 5;
            right: -8px; top: 40px; transform: rotate(6deg); opacity: .88;
        }
        .hero-photo-frame--empty { display: flex; align-items: center; justify-content: center; position: relative; }
        .hero-photo-frame--empty::after {
            content: "Tempatkan Foto Terminal"; font-family: 'Space Mono', monospace; font-size: .66rem;
            letter-spacing: 1.5px; text-transform: uppercase; color: rgba(251,247,236,.4); text-align: center; padding: 0 16px;
        }
        .hero-photo-frame--front.hero-photo-frame--empty {
            background: repeating-linear-gradient(135deg, #24393c 0 14px, #1c2e31 14px 28px);
        }
        .hero-photo-frame--back.hero-photo-frame--empty {
            background: repeating-linear-gradient(135deg, #2c211a 0 14px, #23190f 14px 28px);
        }
        .hero-photo-badge {
            position: absolute; top: -16px; right: -14px; width: 54px; height: 54px; border-radius: 50%;
            background: var(--pt-rust); border: 2px dashed var(--pt-gold); color: var(--pt-paper);
            display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
            box-shadow: 0 10px 22px rgba(0,0,0,.4); z-index: 3;
        }
        .hero-photo-tag {
            position: absolute; left: 14px; bottom: 14px; z-index: 3;
            background: rgba(22,38,43,.82); color: var(--pt-gold); font-family: 'Space Mono', monospace;
            font-size: .64rem; letter-spacing: 1px; text-transform: uppercase; padding: 6px 10px;
            border-radius: 3px; border: 1px solid rgba(221,164,58,.4);
        }

        .status-band { background: #0E1517; border-bottom: 1px solid rgba(221,164,58,.25); padding: 18px 0; }
        .status-band__label { font-family: 'Space Mono', monospace; color: var(--pt-gold); font-size: .68rem; letter-spacing: 2px; text-transform: uppercase; white-space: nowrap; padding-right: 18px; border-right: 1px dashed rgba(221,164,58,.3); }
        .status-scroll { display: flex; gap: 14px; overflow-x: auto; padding-bottom: 2px; }
        .status-scroll::-webkit-scrollbar { height: 5px; }
        .status-scroll::-webkit-scrollbar-thumb { background: rgba(221,164,58,.35); border-radius: 4px; }
        .status-chip { flex: 0 0 auto; min-width: 190px; background: rgba(251,247,236,.04); border: 1px solid rgba(251,247,236,.1); border-radius: 6px; padding: 10px 14px; font-family: 'Space Mono', monospace; }
        .status-chip__top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .status-chip__name { color: var(--pt-paper); font-size: .78rem; text-transform: uppercase; letter-spacing: .5px; }
        .status-chip__tag { font-size: .64rem; padding: 2px 7px; border-radius: 3px; }
        .status-chip__tag--ok { background: rgba(62,110,107,.35); color: #9FD6D0; }
        .status-chip__tag--warn { background: rgba(221,164,58,.25); color: var(--pt-gold); }
        .status-chip__tag--full { background: rgba(190,78,44,.3); color: #F0A17F; }
        .status-chip__bar { height: 4px; border-radius: 3px; background: rgba(251,247,236,.1); overflow: hidden; }
        .status-chip__bar span { display: block; height: 100%; background: var(--pt-gold); }
        .status-chip__sub { color: rgba(251,247,236,.45); font-size: .64rem; margin-top: 6px; }
        .status-empty { color: rgba(251,247,236,.5); font-family: 'Space Mono', monospace; font-size: .8rem; }

        .section-eyebrow { font-family: 'Space Mono', monospace; font-size: .72rem; letter-spacing: 3px; text-transform: uppercase; color: var(--pt-rust); margin-bottom: 10px; display: block; }
        .section-title { font-weight: 600; font-size: 2rem; margin-bottom: 10px; }
        .section-sub { color: #6b6b60; margin-bottom: 46px; max-width: 560px; }

        .route { position: relative; }
        .route-line { position: absolute; top: 34px; left: 8%; right: 8%; height: 2px; background: repeating-linear-gradient(90deg, var(--pt-rust) 0 10px, transparent 10px 18px); z-index: 0; }
        .route-steps { display: flex; gap: 0; position: relative; z-index: 1; }
        .route-step { flex: 1; text-align: center; padding: 0 14px; cursor: pointer; }
        .route-step:nth-child(2) { margin-top: 46px; }
        .route-step__dot { width: 68px; height: 68px; border-radius: 50%; background: var(--pt-paper); border: 3px solid var(--pt-rust); display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; font-family: 'Space Mono', monospace; font-weight: 700; color: var(--pt-rust); font-size: .95rem; transition: transform .18s ease, background .18s ease; }
        .route-step:hover .route-step__dot { transform: translateY(-4px); background: var(--pt-rust); color: #fff; }
        .route-step h5 { font-family: 'Fraunces', serif; font-weight: 600; margin-bottom: 8px; }
        .route-step p { color: #6b6b60; font-size: .92rem; }

        .modal-content { border-radius: 8px; border: none; }
        .modal-header { background: var(--pt-ink); color: var(--pt-paper); border-radius: 8px 8px 0 0; border-bottom: 3px dashed rgba(221,164,58,.4); }
        .modal-header .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
        .modal-title { font-family: 'Fraunces', serif; }
        .modal-icon-lg { width: 58px; height: 58px; border-radius: 10px; background: var(--pt-rust); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.6rem; margin-bottom: 16px; }

        .layanan-section { background: var(--pt-ink); color: var(--pt-paper); padding: 70px 0; }
        .layanan-section .section-eyebrow { color: var(--pt-gold); }
        .layanan-section .section-sub { color: rgba(251,247,236,.6); }
        .ticket-fan { position: relative; padding-top: 6px; }
        .ticket-card { border-radius: 10px; padding: 0; color: var(--pt-paper); background: var(--pt-ink-2); border: 1px solid rgba(221,164,58,.2); overflow: hidden; position: relative; transition: transform .18s ease, box-shadow .18s ease; margin-bottom: -14px; }
        .ticket-fan .ticket-card:nth-child(1) { transform: rotate(-1.4deg); z-index: 3; }
        .ticket-fan .ticket-card:nth-child(2) { transform: rotate(.8deg) translateX(10px); z-index: 2; }
        .ticket-fan .ticket-card:nth-child(3) { transform: rotate(-.6deg) translateX(4px); z-index: 1; margin-bottom: 0; }
        .ticket-card:hover { transform: translateY(-6px) rotate(0deg) !important; box-shadow: 0 20px 44px rgba(0,0,0,.35); z-index: 5 !important; }
        .ticket-card__top { padding: 20px 22px 16px; display: flex; align-items: center; gap: 14px; }
        .ticket-card i { font-size: 1.5rem; color: var(--pt-gold); }
        .ticket-card__top div h5 { margin: 0 0 4px; font-weight: 700; font-size: 1rem; }
        .ticket-card__top div p { margin: 0; opacity: .75; font-size: .85rem; }
        .ticket-card__perf { border-top: 2px dashed rgba(251,247,236,.25); position: relative; }
        .ticket-card__perf::before, .ticket-card__perf::after { content: ""; position: absolute; top: -9px; width: 18px; height: 18px; border-radius: 50%; background: var(--pt-ink); }
        .ticket-card__perf::before { left: -9px; }
        .ticket-card__perf::after { right: -9px; }
        .ticket-card__bottom { padding: 10px 22px 14px; font-family: 'Space Mono', monospace; font-size: .68rem; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(251,247,236,.5); }
        .video-parkir-wrap { border-radius: 10px; overflow: hidden; box-shadow: 0 22px 50px rgba(0,0,0,.4); border: 1px solid rgba(221,164,58,.2); background: #000; }
        .video-parkir-wrap video { width: 100%; height: 100%; display: block; object-fit: cover; }

        .info-section { background: var(--pt-paper); }
        .stat-ticker { display: flex; flex-wrap: wrap; gap: 0; border: 1px solid rgba(22,38,43,.1); border-radius: 6px; overflow: hidden; margin-bottom: 46px; }
        .stat-ticker__item { flex: 1 1 160px; text-align: center; padding: 22px 14px; border-right: 1px dashed rgba(22,38,43,.15); background: #fff; }
        .stat-ticker__item:last-child { border-right: none; }
        .stat-ticker__item h3 { font-family: 'Space Mono', monospace; font-weight: 700; font-size: 1.9rem; color: var(--pt-rust); margin-bottom: 4px; }
        .stat-ticker__item p { color: #7a7a6e; margin: 0; font-weight: 600; font-size: .78rem; text-transform: uppercase; letter-spacing: .5px; }

        .grafik-card { background: #fff; border-radius: 8px; padding: 28px; border: 1px solid rgba(22,38,43,.07); }
        .grafik-card canvas { max-height: 300px; }
        .grafik-card h6 { font-family: 'Fraunces', serif; font-weight: 600; }

        .info-box { background: var(--pt-paper); border-radius: 6px; padding: 26px; border: 1px solid rgba(22,38,43,.07); height: 100%; }
        .info-box i { color: var(--pt-rust); font-size: 1.3rem; margin-right: 10px; }
        .info-box h6 { font-family: 'Fraunces', serif; font-weight: 600; }
        .info-box.info-box-clickable { cursor: pointer; transition: transform .18s ease, box-shadow .18s ease; }
        .info-box.info-box-clickable:hover { transform: translateY(-6px); box-shadow: 0 16px 32px rgba(22,38,43,.1); }
        .info-box-link { color: var(--pt-sea); font-family: 'Space Mono', monospace; font-size: .76rem; text-transform: uppercase; letter-spacing: 1px; }

        .help-float-btn { position: fixed; right: 24px; bottom: 24px; width: 56px; height: 56px; border-radius: 50%; background: var(--pt-rust); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; border: 2px solid var(--pt-gold); box-shadow: 0 10px 26px rgba(190,78,44,.4); z-index: 1040; }
        .help-float-btn:hover { color: #fff; }
        #modalBantuan .accordion-button:not(.collapsed) { background: rgba(190,78,44,.08); color: var(--pt-rust); box-shadow: none; }
        #modalBantuan .accordion-button:focus { box-shadow: none; }
        .bantuan-contact-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 6px; background: var(--pt-sand); margin-bottom: 10px; text-decoration: none; color: var(--pt-text); }
        .bantuan-contact-item:hover { background: rgba(190,78,44,.12); color: var(--pt-rust); }
        .bantuan-contact-item i { font-size: 1.2rem; color: var(--pt-rust); }

        .testi-section { background: var(--pt-sand); }
        .testi-masonry { column-count: 3; column-gap: 20px; }
        .testi-card { background: var(--pt-paper); border-radius: 4px; padding: 24px; margin-bottom: 20px; break-inside: avoid; box-shadow: 0 10px 24px rgba(22,38,43,.08); border-top: 3px solid var(--pt-rust); }
        .testi-card .stars { color: var(--pt-gold); margin-bottom: 12px; font-size: .9rem; }
        .testi-card p.testi-text { color: #3a3a30; font-style: italic; }
        .testi-user { display: flex; align-items: center; margin-top: 16px; border-top: 1px dashed rgba(22,38,43,.15); padding-top: 14px; }
        .testi-avatar { width: 42px; height: 42px; border-radius: 50%; background: var(--pt-rust); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; margin-right: 12px; flex-shrink: 0; font-family: 'Fraunces', serif; }
        .testi-user h6 { margin: 0; font-weight: 700; }
        .testi-user small { color: #8a8a7c; font-family: 'Space Mono', monospace; font-size: .68rem; text-transform: uppercase; }
        @media (max-width: 991px) { .testi-masonry { column-count: 2; } }
        @media (max-width: 575px) { .testi-masonry { column-count: 1; } }

        .cta-section { background: linear-gradient(120deg, #0F1C20, var(--pt-ink)); color: var(--pt-paper); border-radius: 10px; padding: 58px 40px; margin: 10px 0 60px; border: 1px dashed rgba(221,164,58,.35); }
        .cta-section h3 { font-family: 'Fraunces', serif; }

        footer { background: var(--pt-ink); color: rgba(251,247,236,.6); padding: 50px 0 22px; }
        .footer-grid { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1.2fr; gap: 30px; padding-bottom: 30px; border-bottom: 1px dashed rgba(251,247,236,.15); }
        .footer-grid h6 { color: var(--pt-gold); font-family: 'Space Mono', monospace; font-size: .72rem; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 14px; }
        .footer-grid .brand { color: var(--pt-paper); font-family: 'Fraunces', serif; font-weight: 700; font-size: 1.1rem; margin-bottom: 10px; }
        .footer-grid p { font-size: .88rem; line-height: 1.7; }
        .footer-grid ul { list-style: none; padding: 0; margin: 0; }
        .footer-grid ul li { margin-bottom: 8px; }
        .footer-grid ul a { color: rgba(251,247,236,.65); text-decoration: none; font-size: .88rem; }
        .footer-grid ul a:hover { color: var(--pt-gold); }
        .footer-bottom { text-align: center; padding-top: 20px; font-family: 'Space Mono', monospace; font-size: .78rem; letter-spacing: .5px; }
        @media (max-width: 767px) { .footer-grid { grid-template-columns: 1fr 1fr; } }

        .rating-input { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 4px; }
        .rating-input input { display: none; }
        .rating-input label { font-size: 1.9rem; color: #ddd; cursor: pointer; transition: color .15s ease; }
        .rating-input input:checked ~ label, .rating-input label:hover, .rating-input label:hover ~ label { color: var(--pt-gold); }

        @media (max-width: 991px) {
            .hero { padding: 60px 0 44px; }
            .hero h1 { font-size: 2.1rem; }
            .route-line { display: none; }
            .route-steps { flex-direction: column; gap: 26px; }
            .route-step:nth-child(2) { margin-top: 0; }
            .layanan-section .row > div:first-child { margin-bottom: 40px; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-tp">
    <div class="container">
        <a class="brand navbar-brand d-flex align-items-center gap-2" href="#beranda">
            <img src="<?= BASE_URL ?>img/logo parkir.jpeg" alt="Logo Terminal Parangtritis" class="navbar-logo"
                 onerror="this.style.display='none'">
            TERMINAL PARANGTRITIS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTP">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarTP">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="#beranda">Beranda</a></li>
                <li class="nav-item"><a class="nav-link" href="#alur">Alur Layanan</a></li>
                <li class="nav-item"><a class="nav-link" href="#layanan">Loket &amp; Area</a></li>
                <li class="nav-item"><a class="nav-link" href="#informasi">Informasi</a></li>
                <li class="nav-item"><a class="nav-link" href="#testimoni">Testimoni</a></li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-tp btn-sm px-4 py-2">
                        Login <i class="bi bi-box-arrow-in-right"></i>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<section class="hero" id="beranda">
    <div class="hero-sun"></div>
    <div class="container position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 hero-copy">
                <span class="hero-eyebrow"><i class="bi bi-signpost-2"></i> Gerbang Parkir Digital</span>
                <h1>Setiap Kendaraan <em>Tercatat Rapi</em>, Sejak Gerbang Masuk</h1>
                <p class="lead">
                    Sistem parkir Terminal Parangtritis untuk Admin, Petugas, dan Owner —
                    catat kendaraan masuk, pantau slot, dan cetak struk dalam hitungan detik.
                </p>
                <div class="hero-actions">
                    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-tp px-4 py-2">
                        Masuk ke Sistem <i class="bi bi-arrow-right-circle"></i>
                    </a>
                    <a href="#alur" class="btn btn-outline-tp px-4 py-2">Lihat Alur Layanan</a>
                </div>
            </div>
            <div class="col-lg-6 order-first order-lg-last">
                <div class="hero-photo-wrap">
                    <div class="hero-photo-frame hero-photo-frame--back">
                        <img src="<?= BASE_URL ?>img/mockup.jpeg" alt="Suasana area parkir Terminal Parangtritis"
                             onerror="this.style.display='none'; this.parentElement.classList.add('hero-photo-frame--empty')">
                    </div>
                    <div class="hero-photo-frame hero-photo-frame--front">
                        <img src="<?= BASE_URL ?>img/mockup.jpeg" alt="Gedung Terminal Parangtritis"
                             onerror="this.style.display='none'; this.parentElement.classList.add('hero-photo-frame--empty')">
                        <span class="hero-photo-badge"><i class="bi bi-compass"></i></span>
                        <span class="hero-photo-tag">Terminal Parangtritis · Kretek, Bantul</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="status-band">
    <div class="container d-flex align-items-center gap-4">
        <span class="status-band__label d-none d-md-inline">Status<br>Area</span>
        <?php if (count($daftarAreaLanding) === 0): ?>
            <span class="status-empty">Data area parkir belum tersedia.</span>
        <?php else: ?>
            <div class="status-scroll">
                <?php foreach ($daftarAreaLanding as $al):
                    $kap = (int) $al['kapasitas'];
                    $isi = (int) $al['terisi'];
                    $sisa = max(0, $kap - $isi);
                    $persen = $kap > 0 ? round(($isi / $kap) * 100) : 0;
                    $tagClass = $persen >= 90 ? 'status-chip__tag--full' : ($persen >= 60 ? 'status-chip__tag--warn' : 'status-chip__tag--ok');
                    $tagLabel = $persen >= 90 ? 'Penuh' : ($persen >= 60 ? 'Padat' : 'Tersedia');
                ?>
                <div class="status-chip">
                    <div class="status-chip__top">
                        <span class="status-chip__name"><?= htmlspecialchars($al['nama_area']) ?></span>
                        <span class="status-chip__tag <?= $tagClass ?>"><?= $tagLabel ?></span>
                    </div>
                    <div class="status-chip__bar"><span style="width: <?= $persen ?>%"></span></div>
                    <div class="status-chip__sub">Sisa <?= $sisa ?> / <?= $kap ?> slot</div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<section class="container py-5 route" id="alur">
    <div>
        <span class="section-eyebrow">Perjalanan Kendaraan</span>
        <h2 class="section-title">Alur Layanan di Terminal</h2>
        <p class="section-sub">Tiga tahap sederhana, dari kendaraan masuk sampai laporan tercatat otomatis.</p>
    </div>
    <div class="route-line d-none d-lg-block"></div>
    <div class="route-steps">
        <div class="route-step" data-bs-toggle="modal" data-bs-target="#modalFitur1">
            <div class="route-step__dot">01</div>
            <h5>Akses Sesuai Peran</h5>
            <p>Admin, Petugas, dan Owner masuk ke dashboard masing-masing sesuai kewenangan.</p>
        </div>
        <div class="route-step" data-bs-toggle="modal" data-bs-target="#modalFitur2">
            <div class="route-step__dot">02</div>
            <h5>Slot Dipantau Real-Time</h5>
            <p>Ketersediaan area parkir terlihat langsung, tanpa perlu pengecekan manual.</p>
        </div>
        <div class="route-step" data-bs-toggle="modal" data-bs-target="#modalFitur3">
            <div class="route-step__dot">03</div>
            <h5>Struk &amp; Rekap Otomatis</h5>
            <p>Transaksi tercatat, struk tercetak, laporan tersusun tanpa input berulang.</p>
        </div>
    </div>
</section>

<div class="modal fade" id="modalFitur1" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Akses Berbasis Peran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="modal-icon-lg"><i class="bi bi-shield-check"></i></div>
                <p>Sistem membagi hak akses ke dalam tiga peran, masing-masing dengan tampilan dan kewenangan berbeda:</p>
                <ul>
                    <li><strong>Admin</strong> &mdash; kelola akun pengguna, master data, dan konfigurasi sistem.</li>
                    <li><strong>Petugas</strong> &mdash; proses transaksi parkir harian dan cetak struk.</li>
                    <li><strong>Owner</strong> &mdash; pantau laporan dan performa operasional secara keseluruhan.</li>
                </ul>
                <p class="mb-0 text-muted">Setiap login otomatis diarahkan ke dashboard sesuai peran, sehingga tidak ada akses yang tumpang tindih.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFitur2" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Pantau Slot Real-Time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="modal-icon-lg"><i class="bi bi-p-circle"></i></div>
                <p>Ketersediaan area parkir dapat dipantau secara langsung, sehingga petugas dan owner selalu tahu:</p>
                <ul>
                    <li>Jumlah slot yang masih kosong.</li>
                    <li>Kendaraan mana saja yang sedang parkir.</li>
                    <li>Estimasi kepadatan area parkir pada jam tertentu.</li>
                </ul>
                <p class="mb-0 text-muted">Membantu menghindari penumpukan kendaraan dan mempercepat pengambilan keputusan operasional.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFitur3" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Struk &amp; Rekap Otomatis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="modal-icon-lg"><i class="bi bi-receipt"></i></div>
                <p>Setiap transaksi parkir tercatat otomatis dan dapat langsung dicetak dalam bentuk struk. Sistem juga menyediakan:</p>
                <ul>
                    <li>Rekap transaksi harian, mingguan, hingga bulanan.</li>
                    <li>Ringkasan pendapatan yang siap dilihat owner.</li>
                    <li>Riwayat transaksi yang mudah ditelusuri kembali.</li>
                </ul>
                <p class="mb-0 text-muted">Mengurangi pencatatan manual dan risiko kesalahan hitung.</p>
            </div>
        </div>
    </div>
</div>

<section class="layanan-section" id="layanan">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5">
                <span class="section-eyebrow">Loket Akses</span>
                <h2 class="section-title">Dibuat untuk Setiap Peran</h2>
                <p class="section-sub">Tiga loket, tiga tanggung jawab — tampilan dan hak akses menyesuaikan siapa yang login.</p>
                <div class="ticket-fan">
                    <div class="ticket-card">
                        <div class="ticket-card__top">
                            <i class="bi bi-person-gear"></i>
                            <div><h5>Admin</h5><p>Kelola akun &amp; konfigurasi sistem.</p></div>
                        </div>
                        <div class="ticket-card__perf"></div>
                        <div class="ticket-card__bottom">Loket · 01 · Full Access</div>
                    </div>
                    <div class="ticket-card">
                        <div class="ticket-card__top">
                            <i class="bi bi-person-badge"></i>
                            <div><h5>Petugas</h5><p>Transaksi harian &amp; cetak struk.</p></div>
                        </div>
                        <div class="ticket-card__perf"></div>
                        <div class="ticket-card__bottom">Loket · 02 · Operasional</div>
                    </div>
                    <div class="ticket-card">
                        <div class="ticket-card__top">
                            <i class="bi bi-graph-up-arrow"></i>
                            <div><h5>Owner</h5><p>Laporan &amp; performa operasional.</p></div>
                        </div>
                        <div class="ticket-card__perf"></div>
                        <div class="ticket-card__bottom">Loket · 03 · Monitoring</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <span class="section-eyebrow">Tinjau Lokasi</span>
                <h2 class="section-title">Lihat Area Parkir Kami</h2>
                <p class="section-sub mb-4">Tonton video singkat suasana dan tata letak area parkir Terminal Parangtritis.</p>
                <div class="ratio ratio-16x9 video-parkir-wrap">
                    <video controls preload="metadata" poster="<?= BASE_URL ?>img/parkir.mp4">
                        <source src="<?= BASE_URL ?>img/parkir.mp4" type="video/mp4">
                        Maaf, browser Anda tidak mendukung pemutaran video. Anda dapat
                        <a href="<?= BASE_URL ?>video/area-parkir.mp4">mengunduh video di sini</a>.
                    </video>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="info-section py-5" id="informasi">
    <div class="container">
        <div>
            <span class="section-eyebrow">Ringkasan</span>
            <h2 class="section-title">Informasi</h2>
            <p class="section-sub">Sekilas tentang layanan parkir Terminal Parangtritis.</p>
        </div>

        <div class="stat-ticker">
            <div class="stat-ticker__item"><h3>24/7</h3><p>Pemantauan Real-Time</p></div>
            <div class="stat-ticker__item"><h3>3</h3><p>Peran Pengguna</p></div>
            <div class="stat-ticker__item"><h3>100%</h3><p>Struk Otomatis</p></div>
            <div class="stat-ticker__item"><h3>0</h3><p>Pencatatan Manual</p></div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="grafik-card">
                    <h6 class="fw-bold mb-1"><i class="bi bi-bar-chart-line"></i> Kepadatan Parkir per Jam</h6>
                    <p class="text-muted small mb-3">Grafik jumlah kendaraan masuk berdasarkan jam (hari ini).</p>
                    <canvas id="chartKepadatan"></canvas>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="info-box">
                    <h6 class="fw-bold"><i class="bi bi-clock-history"></i>Jam Operasional</h6>
                    <p class="text-muted mb-0">Sistem parkir aktif mengikuti jam operasional Terminal Parangtritis setiap hari.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box">
                    <h6 class="fw-bold"><i class="bi bi-person-plus"></i>Akun Pengguna</h6>
                    <p class="text-muted mb-0">Akun baru untuk Admin, Petugas, atau Owner hanya dapat dibuat oleh Administrator.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box info-box-clickable" data-bs-toggle="modal" data-bs-target="#modalBantuan">
                    <h6 class="fw-bold"><i class="bi bi-headset"></i>Bantuan</h6>
                    <p class="text-muted mb-0">Kendala login atau transaksi dapat dilaporkan langsung ke Admin sistem.</p>
                    <p class="info-box-link mb-0">Lihat FAQ &amp; kontak <i class="bi bi-arrow-right"></i></p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="testi-section py-5" id="testimoni">
    <div class="container">
        <div class="text-center">
            <span class="section-eyebrow d-block">Kartu Pos Pengguna</span>
            <h2 class="section-title">Testimoni</h2>
            <p class="section-sub mx-auto">Apa kata pengguna sistem parkir Terminal Parangtritis.</p>
            <button type="button" class="btn btn-tp px-4 py-2 mb-4" data-bs-toggle="modal" data-bs-target="#modalTestimoni">
                <i class="bi bi-chat-left-text"></i> Tulis Komentar &amp; Rating
            </button>
        </div>

        <?php if (isset($_GET['testimoni']) && $_GET['testimoni'] === 'sukses'): ?>
            <div class="alert alert-success text-center" role="alert">
                Terima kasih! Komentar Anda sudah terkirim dan akan tampil setelah disetujui Admin.
            </div>
        <?php elseif (isset($_GET['testimoni']) && $_GET['testimoni'] === 'gagal'): ?>
            <div class="alert alert-danger text-center" role="alert">
                Gagal mengirim komentar. Pastikan nama, rating, dan komentar terisi dengan benar.
            </div>
        <?php endif; ?>

        <div class="testi-masonry">
            <?php if (!empty($daftarTestimoni)): ?>
                <?php foreach ($daftarTestimoni as $t): ?>
                    <div class="testi-card">
                        <div class="stars">
                            <?php
                            $r = (int)$t['rating'];
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $r
                                    ? '<i class="bi bi-star-fill"></i>'
                                    : '<i class="bi bi-star"></i>';
                            }
                            ?>
                        </div>
                        <p class="testi-text">"<?= htmlspecialchars($t['komentar']) ?>"</p>
                        <div class="testi-user">
                            <div class="testi-avatar"><?= strtoupper(substr($t['nama'], 0, 1)) ?></div>
                            <div>
                                <h6><?= htmlspecialchars($t['nama']) ?></h6>
                                <small><?= htmlspecialchars($t['role']) ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="testi-card">
                    <div class="stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="testi-text">"Sejak pakai sistem ini, transaksi parkir jadi lebih cepat dan struk langsung tercetak otomatis."</p>
                    <div class="testi-user">
                        <div class="testi-avatar">R</div>
                        <div>
                            <h6>Rian</h6>
                            <small>Petugas Parkir</small>
                        </div>
                    </div>
                </div>
                <div class="testi-card">
                    <div class="stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="testi-text">"Laporan pendapatan parkir bisa saya pantau kapan saja tanpa harus datang langsung ke lokasi. Dashboard owner ringkas dan jelas."</p>
                    <div class="testi-user">
                        <div class="testi-avatar">D</div>
                        <div>
                            <h6>Coach Zaenal</h6>
                            <small>Owner Terminal Parangtritis</small>
                        </div>
                    </div>
                </div>
                <div class="testi-card">
                    <div class="stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
                    </div>
                    <p class="testi-text">"Sebagai Admin, mengelola akun dan data pengguna jadi jauh lebih rapi dan terstruktur."</p>
                    <div class="testi-user">
                        <div class="testi-avatar">A</div>
                        <div>
                            <h6>Admin D</h6>
                            <small>Administrator</small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="modal fade" id="modalTestimoni" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-chat-left-text"></i> Tulis Komentar &amp; Rating</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>testimoni_submit.php" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="inputNama" class="form-label fw-semibold">Nama</label>
                        <input type="text" class="form-control" id="inputNama" name="nama" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label for="inputRole" class="form-label fw-semibold">Peran / Status <span class="text-muted fw-normal">(opsional)</span></label>
                        <input type="text" class="form-control" id="inputRole" name="role" maxlength="50" placeholder="Contoh: User, Petugas, Owner">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-block">Rating</label>
                        <div class="rating-input">
                            <input type="radio" id="star5" name="rating" value="5" required><label for="star5" title="5 bintang"><i class="bi bi-star-fill"></i></label>
                            <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 bintang"><i class="bi bi-star-fill"></i></label>
                            <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 bintang"><i class="bi bi-star-fill"></i></label>
                            <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 bintang"><i class="bi bi-star-fill"></i></label>
                            <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 bintang"><i class="bi bi-star-fill"></i></label>
                        </div>
                    </div>
                    <div class="mb-1">
                        <label for="inputKomentar" class="form-label fw-semibold">Komentar</label>
                        <textarea class="form-control" id="inputKomentar" name="komentar" rows="4" maxlength="1000" required placeholder="Ceritakan pengalaman Anda menggunakan sistem parkir Terminal Parangtritis..."></textarea>
                    </div>
                    <p class="text-muted small mb-0">Komentar Anda akan ditinjau oleh Admin sebelum tampil di halaman ini.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-tp px-4">Kirim <i class="bi bi-send"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>

<section class="container">
    <div class="cta-section text-center">
        <h3 class="fw-bold mb-2">Siap mengelola parkir Terminal Parangtritis?</h3>
        <p class="opacity-75 mb-4">Masuk ke sistem untuk mulai memantau dan mengelola transaksi parkir.</p>
        <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-tp px-5 py-2">
            Masuk Sekarang <i class="bi bi-box-arrow-in-right"></i>
        </a>
        <div class="mt-3">
            <small class="opacity-50">Akun Petugas/Owner hanya dapat dibuat oleh Administrator.</small>
        </div>
    </div>
</section>

<footer>
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="brand">Terminal Parangtritis</div>
                <p>Sistem manajemen parkir untuk user &amp; tamu Terminal Parangtritis — dikelola oleh Admin, Petugas, dan Owner dalam satu platform.</p>
            </div>
            <div>
                <h6>Navigasi</h6>
                <ul>
                    <li><a href="#beranda">Beranda</a></li>
                    <li><a href="#alur">Alur Layanan</a></li>
                    <li><a href="#layanan">Loket &amp; Area</a></li>
                    <li><a href="#testimoni">Testimoni</a></li>
                </ul>
            </div>
            <div>
                <h6>Bantuan</h6>
                <ul>
                    <li><a href="#" data-bs-toggle="modal" data-bs-target="#modalBantuan">Pusat Bantuan</a></li>
                    <li><a href="<?= BASE_URL ?>auth/login.php">Login Sistem</a></li>
                </ul>
            </div>
            <div>
                <h6>Kontak</h6>
                <ul>
                    <li><a href="https://wa.me/6288216158488?text=Halo%2C%20min%20saya%20perlu%20bantuan%20Anda" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp Admin</a></li>
                    <li><a href="mailto:zaenal07@gmail.com"><i class="bi bi-envelope"></i> zaenal07@gmail.com</a></li>
                    <li><i class="bi bi-geo-alt"></i> Terminal Parangtritis, pos operator parkir</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> Parkir Terminal Parangtritis System. All rights reserved. — BY Zaenal
        </div>
    </div>
</footer>

<div class="modal fade" id="modalBantuan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-headset"></i> Pusat Bantuan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <h6 class="fw-bold mb-3">Pertanyaan yang Sering Diajukan</h6>
                <div class="accordion mb-4" id="accordionBantuan">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                Saya lupa password akun, bagaimana cara reset?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#accordionBantuan">
                            <div class="accordion-body text-muted">
                                Reset password hanya dapat dilakukan oleh Administrator. Silakan hubungi Admin melalui kontak di bawah dengan menyertakan username akun Anda.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                Bagaimana cara membuat akun baru?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#accordionBantuan">
                            <div class="accordion-body text-muted">
                                Akun untuk Admin, Petugas, maupun Owner hanya dapat dibuat oleh Administrator melalui menu kelola pengguna. Pengguna baru tidak dapat mendaftar sendiri.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                Struk transaksi tidak tercetak, apa yang harus dilakukan?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#accordionBantuan">
                            <div class="accordion-body text-muted">
                                Periksa koneksi printer terlebih dahulu. Jika masih bermasalah, transaksi tetap tersimpan di sistem dan struk dapat dicetak ulang oleh Petugas melalui riwayat transaksi.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                Data slot parkir tidak update secara real-time?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#accordionBantuan">
                            <div class="accordion-body text-muted">
                                Pastikan koneksi internet perangkat stabil, lalu muat ulang (refresh) halaman. Jika masalah berlanjut, laporkan ke Admin sistem.
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-3">Hubungi Kami</h6>
                <a href="https://wa.me/6288216158488?text=Halo%2C%20min%20saya%20perlu%20bantuan%20Anda" target="_blank" rel="noopener" class="bantuan-contact-item">
                    <i class="bi bi-whatsapp"></i>
                    <div>
                        <strong>WhatsApp Admin</strong>
                        <div class="small text-muted">Respon cepat untuk kendala teknis</div>
                    </div>
                </a>
                <a href="mailto:zaenal07@gmail.com" class="bantuan-contact-item">
                    <i class="bi bi-envelope"></i>
                    <div>
                        <strong>Email</strong>
                        <div class="small text-muted">zaenal07@gmail.com</div>
                    </div>
                </a>
                <div class="bantuan-contact-item" style="cursor:default;">
                    <i class="bi bi-geo-alt"></i>
                    <div>
                        <strong>Lokasi</strong>
                        <div class="small text-muted">Terminal Parangtritis, area pos operator parkir</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<button type="button" class="help-float-btn" data-bs-toggle="modal" data-bs-target="#modalBantuan" title="Bantuan">
    <i class="bi bi-headset"></i>
</button>

<?php if (isset($_GET['testimoni'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById('testimoni');
        if (el) el.scrollIntoView({ behavior: 'instant', block: 'start' });
    });
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const ctxKepadatan = document.getElementById('chartKepadatan');
    if (ctxKepadatan) {
        new Chart(ctxKepadatan, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labelJam) ?>,
                datasets: [{
                    label: 'Jumlah Kendaraan',
                    data: <?= json_encode($dataKepadatan) ?>,
                    backgroundColor: '#BE4E2C',
                    hoverBackgroundColor: '#DDA43A',
                    borderRadius: 3,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(22,38,43,.06)' } }
                }
            }
        });
    }
</script>
</body>
</html>
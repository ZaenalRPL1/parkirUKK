<?php
require_once __DIR__ . '/../config/koneksi.php';

// Jika sudah login, langsung arahkan ke dashboard sesuai role
if (isset($_SESSION['id_user'])) {
    switch ($_SESSION['role']) {
        case 'admin': header("Location: " . BASE_URL . "admin/index.php"); exit;
        case 'petugas': header("Location: " . BASE_URL . "operator/index.php"); exit;
        case 'owner': header("Location: " . BASE_URL . "owner/index.php"); exit;
        case 'user': header("Location: " . BASE_URL . "user/index.php"); exit;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    if ($nama_lengkap === '' || $username === '' || $password === '' || $konfirmasi_password === '') {
        $error = 'Semua kolom wajib diisi.';
    } elseif (strlen($username) < 4) {
        $error = 'Username minimal 4 karakter.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $konfirmasi_password) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        // Cek username sudah dipakai atau belum
        $cek = $koneksi->prepare("SELECT id_user FROM tb_user WHERE username = ? LIMIT 1");
        $cek->execute([$username]);

        if ($cek->fetch()) {
            $error = 'Username sudah digunakan, silakan pilih username lain.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user'; // registrasi mandiri publik HANYA untuk role user

            $stmt = $koneksi->prepare(
                "INSERT INTO tb_user (nama_lengkap, username, password, role, status_aktif) 
                 VALUES (?, ?, ?, ?, 1)"
            );
            $stmt->execute([$nama_lengkap, $username, $hash, $role]);

            $success = 'Registrasi berhasil! Silakan login menggunakan akun anda.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Registrasi — Terminal Parangtritis</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,500&family=Work+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        :root {
            /* ===== Palet "Senja Dermaga" — sama dengan halaman beranda & login ===== */
            --pt-ink:    #16262B;
            --pt-ink-2:  #1F3B3E;
            --pt-sand:   #F2E9D6;
            --pt-paper:  #FBF7EC;
            --pt-rust:   #BE4E2C;
            --pt-gold:   #DDA43A;
            --pt-sea:    #3E6E6B;
            --pt-text:   #1C2B27;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; font-family: 'Work Sans', system-ui, -apple-system, sans-serif; color: var(--pt-text); }
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--pt-sand); padding: 24px; position: relative; }
        h1, h2, h3, .display-face { font-family: 'Fraunces', Georgia, serif; }
        .mono-face { font-family: 'Space Mono', 'Courier New', monospace; }

        .btn-back-landing {
            position: fixed; top: 22px; left: 22px;
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--pt-ink); color: var(--pt-paper);
            font-family: 'Space Mono', monospace; font-weight: 600; font-size: .78rem;
            letter-spacing: .5px; text-transform: uppercase;
            padding: 9px 18px; border-radius: 3px; text-decoration: none;
            border: 1px solid rgba(221,164,58,.4);
            transition: transform .15s ease, background .15s ease;
            z-index: 10;
        }
        .btn-back-landing:hover { color: var(--pt-gold); transform: translateX(-3px); }

        /* ===== Kartu registrasi: sepasang tiket — stub info (kiri) + formulir (kanan) ===== */
        .auth-wrapper { width: 100%; max-width: 920px; }
        .auth-card {
            display: grid; grid-template-columns: 1fr 1fr;
            background: var(--pt-paper); border-radius: 12px; overflow: hidden;
            box-shadow: 0 30px 70px rgba(22,38,43,.22);
            border: 1px solid rgba(22,38,43,.06);
        }
        .auth-side {
            position: relative;
            background: linear-gradient(165deg, #0F1C20 0%, #1B2E30 55%, #3B3327 100%);
            color: #fff; padding: 46px 40px;
            display: flex; flex-direction: column; justify-content: center;
        }
        .auth-side::after { content: ""; position: absolute; top: 0; right: -1px; bottom: 0; width: 0; border-right: 2px dashed rgba(221,164,58,.35); }
        .auth-side .badge-tag {
            display: inline-flex; align-items: center; gap: 8px;
            font-family: 'Space Mono', monospace; font-size: .68rem; letter-spacing: 2.5px; text-transform: uppercase;
            color: var(--pt-gold); border: 1px solid rgba(221,164,58,.5); padding: 5px 12px; border-radius: 50px;
            width: fit-content;
        }
        .auth-side h1 { font-size: 1.7rem; font-weight: 600; margin: 18px 0 12px; letter-spacing: .5px; }
        .auth-side p { color: rgba(251,247,236,.75); font-size: .92rem; line-height: 1.6; }
        .auth-side hr { border-top: 1px dashed rgba(251,247,236,.25); }
        .auth-side .feature-line {
            display: flex; align-items: center; gap: 10px;
            font-family: 'Space Mono', monospace; font-size: .74rem; letter-spacing: .5px; text-transform: uppercase;
            color: rgba(251,247,236,.65); margin-bottom: 10px;
        }
        .auth-side .feature-line i { color: var(--pt-gold); font-size: 1rem; }
        .auth-side .ticket-num { margin-top: 28px; font-family: 'Space Mono', monospace; font-size: .68rem; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(251,247,236,.4); }

        .auth-form { padding: 42px 42px; }
        .auth-form h3 { font-weight: 600; font-size: 1.5rem; margin-bottom: 6px; }
        .auth-form .text-muted { color: #7a7a6e !important; font-size: .92rem; }
        .auth-form .form-label { font-family: 'Space Mono', monospace; font-size: .7rem; letter-spacing: 1.5px; text-transform: uppercase; color: var(--pt-sea); font-weight: 700; margin-bottom: 6px; }
        .auth-form .form-control { border: 1px solid rgba(22,38,43,.15); border-radius: 4px; padding: 10px 14px; background: #fff; font-family: 'Work Sans', sans-serif; }
        .auth-form .form-control:focus { border-color: var(--pt-rust); box-shadow: 0 0 0 3px rgba(190,78,44,.12); }
        .btn-tp {
            background: var(--pt-rust); color: var(--pt-paper); border: none; font-weight: 600; border-radius: 4px;
            font-family: 'Space Mono', monospace; font-size: .85rem; letter-spacing: .5px; text-transform: uppercase;
            transition: transform .15s ease, background .15s ease;
        }
        .btn-tp:hover { color: var(--pt-paper); background: #a3421f; transform: translateY(-2px); }
        .auth-form .alert-danger { background: rgba(190,78,44,.1); border: 1px solid rgba(190,78,44,.3); color: #8a3010; border-radius: 4px; font-size: .88rem; }
        .auth-form .alert-success { background: rgba(62,110,107,.12); border: 1px solid rgba(62,110,107,.35); color: #23443f; border-radius: 4px; font-size: .88rem; }
        .auth-form a { color: var(--pt-sea); font-weight: 600; text-decoration: none; }
        .auth-form a:hover { color: var(--pt-rust); }

        @media (max-width: 767px) {
            .auth-card { grid-template-columns: 1fr; }
            .auth-side::after { display: none; }
            .auth-side { border-bottom: 2px dashed rgba(221,164,58,.35); }
            .btn-back-landing { position: absolute; }
            body { padding: 70px 16px 24px; align-items: flex-start; }
        }
    </style>
</head>
<body>
<a href="<?= BASE_URL ?>auth/login.php" class="btn-back-landing">
    <i class="bi bi-arrow-left"></i> Kembali ke Login
</a>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-side">
            <span class="badge-tag"><i class="bi bi-signpost-2"></i> Terminal Parking System</span>
            <h1>TERMINAL PARANGTRITIS</h1>
            <p class="mb-0">Sistem manajemen parkir untuk user &amp; tamu Terminal Parangtritis. Cepat, rapi, dan
                terpantau real-time untuk Admin, Petugas, dan Owner.</p>
            <hr class="my-4">
            <div class="feature-line"><i class="bi bi-shield-check"></i> Akses berbasis peran</div>
            <div class="feature-line"><i class="bi bi-p-circle"></i> Slot area parkir real-time</div>
            <div class="feature-line"><i class="bi bi-receipt"></i> Struk &amp; rekap otomatis</div>
            <div class="ticket-num">Loket · Pendaftaran Akun</div>
        </div>
        <div class="auth-form">
            <h3>Buat Akun Baru 📝</h3>
            <p class="text-muted mb-4">Daftar sebagai user untuk menikmati layanan parkir Terminal Parangtritis</p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" placeholder="Masukkan nama lengkap"
                           value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Konfirmasi Password</label>
                    <input type="password" name="konfirmasi_password" class="form-control" placeholder="Ulangi password" required>
                </div>
                <button type="submit" class="btn btn-tp w-100 py-2 mt-2">Daftar <i class="bi bi-person-plus"></i></button>
            </form>
            <div class="text-center mt-4">
                <small class="text-muted">Sudah punya akun? <a href="<?= BASE_URL ?>auth/login.php">Masuk di sini</a></small>
            </div>
        </div>
    </div>
</div>
</body>
</html>
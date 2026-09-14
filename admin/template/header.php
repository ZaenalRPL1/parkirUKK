<?php
/**
 * Dipakai di dalam admin/*.php setelah $page_title didefinisikan.
 * Membutuhkan variabel $koneksi & session admin aktif (cekLogin(['admin'])).
 */

// Ambil foto profil terbaru dari database (biar langsung update tanpa perlu login ulang)
$fotoAdmin = null;
if (isset($_SESSION['id_user'])) {
    $stmtFotoAdmin = $koneksi->prepare("SELECT foto FROM tb_user WHERE id_user = ?");
    $stmtFotoAdmin->execute([$_SESSION['id_user']]);
    $rowFotoAdmin = $stmtFotoAdmin->fetch();
    $fotoAdmin = $rowFotoAdmin['foto'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>Parkir Terminal Parangtritis</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        /* Poles kecil khusus topbar admin — menyamakan elemen yang belum ikut tema di style.css */
        #sidebarToggle {
            background: var(--tp-dark, #16262B);
            color: #fff;
            border: none;
            border-radius: 6px;
        }
        #sidebarToggle:hover { background: var(--tp-blue, #BE4E2C); color: #fff; }

        .user-chip .role-label { font-size: .7rem; color: var(--tp-muted, #7E8F86); font-family: 'Space Mono', monospace; text-transform: uppercase; letter-spacing: .5px; }

        .dropdown-menu {
            border: 1px solid rgba(22,38,43,.08);
            border-radius: 8px;
            box-shadow: 0 14px 34px rgba(22,38,43,.14);
            padding: 8px;
        }
        .dropdown-menu .dropdown-item {
            border-radius: 6px;
            font-size: .88rem;
            padding: 9px 12px;
        }
        .dropdown-menu .dropdown-item:hover,
        .dropdown-menu .dropdown-item:focus {
            background: rgba(190,78,44,.08);
            color: var(--tp-blue, #BE4E2C);
        }
        .dropdown-menu .dropdown-divider { border-top-color: rgba(22,38,43,.08); }

        .alert-auto-dismiss.alert-success { background: rgba(62,110,107,.12); border: 1px solid rgba(62,110,107,.35); color: #23443f; }
        .alert-auto-dismiss.alert-danger { background: rgba(190,78,44,.1); border: 1px solid rgba(190,78,44,.3); color: #8a3010; }
    </style>
</head>
<body>
<?php tampilkanNotifikasiLogin(); ?>
<?php include __DIR__ . '/sidebar_admin.php'; ?>

<div class="tp-content">
    <div class="tp-topbar">
        <div class="d-flex align-items-center gap-3">
            <button id="sidebarToggle" class="btn btn-sm d-lg-none"><i class="bi bi-list"></i></button>
            <h1 class="page-title"><?= isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard' ?></h1>
        </div>
        <div class="dropdown">
            <div class="user-chip" role="button" data-bs-toggle="dropdown">
                <div class="avatar-mini avatar-mini-img">
                    <?php if ($fotoAdmin): ?>
                        <img src="<?= BASE_URL ?>uploads/profil/<?= htmlspecialchars($fotoAdmin) ?>"
                             alt="Foto Profil"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <?php endif; ?>
                    <span class="avatar-fallback" style="<?= $fotoAdmin ? 'display:none;' : 'display:flex;' ?>">
                        <?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?>
                    </span>
                </div>
                <div>
                    <div style="font-size:.85rem;font-weight:600;line-height:1;"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></div>
                    <div class="role-label">Administrator</div>
                </div>
                <i class="bi bi-chevron-down small"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end mt-2">
                <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/edit_profil.php"><i class="bi bi-person-circle me-2"></i>Edit Profil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
    <div class="tp-body">
        <?php if (isset($_GET['sukses'])): ?>
            <div class="alert alert-success alert-auto-dismiss"><i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($_GET['sukses']) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['gagal'])): ?>
            <div class="alert alert-danger alert-auto-dismiss"><i class="bi bi-x-circle me-1"></i> <?= htmlspecialchars($_GET['gagal']) ?></div>
        <?php endif; ?>
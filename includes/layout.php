<?php

/* === includes/layout.php === */

if (!isset($page_title)) {

    $page_title = 'Toko Victory';

}

if (!isset($BASE)) {

    $BASE = '.';

}

if (!isset($active_menu)) {

    $active_menu = '';

}

$masterOpen = in_array($active_menu, ['kategori', 'satuan', 'warna', 'merek', 'lokasi'], true);

$user = $_SESSION['user'] ?? [];

$nama = h($user['nama'] ?? 'Pengguna');

$initials = itemInitials($user['nama'] ?? 'P');

$roleUser = $user['role'] ?? 'karyawan';

$roleLabel = ucfirst($roleUser);

$roleBadgeClass = $roleUser === 'owner' ? 'badge-owner' : ($roleUser === 'admin' ? 'badge-admin' : 'badge-karyawan');

$flash = getFlash();

if (!isset($BASE) || $BASE === '.') {

    $BASE = rtrim(appBasePath(), '/');

}

$BASE = $BASE === '' ? '' : $BASE;

$tvBase = $BASE;

$tvTitle = h($page_title) . ' — Toko Victory';

?>

<!DOCTYPE html>

<html lang="id">

<head>

<?php include __DIR__ . '/head.php'; ?>

</head>

<body class="app-body">

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app-shell layout">

  <aside class="sidebar" id="sidebar">

    <a href="<?= $BASE ?>/dashboard.php" class="sidebar-logo">

      <div class="logo-box">VY</div>

      <div class="logo-text">Toko Victory<span>Inventori</span></div>

    </a>

    <nav class="nav">

      <a href="<?= $BASE ?>/dashboard.php" class="<?= $active_menu === 'dashboard' ? 'active' : '' ?>" data-tooltip="Dashboard"><i class="ti ti-layout-dashboard"></i><span>Dashboard</span></a>

      <?php if (isFullAccess($user['role'] ?? '')): ?>

      <div class="nav-section">Master</div>

      <a href="<?= $BASE ?>/barang/index.php" class="<?= $active_menu === 'barang' ? 'active' : '' ?>"><i class="ti ti-package"></i><span>Daftar Barang</span></a>

      <a href="<?= $BASE ?>/supplier/index.php" class="<?= $active_menu === 'supplier' ? 'active' : '' ?>"><i class="ti ti-truck"></i><span>Supplier</span></a>

      <a href="<?= $BASE ?>/pengguna/index.php" class="<?= $active_menu === 'pengguna' ? 'active' : '' ?>"><i class="ti ti-users"></i><span>Pengguna</span></a>

      <button type="button" class="nav-toggle" id="masterToggle"><i class="ti ti-database"></i><span>Master Data</span><i class="ti ti-chevron-down" style="margin-left:auto;font-size:14px"></i></button>

      <div class="nav-sub <?= $masterOpen ? 'open' : '' ?>" id="masterSub">

        <a href="<?= $BASE ?>/referensi/kategori.php" class="<?= $active_menu === 'kategori' ? 'active' : '' ?>">Kategori</a>

        <a href="<?= $BASE ?>/referensi/satuan.php" class="<?= $active_menu === 'satuan' ? 'active' : '' ?>">Satuan</a>

        <a href="<?= $BASE ?>/referensi/warna.php" class="<?= $active_menu === 'warna' ? 'active' : '' ?>">Warna</a>

        <a href="<?= $BASE ?>/referensi/merek.php" class="<?= $active_menu === 'merek' ? 'active' : '' ?>">Merek</a>

        <a href="<?= $BASE ?>/referensi/lokasi.php" class="<?= $active_menu === 'lokasi' ? 'active' : '' ?>">Lokasi</a>

      </div>

      <a href="<?= $BASE ?>/pemesanan/index.php" class="<?= str_starts_with($active_menu ?? '', 'pemesanan') ? 'active' : '' ?>"><i class="ti ti-shopping-cart"></i><span>Pemesanan</span></a>

      <?php endif; ?>

      <a href="<?= $BASE ?>/transaksi/index.php" class="<?= str_starts_with($active_menu ?? '', 'transaksi') ? 'active' : '' ?>"><i class="ti ti-arrows-exchange"></i><span>Transaksi</span></a>

      <a href="<?= $BASE ?>/laporan/index.php" class="<?= str_starts_with($active_menu ?? '', 'laporan') ? 'active' : '' ?>"><i class="ti ti-report-analytics"></i><span>Laporan</span></a>

    </nav>

    <div class="sidebar-user">

      <div class="user-row">

        <div class="avatar"><?= $initials ?></div>

        <div class="user-info">

          <div class="name"><?= $nama ?></div>

          <span class="badge <?= h($roleBadgeClass) ?>"><?= h($roleLabel) ?></span>

        </div>

      </div>

      <a href="<?= $BASE ?>/logout.php" class="btn-logout"><i class="ti ti-logout"></i> Keluar</a>

    </div>

  </aside>

  <div class="main">

    <header class="app-header header">

      <button type="button" class="btn-icon hamburger" id="btnMenu" aria-label="Menu"><i class="ti ti-menu-2"></i></button>

      <div class="header-titles">
        <div class="breadcrumb"><i class="ti ti-building-store"></i> Toko Victory <span class="breadcrumb-sep">/</span> <strong><?= h($page_title) ?></strong></div>
      </div>

      <div class="header-right">

        <div class="avatar header-avatar" title="<?= $nama ?>"><?= $initials ?></div>

      </div>

    </header>

    <div class="app-content content">

      <?php if ($flash): ?>

      <div class="flash <?= h($flash['type']) ?>" id="flashMsg">

        <i class="ti ti-<?= $flash['type'] === 'success' ? 'circle-check' : ($flash['type'] === 'warning' ? 'alert-triangle' : 'alert-circle') ?>"></i>

        <?= h($flash['message']) ?>

      </div>

      <?php endif; ?>

      <?= $content ?? '' ?>

    </div>

  </div>

</div>

<?php include __DIR__ . '/app_scripts.php'; ?>

</body>

</html>


<?php
/* === includes/auth_shell.php — buka auth (set $auth_mode = login|register) === */
$tvBase = rtrim(appBasePath(), '/');
$authMode = $authMode ?? 'login';
$authCtaHref = $authMode === 'login' ? $tvBase . '/register.php' : $tvBase . '/login.php';
$authCtaLabel = $authMode === 'login' ? 'Belum punya akun? Daftar' : 'Sudah punya akun? Masuk';
$authTitle = $authMode === 'login' ? 'Login — Toko Victory' : 'Daftar Akun — Toko Victory';
$tvTitle = $authTitle;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php include __DIR__ . '/head.php'; ?>
</head>
<body class="auth-page">
<header class="auth-navbar">
  <a href="<?= h($tvBase) ?>/login.php" class="sidebar-brand navbar-brand">
    <span class="logo-box">VY</span>
    <span class="logo-text">Toko Victory<span>Inventori Stok</span></span>
  </a>
  <a href="<?= h($authCtaHref) ?>" class="btn-nav"><?= h($authCtaLabel) ?></a>
</header>
<div class="auth-shell">
  <div class="auth-panel-left">
    <div class="auth-dots" aria-hidden="true"></div>
    <div class="auth-hero">
      <div class="logo-box">VY</div>
      <h2>Toko Victory</h2>
      <p>Aplikasi inventori modern untuk mengelola stok toko Anda secara real-time.</p>
      <p class="auth-quote">Kelola stok dengan presisi — dari gudang hingga etalase.</p>
      <div class="auth-features">
        <div class="auth-feature"><i class="ti ti-chart-line"></i> Laporan real-time</div>
        <div class="auth-feature"><i class="ti ti-package"></i> Master barang</div>
        <div class="auth-feature"><i class="ti ti-arrows-exchange"></i> Transaksi stok</div>
        <div class="auth-feature"><i class="ti ti-shield-check"></i> Aman & terstruktur</div>
      </div>
    </div>
  </div>
  <div class="auth-panel-right">
    <div class="auth-form-wrap">

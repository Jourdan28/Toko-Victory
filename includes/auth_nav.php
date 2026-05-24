<?php
/** @var string $auth_nav_cta_href */
/** @var string $auth_nav_cta_label */
?>
<header class="navbar">
  <a href="login.php" class="navbar-brand">
    <span class="logo-box">VY</span>
    <span class="brand-text">Toko Victory<span>Inventori Stok</span></span>
  </a>
  <a href="<?= h($auth_nav_cta_href) ?>" class="btn-nav"><?= h($auth_nav_cta_label) ?></a>
</header>

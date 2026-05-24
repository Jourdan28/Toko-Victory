<?php
/* === includes/head.php === */
if (!isset($tvBase)) {
    $tvBase = rtrim(appBasePath(), '/');
}
$tvTitle = $tvTitle ?? 'Toko Victory';
$cssMain = __DIR__ . '/styles.css';
$cssPremium = __DIR__ . '/styles-premium.css';
$cssVer = max(
    is_file($cssMain) ? (int) filemtime($cssMain) : 0,
    is_file($cssPremium) ? (int) filemtime($cssPremium) : 0
) ?: time();
?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($tvTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <link rel="stylesheet" href="<?= h($tvBase) ?>/includes/styles.css?v=<?= $cssVer ?>">
  <link rel="stylesheet" href="<?= h($tvBase) ?>/includes/styles-premium.css?v=<?= $cssVer ?>">
  <script>
  (function () {
    if (localStorage.getItem('theme') === 'light') {
      document.documentElement.classList.add('light');
    }
  })();
  </script>
  <script src="<?= h($tvBase) ?>/includes/page_utils.js"></script>

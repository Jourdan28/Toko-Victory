<?php
/* === transaksi/keluar.php === */
require_once __DIR__ . '/../includes/db.php';
requireStaff();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'transaksi_keluar';
$page_title = 'Barang Keluar';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$total = (int) $pdo->query(
    "SELECT COUNT(*) FROM transaksi t
     INNER JOIN barang b ON t.id_barang = b.id
     WHERE t.jenis = 'keluar'"
)->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    header('Location: keluar.php' . ($totalPages > 1 ? '?page=' . $totalPages : ''));
    exit;
}

$stmt = $pdo->prepare(
    "SELECT t.*, b.nama_barang, b.rop AS rop_barang, u.name AS nama_user
     FROM transaksi t
     JOIN barang b ON t.id_barang = b.id
     LEFT JOIN users u ON t.id_user = u.id
     WHERE t.jenis = 'keluar'
     ORDER BY t.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute();
$riwayat = $stmt->fetchAll();

ob_start();
?>
<div class="page-head">
  <h2><i class="ti ti-arrow-bar-up" style="color:var(--red)"></i> Barang Keluar</h2>
  <a href="index.php" class="btn-outline">← Manajemen Transaksi</a>
</div>

<nav class="tabs" id="tabTransaksiNav" aria-label="Jenis transaksi">
  <a href="index.php" class="report-tab">Semua</a>
  <a href="masuk.php" class="report-tab">Barang Masuk</a>
  <a href="keluar.php" class="report-tab active">Barang Keluar</a>
</nav>

<div class="search-box"><i class="ti ti-search"></i><input type="search" id="searchRiwayat" placeholder="Cari barang keluar..."></div>
<div class="card-table">
  <table id="tblRiwayat">
    <thead><tr><th>No</th><th>Kode</th><th>Barang</th><th>Jml</th><th>Stok</th><th>ROP</th><th>Oleh</th><th>Tanggal</th></tr></thead>
    <tbody>
      <?php if (empty($riwayat)): ?>
      <tr><td colspan="8" class="empty-state">Belum ada data barang keluar.</td></tr>
      <?php else: foreach ($riwayat as $i => $t): ?>
      <tr>
        <td><?= $offset + $i + 1 ?></td>
        <td class="mono"><?= h($t['kode_transaksi'] ?? '-') ?></td>
        <td><?= h($t['nama_barang']) ?></td>
        <td class="mono">-<?= (int)$t['jumlah'] ?></td>
        <td class="mono"><?= (int)($t['stok_sebelum'] ?? 0) ?> → <?= (int)($t['stok_sesudah'] ?? 0) ?></td>
        <td><?= (int)($t['rop_barang'] ?? 0) ?></td>
        <td><?= h($t['nama_user'] ?? '-') ?></td>
        <td><?= date('d M Y H:i', strtotime($t['created_at'])) ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php
    $pgBase = static fn(int $p): string => $p > 1 ? 'keluar.php?page=' . $p : 'keluar.php';
    if ($page > 1): ?>
    <a href="<?= h($pgBase($page - 1)) ?>">‹ Prev</a>
    <?php endif;
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    for ($p = $start; $p <= $end; $p++): ?>
    <a href="<?= h($pgBase($p)) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor;
    if ($page < $totalPages): ?>
    <a href="<?= h($pgBase($page + 1)) ?>">Next ›</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<script>runPageInit(() => liveSearch('searchRiwayat', 'tblRiwayat'));</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

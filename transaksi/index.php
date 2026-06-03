<?php
/* === transaksi/index.php === */
require_once __DIR__ . '/../includes/db.php';
requireStaff();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'transaksi';
$page_title = 'Manajemen Transaksi';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM transaksi t
     INNER JOIN barang b ON t.id_barang = b.id"
);
$countStmt->execute();
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    header('Location: index.php' . ($totalPages > 1 ? '?page=' . $totalPages : ''));
    exit;
}
$offset = ($page - 1) * $perPage;

$sql = "SELECT t.*, b.nama_barang, u.name AS nama_user
        FROM transaksi t
        JOIN barang b ON t.id_barang = b.id
        LEFT JOIN users u ON t.id_user = u.id
        ORDER BY t.created_at DESC
        LIMIT $perPage OFFSET $offset";
$rows = $pdo->query($sql)->fetchAll();

$trxHari = (int) $pdo->query('SELECT COUNT(*) FROM transaksi WHERE DATE(created_at) = CURDATE()')->fetchColumn();
$masukHari = (int) $pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM transaksi WHERE jenis='masuk' AND DATE(created_at)=CURDATE()")->fetchColumn();
$keluarHari = (int) $pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM transaksi WHERE jenis='keluar' AND DATE(created_at)=CURDATE()")->fetchColumn();

ob_start();
?>
<div class="page-head">
  <h2>Manajemen Transaksi</h2>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="catat_keluar.php" class="btn-red"><i class="ti ti-arrow-bar-up"></i> Barang Keluar</a>
  </div>
</div>

<div class="stats-row">
  <div class="stat-mini"><div class="val"><?= $trxHari ?></div><div class="lbl">Transaksi hari ini</div></div>
  <div class="stat-mini"><div class="val" style="color:var(--green)"><?= number_format($masukHari) ?></div><div class="lbl">Unit masuk hari ini</div></div>
  <div class="stat-mini"><div class="val" style="color:var(--red)"><?= number_format($keluarHari) ?></div><div class="lbl">Unit keluar hari ini</div></div>
</div>

<nav class="tabs" id="tabTransaksiNav" aria-label="Jenis transaksi">
  <a href="index.php" class="report-tab active">Semua</a>
  <a href="masuk.php" class="report-tab">Barang Masuk</a>
  <a href="keluar.php" class="report-tab">Barang Keluar</a>
</nav>

<div class="search-box"><i class="ti ti-search"></i><input type="search" id="searchTable" placeholder="Cari barang atau kode transaksi..."></div>

<div class="card-table">
  <table id="dataTable">
    <thead>
      <tr>
        <th>No</th><th>Kode</th><th>Barang</th><th>Jenis</th><th>Jml</th>
        <th>Stok</th><th>Oleh</th><th>Waktu</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
      <tr><td colspan="8" class="empty-state">Belum ada transaksi.</td></tr>
      <?php else: foreach ($rows as $i => $t):
        $kode = $t['kode_transaksi'] ?? ('#' . $t['id']);
        $sb = (int)($t['stok_sebelum'] ?? 0);
        $ss = (int)($t['stok_sesudah'] ?? 0);
      ?>
      <tr data-jenis="<?= h($t['jenis']) ?>">
        <td><?= $offset + $i + 1 ?></td>
        <td class="mono"><?= h($kode) ?></td>
        <td><?= h($t['nama_barang']) ?></td>
        <td><span class="tag <?= $t['jenis'] === 'masuk' ? 'tag-green' : 'tag-red' ?>"><?= h(ucfirst($t['jenis'])) ?></span></td>
        <td class="mono"><?= (int)$t['jumlah'] ?></td>
        <td class="mono" title="<?= h($t['created_at']) ?>"><?= $sb ?> → <?= $ss ?></td>
        <td><?= h($t['nama_user'] ?? '-') ?></td>
        <td><?= date('d M Y H:i', strtotime($t['created_at'])) ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php
    $pgBase = static function (int $p): string {
        return $p > 1 ? 'index.php?page=' . $p : 'index.php';
    };
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
<script>liveSearch('searchTable','dataTable');</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

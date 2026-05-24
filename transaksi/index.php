<?php
/* === transaksi/index.php === */
require_once __DIR__ . '/../includes/db.php';
requireStaff();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'transaksi';
$page_title = 'Manajemen Transaksi';

$filterJenis = $_GET['jenis'] ?? '';
$filterDari = $_GET['dari'] ?? '';
$filterSampai = $_GET['sampai'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if (in_array($filterJenis, ['masuk', 'keluar'], true)) {
    $where[] = 't.jenis = ?';
    $params[] = $filterJenis;
}
if ($filterDari !== '') {
    $where[] = 'DATE(t.created_at) >= ?';
    $params[] = $filterDari;
}
if ($filterSampai !== '') {
    $where[] = 'DATE(t.created_at) <= ?';
    $params[] = $filterSampai;
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM transaksi t WHERE $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$sql = "SELECT t.*, b.nama_barang, u.name AS nama_user
        FROM transaksi t
        JOIN barang b ON t.id_barang = b.id
        LEFT JOIN users u ON t.id_user = u.id
        WHERE $whereSql
        ORDER BY t.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$trxHari = (int) $pdo->query('SELECT COUNT(*) FROM transaksi WHERE DATE(created_at) = CURDATE()')->fetchColumn();
$masukHari = (int) $pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM transaksi WHERE jenis='masuk' AND DATE(created_at)=CURDATE()")->fetchColumn();
$keluarHari = (int) $pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM transaksi WHERE jenis='keluar' AND DATE(created_at)=CURDATE()")->fetchColumn();

ob_start();
?>
<div class="page-head">
  <h2>Manajemen Transaksi</h2>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="masuk.php" class="btn-green"><i class="ti ti-arrow-bar-to-down"></i> Barang Masuk</a>
    <a href="keluar.php" class="btn-red"><i class="ti ti-arrow-bar-up"></i> Barang Keluar</a>
  </div>
</div>

<div class="stats-row">
  <div class="stat-mini"><div class="val"><?= $trxHari ?></div><div class="lbl">Transaksi hari ini</div></div>
  <div class="stat-mini"><div class="val" style="color:var(--green)"><?= number_format($masukHari) ?></div><div class="lbl">Unit masuk hari ini</div></div>
  <div class="stat-mini"><div class="val" style="color:var(--red)"><?= number_format($keluarHari) ?></div><div class="lbl">Unit keluar hari ini</div></div>
</div>

<div class="tabs" id="tabJenis">
  <button type="button" class="active" data-jenis="all">Semua</button>
  <button type="button" data-jenis="masuk">Barang Masuk</button>
  <button type="button" data-jenis="keluar">Barang Keluar</button>
</div>
<p class="tab-filter-hint" id="tabJenis_hint"></p>

<form method="get" class="form-card" style="margin-bottom:16px;padding:16px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
  <div class="form-group" style="margin:0"><label>Filter jenis</label>
    <select name="jenis"><option value="">Semua</option>
      <option value="masuk" <?= $filterJenis === 'masuk' ? 'selected' : '' ?>>Masuk</option>
      <option value="keluar" <?= $filterJenis === 'keluar' ? 'selected' : '' ?>>Keluar</option>
    </select>
  </div>
  <div class="form-group" style="margin:0"><label>Dari</label><input type="date" name="dari" value="<?= h($filterDari) ?>"></div>
  <div class="form-group" style="margin:0"><label>Sampai</label><input type="date" name="sampai" value="<?= h($filterSampai) ?>"></div>
  <button type="submit" class="btn-primary">Terapkan</button>
  <?php if ($filterJenis || $filterDari || $filterSampai): ?>
  <a href="index.php" class="btn-outline">Reset filter</a>
  <?php endif; ?>
</form>

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
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="?page=<?= $p ?>&jenis=<?= h($filterJenis) ?>&dari=<?= h($filterDari) ?>&sampai=<?= h($filterSampai) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<script>
runPageInit(function () {
  const selectJenis = document.querySelector('form select[name="jenis"]');
  const initial = <?= json_encode($filterJenis !== '' ? $filterJenis : 'all') ?>;
  initTableTabs('tabJenis', 'jenis', {
    tableId: 'dataTable',
    searchInputId: 'searchTable',
    syncSelect: selectJenis,
    initial: initial === '' ? 'all' : initial,
  });
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

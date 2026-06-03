<?php
/* === laporan/stok.php === */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/laporan_lib.php';
requireStaff();
ensureLaporanSchema($pdo);

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'laporan';
$laporan_tab = 'stok';
$page_title = 'Laporan';
$isOwner = laporanIsOwner();
$f = laporanFiltersFromRequest();
$perPage = 15;
$self = 'stok.php';

$where = ['1=1'];
$params = [];
if ($f['q'] !== '') {
    $where[] = '(b.nama_barang LIKE ? OR k.nama_kategori LIKE ?)';
    $params[] = '%' . $f['q'] . '%';
    $params[] = '%' . $f['q'] . '%';
}
if ($f['kategori'] > 0) {
    $where[] = 'b.id_kategori = ?';
    $params[] = $f['kategori'];
}
if ($f['status'] === 'aman') {
    $where[] = 'b.stok_saat_ini > GREATEST(b.rop, 1)';
} elseif ($f['status'] === 'menipis') {
    $where[] = 'b.stok_saat_ini > 0 AND b.stok_saat_ini <= GREATEST(b.rop, 1)';
} elseif ($f['status'] === 'habis') {
    $where[] = 'b.stok_saat_ini = 0';
}
$whereSql = implode(' AND ', $where);

[$total, $totalPages, $page, $offset] = laporanPaginate(
    $pdo,
    "SELECT COUNT(*) FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id WHERE $whereSql",
    $params,
    $f['page'],
    $perPage
);

$sql = "SELECT b.id, b.nama_barang, k.nama_kategori, b.stok_saat_ini, b.stok_catatan,
        (b.stok_saat_ini - b.stok_catatan) AS selisih, b.rop, b.safety_stock
        FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id
        WHERE $whereSql ORDER BY b.nama_barang ASC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$sumStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
     SUM(CASE WHEN (b.stok_saat_ini - b.stok_catatan) = 0 THEN 1 ELSE 0 END) AS sesuai,
     SUM(CASE WHEN (b.stok_saat_ini - b.stok_catatan) != 0 THEN 1 ELSE 0 END) AS beda
     FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id WHERE $whereSql"
);
$sumStmt->execute($params);
$sum = $sumStmt->fetch();
$kategoriList = laporanKategoriList($pdo);

ob_start();
laporanRenderStyles();
?>
<?php laporanRenderPageHead('Laporan', 'Rekonsiliasi stok sistem dengan stok fisik di lapangan'); ?>
<?php laporanRenderTabs($laporan_tab, $BASE); ?>
<div class="laporan-panel">
  <form method="get" class="laporan-toolbar">
    <div class="form-group flex-grow">
      <label>Cari barang</label>
      <input type="text" name="q" id="liveSearchInput" placeholder="Nama barang…" value="<?= h($f['q']) ?>">
    </div>
    <div class="form-group">
      <label>Kategori</label>
      <select name="kategori"><option value="0">Semua</option>
        <?php foreach ($kategoriList as $k): ?>
        <option value="<?= (int) $k['id'] ?>" <?= $f['kategori'] === (int) $k['id'] ? 'selected' : '' ?>><?= h($k['nama_kategori']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Status stok</label>
      <select name="status">
        <option value="">Semua</option>
        <option value="aman" <?= $f['status'] === 'aman' ? 'selected' : '' ?>>Aman</option>
        <option value="menipis" <?= $f['status'] === 'menipis' ? 'selected' : '' ?>>Menipis</option>
        <option value="habis" <?= $f['status'] === 'habis' ? 'selected' : '' ?>>Habis</option>
      </select>
    </div>
    <button type="submit" class="btn-primary">Terapkan Filter</button>
    <a href="<?= h($self) ?>" class="btn-outline">Reset</a>
  </form>

  <div class="laporan-panel-body">
    <div class="laporan-summary">
      <div class="summary-card accent-blue">
        <div class="summary-icon icon-blue"><i class="ti ti-packages"></i></div>
        <div><div class="summary-val"><?= number_format((int) $sum['total']) ?></div><div class="summary-lbl">Total jenis barang</div></div>
      </div>
      <div class="summary-card accent-green">
        <div class="summary-icon icon-green"><i class="ti ti-circle-check"></i></div>
        <div><div class="summary-val"><?= number_format((int) $sum['sesuai']) ?></div><div class="summary-lbl">Stok sesuai catatan</div></div>
      </div>
      <div class="summary-card accent-amber">
        <div class="summary-icon icon-amber"><i class="ti ti-arrows-diff"></i></div>
        <div><div class="summary-val"><?= number_format((int) $sum['beda']) ?></div><div class="summary-lbl">Ada selisih</div></div>
      </div>
    </div>

    <?php if (empty($rows)): ?>
    <div class="laporan-empty"><i class="ti ti-box-off"></i><p>Tidak ada data ditemukan</p></div>
    <?php else: ?>
    <div class="card-table laporan-table-wrap">
      <table class="laporan-table" id="laporanTable">
        <thead><tr>
          <th>No</th><th>Nama Barang</th><th>Kategori</th><th>Stok Sistem</th><th>Stok Catatan</th><th>Selisih</th><th>ROP</th><th>Status</th>
        </tr></thead>
        <tbody>
        <?php $no = $offset + 1; foreach ($rows as $r):
          $sel = (int) $r['selisih'];
          $st = laporanStokStatus((int) $r['stok_saat_ini'], (int) $r['rop']);
        ?>
        <tr data-id="<?= (int) $r['id'] ?>">
          <td><?= $no++ ?></td>
          <td><?= h($r['nama_barang']) ?></td>
          <td><?= h($r['nama_kategori'] ?? '—') ?></td>
          <td class="mono"><?= number_format((int) $r['stok_saat_ini']) ?></td>
          <td class="mono stok-catatan-val"><?= number_format((int) $r['stok_catatan']) ?></td>
          <td><?= laporanSelisihHtml($sel) ?></td>
          <td class="mono"><?= number_format((int) $r['rop']) ?></td>
          <td><?= laporanStatusBadge($st) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php laporanRenderPagination($page, $totalPages, $self, $f); endif; ?>
  </div>
  <?php laporanRenderFooter($total, 'stok', $f, $BASE, $isOwner); ?>
</div>
<script>liveSearch('liveSearchInput','laporanTable');</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

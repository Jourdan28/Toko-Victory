<?php
/* === laporan/barang_masuk.php === */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/laporan_lib.php';
requireStaff();
ensureLaporanSchema($pdo);

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'laporan';
$laporan_tab = 'barang_masuk';
$page_title = 'Laporan';
$isOwner = laporanIsOwner();
$f = laporanFiltersFromRequest();
$perPage = 15;
$self = 'barang_masuk.php';
$supName = supplierNameSql($pdo, 's');

$where = ["t.jenis = 'masuk'"];
$params = [];
if ($f['q'] !== '') {
    $where[] = '(b.nama_barang LIKE ? OR t.kode_transaksi LIKE ?)';
    $params[] = '%' . $f['q'] . '%';
    $params[] = '%' . $f['q'] . '%';
}
if ($f['dari'] !== '') {
    $where[] = 'DATE(t.created_at) >= ?';
    $params[] = $f['dari'];
}
if ($f['sampai'] !== '') {
    $where[] = 'DATE(t.created_at) <= ?';
    $params[] = $f['sampai'];
}
if ($f['supplier'] > 0) {
    $where[] = 'b.id_supplier = ?';
    $params[] = $f['supplier'];
}
$whereSql = implode(' AND ', $where);

[$total, $totalPages, $page, $offset] = laporanPaginate(
    $pdo,
    "SELECT COUNT(*) FROM transaksi t JOIN barang b ON t.id_barang = b.id LEFT JOIN supplier s ON b.id_supplier = s.id WHERE $whereSql",
    $params,
    $f['page'],
    $perPage
);

$sql = "SELECT t.id, t.kode_transaksi, b.nama_barang, k.nama_kategori, {$supName} AS nama_supplier,
        t.jumlah, t.stok_sesudah, t.keterangan, u.name AS dicatat_oleh, t.created_at
        FROM transaksi t
        JOIN barang b ON t.id_barang = b.id
        LEFT JOIN kategori k ON b.id_kategori = k.id
        LEFT JOIN supplier s ON b.id_supplier = s.id
        LEFT JOIN users u ON t.id_user = u.id
        WHERE $whereSql ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$sumStmt = $pdo->prepare(
    "SELECT COUNT(*) AS trx, COALESCE(SUM(t.jumlah),0) AS unit, MAX(t.created_at) AS terakhir
     FROM transaksi t JOIN barang b ON t.id_barang = b.id WHERE $whereSql"
);
$sumStmt->execute($params);
$sum = $sumStmt->fetch();
$supplierList = laporanSupplierList($pdo);

ob_start();
laporanRenderStyles();
?>
<?php laporanRenderPageHead('Laporan', 'Riwayat transaksi barang masuk dari supplier dan restock'); ?>
<?php laporanRenderTabs($laporan_tab, $BASE); ?>
<div class="laporan-panel">
  <form method="get" class="laporan-toolbar">
    <div class="form-group flex-grow">
      <label>Cari</label>
      <input type="text" name="q" id="liveSearchInput" placeholder="Nama barang atau kode transaksi…" value="<?= h($f['q']) ?>">
    </div>
    <div class="form-group"><label>Dari</label><input type="date" name="dari" value="<?= h($f['dari']) ?>"></div>
    <div class="form-group"><label>Sampai</label><input type="date" name="sampai" value="<?= h($f['sampai']) ?>"></div>
    <div class="form-group">
      <label>Supplier</label>
      <select name="supplier"><option value="0">Semua</option>
        <?php foreach ($supplierList as $s): ?>
        <option value="<?= (int) $s['id'] ?>" <?= $f['supplier'] === (int) $s['id'] ? 'selected' : '' ?>><?= h($s['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn-primary">Terapkan Filter</button>
    <a href="<?= h($self) ?>" class="btn-outline">Reset</a>
  </form>
  <?php if ($f['dari'] || $f['sampai']): ?>
  <div class="laporan-filter-note"><i class="ti ti-calendar"></i> Menampilkan data <?= h(laporanPeriodeLabel($f)) ?></div>
  <?php endif; ?>

  <div class="laporan-panel-body">
    <div class="laporan-summary">
      <div class="summary-card accent-green">
        <div class="summary-icon icon-green"><i class="ti ti-arrow-bar-to-down"></i></div>
        <div><div class="summary-val"><?= number_format((int) $sum['trx']) ?></div><div class="summary-lbl">Total transaksi masuk</div></div>
      </div>
      <div class="summary-card accent-blue">
        <div class="summary-icon icon-blue"><i class="ti ti-sum"></i></div>
        <div><div class="summary-val"><?= number_format((int) $sum['unit']) ?></div><div class="summary-lbl">Total unit diterima</div></div>
      </div>
      <div class="summary-card accent-amber">
        <div class="summary-icon icon-amber"><i class="ti ti-clock"></i></div>
        <div><div class="summary-val" style="font-size:16px"><?= $sum['terakhir'] ? laporanFormatTanggal($sum['terakhir']) : '—' ?></div><div class="summary-lbl">Transaksi masuk terakhir</div></div>
      </div>
    </div>

    <?php if (empty($rows)): ?>
    <div class="laporan-empty"><i class="ti ti-box-off"></i><p>Tidak ada data ditemukan</p></div>
    <?php else: ?>
    <div class="card-table laporan-table-wrap">
      <table class="laporan-table" id="laporanTable">
        <thead><tr>
          <th>No</th><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Supplier</th>
          <th>Jumlah Masuk</th><th>Stok Sesudah</th><th>Dicatat Oleh</th><th>Tanggal</th>
        </tr></thead>
        <tbody>
        <?php $no = $offset + 1; foreach ($rows as $r): ?>
        <tr>
          <td><?= $no++ ?></td>
          <td class="mono"><?= h($r['kode_transaksi'] ?? '—') ?></td>
          <td><?= h($r['nama_barang']) ?></td>
          <td><?= h($r['nama_kategori'] ?? '—') ?></td>
          <td><?= h($r['nama_supplier'] ?? '—') ?></td>
          <td class="mono" style="color:var(--green);font-weight:600">+<?= number_format((int) $r['jumlah']) ?></td>
          <td class="mono"><?= number_format((int) $r['stok_sesudah']) ?></td>
          <td><?= h($r['dicatat_oleh'] ?? '—') ?></td>
          <td title="<?= h(date('H:i:s', strtotime($r['created_at']))) ?>"><?= laporanFormatTanggal($r['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php laporanRenderPagination($page, $totalPages, $self, $f); endif; ?>
  </div>
  <?php laporanRenderFooter($total, 'barang_masuk', $f, $BASE, $isOwner); ?>
</div>
<script>liveSearch('liveSearchInput','laporanTable');</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

<?php
/* === laporan/barang_tersedia.php === */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/laporan_lib.php';
requireStaff();
ensureLaporanSchema($pdo);

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'laporan';
$laporan_tab = 'barang_tersedia';
$page_title = 'Laporan';
$isOwner = laporanIsOwner();
$f = laporanFiltersFromRequest();
$perPage = 15;
$self = 'barang_tersedia.php';

$where = ['b.stok_saat_ini > 0'];
$params = [];
if ($f['q'] !== '') {
    $where[] = '(b.nama_barang LIKE ? OR k.nama_kategori LIKE ?)';
    $params[] = '%' . $f['q'] . '%';
    $params[] = '%' . $f['q'] . '%';
}
$whereSql = implode(' AND ', $where);

[$total, $totalPages, $page, $offset] = laporanPaginate(
    $pdo,
    "SELECT COUNT(*) FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id WHERE $whereSql",
    $params,
    $f['page'],
    $perPage
);

$sql = "SELECT b.id, b.nama_barang, k.nama_kategori, s.nama_satuan, m.nama_merek, l.nama_lokasi,
        b.stok_saat_ini, b.rop, b.safety_stock, b.harga
        FROM barang b
        LEFT JOIN kategori k ON b.id_kategori = k.id
        LEFT JOIN satuan s ON b.id_satuan = s.id
        LEFT JOIN merek m ON b.id_merek = m.id
        LEFT JOIN lokasi l ON b.id_lokasi = l.id
        WHERE $whereSql
        ORDER BY b.stok_saat_ini DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$sumStmt = $pdo->prepare(
    "SELECT COUNT(*) AS jenis, COALESCE(SUM(b.stok_saat_ini),0) AS unit,
     SUM(CASE WHEN b.stok_saat_ini > 0 AND b.stok_saat_ini <= GREATEST(b.rop,1) THEN 1 ELSE 0 END) AS menipis
     FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id WHERE $whereSql"
);
$sumStmt->execute($params);
$sum = $sumStmt->fetch();

ob_start();
laporanRenderStyles();
?>
<?php laporanRenderPageHead('Laporan', 'Ringkasan barang yang masih tersedia di gudang dan etalase'); ?>
<?php laporanRenderTabs($laporan_tab, $BASE); ?>
<div class="laporan-panel">
  <div class="laporan-toolbar laporan-search-only">
    <form method="get" class="laporan-search-form">
      <div class="search-box">
        <i class="ti ti-search"></i>
        <input type="search" name="q" id="liveSearchInput" placeholder="Cari barang..." value="<?= h($f['q']) ?>" autocomplete="off">
      </div>
    </form>
  </div>

  <div class="laporan-panel-body">
    <div class="laporan-summary">
      <div class="summary-card accent-blue">
        <div class="summary-icon icon-blue"><i class="ti ti-package"></i></div>
        <div><div class="summary-val"><?= number_format((int) $sum['jenis']) ?></div><div class="summary-lbl">Jenis barang tersedia</div></div>
      </div>
      <div class="summary-card accent-green">
        <div class="summary-icon icon-green"><i class="ti ti-stack-2"></i></div>
        <div><div class="summary-val"><?= number_format((int) $sum['unit']) ?></div><div class="summary-lbl">Total unit tersedia</div></div>
      </div>
      <div class="summary-card accent-red">
        <div class="summary-icon icon-red"><i class="ti ti-alert-triangle"></i></div>
        <div><div class="summary-val"><?= number_format((int) $sum['menipis']) ?></div><div class="summary-lbl">Barang menipis</div></div>
      </div>
    </div>

    <?php if (empty($rows)): ?>
    <div class="laporan-empty"><i class="ti ti-box-off"></i><p>Tidak ada data ditemukan</p>
      <?php if ($f['q'] !== ''): ?>
      <a href="<?= h($self) ?>" class="btn-outline" style="margin-top:12px;display:inline-flex">Hapus pencarian</a>
      <?php endif; ?></div>
    <?php else: ?>
    <div class="card-table laporan-table-wrap">
      <table class="laporan-table" id="laporanTable">
        <thead><tr>
          <th>No</th><th>Nama Barang</th><th>Kategori</th><th>Satuan</th><th>Merek</th><th>Lokasi</th>
          <th>Stok Tersedia</th><th>ROP</th><th>Status</th>
        </tr></thead>
        <tbody>
        <?php $no = $offset + 1; foreach ($rows as $r):
          $st = laporanStokStatus((int) $r['stok_saat_ini'], (int) $r['rop']);
        ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><?= h($r['nama_barang']) ?></td>
          <td><?= h($r['nama_kategori'] ?? '—') ?></td>
          <td><?= h($r['nama_satuan'] ?? '—') ?></td>
          <td><?= h($r['nama_merek'] ?? '—') ?></td>
          <td><?= h($r['nama_lokasi'] ?? '—') ?></td>
          <td class="mono fw-semibold"><?= number_format((int) $r['stok_saat_ini']) ?></td>
          <td class="mono"><?= number_format((int) $r['rop']) ?></td>
          <td><?= laporanStatusBadge($st) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php laporanRenderPagination($page, $totalPages, $self, $f); endif; ?>
  </div>
  <?php laporanRenderFooter($total, 'barang_tersedia', $f, $BASE, $isOwner); ?>
</div>
<script>liveSearch('liveSearchInput','laporanTable');</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

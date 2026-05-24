<?php
/* === laporan/cetak/barang_tersedia.php === */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/laporan_lib.php';
require_once __DIR__ . '/../../includes/laporan_cetak.php';
cetakRequireLogin();
ensureLaporanSchema($pdo);

$f = laporanFiltersFromRequest();
$where = ['b.stok_saat_ini > 0'];
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
if ($f['lokasi'] > 0) {
    $where[] = 'b.id_lokasi = ?';
    $params[] = $f['lokasi'];
}
$whereSql = implode(' AND ', $where);

$sum = $pdo->prepare("SELECT COUNT(*) AS j, COALESCE(SUM(stok_saat_ini),0) AS u FROM barang b WHERE $whereSql");
$sum->execute($params);
$s = $sum->fetch();

$sql = "SELECT b.nama_barang, k.nama_kategori, s.nama_satuan, l.nama_lokasi, b.stok_saat_ini, b.rop
        FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id
        LEFT JOIN satuan s ON b.id_satuan = s.id LEFT JOIN lokasi l ON b.id_lokasi = l.id
        WHERE $whereSql ORDER BY b.stok_saat_ini DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

cetakHeader('LAPORAN BARANG TERSEDIA', $f, [
    ['label' => 'Jenis Barang', 'value' => (string) $s['j']],
    ['label' => 'Total Unit', 'value' => number_format((int) $s['u'])],
    ['label' => 'Periode', 'value' => laporanPeriodeLabel($f)],
]);
?>
<table class="data">
  <thead><tr><th>No</th><th>Nama Barang</th><th>Kategori</th><th>Satuan</th><th>Lokasi</th><th>Stok</th><th>ROP</th><th>Status</th></tr></thead>
  <tbody>
  <?php $n = 1; foreach ($rows as $r):
    $st = ucfirst(laporanStokStatus((int) $r['stok_saat_ini'], (int) $r['rop']));
  ?>
  <tr>
    <td><?= $n++ ?></td>
    <td><?= h($r['nama_barang']) ?></td>
    <td><?= h($r['nama_kategori'] ?? '—') ?></td>
    <td><?= h($r['nama_satuan'] ?? '—') ?></td>
    <td><?= h($r['nama_lokasi'] ?? '—') ?></td>
    <td><?= number_format((int) $r['stok_saat_ini']) ?></td>
    <td><?= number_format((int) $r['rop']) ?></td>
    <td><?= h($st) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php cetakFooter();

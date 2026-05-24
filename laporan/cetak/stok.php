<?php
/* === laporan/cetak/stok.php === */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/laporan_lib.php';
require_once __DIR__ . '/../../includes/laporan_cetak.php';
cetakRequireLogin();
ensureLaporanSchema($pdo);

$f = laporanFiltersFromRequest();
$where = ['1=1'];
$params = [];
if ($f['q'] !== '') {
    $where[] = 'b.nama_barang LIKE ?';
    $params[] = '%' . $f['q'] . '%';
}
if ($f['kategori'] > 0) {
    $where[] = 'b.id_kategori = ?';
    $params[] = $f['kategori'];
}
$whereSql = implode(' AND ', $where);

$sum = $pdo->prepare(
    "SELECT COUNT(*) AS t, SUM(CASE WHEN (stok_saat_ini-stok_catatan)=0 THEN 1 ELSE 0 END) AS ok
     FROM barang b WHERE $whereSql"
);
$sum->execute($params);
$s = $sum->fetch();

$sql = "SELECT b.nama_barang, k.nama_kategori, b.stok_saat_ini, b.stok_catatan,
        (b.stok_saat_ini - b.stok_catatan) AS selisih, b.rop
        FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id
        WHERE $whereSql ORDER BY b.nama_barang";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

cetakHeader('LAPORAN STOK BARANG', $f, [
    ['label' => 'Total Barang', 'value' => (string) $s['t']],
    ['label' => 'Stok Sesuai', 'value' => (string) $s['ok']],
    ['label' => 'Periode', 'value' => laporanPeriodeLabel($f)],
]);
?>
<table class="data">
  <thead><tr><th>No</th><th>Nama Barang</th><th>Kategori</th><th>Stok Sistem</th><th>Stok Catatan</th><th>Selisih</th><th>ROP</th><th>Status</th></tr></thead>
  <tbody>
  <?php $n = 1; foreach ($rows as $r):
    $st = ucfirst(laporanStokStatus((int) $r['stok_saat_ini'], (int) $r['rop']));
  ?>
  <tr>
    <td><?= $n++ ?></td>
    <td><?= h($r['nama_barang']) ?></td>
    <td><?= h($r['nama_kategori'] ?? '—') ?></td>
    <td><?= number_format((int) $r['stok_saat_ini']) ?></td>
    <td><?= number_format((int) $r['stok_catatan']) ?></td>
    <td><?= (int) $r['selisih'] ?></td>
    <td><?= number_format((int) $r['rop']) ?></td>
    <td><?= h($st) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php cetakFooter();

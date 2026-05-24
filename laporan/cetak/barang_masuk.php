<?php
/* === laporan/cetak/barang_masuk.php === */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/laporan_lib.php';
require_once __DIR__ . '/../../includes/laporan_cetak.php';
cetakRequireLogin();
ensureLaporanSchema($pdo);

$f = laporanFiltersFromRequest();
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
$whereSql = implode(' AND ', $where);

$sum = $pdo->prepare("SELECT COUNT(*) AS t, COALESCE(SUM(jumlah),0) AS u FROM transaksi t JOIN barang b ON t.id_barang=b.id WHERE $whereSql");
$sum->execute($params);
$s = $sum->fetch();

$sql = "SELECT t.kode_transaksi, b.nama_barang, k.nama_kategori, {$supName} AS sup, t.jumlah, t.stok_sesudah, u.name, t.created_at
        FROM transaksi t JOIN barang b ON t.id_barang = b.id
        LEFT JOIN kategori k ON b.id_kategori = k.id LEFT JOIN supplier s ON b.id_supplier = s.id
        LEFT JOIN users u ON t.id_user = u.id WHERE $whereSql ORDER BY t.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

cetakHeader('LAPORAN BARANG MASUK', $f, [
    ['label' => 'Transaksi', 'value' => (string) $s['t']],
    ['label' => 'Total Unit', 'value' => number_format((int) $s['u'])],
    ['label' => 'Periode', 'value' => laporanPeriodeLabel($f)],
]);
?>
<table class="data">
  <thead><tr><th>No</th><th>Kode</th><th>Barang</th><th>Kategori</th><th>Supplier</th><th>Masuk</th><th>Stok Sesudah</th><th>Oleh</th><th>Tanggal</th></tr></thead>
  <tbody>
  <?php $n = 1; foreach ($rows as $r): ?>
  <tr>
    <td><?= $n++ ?></td>
    <td><?= h($r['kode_transaksi'] ?? '') ?></td>
    <td><?= h($r['nama_barang']) ?></td>
    <td><?= h($r['nama_kategori'] ?? '—') ?></td>
    <td><?= h($r['sup'] ?? '—') ?></td>
    <td>+<?= number_format((int) $r['jumlah']) ?></td>
    <td><?= number_format((int) $r['stok_sesudah']) ?></td>
    <td><?= h($r['name'] ?? '—') ?></td>
    <td><?= laporanFormatTanggal($r['created_at']) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php cetakFooter();

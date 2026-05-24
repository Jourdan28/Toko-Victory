<?php
/* === laporan/cetak/pemasok.php === */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/laporan_lib.php';
require_once __DIR__ . '/../../includes/laporan_cetak.php';
cetakRequireLogin();
ensureLaporanSchema($pdo);

$f = laporanFiltersFromRequest();
$supName = supplierNameSql($pdo, 's');
$where = ['1=1'];
$params = [];
if ($f['q'] !== '') {
    $where[] = "({$supName} LIKE ? OR s.email LIKE ?)";
    $params[] = '%' . $f['q'] . '%';
    $params[] = '%' . $f['q'] . '%';
}
$whereSql = implode(' AND ', $where);

$stats = $pdo->query('SELECT COUNT(*) AS t FROM supplier')->fetch();

$sql = "SELECT {$supName} AS nama, COALESCE(s.no_telepon, s.kontak) AS tel, s.email,
        COUNT(b.id) AS jb, COALESCE(SUM(b.stok_saat_ini),0) AS ts,
        ROUND(AVG(b.delivery_avg),0) AS da, MAX(b.delivery_max) AS dm
        FROM supplier s LEFT JOIN barang b ON b.id_supplier = s.id
        WHERE $whereSql GROUP BY s.id ORDER BY nama";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

cetakHeader('LAPORAN DATA PEMASOK', $f, [
    ['label' => 'Total Pemasok', 'value' => (string) $stats['t']],
    ['label' => 'Data Tercetak', 'value' => (string) count($rows)],
    ['label' => 'Periode', 'value' => laporanPeriodeLabel($f)],
]);
?>
<table class="data">
  <thead><tr><th>No</th><th>Nama Pemasok</th><th>Telepon</th><th>Email</th><th>Barang</th><th>Total Stok</th><th>Lead Time</th></tr></thead>
  <tbody>
  <?php $n = 1; foreach ($rows as $r):
    $lead = ($r['da'] || $r['dm']) ? $r['da'] . '–' . max((int) $r['dm'], (int) $r['da']) . ' hari' : '—';
  ?>
  <tr>
    <td><?= $n++ ?></td>
    <td><?= h($r['nama']) ?></td>
    <td><?= h($r['tel'] ?? '—') ?></td>
    <td><?= h($r['email'] ?? '—') ?></td>
    <td><?= number_format((int) $r['jb']) ?></td>
    <td><?= number_format((int) $r['ts']) ?></td>
    <td><?= h($lead) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php cetakFooter();

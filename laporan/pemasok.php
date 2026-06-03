<?php
/* === laporan/pemasok.php === */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/laporan_lib.php';
requireStaff();
ensureLaporanSchema($pdo);

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'laporan';
$laporan_tab = 'pemasok';
$page_title = 'Laporan';
$isOwner = laporanIsOwner();
$f = laporanFiltersFromRequest();
$perPage = 15;
$self = 'pemasok.php';
$supName = supplierNameSql($pdo, 's');

$where = ['1=1'];
$params = [];
if ($f['q'] !== '') {
    $where[] = "({$supName} LIKE ? OR s.email LIKE ? OR COALESCE(s.no_telepon, s.kontak) LIKE ?)";
    $params[] = '%' . $f['q'] . '%';
    $params[] = '%' . $f['q'] . '%';
    $params[] = '%' . $f['q'] . '%';
}
$whereSql = implode(' AND ', $where);

[$total, $totalPages, $page, $offset] = laporanPaginate(
    $pdo,
    "SELECT COUNT(DISTINCT s.id) FROM supplier s WHERE $whereSql",
    $params,
    $f['page'],
    $perPage
);

$sql = "SELECT s.id, {$supName} AS nama_supplier, COALESCE(s.no_telepon, s.kontak) AS no_telepon,
        s.email, s.alamat,
        ROUND(AVG(b.delivery_avg), 0) AS delivery_avg,
        MAX(b.delivery_max) AS delivery_max,
        COUNT(b.id) AS jumlah_barang,
        COALESCE(SUM(b.stok_saat_ini), 0) AS total_stok_disuplai
        FROM supplier s
        LEFT JOIN barang b ON b.id_supplier = s.id
        WHERE $whereSql
        GROUP BY s.id
        ORDER BY nama_supplier ASC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$stats = $pdo->query(
    "SELECT COUNT(DISTINCT s.id) AS total_sup,
     (SELECT COUNT(DISTINCT s2.id) FROM supplier s2
      JOIN barang b2 ON b2.id_supplier = s2.id
      WHERE b2.stok_saat_ini <= GREATEST(b2.rop, 1)) AS sup_menipis,
     ROUND(AVG(b.delivery_avg), 1) AS avg_lead
     FROM supplier s LEFT JOIN barang b ON b.id_supplier = s.id"
)->fetch();

ob_start();
laporanRenderStyles();
?>
<?php laporanRenderPageHead('Laporan', 'Data pemasok, lead time, dan ketersediaan barang'); ?>
<?php laporanRenderTabs($laporan_tab, $BASE); ?>
<div class="laporan-panel">
  <form method="get" class="laporan-toolbar">
    <div class="form-group flex-grow">
      <label>Cari pemasok</label>
      <input type="text" name="q" id="liveSearchInput" placeholder="Nama, email, atau telepon…" value="<?= h($f['q']) ?>">
    </div>
    <button type="submit" class="btn-primary">Terapkan Filter</button>
    <a href="<?= h($self) ?>" class="btn-outline">Reset</a>
  </form>

  <div class="laporan-panel-body">
    <div class="laporan-summary">
      <div class="summary-card accent-blue">
        <div class="summary-icon icon-blue"><i class="ti ti-truck"></i></div>
        <div><div class="summary-val"><?= number_format((int) $stats['total_sup']) ?></div><div class="summary-lbl">Total pemasok aktif</div></div>
      </div>
      <div class="summary-card accent-amber">
        <div class="summary-icon icon-amber"><i class="ti ti-alert-triangle"></i></div>
        <div><div class="summary-val"><?= number_format((int) $stats['sup_menipis']) ?></div><div class="summary-lbl">Pemasok barang menipis</div></div>
      </div>
      <div class="summary-card accent-green">
        <div class="summary-icon icon-green"><i class="ti ti-clock"></i></div>
        <div><div class="summary-val"><?= $stats['avg_lead'] ? h($stats['avg_lead']) . ' hr' : '—' ?></div><div class="summary-lbl">Rata-rata lead time</div></div>
      </div>
    </div>

    <?php if (empty($rows)): ?>
    <div class="laporan-empty"><i class="ti ti-truck-off"></i><p>Tidak ada data ditemukan</p></div>
    <?php else: ?>
    <div class="card-table laporan-table-wrap">
      <table class="laporan-table" id="laporanTable">
        <thead><tr>
          <th>No</th><th>Nama Pemasok</th><th>No. Telepon</th><th>Email</th>
          <th>Barang Disuplai</th><th>Total Stok</th><th>Lead Time</th>
        </tr></thead>
        <tbody>
        <?php $no = $offset + 1; foreach ($rows as $r):
          $tel = trim($r['no_telepon'] ?? '');
          $email = trim($r['email'] ?? '');
          $avg = (int) ($r['delivery_avg'] ?? 0);
          $max = (int) ($r['delivery_max'] ?? 0);
          $lead = ($avg || $max) ? ($avg . '–' . max($max, $avg) . ' hari') : '—';
        ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><?= h($r['nama_supplier']) ?></td>
          <td><?php if ($tel): ?><a href="tel:<?= h(preg_replace('/\s+/', '', $tel)) ?>" style="color:var(--blue)"><i class="ti ti-phone"></i> <?= h($tel) ?></a><?php else: ?>—<?php endif; ?></td>
          <td><?php if ($email): ?><a href="mailto:<?= h($email) ?>" style="color:var(--blue)"><i class="ti ti-mail"></i> <?= h($email) ?></a><?php else: ?>—<?php endif; ?></td>
          <td>
            <?= number_format((int) $r['jumlah_barang']) ?>
            <?php if ((int) $r['jumlah_barang'] > 0): ?>
            <button type="button" class="btn-stok-catatan btn-lihat-barang" data-id="<?= (int) $r['id'] ?>" data-nama="<?= h($r['nama_supplier']) ?>">Lihat →</button>
            <?php endif; ?>
          </td>
          <td class="mono"><?= number_format((int) $r['total_stok_disuplai']) ?></td>
          <td><?= h($lead) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php laporanRenderPagination($page, $totalPages, $self, $f); endif; ?>
  </div>
  <?php laporanRenderFooter($total, 'pemasok', $f, $BASE, $isOwner); ?>
</div>

<div class="modal-overlay modal-supplier-barang" id="modalBarang">
  <div class="modal-box">
    <div class="modal-head">
      <strong id="modalTitle">Barang Supplier</strong>
      <button type="button" class="btn-icon" onclick="document.getElementById('modalBarang').classList.remove('open')"><i class="ti ti-x"></i></button>
    </div>
    <div class="modal-body" id="modalBody"><p style="color:var(--text-muted)">Memuat…</p></div>
  </div>
</div>

<script>
liveSearch('liveSearchInput','laporanTable');
document.querySelectorAll('.btn-lihat-barang').forEach(btn => {
  btn.addEventListener('click', async () => {
    const modal = document.getElementById('modalBarang');
    document.getElementById('modalTitle').textContent = 'Barang — ' + btn.dataset.nama;
    document.getElementById('modalBody').innerHTML = '<p style="color:var(--text-muted)">Memuat…</p>';
    modal.classList.add('open');
    const res = await fetch('<?= h($BASE) ?>/api/laporan_barang_supplier.php?id=' + btn.dataset.id);
    const j = await res.json();
    if (!j.ok) { document.getElementById('modalBody').innerHTML = '<p class="field-error">' + (j.message||'Gagal') + '</p>'; return; }
    let html = '<table style="width:100%;font-size:13px"><thead><tr><th>Barang</th><th>Stok</th><th>ROP</th><th>Status</th></tr></thead><tbody>';
    j.data.forEach(r => {
      html += '<tr><td>' + r.nama + '</td><td class="mono">' + r.stok + '</td><td>' + r.rop + '</td><td>' + r.status + '</td></tr>';
    });
    html += '</tbody></table>';
    document.getElementById('modalBody').innerHTML = html || '<p>Tidak ada barang</p>';
  });
});
document.getElementById('modalBarang').addEventListener('click', e => {
  if (e.target.id === 'modalBarang') e.target.classList.remove('open');
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

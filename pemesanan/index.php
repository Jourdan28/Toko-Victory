<?php
/* === pemesanan/index.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwnerOnly();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'pemesanan';
$page_title = 'Pemesanan Barang';

$counts = [];
foreach (['pending', 'diproses', 'diterima', 'dibatalkan'] as $s) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM pemesanan WHERE status = ?');
    $stmt->execute([$s]);
    $counts[$s] = (int) $stmt->fetchColumn();
}
$diterimaBulan = (int) $pdo->query(
    "SELECT COUNT(*) FROM pemesanan WHERE status='diterima' AND MONTH(tanggal_diterima)=MONTH(CURDATE())"
)->fetchColumn();

$rows = $pdo->query(
    "SELECT p.*, b.nama_barang, COALESCE(s.nama_supplier, s.nama) AS supplier_nama
     FROM pemesanan p
     JOIN barang b ON p.id_barang = b.id
     JOIN supplier s ON p.id_supplier = s.id
     ORDER BY p.created_at DESC"
)->fetchAll();

$statusLabel = [
    'pending' => ['Menunggu', 'tag-amber'],
    'diproses' => ['Diproses', 'tag-blue'],
    'diterima' => ['Diterima', 'tag-green'],
    'dibatalkan' => ['Dibatalkan', 'tag-red'],
];

ob_start();
?>
<div class="page-head">
  <h2>Pemesanan Barang</h2>
  <a href="form.php" class="btn-primary"><i class="ti ti-plus"></i> Buat Pesanan Baru</a>
</div>

<div class="stats-row">
  <div class="stat-mini"><div class="val" style="color:var(--amber)"><?= $counts['pending'] ?></div><div class="lbl">Pending</div></div>
  <div class="stat-mini"><div class="val" style="color:var(--blue)"><?= $counts['diproses'] ?></div><div class="lbl">Diproses</div></div>
  <div class="stat-mini"><div class="val" style="color:var(--green)"><?= $diterimaBulan ?></div><div class="lbl">Diterima (bulan ini)</div></div>
  <div class="stat-mini"><div class="val" style="color:var(--red)"><?= $counts['dibatalkan'] ?></div><div class="lbl">Dibatalkan</div></div>
</div>

<div class="tabs" id="tabStatus">
  <button type="button" class="active" data-status="all">Semua</button>
  <button type="button" data-status="pending">Pending</button>
  <button type="button" data-status="diproses">Diproses</button>
  <button type="button" data-status="diterima">Diterima</button>
  <button type="button" data-status="dibatalkan">Dibatalkan</button>
</div>

<div class="search-box"><i class="ti ti-search"></i><input type="search" id="searchTable" placeholder="Cari pesanan..."></div>

<div class="card-table">
  <table id="dataTable">
    <thead>
      <tr>
        <th>No</th><th>Kode</th><th>Barang</th><th>Supplier</th><th>Jumlah</th>
        <th>Status</th><th>Tgl Pesan</th><th>Est. Tiba</th><th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
      <tr><td colspan="9" class="empty-state">Belum ada pesanan.</td></tr>
      <?php else: foreach ($rows as $i => $p):
        $sl = $statusLabel[$p['status']] ?? ['?', 'tag-gray'];
        $late = $p['tanggal_estimasi'] && $p['status'] !== 'diterima' && $p['status'] !== 'dibatalkan'
            && strtotime($p['tanggal_estimasi']) < time();
        $rowClass = $p['status'] === 'diterima' ? 'row-dim' : '';
      ?>
      <tr data-status="<?= h($p['status']) ?>" class="<?= $rowClass ?>">
        <td><?= $i + 1 ?></td>
        <td class="mono"><?= h($p['kode_pesanan']) ?></td>
        <td><?= h($p['nama_barang']) ?></td>
        <td><?= h($p['supplier_nama']) ?></td>
        <td class="mono"><?= (int)$p['jumlah_pesan'] ?></td>
        <td><span class="tag <?= $sl[1] ?>"><?= h($sl[0]) ?></span></td>
        <td><?= date('d M Y', strtotime($p['tanggal_pesan'])) ?></td>
        <td>
          <?php if ($p['tanggal_estimasi']): ?>
            <span style="<?= $late ? 'color:var(--red)' : '' ?>">
              <?= $late ? '<i class="ti ti-alert-triangle"></i> ' : '' ?><?= date('d M Y', strtotime($p['tanggal_estimasi'])) ?>
            </span>
          <?php else: ?>-<?php endif; ?>
        </td>
        <td>
          <div class="actions actions-wrap">
            <?php if ($p['status'] === 'pending'): ?>
            <button type="button" class="btn-sm btn-quick-status" data-id="<?= (int)$p['id'] ?>" data-kode="<?= h($p['kode_pesanan']) ?>" data-next="diproses">Proses</button>
            <?php elseif ($p['status'] === 'diproses'): ?>
            <button type="button" class="btn-sm btn-quick-status btn-quick-green" data-id="<?= (int)$p['id'] ?>" data-kode="<?= h($p['kode_pesanan']) ?>" data-next="diterima">Diterima</button>
            <?php endif; ?>
            <?php if (in_array($p['status'], ['pending', 'diproses'], true)): ?>
            <a href="form.php?id=<?= (int)$p['id'] ?>" class="btn-icon edit" title="Edit"><i class="ti ti-pencil"></i></a>
            <button type="button" class="btn-icon edit btn-status" title="Update status" data-id="<?= (int)$p['id'] ?>" data-kode="<?= h($p['kode_pesanan']) ?>" data-status="<?= h($p['status']) ?>"><i class="ti ti-refresh"></i></button>
            <?php endif; ?>
            <?php if ($p['status'] === 'pending'): ?>
            <form method="post" action="hapus.php" style="display:inline" onsubmit="return confirmDelete('Hapus pesanan ini?')">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button type="submit" class="btn-icon del" title="Hapus"><i class="ti ti-trash"></i></button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-overlay" id="modalStatus">
  <div class="modal-box">
    <div class="modal-head"><h3>Update Status</h3><button type="button" class="btn-notif-close" id="closeModal"><i class="ti ti-x"></i></button></div>
    <form method="post" action="update_status.php">
      <div class="modal-body">
        <input type="hidden" name="id" id="modalId">
        <p id="modalKode" style="font-size:12px;color:var(--text-muted);margin-bottom:12px"></p>
        <div class="form-group"><label>Status baru *</label>
          <select name="status" id="modalStatusSelect" required>
            <option value="pending">Menunggu</option>
            <option value="diproses">Diproses</option>
            <option value="diterima">Diterima</option>
            <option value="dibatalkan">Dibatalkan</option>
          </select>
        </div>
        <div class="form-group" id="modalTglTerima" style="display:none">
          <label>Tanggal Diterima *</label>
          <input type="date" name="tanggal_diterima" value="<?= date('Y-m-d') ?>">
          <p style="font-size:11px;color:var(--blue);margin-top:6px">Stok akan ditambah otomatis sesuai jumlah pesanan.</p>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn-outline" id="cancelModal">Batal</button>
        <button type="submit" class="btn-primary">Update Status</button>
      </div>
    </form>
  </div>
</div>

<script>
runPageInit(function () {
  liveSearch('searchTable', 'dataTable');
  initTableTabs('tabStatus', 'status', { tableId: 'dataTable' });

  const modal = document.getElementById('modalStatus');
  function openStatusModal(id, kode, status) {
    document.getElementById('modalId').value = id;
    document.getElementById('modalKode').textContent = kode;
    document.getElementById('modalStatusSelect').value = status;
    modal.classList.add('open');
    toggleTglTerima();
  }
  document.querySelectorAll('.btn-status').forEach((btn) => {
    btn.addEventListener('click', () => {
      openStatusModal(btn.dataset.id, btn.dataset.kode, btn.dataset.status);
    });
  });
  document.querySelectorAll('.btn-quick-status').forEach((btn) => {
    btn.addEventListener('click', () => {
      openStatusModal(btn.dataset.id, btn.dataset.kode, btn.dataset.next);
    });
  });
  function toggleTglTerima() {
    document.getElementById('modalTglTerima').style.display =
      document.getElementById('modalStatusSelect').value === 'diterima' ? 'block' : 'none';
  }
  document.getElementById('modalStatusSelect').addEventListener('change', toggleTglTerima);
  document.getElementById('closeModal').onclick = () => modal.classList.remove('open');
  document.getElementById('cancelModal').onclick = () => modal.classList.remove('open');
  modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('open'); });
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

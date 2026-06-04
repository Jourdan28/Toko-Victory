<?php
/* === pemesanan/index.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwnerOnly();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'pemesanan';
$page_title = 'Pemesanan Barang';

$counts = [];
foreach (['diproses', 'diterima', 'dibatalkan'] as $s) {
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
  <div class="stat-mini"><div class="val" style="color:var(--blue)"><?= $counts['diproses'] ?></div><div class="lbl">Diproses</div></div>
  <div class="stat-mini"><div class="val" style="color:var(--green)"><?= $diterimaBulan ?></div><div class="lbl">Diterima (bulan ini)</div></div>
  <div class="stat-mini"><div class="val" style="color:var(--red)"><?= $counts['dibatalkan'] ?></div><div class="lbl">Dibatalkan</div></div>
</div>

<div class="tabs" id="tabStatus">
  <button type="button" class="active" data-status="all">Semua</button>
  <button type="button" data-status="diproses">Diproses</button>
  <button type="button" data-status="diterima">Diterima</button>
  <button type="button" data-status="dibatalkan">Dibatalkan</button>
</div>

<div class="pemesanan-toolbar">
  <div class="search-box"><i class="ti ti-search"></i><input type="search" id="searchTable" placeholder="Cari pesanan..."></div>
  <input type="text" id="filterTanggal" class="pemesanan-date-input" placeholder="Cari tanggal..." autocomplete="off">
</div>

<div class="card-table">
  <table id="dataTable">
    <thead>
      <tr>
        <th>No</th><th>Kode</th><th>Barang</th><th>Supplier</th><th>Jumlah</th>
        <th>Status</th><th>Tgl Pesan</th><th>Tiba pd Tanggal</th><th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
      <tr><td colspan="9" class="empty-state">Belum ada pesanan.</td></tr>
      <?php else: foreach ($rows as $i => $p):
        $stKey = $p['status'] === 'pending' ? 'diproses' : $p['status'];
        $sl = $statusLabel[$stKey] ?? ['?', 'tag-gray'];
        $rowClass = $p['status'] === 'diterima' ? 'row-dim' : '';
      ?>
      <tr data-status="<?= h($stKey) ?>" data-tgl="<?= h(strtolower(date('d M Y', strtotime($p['tanggal_pesan'])))) ?>" class="<?= $rowClass ?>">
        <td><?= $i + 1 ?></td>
        <td class="mono"><?= h($p['kode_pesanan']) ?></td>
        <td><?= h($p['nama_barang']) ?></td>
        <td><?= h($p['supplier_nama']) ?></td>
        <td class="mono"><?= (int)$p['jumlah_pesan'] ?></td>
        <td><span class="tag <?= $sl[1] ?>"><?= h($sl[0]) ?></span></td>
        <td><?= date('d M Y', strtotime($p['tanggal_pesan'])) ?></td>
        <td>
          <?php if ($p['status'] === 'diterima' && !empty($p['tanggal_diterima'])): ?>
            <?= date('d M Y', strtotime($p['tanggal_diterima'])) ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <div class="actions actions-wrap">
            <?php if (in_array($p['status'], ['pending', 'diproses'], true)): ?>
            <button type="button" class="btn-sm btn-quick-status" data-id="<?= (int)$p['id'] ?>" data-kode="<?= h($p['kode_pesanan']) ?>" data-next="diterima">Proses</button>
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

<div class="modal-overlay modal-status-pesan" id="modalStatus" aria-hidden="true">
  <div class="modal-box" role="dialog" aria-labelledby="modalStatusTitle">
    <div class="modal-head"><h3 id="modalStatusTitle">Update Status</h3><button type="button" class="btn-notif-close" id="closeModal" aria-label="Tutup"><i class="ti ti-x"></i></button></div>
    <form method="post" action="update_status.php">
      <div class="modal-body">
        <input type="hidden" name="id" id="modalId">
        <p id="modalKode" style="font-size:12px;color:var(--text-muted);margin-bottom:12px"></p>
        <div class="form-group"><label>Status baru *</label>
          <select name="status" id="modalStatusSelect" required>
            <option value="diproses">Diproses</option>
            <option value="diterima">Diterima</option>
            <option value="dibatalkan">Dibatalkan</option>
          </select>
        </div>
        <div class="form-group" id="modalTglTerima" style="display:none">
          <label>Tiba pd Tanggal *</label>
          <input type="date" name="tanggal_diterima" value="<?= date('Y-m-d') ?>">
          <p style="font-size:11px;color:var(--blue);margin-top:6px">Tanggal ini akan tercatat saat pesanan diterima. Stok ditambah otomatis.</p>
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
  const tbl = document.getElementById('dataTable');
  const searchInp = document.getElementById('searchTable');
  const dateInp = document.getElementById('filterTanggal');
  const tabRoot = document.getElementById('tabStatus');
  let activeTab = 'all';

  function normText(s) {
    return (s || '').toLowerCase().replace(/[\/\-\.]/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function applyFilters() {
    if (!tbl) return;
    const q = normText(searchInp?.value);
    const dateQ = normText(dateInp?.value);
    tbl.querySelectorAll('tbody tr').forEach((tr) => {
      if (tr.querySelector('.empty-state')) return;
      const tabOk = activeTab === 'all' || tr.getAttribute('data-status') === activeTab;
      const tgl = normText(tr.getAttribute('data-tgl') || '');
      const rowText = normText(tr.textContent);
      const searchOk = !q || rowText.includes(q);
      const dateOk = !dateQ || tgl.includes(dateQ);
      tr.style.display = tabOk && searchOk && dateOk ? '' : 'none';
    });
  }

  tabRoot?.querySelectorAll('button[data-status]').forEach((btn) => {
    btn.addEventListener('click', () => {
      activeTab = btn.getAttribute('data-status') || 'all';
      tabRoot.querySelectorAll('button[data-status]').forEach((b) => {
        b.classList.toggle('active', b === btn);
      });
      applyFilters();
    });
  });

  searchInp?.addEventListener('input', applyFilters);
  dateInp?.addEventListener('input', applyFilters);

  const modal = document.getElementById('modalStatus');
  if (modal.parentElement && modal.parentElement !== document.body) {
    document.body.appendChild(modal);
  }

  function closeStatusModal() {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  function setTglTerimaToday() {
    const inp = document.querySelector('#modalTglTerima input[name=tanggal_diterima]');
    if (inp) inp.value = new Date().toISOString().slice(0, 10);
  }
  function openStatusModal(id, kode, status) {
    document.getElementById('modalId').value = id;
    document.getElementById('modalKode').textContent = kode;
    document.getElementById('modalStatusSelect').value = status;
    if (status === 'diterima') setTglTerimaToday();
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    toggleTglTerima();
  }
  document.querySelectorAll('.btn-quick-status').forEach((btn) => {
    btn.addEventListener('click', () => {
      openStatusModal(btn.dataset.id, btn.dataset.kode, btn.dataset.next);
    });
  });
  function toggleTglTerima() {
    const isDiterima = document.getElementById('modalStatusSelect').value === 'diterima';
    document.getElementById('modalTglTerima').style.display = isDiterima ? 'block' : 'none';
    if (isDiterima) setTglTerimaToday();
  }
  document.getElementById('modalStatusSelect').addEventListener('change', toggleTglTerima);
  document.getElementById('closeModal').onclick = closeStatusModal;
  document.getElementById('cancelModal').onclick = closeStatusModal;
  modal.addEventListener('click', (e) => { if (e.target === modal) closeStatusModal(); });
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

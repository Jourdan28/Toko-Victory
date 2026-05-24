<?php
/* === transaksi/masuk.php === */
require_once __DIR__ . '/../includes/db.php';
requireStaff();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'transaksi_masuk';
$page_title = 'Barang Masuk';

require_once __DIR__ . '/../includes/schema_master.php';
ensureSupplierNamaSupplierColumn($pdo);
$supplierNameCol = tableHasColumn($pdo, 'supplier', 'nama_supplier') ? 'nama_supplier' : 'nama';

$barangRows = $pdo->query(
    "SELECT b.id, b.nama_barang, b.stok_saat_ini, GREATEST(b.rop,1) AS rop,
     COALESCE(b.safety_stock,0) AS safety_stock,
     COALESCE(s.`{$supplierNameCol}`, s.nama) AS supplier_nama, b.id_supplier
     FROM barang b LEFT JOIN supplier s ON b.id_supplier = s.id ORDER BY b.nama_barang"
)->fetchAll();

$suppliers = $pdo->query(
    "SELECT id, COALESCE(`{$supplierNameCol}`, nama) AS nama FROM supplier ORDER BY nama"
)->fetchAll();
$barangList = [];
foreach ($barangRows as $b) {
    $st = getStokStatus((int)$b['stok_saat_ini'], (int)$b['rop']);
    $barangList[] = [
        'id' => (int)$b['id'],
        'nama_barang' => $b['nama_barang'],
        'stok_saat_ini' => (int)$b['stok_saat_ini'],
        'rop' => (int)$b['rop'],
        'safety_stock' => (int)$b['safety_stock'],
        'supplier_nama' => $b['supplier_nama'],
        'id_supplier' => (int)($b['id_supplier'] ?? 0),
        'status' => $st,
    ];
}

$riwayat = $pdo->query(
    "SELECT t.*, b.nama_barang, COALESCE(s.nama_supplier, s.nama) AS supplier_nama, u.name AS nama_user
     FROM transaksi t
     JOIN barang b ON t.id_barang = b.id
     LEFT JOIN supplier s ON b.id_supplier = s.id
     LEFT JOIN users u ON t.id_user = u.id
     WHERE t.jenis = 'masuk' ORDER BY t.created_at DESC LIMIT 50"
)->fetchAll();

ob_start();
?>
<div class="page-head">
  <h2><i class="ti ti-arrow-bar-to-down" style="color:var(--green)"></i> Catat Barang Masuk</h2>
  <a href="index.php" class="btn-outline">← Manajemen Transaksi</a>
</div>

<nav class="tabs" id="tabTransaksiNav" aria-label="Jenis transaksi">
  <a href="index.php" class="report-tab">Semua</a>
  <a href="masuk.php" class="report-tab active">Barang Masuk</a>
  <a href="keluar.php" class="report-tab">Barang Keluar</a>
</nav>

<form method="post" action="proses_masuk.php" class="form-card" id="formMasuk">
  <div class="form-grid">
    <div>
      <?php include __DIR__ . '/../includes/barang_select_dropdown.php'; ?>
      <div class="form-group"><label>Jumlah Masuk *</label>
        <input type="number" name="jumlah" id="jumlah" min="1" value="1" required>
      </div>
      <div class="form-group"><label>Keterangan</label>
        <textarea name="keterangan" rows="2" placeholder="Pembelian dari supplier..."></textarea>
      </div>
    </div>
    <div>
      <div class="form-group"><label>Supplier</label>
        <select name="id_supplier" id="id_supplier">
          <option value="">— Pilih supplier —</option>
          <?php foreach ($suppliers as $sp): ?>
          <option value="<?= (int)$sp['id'] ?>"><?= h($sp['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Tanggal Masuk *</label>
        <input type="date" name="tanggal_masuk" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="preview-stok ok" id="previewStok">Stok setelah transaksi: — unit</div>
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn btn-green btn-action"><i class="ti ti-device-floppy"></i> Simpan</button>
    <button type="reset" class="btn btn-reset">Reset</button>
  </div>
</form>

<h3 style="font-size:15px;margin:24px 0 12px">Riwayat Barang Masuk</h3>
<div class="search-box"><i class="ti ti-search"></i><input type="search" id="searchRiwayat" placeholder="Cari riwayat..."></div>
<div class="card-table">
  <table id="tblRiwayat">
    <thead><tr><th>No</th><th>Kode</th><th>Barang</th><th>Supplier</th><th>Jml</th><th>Stok+</th><th>Oleh</th><th>Tanggal</th></tr></thead>
    <tbody>
      <?php foreach ($riwayat as $i => $t): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td class="mono"><?= h($t['kode_transaksi'] ?? '-') ?></td>
        <td><?= h($t['nama_barang']) ?></td>
        <td><?= h($t['supplier_nama'] ?? '-') ?></td>
        <td class="mono">+<?= (int)$t['jumlah'] ?></td>
        <td class="mono"><?= (int)($t['stok_sesudah'] ?? 0) ?></td>
        <td><?= h($t['nama_user'] ?? '-') ?></td>
        <td><?= date('d M Y H:i', strtotime($t['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script>
const selBarang = document.getElementById('id_barang');
selBarang.addEventListener('change', () => {
  onBarangSelectChange(selBarang, 'infoBarang', (b) => {
    const sup = document.getElementById('id_supplier');
    if (b.id_supplier && sup) sup.value = String(b.id_supplier);
    updatePreviewMasuk();
  });
});

function updatePreviewMasuk() {
  const b = barangData.find((x) => String(x.id) === String(selBarang.value));
  const j = +document.getElementById('jumlah').value || 0;
  const el = document.getElementById('previewStok');
  if (!b) { el.textContent = 'Stok setelah transaksi: — unit'; el.className = 'preview-stok ok'; return; }
  const after = b.stok_saat_ini + j;
  el.textContent = `Stok setelah transaksi: ${after} unit`;
  el.className = 'preview-stok ' + (after > b.rop ? 'ok' : (after > 0 ? 'warn' : 'bad'));
}
document.getElementById('jumlah').addEventListener('input', updatePreviewMasuk);
document.getElementById('formMasuk').addEventListener('reset', () => {
  setTimeout(() => { document.getElementById('infoBarang').classList.remove('show'); updatePreviewMasuk(); }, 0);
});
runPageInit(() => liveSearch('searchRiwayat', 'tblRiwayat'));
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

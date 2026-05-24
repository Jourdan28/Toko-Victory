<?php
/* === transaksi/keluar.php === */
require_once __DIR__ . '/../includes/db.php';
requireStaff();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'transaksi_keluar';
$page_title = 'Barang Keluar';

$barangRows = $pdo->query(
    "SELECT b.id, b.nama_barang, b.stok_saat_ini, GREATEST(b.rop,1) AS rop,
     COALESCE(b.safety_stock,0) AS safety_stock
     FROM barang b ORDER BY b.nama_barang"
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
        'status' => $st,
    ];
}

$riwayat = $pdo->query(
    "SELECT t.*, b.nama_barang, b.rop AS rop_barang, u.name AS nama_user
     FROM transaksi t JOIN barang b ON t.id_barang = b.id
     LEFT JOIN users u ON t.id_user = u.id
     WHERE t.jenis = 'keluar' ORDER BY t.created_at DESC LIMIT 50"
)->fetchAll();

ob_start();
?>
<div class="page-head">
  <h2><i class="ti ti-arrow-bar-up" style="color:var(--red)"></i> Catat Barang Keluar</h2>
  <a href="index.php" class="btn-outline">← Manajemen Transaksi</a>
</div>

<nav class="tabs" id="tabTransaksiNav" aria-label="Jenis transaksi">
  <a href="index.php" class="report-tab">Semua</a>
  <a href="masuk.php" class="report-tab">Barang Masuk</a>
  <a href="keluar.php" class="report-tab active">Barang Keluar</a>
</nav>

<form method="post" action="proses_keluar.php" class="form-card" id="formKeluar">
  <div class="form-grid">
    <div>
      <?php include __DIR__ . '/../includes/barang_select_dropdown.php'; ?>
      <p class="field-error" id="errJumlah" style="display:none"></p>
      <div class="form-group"><label>Jumlah Keluar *</label>
        <input type="number" name="jumlah" id="jumlah" min="1" value="1" required>
      </div>
      <div class="form-group"><label>Keterangan</label>
        <textarea name="keterangan" rows="2"></textarea>
      </div>
    </div>
    <div>
      <div class="form-group"><label>Tanggal Keluar *</label>
        <input type="date" name="tanggal_keluar" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="preview-stok ok" id="previewStok">Stok setelah transaksi: — unit</div>
      <div class="rop-result" id="ropEstimasi" style="margin-top:8px">Estimasi ROP setelah disimpan: —</div>
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn btn-red btn-action" id="btnSubmit"><i class="ti ti-device-floppy"></i> Simpan</button>
    <button type="reset" class="btn btn-reset">Reset</button>
  </div>
</form>

<h3 style="font-size:15px;margin:24px 0 12px">Riwayat Barang Keluar</h3>
<div class="card-table">
  <table id="tblRiwayat">
    <thead><tr><th>No</th><th>Kode</th><th>Barang</th><th>Jml</th><th>Stok</th><th>ROP</th><th>Oleh</th><th>Tanggal</th></tr></thead>
    <tbody>
      <?php foreach ($riwayat as $i => $t): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td class="mono"><?= h($t['kode_transaksi'] ?? '-') ?></td>
        <td><?= h($t['nama_barang']) ?></td>
        <td class="mono">-<?= (int)$t['jumlah'] ?></td>
        <td class="mono"><?= (int)($t['stok_sebelum']??0) ?> → <?= (int)($t['stok_sesudah']??0) ?></td>
        <td><?= (int)($t['rop_barang']??0) ?></td>
        <td><?= h($t['nama_user']??'-') ?></td>
        <td><?= date('d M Y H:i', strtotime($t['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script>
const selBarangKeluar = document.getElementById('id_barang');
selBarangKeluar.addEventListener('change', () => {
  onBarangSelectChange(selBarangKeluar, 'infoBarang', () => updateKeluar());
});

async function updateKeluar() {
  const id = selBarangKeluar.value;
  const j = +document.getElementById('jumlah').value || 0;
  const err = document.getElementById('errJumlah');
  const b = barangData.find((x) => String(x.id) === String(id));
  const prev = document.getElementById('previewStok');
  if (!b) {
    prev.textContent = 'Stok setelah transaksi: — unit';
    prev.className = 'preview-stok ok';
    err.style.display = 'none';
    return;
  }
  if (j > b.stok_saat_ini) {
    err.style.display = 'block';
    err.textContent = `Maksimal ${b.stok_saat_ini} unit (stok saat ini).`;
    document.getElementById('jumlah').setAttribute('max', b.stok_saat_ini);
  } else {
    err.style.display = 'none';
    document.getElementById('jumlah').setAttribute('max', b.stok_saat_ini);
  }
  const after = Math.max(0, b.stok_saat_ini - j);
  prev.textContent = `Stok setelah transaksi: ${after} unit`;
  prev.className = 'preview-stok ' + (after > b.rop ? 'ok' : (after > 0 ? 'warn' : 'bad'));
  try {
    const res = await fetch(`${basePath}/api/hitung_rop.php?id_barang=${id}&jumlah_keluar=${j}`);
    const d = await res.json();
    if (d.success) {
      document.getElementById('ropEstimasi').innerHTML =
        `Estimasi ROP saat ini: <strong>${d.estimasi_rop}</strong> unit` +
        (d.menipis ? ' <span style="color:var(--amber)">— akan menipis</span>' : '');
    }
  } catch (e) {}
}
document.getElementById('jumlah').addEventListener('input', updateKeluar);
document.getElementById('formKeluar').addEventListener('submit', (e) => {
  const b = barangData.find((x) => String(x.id) === String(selBarangKeluar.value));
  const j = +document.getElementById('jumlah').value;
  if (!b) {
    e.preventDefault();
    selBarangKeluar.focus();
    alert('Pilih barang dari daftar terlebih dahulu.');
    return;
  }
  if (j < 1 || j > b.stok_saat_ini) {
    e.preventDefault();
    alert('Jumlah tidak valid atau melebihi stok.');
  }
});
document.getElementById('formKeluar').addEventListener('reset', () => {
  setTimeout(() => {
    document.getElementById('infoBarang').classList.remove('show');
    document.getElementById('errJumlah').style.display = 'none';
    updateKeluar();
  }, 0);
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

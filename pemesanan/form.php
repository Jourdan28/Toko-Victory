<?php
/* === pemesanan/form.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwnerOnly();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'pemesanan';
$id = (int) ($_GET['id'] ?? 0);
$edit = $id > 0;
$page_title = $edit ? 'Edit Pesanan' : 'Buat Pesanan';
$item = null;

if ($edit) {
    $stmt = $pdo->prepare(
        'SELECT p.*, b.nama_barang, b.stok_saat_ini, b.rop, COALESCE(b.safety_stock,0) AS safety_stock
         FROM pemesanan p JOIN barang b ON p.id_barang = b.id WHERE p.id = ?'
    );
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        setFlash('error', 'Pesanan tidak ditemukan.');
        header('Location: index.php');
        exit;
    }
}

$barangRows = $pdo->query(
    "SELECT b.id, b.nama_barang, b.stok_saat_ini, GREATEST(b.rop,1) AS rop,
     COALESCE(b.safety_stock,0) AS safety_stock, b.id_supplier,
     COALESCE(s.nama_supplier, s.nama) AS supplier_nama
     FROM barang b LEFT JOIN supplier s ON b.id_supplier = s.id ORDER BY b.nama_barang"
)->fetchAll();
$suppliers = $pdo->query('SELECT id, COALESCE(nama_supplier, nama) AS nama FROM supplier ORDER BY nama')->fetchAll();

$barangList = [];
foreach ($barangRows as $b) {
    $barangList[] = [
        'id' => (int)$b['id'],
        'nama_barang' => $b['nama_barang'],
        'stok_saat_ini' => (int)$b['stok_saat_ini'],
        'rop' => (int)$b['rop'],
        'safety_stock' => (int)$b['safety_stock'],
        'id_supplier' => (int)($b['id_supplier'] ?? 0),
        'supplier_nama' => $b['supplier_nama'],
        'status' => getStokStatus((int)$b['stok_saat_ini'], (int)$b['rop']),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_barang = (int) ($_POST['id_barang'] ?? 0);
    $id_supplier = (int) ($_POST['id_supplier'] ?? 0);
    $jumlah = (int) ($_POST['jumlah_pesan'] ?? 0);
    $tglPesan = $_POST['tanggal_pesan'] ?? date('Y-m-d');
    $tglEst = $_POST['tanggal_estimasi'] ?? null;
    $catatan = trim($_POST['catatan'] ?? '');
    $status = $_POST['status'] ?? 'pending';

    if ($id_barang < 1 || $id_supplier < 1 || $jumlah < 1) {
        setFlash('error', 'Barang, supplier, dan jumlah wajib diisi.');
    } elseif ($tglEst && $tglEst < $tglPesan) {
        setFlash('error', 'Tanggal estimasi tidak boleh sebelum tanggal pesan.');
    } else {
        try {
            $pdo->beginTransaction();
            if ($edit) {
                $statusBaru = in_array($status, ['pending','diproses','diterima','dibatalkan'], true) ? $status : $item['status'];
                if ($statusBaru === 'diterima' && $item['status'] !== 'diterima') {
                    require_once __DIR__ . '/../includes/rop.php';
                    $tglTerima = $_POST['tanggal_diterima'] ?? date('Y-m-d');
                    $st = $pdo->prepare('SELECT stok_saat_ini FROM barang WHERE id = ?');
                    $st->execute([$id_barang]);
                    $stokSebelum = (int) $st->fetchColumn();
                    $stokSesudah = $stokSebelum + $jumlah;
                    $pdo->prepare(
                        'UPDATE pemesanan SET id_barang=?, id_supplier=?, jumlah_pesan=?, tanggal_pesan=?,
                         tanggal_estimasi=?, catatan=?, status=?, tanggal_diterima=? WHERE id=?'
                    )->execute([$id_barang, $id_supplier, $jumlah, $tglPesan, $tglEst ?: null, $catatan, 'diterima', $tglTerima, $id]);
                    $pdo->prepare('UPDATE barang SET stok_saat_ini = ? WHERE id = ?')->execute([$stokSesudah, $id_barang]);
                    insertTransaksiFull($pdo, $id_barang, 'masuk', $jumlah, $stokSebelum, $stokSesudah, (int)$_SESSION['user']['id'], 'Dari pesanan edit');
                    hitung_rop($pdo, $id_barang);
                    log_activity($pdo, (int)$_SESSION['user']['id'], $_SESSION['user']['nama'], 'transaksi', "Pesanan diterima via edit, stok +$jumlah");
                    $pdo->commit();
                    setFlash('success', 'Pesanan diterima. Stok bertambah otomatis.');
                    header('Location: index.php');
                    exit;
                }
                $pdo->prepare(
                    'UPDATE pemesanan SET id_barang=?, id_supplier=?, jumlah_pesan=?, tanggal_pesan=?,
                     tanggal_estimasi=?, catatan=?, status=? WHERE id=?'
                )->execute([$id_barang, $id_supplier, $jumlah, $tglPesan, $tglEst ?: null, $catatan, $statusBaru, $id]);
                log_activity($pdo, (int)$_SESSION['user']['id'], $_SESSION['user']['nama'], 'edit', 'Mengedit pesanan #' . $id);
                $pdo->commit();
                setFlash('success', 'Pesanan diperbarui.');
            } else {
                $kode = generateKodePesanan($pdo);
                $pdo->prepare(
                    'INSERT INTO pemesanan (kode_pesanan, id_barang, id_supplier, jumlah_pesan, catatan,
                     tanggal_pesan, tanggal_estimasi, id_user) VALUES (?,?,?,?,?,?,?,?)'
                )->execute([$kode, $id_barang, $id_supplier, $jumlah, $catatan, $tglPesan, $tglEst ?: null, (int)$_SESSION['user']['id']]);
                log_activity($pdo, (int)$_SESSION['user']['id'], $_SESSION['user']['nama'], 'tambah', "Pesanan baru $kode");
                $pdo->commit();
                setFlash('success', "Pesanan $kode berhasil dibuat.");
            }
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', $e->getMessage());
        }
    }
}

ob_start();
?>
<div class="page-head">
  <h2><?= $edit ? 'Edit' : 'Buat' ?> Pesanan</h2>
  <a href="index.php" class="btn-outline">← Kembali</a>
</div>
<form method="post" class="form-card" id="formPesan">
  <div class="form-grid">
    <div>
      <div class="form-group">
        <label>Pilih Barang *</label>
        <div class="search-select-wrap">
          <input type="text" id="barang_search" value="<?= h($item['nama_barang'] ?? '') ?>" autocomplete="off">
          <input type="hidden" name="id_barang" id="id_barang" value="<?= (int)($item['id_barang'] ?? 0) ?>" required>
          <div class="search-select-list" id="barang_search_list"></div>
        </div>
        <div class="info-card-barang <?= ($item || ($_POST['id_barang'] ?? '')) ? 'show' : '' ?>" id="infoBarang"></div>
        <p id="saranPesan" style="font-size:11px;color:var(--amber);margin-top:6px"></p>
      </div>
      <div class="form-group"><label>Supplier *</label>
        <select name="id_supplier" id="id_supplier" required>
          <option value="">— Pilih —</option>
          <?php foreach ($suppliers as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= ($item['id_supplier'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= h($s['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Jumlah Pesan *</label>
        <input type="number" name="jumlah_pesan" id="jumlah_pesan" min="1" value="<?= (int)($item['jumlah_pesan'] ?? $_POST['jumlah_pesan'] ?? 1) ?>" required>
        <p class="preview-stok ok" id="previewTerima" style="margin-top:8px">Estimasi stok setelah diterima: —</p>
      </div>
    </div>
    <div>
      <div class="form-group"><label>Tanggal Pesan *</label>
        <input type="date" name="tanggal_pesan" value="<?= h($item['tanggal_pesan'] ?? date('Y-m-d')) ?>" required>
      </div>
      <div class="form-group"><label>Estimasi Tiba</label>
        <input type="date" name="tanggal_estimasi" value="<?= h($item['tanggal_estimasi'] ?? '') ?>">
      </div>
      <div class="form-group"><label>Catatan</label><textarea name="catatan" rows="3"><?= h($item['catatan'] ?? '') ?></textarea></div>
      <?php if ($edit): ?>
      <div class="form-group"><label>Status *</label>
        <select name="status" id="statusPesan">
          <?php foreach (['pending'=>'Menunggu','diproses'=>'Diproses','diterima'=>'Diterima','dibatalkan'=>'Dibatalkan'] as $k=>$v): ?>
          <option value="<?= $k ?>" <?= ($item['status']??'')===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" id="wrapTglTerima" style="display:none">
        <label>Tanggal Diterima *</label>
        <input type="date" name="tanggal_diterima" value="<?= date('Y-m-d') ?>">
        <p style="font-size:11px;color:var(--blue);margin-top:6px">Mengubah ke Diterima akan menambah stok otomatis.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn-primary">Simpan Pesanan</button>
    <a href="index.php" class="btn-outline">Batal</a>
  </div>
</form>
<?php include __DIR__ . '/../includes/barang_select_js.php'; ?>
<script>
initBarangSelect({ inputId: 'barang_search', hiddenId: 'id_barang', infoId: 'infoBarang', onSelect: onBarangPesan });
<?php if ($edit && $item): ?>document.getElementById('id_barang').value = <?= (int)$item['id_barang'] ?>; onBarangPesan(barangData.find(b=>b.id==<?= (int)$item['id_barang'] ?>));<?php endif; ?>

function onBarangPesan(b) {
  if (!b) return;
  const sup = document.getElementById('id_supplier');
  if (b.id_supplier && sup) sup.value = b.id_supplier;
  const saran = Math.max(1, b.rop - b.stok_saat_ini + (b.safety_stock||0));
  document.getElementById('saranPesan').textContent =
    b.stok_saat_ini <= b.rop ? `Disarankan pesan minimal ~${saran} unit` : '';
  updatePreviewTerima();
}
function updatePreviewTerima() {
  const b = barangData.find(x => x.id == document.getElementById('id_barang').value);
  const j = +document.getElementById('jumlah_pesan').value || 0;
  const el = document.getElementById('previewTerima');
  if (b) el.textContent = `Estimasi stok setelah diterima: ${b.stok_saat_ini + j} unit`;
}
document.getElementById('jumlah_pesan')?.addEventListener('input', updatePreviewTerima);
document.getElementById('statusPesan')?.addEventListener('change', e => {
  document.getElementById('wrapTglTerima').style.display = e.target.value === 'diterima' ? 'block' : 'none';
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

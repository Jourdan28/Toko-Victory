<?php
/* === barang/form.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'barang';
$id = (int) ($_GET['id'] ?? 0);
$edit = $id > 0;
$page_title = $edit ? 'Edit Barang' : 'Tambah Barang';
$item = null;

if ($edit) {
    $stmt = $pdo->prepare('SELECT * FROM barang WHERE id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        setFlash('error', 'Barang tidak ditemukan.');
        header('Location: index.php');
        exit;
    }
}

$kategori = $pdo->query('SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori')->fetchAll();
$satuan = $pdo->query('SELECT id, nama_satuan FROM satuan ORDER BY nama_satuan')->fetchAll();
$warna = $pdo->query('SELECT id, nama_warna FROM warna ORDER BY nama_warna')->fetchAll();
$merek = $pdo->query('SELECT id, nama_merek FROM merek ORDER BY nama_merek')->fetchAll();
$lokasi = $pdo->query('SELECT id, nama_lokasi FROM lokasi ORDER BY nama_lokasi')->fetchAll();
$supplier = $pdo->query('SELECT id, COALESCE(nama_supplier, nama) AS nama_supplier FROM supplier ORDER BY nama_supplier')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_barang'] ?? '');
    $id_kategori = (int) ($_POST['id_kategori'] ?? 0) ?: null;
    $id_satuan = (int) ($_POST['id_satuan'] ?? 0) ?: null;
    $id_warna = (int) ($_POST['id_warna'] ?? 0) ?: null;
    $id_merek = (int) ($_POST['id_merek'] ?? 0) ?: null;
    $id_lokasi = (int) ($_POST['id_lokasi'] ?? 0) ?: null;
    $id_supplier = (int) ($_POST['id_supplier'] ?? 0) ?: null;
    $harga = max(0, (float) ($_POST['harga'] ?? 0));
    $stok = max(0, (int) ($_POST['stok_saat_ini'] ?? 0));
    $keterangan = trim($_POST['keterangan'] ?? '');
    $demand_avg = max(0, (float) ($_POST['demand_avg'] ?? 0));
    $demand_max = max(0, (int) ($_POST['demand_max'] ?? 0));
    $delivery_avg = max(0, (float) ($_POST['delivery_avg'] ?? 0));
    $delivery_max = max(0, (int) ($_POST['delivery_max'] ?? 0));
    $calc = autoCalculateRop($stok, $demand_avg, $demand_max, $delivery_avg, $delivery_max);
    $safety_stock = $calc['safety_stock'];
    $rop = $calc['rop'];

    $errors = [];
    if ($nama === '') {
        $errors[] = 'Nama barang wajib diisi.';
    }
    if (!$id_kategori) {
        $errors[] = 'Kategori wajib dipilih.';
    }
    if (!$id_satuan) {
        $errors[] = 'Satuan wajib dipilih.';
    }

    if (empty($errors)) {
        $dup = $pdo->prepare('SELECT id FROM barang WHERE nama_barang = ? AND id != ?');
        $dup->execute([$nama, $edit ? $id : 0]);
        if ($dup->fetch()) {
            $errors[] = 'Nama barang sudah digunakan.';
        }
    }

    $katNama = '';
    if ($id_kategori) {
        $k = $pdo->prepare('SELECT nama_kategori FROM kategori WHERE id = ?');
        $k->execute([$id_kategori]);
        $katNama = $k->fetchColumn() ?: '';
    }

    if (empty($errors)) {
        if ($edit) {
            $oldStok = (int) ($item['stok_saat_ini'] ?? 0);
            $sql = 'UPDATE barang SET nama_barang=?, id_kategori=?, id_satuan=?, id_warna=?, id_merek=?, id_lokasi=?, id_supplier=?,
                    harga=?, stok_saat_ini=?, kategori=?, keterangan=?, demand_avg=?, demand_max=?, delivery_avg=?, delivery_max=?,
                    safety_stock=?, rop=? WHERE id=?';
            $pdo->prepare($sql)->execute([
                $nama, $id_kategori, $id_satuan, $id_warna, $id_merek, $id_lokasi, $id_supplier,
                $harga, $stok, $katNama, $keterangan, $demand_avg, $demand_max, $delivery_avg, $delivery_max,
                $safety_stock, $rop, $id,
            ]);
            $diff = $stok - $oldStok;
            if ($diff > 0) {
                recordStockTransaction($pdo, $id, 'masuk', $diff, 'Penambahan stok — edit barang');
            } elseif ($diff < 0) {
                recordStockTransaction($pdo, $id, 'keluar', abs($diff), 'Pengurangan stok — edit barang');
            }
            log_activity($pdo, (int) $_SESSION['user']['id'], $_SESSION['user']['nama'], 'transaksi', "Update barang $nama (ROP: $rop)");
            setFlash('success', 'Barang diperbarui. ROP otomatis: ' . $rop . ' unit.');
        } else {
            $sql = 'INSERT INTO barang (nama_barang, id_kategori, id_satuan, id_warna, id_merek, id_lokasi, id_supplier,
                    harga, stok_saat_ini, kategori, keterangan, demand_avg, demand_max, delivery_avg, delivery_max, safety_stock, rop, lokasi)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $pdo->prepare($sql)->execute([
                $nama, $id_kategori, $id_satuan, $id_warna, $id_merek, $id_lokasi, $id_supplier,
                $harga, $stok, $katNama, $keterangan, $demand_avg, $demand_max, $delivery_avg, $delivery_max,
                $safety_stock, $rop, '-',
            ]);
            $barangId = (int) $pdo->lastInsertId();
            if ($stok > 0) {
                recordStockTransaction($pdo, $barangId, 'masuk', $stok, 'Stok awal — pendaftaran barang');
            }
            log_activity($pdo, (int) $_SESSION['user']['id'], $_SESSION['user']['nama'], 'tambah', "Barang baru: $nama (ROP: $rop)");
            setFlash('success', 'Barang ditambahkan. ROP otomatis: ' . $rop . ' unit.');
        }
        header('Location: index.php');
        exit;
    }
    setFlash('error', implode(' ', $errors));
}

$v = function ($key, $default = '') use ($item) {
    return h($_POST[$key] ?? ($item[$key] ?? $default));
};

ob_start();
?>
<div class="page-head">
  <h2><?= $edit ? 'Edit' : 'Tambah' ?> Barang</h2>
  <a href="index.php" class="btn-outline">← Kembali</a>
</div>
<form method="post" class="form-card" id="formBarang">
  <div class="form-grid">
    <div>
      <div class="form-group"><label>Nama Barang *</label><input type="text" name="nama_barang" required value="<?= $v('nama_barang') ?>"></div>
      <div class="form-group"><label>Kategori *</label>
        <select name="id_kategori" required>
          <option value="">— Pilih —</option>
          <?php foreach ($kategori as $k): ?>
          <option value="<?= (int)$k['id'] ?>" <?= ($item['id_kategori'] ?? $_POST['id_kategori'] ?? '') == $k['id'] ? 'selected' : '' ?>><?= h($k['nama_kategori']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Satuan *</label>
        <select name="id_satuan" required>
          <option value="">— Pilih —</option>
          <?php foreach ($satuan as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= ($item['id_satuan'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= h($s['nama_satuan']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Merek</label>
        <select name="id_merek"><option value="">— Opsional —</option>
          <?php foreach ($merek as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= ($item['id_merek'] ?? '') == $m['id'] ? 'selected' : '' ?>><?= h($m['nama_merek']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Warna</label>
        <select name="id_warna"><option value="">— Opsional —</option>
          <?php foreach ($warna as $w): ?>
          <option value="<?= (int)$w['id'] ?>" <?= ($item['id_warna'] ?? '') == $w['id'] ? 'selected' : '' ?>><?= h($w['nama_warna']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div>
      <div class="form-group"><label>Lokasi</label>
        <select name="id_lokasi"><option value="">— Opsional —</option>
          <?php foreach ($lokasi as $l): ?>
          <option value="<?= (int)$l['id'] ?>" <?= ($item['id_lokasi'] ?? '') == $l['id'] ? 'selected' : '' ?>><?= h($l['nama_lokasi']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Supplier</label>
        <select name="id_supplier"><option value="">— Opsional —</option>
          <?php foreach ($supplier as $sp): ?>
          <option value="<?= (int)$sp['id'] ?>" <?= ($item['id_supplier'] ?? '') == $sp['id'] ? 'selected' : '' ?>><?= h($sp['nama_supplier']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Harga (Rp)</label><input type="number" name="harga" min="0" step="1" value="<?= $v('harga', '0') ?>"></div>
      <div class="form-group"><label>Stok Saat Ini</label><input type="number" name="stok_saat_ini" min="0" value="<?= $v('stok_saat_ini', '0') ?>"></div>
      <div class="form-group"><label>Keterangan</label><textarea name="keterangan" rows="3"><?= $v('keterangan') ?></textarea></div>
    </div>
  </div>
  <div class="section-title">Reorder Point — dihitung otomatis saat simpan (berdasarkan permintaan atau stok awal)</div>
  <div class="form-grid">
    <div class="form-group"><label>Permintaan rata-rata / hari</label><input type="number" name="demand_avg" id="demand_avg" min="0" step="0.01" value="<?= $v('demand_avg', '0') ?>"></div>
    <div class="form-group"><label>Permintaan maks / hari</label><input type="number" name="demand_max" id="demand_max" min="0" value="<?= $v('demand_max', '0') ?>"></div>
    <div class="form-group"><label>Lead time rata-rata (hari)</label><input type="number" name="delivery_avg" id="delivery_avg" min="0" step="0.01" value="<?= $v('delivery_avg', '0') ?>"></div>
    <div class="form-group"><label>Lead time maks (hari)</label><input type="number" name="delivery_max" id="delivery_max" min="0" value="<?= $v('delivery_max', '0') ?>"></div>
  </div>
  <input type="hidden" name="safety_stock" id="safety_stock" value="<?= $v('safety_stock', '0') ?>">
  <input type="hidden" name="rop" id="rop" value="<?= $v('rop', '0') ?>">
  <div class="rop-result">Safety Stock: <strong id="ssOut">0</strong> · ROP: <strong id="ropOut">0</strong></div>
  <div class="form-actions">
    <button type="submit" class="btn-primary">Simpan Barang</button>
    <a href="index.php" class="btn-outline">Batal</a>
  </div>
</form>
<script>
function calcRop(){
  const stok=+document.querySelector('[name=stok_saat_ini]').value||0;
  const da=+document.getElementById('demand_avg').value||0;
  let dm=+document.getElementById('demand_max').value||0;
  const la=+document.getElementById('delivery_avg').value||0;
  let lm=+document.getElementById('delivery_max').value||0;
  let ss,rop;
  if(da>0&&la>0){
    if(!dm)dm=Math.ceil(da*1.5);
    if(!lm)lm=Math.ceil(la*1.5);
    ss=Math.max(0,Math.round(dm*lm-da*la));
    rop=Math.max(1,Math.round(da*la+ss));
  }else if(stok>0){
    ss=Math.max(3,Math.ceil(stok*0.2));
    rop=Math.max(10,Math.ceil(stok*0.4));
  }else{
    ss=5;rop=10;
  }
  document.getElementById('ssOut').textContent=ss;
  document.getElementById('ropOut').textContent=rop;
  document.getElementById('safety_stock').value=ss;
  document.getElementById('rop').value=rop;
}
['demand_avg','demand_max','delivery_avg','delivery_max','stok_saat_ini'].forEach(id=>{
  const el=document.getElementById(id)||document.querySelector('[name='+id+']');
  el?.addEventListener('input',calcRop);
});
calcRop();
document.getElementById('formBarang')?.addEventListener('submit',e=>{
  const n=document.querySelector('[name=nama_barang]');if(!n.value.trim()){e.preventDefault();alert('Nama barang wajib diisi');}
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

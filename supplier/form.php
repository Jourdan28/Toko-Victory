<?php
/* === supplier/form.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'supplier';
$id = (int) ($_GET['id'] ?? 0);
$edit = $id > 0;
$page_title = $edit ? 'Edit Supplier' : 'Tambah Supplier';
$item = null;

if ($edit) {
    $stmt = $pdo->prepare('SELECT * FROM supplier WHERE id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        setFlash('error', 'Supplier tidak ditemukan.');
        header('Location: index.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_supplier'] ?? '');
    $telp = trim($_POST['no_telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $errors = [];
    if ($nama === '') {
        $errors[] = 'Nama supplier wajib diisi.';
    }
    if ($telp !== '' && !preg_match('/^[0-9+\-\s()]+$/', $telp)) {
        $errors[] = 'No. telepon tidak valid.';
    }
    if (empty($errors)) {
        if ($edit) {
            $pdo->prepare('UPDATE supplier SET nama_supplier=?, nama=?, no_telepon=?, alamat=? WHERE id=?')
                ->execute([$nama, $nama, $telp ?: null, $alamat, $id]);
            log_activity($pdo, (int) $_SESSION['user']['id'], $_SESSION['user']['nama'], 'edit', 'Mengedit supplier: ' . $nama);
            setFlash('success', 'Supplier diperbarui.');
        } else {
            $pdo->prepare('INSERT INTO supplier (nama_supplier, nama, no_telepon, alamat) VALUES (?,?,?,?)')
                ->execute([$nama, $nama, $telp ?: null, $alamat]);
            log_activity($pdo, (int) $_SESSION['user']['id'], $_SESSION['user']['nama'], 'tambah', 'Menambahkan supplier: ' . $nama);
            setFlash('success', 'Supplier ditambahkan.');
        }
        header('Location: index.php');
        exit;
    }
    setFlash('error', implode(' ', $errors));
}

$v = fn($k, $d = '') => h($_POST[$k] ?? ($item[$k] ?? $item['nama_supplier'] ?? $item['nama'] ?? $d));

ob_start();
?>
<div class="page-head"><h2><?= $edit ? 'Edit' : 'Tambah' ?> Supplier</h2><a href="index.php" class="btn-outline">← Kembali</a></div>
<form method="post" class="form-card">
  <div class="form-group"><label>Nama Supplier *</label><input type="text" name="nama_supplier" required value="<?= $v('nama_supplier') ?>"></div>
  <div class="form-group"><label>No. Telepon</label><input type="text" name="no_telepon" value="<?= $v('no_telepon') ?>"></div>
  <div class="form-group"><label>Alamat</label><textarea name="alamat" rows="3"><?= $v('alamat') ?></textarea></div>
  <div class="form-actions"><button type="submit" class="btn-primary">Simpan</button><a href="index.php" class="btn-outline">Batal</a></div>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

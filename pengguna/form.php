<?php
/* === pengguna/form.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'pengguna';
$id = (int) ($_GET['id'] ?? 0);
$edit = $id > 0;
$page_title = $edit ? 'Edit Pengguna' : 'Tambah Pengguna';
$item = null;

if ($edit) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        setFlash('error', 'Pengguna tidak ditemukan.');
        header('Location: index.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $telp = trim($_POST['no_telepon'] ?? '');
    $role = $_POST['role'] ?? 'karyawan';
    $status = $_POST['status'] ?? 'aktif';
    $pass = $_POST['password'] ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';

    if (!in_array($role, ['owner', 'admin', 'karyawan'], true)) {
        $role = 'karyawan';
    }
    if (!in_array($status, ['aktif', 'nonaktif'], true)) {
        $status = 'aktif';
    }

    $errors = [];
    if ($nama === '') {
        $errors[] = 'Nama wajib diisi.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid.';
    }

    $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $dup->execute([$email, $edit ? $id : 0]);
    if ($dup->fetch()) {
        $errors[] = 'Email sudah digunakan.';
    }

    if (!$edit) {
        if (strlen($pass) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }
        if ($pass !== $pass2) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }
    } elseif ($pass !== '') {
        if (strlen($pass) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }
        if ($pass !== $pass2) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }
    }

    if (empty($errors)) {
        if ($edit) {
            if ($pass !== '') {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET name=?, email=?, no_telepon=?, role=?, status=?, password=? WHERE id=?')
                    ->execute([$nama, $email, $telp ?: null, $role, $status, $hash, $id]);
            } else {
                $pdo->prepare('UPDATE users SET name=?, email=?, no_telepon=?, role=?, status=? WHERE id=?')
                    ->execute([$nama, $email, $telp ?: null, $role, $status, $id]);
            }
            log_activity($pdo, (int) $_SESSION['user']['id'], $_SESSION['user']['nama'], 'edit', 'Mengedit pengguna: ' . $nama);
            setFlash('success', 'Pengguna diperbarui.');
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (name, email, password, no_telepon, role, status) VALUES (?,?,?,?,?,?)')
                ->execute([$nama, $email, $hash, $telp ?: null, $role, $status]);
            log_activity($pdo, (int) $_SESSION['user']['id'], $_SESSION['user']['nama'], 'tambah', 'Menambahkan pengguna: ' . $nama);
            setFlash('success', 'Pengguna ditambahkan.');
        }
        header('Location: index.php');
        exit;
    }
    setFlash('error', implode(' ', $errors));
}

ob_start();
?>
<div class="page-head"><h2><?= $edit ? 'Edit' : 'Tambah' ?> Pengguna</h2><a href="index.php" class="btn-outline">← Kembali</a></div>
<form method="post" class="form-card">
  <div class="form-grid">
    <div class="form-group"><label>Nama *</label><input type="text" name="nama" required value="<?= h($_POST['nama'] ?? $item['name'] ?? '') ?>"></div>
    <div class="form-group"><label>Email *</label><input type="email" name="email" required value="<?= h($_POST['email'] ?? $item['email'] ?? '') ?>"></div>
    <div class="form-group"><label>No. Telepon</label><input type="text" name="no_telepon" value="<?= h($_POST['no_telepon'] ?? $item['no_telepon'] ?? '') ?>"></div>
    <div class="form-group"><label>Role *</label>
      <select name="role">
        <option value="owner" <?= ($_POST['role'] ?? $item['role'] ?? '') === 'owner' ? 'selected' : '' ?>>Owner</option>
        <option value="admin" <?= ($_POST['role'] ?? $item['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
        <option value="karyawan" <?= ($_POST['role'] ?? $item['role'] ?? 'karyawan') === 'karyawan' ? 'selected' : '' ?>>Karyawan</option>
      </select>
    </div>
    <div class="form-group"><label>Status *</label>
      <select name="status">
        <option value="aktif" <?= ($_POST['status'] ?? $item['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
        <option value="nonaktif" <?= ($_POST['status'] ?? $item['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
      </select>
    </div>
  </div>
  <div class="form-group"><label><?= $edit ? 'Password baru (kosongkan jika tidak diubah)' : 'Password *' ?></label>
    <div style="position:relative"><input type="password" name="password" id="pwd" <?= $edit ? '' : 'required minlength="8"' ?>>
    <button type="button" onclick="togglePwd('pwd')" style="position:absolute;right:8px;top:8px;background:none;border:none;color:var(--text-muted);cursor:pointer"><i class="ti ti-eye" id="pwdIcon"></i></button></div>
  </div>
  <div class="form-group"><label>Konfirmasi Password <?= $edit ? '' : '*' ?></label>
    <input type="password" name="password_confirm" id="pwd2" <?= $edit ? '' : 'required' ?>></div>
  <div class="form-actions"><button type="submit" class="btn-primary">Simpan</button><a href="index.php" class="btn-outline">Batal</a></div>
</form>
<script>
function togglePwd(id){const i=document.getElementById(id);i.type=i.type==='password'?'text':'password';}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

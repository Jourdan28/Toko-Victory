<?php
require_once __DIR__ . '/config.php';

startAppSession();

if (!empty($_SESSION['user']['id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$fieldErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = trim(strtolower($_POST['role'] ?? 'karyawan'));

    if ($name === '') {
        $fieldErrors['name'] = 'Nama tidak boleh kosong.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'Format email tidak valid.';
    }
    if (strlen($password) < 8) {
        $fieldErrors['password'] = 'Password minimal 8 karakter.';
    }
    if (!in_array($role, ['owner', 'admin', 'karyawan'], true)) {
        $role = 'karyawan';
    }

    if (empty($fieldErrors)) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $fieldErrors['email'] = 'Email ini sudah digunakan.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
                $ins->execute([$name, $email, $hash, $role]);
                header('Location: login.php?registered=1');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'Gagal menyimpan ke database. Pastikan setup.php sudah dijalankan.';
        }
    }
}

$post = $_POST;
$authMode = 'register';
include __DIR__ . '/includes/auth_shell.php';
?>
      <div class="card-icon"><i class="ti ti-user-plus"></i></div>
      <h1 class="card-title">Daftar Akun</h1>
      <p class="card-subtitle">Buat akun baru untuk mengakses sistem inventori</p>

      <?php foreach ($errors as $err): ?>
        <p class="form-alert error"><?= h($err) ?></p>
      <?php endforeach; ?>

      <form method="post" action="register.php" novalidate>
        <div class="form-group">
          <label for="name">Nama Pertama</label>
          <input type="text" id="name" name="name" placeholder="Jourdan" autocomplete="given-name" value="<?= h($post['name'] ?? '') ?>" required>
          <?php if (!empty($fieldErrors['name'])): ?><p class="field-error"><?= h($fieldErrors['name']) ?></p><?php endif; ?>
        </div>
        <div class="form-group">
          <label for="email">Akun Email</label>
          <input type="email" id="email" name="email" placeholder="owner@tokovictory.com" autocomplete="email" value="<?= h($post['email'] ?? '') ?>" required>
          <?php if (!empty($fieldErrors['email'])): ?><p class="field-error"><?= h($fieldErrors['email']) ?></p><?php endif; ?>
        </div>
        <div class="form-group">
          <label for="role">Tipe Akun</label>
          <select id="role" name="role" required>
            <option value="karyawan" <?= ($post['role'] ?? '') === 'karyawan' ? 'selected' : '' ?>>Karyawan — akses terbatas</option>
            <option value="admin" <?= ($post['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin — akses penuh</option>
            <option value="owner" <?= ($post['role'] ?? '') === 'owner' ? 'selected' : '' ?>>Owner — akses penuh</option>
          </select>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrap">
            <input type="password" id="password" name="password" class="has-toggle" placeholder="••••••••" autocomplete="new-password" required minlength="8">
            <button type="button" class="toggle-password" data-target="password" aria-label="Tampilkan password"><i class="ti ti-eye"></i></button>
          </div>
          <?php if (!empty($fieldErrors['password'])): ?><p class="field-error"><?= h($fieldErrors['password']) ?></p><?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="width:100%"><i class="ti ti-check"></i> Buat Akun</button>
      </form>
      <p class="auth-link">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
<?php include __DIR__ . '/includes/auth_shell_end.php'; ?>

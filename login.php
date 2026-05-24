<?php
require_once __DIR__ . '/config.php';

startAppSession();

if (!empty($_SESSION['user']['id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if (!empty($_GET['registered'])) {
    $success = 'Akun berhasil dibuat! Silakan login.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'nama' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                ];
                try {
                    log_activity($pdo, (int) $user['id'], $user['name'], 'login', 'Masuk ke sistem');
                } catch (PDOException $e) {
                }
                header('Location: dashboard.php');
                exit;
            }
            $error = 'Email atau password salah.';
        } catch (PDOException $e) {
            $error = 'Gagal terhubung ke database. Jalankan setup.php terlebih dahulu.';
        }
    }
}

$authMode = 'login';
include __DIR__ . '/includes/auth_shell.php';
?>
      <div class="card-icon"><i class="ti ti-login"></i></div>
      <h1 class="card-title">Login ke Aplikasi</h1>
      <p class="card-subtitle">Masuk untuk mengelola inventori stok Toko Victory</p>

      <?php if ($success): ?>
        <p class="form-alert success"><?= h($success) ?></p>
      <?php endif; ?>
      <?php if ($error): ?>
        <p class="form-alert error"><?= h($error) ?></p>
      <?php endif; ?>

      <form method="post" action="login.php" novalidate>
        <div class="form-group">
          <label for="email">Alamat Email</label>
          <input type="email" id="email" name="email" placeholder="owner@tokovictory.com" autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrap">
            <input type="password" id="password" name="password" class="has-toggle" placeholder="••••••••" autocomplete="current-password" required>
            <button type="button" class="toggle-password" data-target="password" aria-label="Tampilkan password"><i class="ti ti-eye"></i></button>
          </div>
        </div>
        <div class="remember-row">
          <input type="checkbox" id="remember-me" name="remember" value="1" checked>
          <label for="remember-me">Remember Me</label>
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="width:100%"><i class="ti ti-arrow-right"></i> Masuk</button>
      </form>
      <p class="auth-link">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
<?php include __DIR__ . '/includes/auth_shell_end.php'; ?>

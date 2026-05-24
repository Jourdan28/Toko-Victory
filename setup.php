<?php
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');
$messages = [];
$success = false;

try {
    $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');

    $sqlFile = __DIR__ . '/database/project2.sql';
    $sql = file_get_contents($sqlFile);
    $sql = preg_replace('/CREATE DATABASE[^;]+;/i', '', $sql);
    $sql = preg_replace('/USE[^;]+;/i', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }

    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('owner','admin','karyawan') NOT NULL DEFAULT 'karyawan' AFTER password");
    }

    $messages[] = 'Semua tabel berhasil dibuat.';

    $countUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($countUsers === 0) {
        $hash = password_hash('password123', PASSWORD_DEFAULT);
        $users = [
            ['Budi Owner', 'owner@tokovictory.com', 'owner'],
            ['Siti Admin', 'admin@tokovictory.com', 'admin'],
            ['Andi Karyawan', 'karyawan@tokovictory.com', 'karyawan'],
        ];
        $ins = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
        foreach ($users as $u) {
            $ins->execute([$u[0], $u[1], $hash, $u[2]]);
        }
        $messages[] = 'User demo dibuat (password: <strong>password123</strong>).';
    }

    $countBarang = (int) $pdo->query('SELECT COUNT(*) FROM barang')->fetchColumn();
    if ($countBarang === 0) {
        $barangs = [
            ['Kaos Polos Premium', 'Pakaian', 45, 30, 'Rak A1'],
            ['Celana Jeans Slim', 'Pakaian', 8, 15, 'Rak A2'],
            ['Sepatu Sneakers', 'Alas Kaki', 5, 12, 'Rak B1'],
            ['Tas Ransel', 'Aksesoris', 22, 20, 'Rak C1'],
            ['Topi Baseball', 'Aksesoris', 3, 10, 'Rak C2'],
            ['Kemeja Formal', 'Pakaian', 18, 15, 'Rak A3'],
        ];
        $insB = $pdo->prepare('INSERT INTO barang (nama_barang, kategori, stok_saat_ini, rop, lokasi) VALUES (?, ?, ?, ?, ?)');
        foreach ($barangs as $b) {
            $insB->execute($b);
        }

        $pdo->exec("INSERT INTO supplier (nama, kontak) VALUES
            ('PT Sukses Jaya', '081234567890'),
            ('CV Mitra Abadi', '081298765432')");

        $trx = [
            [1, 'masuk', 20, 'Restock supplier'],
            [2, 'keluar', 8, 'Penjualan toko'],
            [3, 'masuk', 15, 'Penerimaan gudang'],
            [4, 'keluar', 5, 'Transfer cabang'],
            [5, 'keluar', 2, 'Penjualan eceran'],
            [1, 'keluar', 10, 'Penjualan grosir'],
            [6, 'masuk', 12, 'Restock musim baru'],
        ];
        $insT = $pdo->prepare('INSERT INTO transaksi (id_barang, jenis, jumlah, keterangan, created_at) VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? HOUR))');
        $hours = [2, 5, 8, 12, 24, 30, 48];
        foreach ($trx as $i => $t) {
            $insT->execute([$t[0], $t[1], $t[2], $t[3], $hours[$i] ?? 1]);
        }

        $logs = [
            [1, 'Budi Owner', 'login', 'Masuk ke sistem'],
            [1, 'Budi Owner', 'tambah', 'Menambah data barang Kaos Polos Premium'],
            [2, 'Siti Admin', 'transaksi', 'Transaksi masuk 20 unit Kaos Polos'],
            [3, 'Andi Karyawan', 'transaksi', 'Transaksi keluar 8 unit Celana Jeans'],
            [2, 'Siti Admin', 'edit', 'Memperbarui stok minimum ROP'],
            [1, 'Budi Owner', 'transaksi', 'Transaksi besar keluar 10 unit'],
        ];
        $insL = $pdo->prepare('INSERT INTO log_aktivitas (id_user, nama_user, aksi, keterangan, created_at) VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? HOUR))');
        foreach ($logs as $i => $l) {
            $insL->execute([$l[0], $l[1], $l[2], $l[3], ($i + 1) * 3]);
        }

        $messages[] = 'Data sample barang, transaksi, dan log aktivitas ditambahkan.';
    }

    $success = true;
} catch (PDOException $e) {
    $messages[] = 'Error: ' . htmlspecialchars($e->getMessage());
}
$tvBase = rtrim(appBasePath(), '/');
$tvTitle = 'Setup — Toko Victory';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body class="auth-page setup-page">
<div class="setup-wrap">
  <div class="setup-card">
    <div class="setup-brand">
      <span class="logo-box">VY</span>
      <div>
        <h1>Setup Database</h1>
        <p>Toko Victory — project2</p>
      </div>
    </div>
    <?php foreach ($messages as $msg): ?>
    <div class="setup-msg <?= $success ? 'success' : 'error' ?>">
      <i class="ti ti-<?= $success ? 'circle-check' : 'alert-circle' ?>"></i>
      <span><?= $msg ?></span>
    </div>
    <?php endforeach; ?>
    <?php if ($success): ?>
    <div class="setup-actions">
      <a href="login.php" class="btn-primary"><i class="ti ti-login"></i> Masuk ke aplikasi</a>
      <a href="register.php" class="btn-outline">Daftar akun</a>
    </div>
    <p class="setup-demo">
      Demo: <code>owner@tokovictory.com</code> · <code>karyawan@tokovictory.com</code> — password <code>password123</code>
    </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

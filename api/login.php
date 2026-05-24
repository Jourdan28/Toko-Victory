<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    jsonResponse(['success' => false, 'message' => 'Data tidak valid.'], 400);
}

$email = trim(strtolower($input['email'] ?? ''));
$password = $input['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'field' => 'email', 'message' => 'Format email tidak valid.'], 400);
}

if (strlen($password) < 6) {
    jsonResponse(['success' => false, 'field' => 'password', 'message' => 'Password minimal 6 karakter.'], 400);
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(['success' => false, 'message' => 'Email atau password salah.'], 401);
    }

    startAppSession();
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'nama' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'] ?? 'karyawan',
    ];

    try {
        log_activity($pdo, (int) $user['id'], $user['name'], 'login', 'Masuk ke sistem');
    } catch (PDOException $e) {
        /* login tetap berhasil meski log gagal */
    }

    jsonResponse([
        'success' => true,
        'message' => 'Login berhasil!',
        'redirect' => 'dashboard.php',
        'user' => [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'] ?? 'karyawan',
        ],
    ]);
} catch (PDOException $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Gagal terhubung ke database. Pastikan MySQL berjalan dan database project2 sudah dibuat (jalankan setup.php).',
    ], 500);
}

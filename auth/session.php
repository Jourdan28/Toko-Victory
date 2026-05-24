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

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    jsonResponse(['success' => false, 'message' => 'Email atau password tidak valid.'], 400);
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
        'role' => $user['role'],
    ];

    try {
        log_activity($pdo, (int) $user['id'], $user['name'], 'login', 'Masuk ke sistem via aplikasi');
    } catch (PDOException $e) {
        /* login tetap berhasil */
    }

    jsonResponse([
        'success' => true,
        'redirect' => 'dashboard.php',
        'user' => $_SESSION['user'],
    ]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Gagal terhubung ke database.'], 500);
}

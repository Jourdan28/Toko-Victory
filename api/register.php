<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    jsonResponse(['success' => false, 'message' => 'Data tidak valid.'], 400);
}

$name = trim($input['name'] ?? '');
$email = trim(strtolower($input['email'] ?? ''));
$password = $input['password'] ?? '';
$role = trim(strtolower($input['role'] ?? 'karyawan'));

$allowedRoles = ['owner', 'admin', 'karyawan'];
if (!in_array($role, $allowedRoles, true)) {
    $role = 'karyawan';
}

if ($name === '') {
    jsonResponse(['success' => false, 'field' => 'name', 'message' => 'Nama tidak boleh kosong.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'field' => 'email', 'message' => 'Format email tidak valid.'], 400);
}

if (strlen($password) < 8) {
    jsonResponse(['success' => false, 'field' => 'password', 'message' => 'Password minimal 8 karakter.'], 400);
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'field' => 'email', 'message' => 'Email ini sudah digunakan.'], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
    $insert->execute([$name, $email, $hash, $role]);

    jsonResponse([
        'success' => true,
        'message' => 'Akun berhasil dibuat! Silakan login.',
        'user' => [
            'id' => (int) $pdo->lastInsertId(),
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ],
    ]);
} catch (PDOException $e) {
    $hint = $e->getMessage();
    if (stripos($hint, 'Unknown database') !== false) {
        $msg = 'Database belum ada. Buka http://localhost/project2/setup.php lalu coba lagi.';
    } elseif (stripos($hint, 'Connection refused') !== false || stripos($hint, 'actively refused') !== false) {
        $msg = 'MySQL tidak berjalan. Nyalakan MySQL di XAMPP Control Panel.';
    } else {
        $msg = 'Gagal menyimpan ke database. Buka setup.php atau hubungi admin.';
    }
    jsonResponse(['success' => false, 'message' => $msg], 500);
}

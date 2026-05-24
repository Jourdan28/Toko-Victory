<?php
require_once __DIR__ . '/config.php';

startAppSession();
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) {
    echo json_encode(['results' => []]);
    exit;
}

$role = $_SESSION['user']['role'] ?? 'karyawan';
$full = isFullAccess($role);
$like = '%' . $q . '%';

try {
    $pdo = getDbConnection();
    $results = [];

    $stmt = $pdo->prepare(
        'SELECT id, nama_barang, kategori, stok_saat_ini, lokasi
         FROM barang
         WHERE nama_barang LIKE ? OR kategori LIKE ? OR lokasi LIKE ?
         ORDER BY nama_barang ASC LIMIT 8'
    );
    $stmt->execute([$like, $like, $like]);
    foreach ($stmt->fetchAll() as $row) {
        $results[] = [
            'type' => 'barang',
            'icon' => 'box',
            'title' => $row['nama_barang'],
            'meta' => $row['kategori'] . ' · Stok ' . $row['stok_saat_ini'],
            'url' => '#barang-' . $row['id'],
        ];
    }

    if ($full) {
        $stmtS = $pdo->prepare('SELECT id, nama FROM supplier WHERE nama LIKE ? LIMIT 4');
        $stmtS->execute([$like]);
        foreach ($stmtS->fetchAll() as $row) {
            $results[] = [
                'type' => 'supplier',
                'icon' => 'truck',
                'title' => $row['nama'],
                'meta' => 'Supplier',
                'url' => '#supplier-' . $row['id'],
            ];
        }

        $stmtU = $pdo->prepare('SELECT id, name, role FROM users WHERE name LIKE ? OR email LIKE ? LIMIT 4');
        $stmtU->execute([$like, $like]);
        foreach ($stmtU->fetchAll() as $row) {
            $results[] = [
                'type' => 'user',
                'icon' => 'user',
                'title' => $row['name'],
                'meta' => ucfirst($row['role']),
                'url' => '#user-' . $row['id'],
            ];
        }
    }

    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['results' => [], 'error' => 'Database error']);
}

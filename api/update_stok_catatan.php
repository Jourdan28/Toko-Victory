<?php
/* === api/update_stok_catatan.php === */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/laporan_lib.php';
startAppSession();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user']['id'])) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isFullAccess($_SESSION['user']['role'] ?? '')) {
    echo json_encode(['ok' => false, 'message' => 'Hanya owner/admin yang dapat mengubah stok fisik']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Method tidak valid']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$stok = (int) ($_POST['stok_catatan'] ?? -1);

if ($id <= 0 || $stok < 0) {
    echo json_encode(['ok' => false, 'message' => 'Data tidak valid']);
    exit;
}

ensureLaporanSchema($pdo);
$stmt = $pdo->prepare('UPDATE barang SET stok_catatan = ? WHERE id = ?');
$stmt->execute([$stok, $id]);

echo json_encode(['ok' => true, 'stok_catatan' => $stok]);

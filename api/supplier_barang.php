<?php
require_once __DIR__ . '/../includes/db.php';
requireOwner();
header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT id, nama_barang, stok_saat_ini, COALESCE(kategori, "Umum") AS kategori
     FROM barang WHERE id_supplier = ? ORDER BY nama_barang'
);
$stmt->execute([$id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['items' => $items, 'count' => count($items)], JSON_UNESCAPED_UNICODE);

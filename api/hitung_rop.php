<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rop.php';

requireStaff();

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id_barang'] ?? 0);
$jumlahKeluar = max(0, (int) ($_GET['jumlah_keluar'] ?? 0));

if ($id < 1) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare('SELECT stok_saat_ini, rop, safety_stock FROM barang WHERE id = ?');
$stmt->execute([$id]);
$b = $stmt->fetch();
if (!$b) {
    echo json_encode(['success' => false]);
    exit;
}

$stokSesudah = max(0, (int) $b['stok_saat_ini'] - $jumlahKeluar);
$ropSaatIni = max(1, (int) $b['rop']);

echo json_encode([
    'success' => true,
    'stok_saat_ini' => (int) $b['stok_saat_ini'],
    'stok_sesudah' => $stokSesudah,
    'rop_saat_ini' => $ropSaatIni,
    'safety_stock' => (int) ($b['safety_stock'] ?? 0),
    'estimasi_rop' => $ropSaatIni,
    'menipis' => $stokSesudah <= $ropSaatIni,
    'status' => getStokStatus($stokSesudah, $ropSaatIni),
], JSON_UNESCAPED_UNICODE);

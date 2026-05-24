<?php
/* === api/laporan_barang_supplier.php === */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/laporan_lib.php';

header('Content-Type: application/json; charset=utf-8');
requireStaff();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'ID tidak valid']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT nama_barang, stok_saat_ini, rop FROM barang WHERE id_supplier = ? ORDER BY nama_barang'
);
$stmt->execute([$id]);
$data = [];
foreach ($stmt->fetchAll() as $r) {
    $st = laporanStokStatus((int) $r['stok_saat_ini'], (int) $r['rop']);
    $badge = match ($st) {
        'aman' => '<span style="color:green">Aman</span>',
        'menipis' => '<span style="color:#f59e0b">Menipis</span>',
        default => '<span style="color:red">Habis</span>',
    };
    $data[] = [
        'nama' => $r['nama_barang'],
        'stok' => number_format((int) $r['stok_saat_ini']),
        'rop' => number_format((int) $r['rop']),
        'status' => $badge,
    ];
}

echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);

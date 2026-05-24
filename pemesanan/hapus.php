<?php
/* === pemesanan/hapus.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwnerOnly();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT kode_pesanan, status FROM pemesanan WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    setFlash('error', 'Pesanan tidak ditemukan.');
} elseif ($row['status'] !== 'pending') {
    setFlash('error', 'Hanya pesanan berstatus pending yang bisa dihapus.');
} else {
    $pdo->prepare('DELETE FROM pemesanan WHERE id = ?')->execute([$id]);
    log_activity($pdo, (int)$_SESSION['user']['id'], $_SESSION['user']['nama'], 'hapus', 'Menghapus pesanan: ' . $row['kode_pesanan']);
    setFlash('success', 'Pesanan dihapus.');
}
header('Location: index.php');
exit;

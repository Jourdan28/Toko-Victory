<?php
/* === barang/hapus.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT nama_barang FROM barang WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $del = $pdo->prepare('DELETE FROM barang WHERE id = ?');
        $del->execute([$id]);
        try {
            log_activity($pdo, (int) $_SESSION['user']['id'], $_SESSION['user']['nama'], 'hapus', 'Menghapus barang: ' . $row['nama_barang']);
        } catch (PDOException $e) {
        }
        setFlash('success', 'Barang berhasil dihapus.');
    }
}
header('Location: index.php');
exit;

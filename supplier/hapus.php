<?php
/* === supplier/hapus.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM barang WHERE id_supplier = ?');
    $cnt->execute([$id]);
    if ((int) $cnt->fetchColumn() > 0) {
        setFlash('error', 'Supplier tidak bisa dihapus, masih digunakan oleh barang.');
    } else {
        $stmt = $pdo->prepare('SELECT COALESCE(nama_supplier, nama) AS nama FROM supplier WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare('DELETE FROM supplier WHERE id = ?')->execute([$id]);
            log_activity($pdo, (int) $_SESSION['user']['id'], $_SESSION['user']['nama'], 'hapus', 'Menghapus supplier: ' . $row['nama']);
            setFlash('success', 'Supplier berhasil dihapus.');
        }
    }
}
header('Location: index.php');
exit;

<?php
/* === pengguna/hapus.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$selfId = (int) $_SESSION['user']['id'];

if ($id === $selfId) {
    setFlash('error', 'Anda tidak dapat menghapus akun sendiri.');
} elseif ($id > 0) {
    $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        log_activity($pdo, $selfId, $_SESSION['user']['nama'], 'hapus', 'Menghapus pengguna: ' . $row['name']);
        setFlash('success', 'Pengguna dihapus.');
    }
}
header('Location: index.php');
exit;

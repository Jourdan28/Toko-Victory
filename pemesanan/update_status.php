<?php
/* === pemesanan/update_status.php === */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rop.php';
requireOwnerOnly();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$tglTerima = $_POST['tanggal_diterima'] ?? date('Y-m-d');
$allowed = ['diproses', 'diterima', 'dibatalkan'];

if (!in_array($status, $allowed, true)) {
    setFlash('error', 'Status tidak valid.');
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT p.*, b.nama_barang, b.stok_saat_ini FROM pemesanan p
         JOIN barang b ON p.id_barang = b.id WHERE p.id = ? FOR UPDATE'
    );
    $stmt->execute([$id]);
    $pesan = $stmt->fetch();
    if (!$pesan) {
        throw new RuntimeException('Pesanan tidak ditemukan.');
    }

    $oldStatus = $pesan['status'];

    if ($status === 'diterima' && $oldStatus !== 'diterima') {
        $stokSebelum = (int) $pesan['stok_saat_ini'];
        $jumlah = (int) $pesan['jumlah_pesan'];
        $stokSesudah = $stokSebelum + $jumlah;

        $pdo->prepare(
            'UPDATE pemesanan SET status=?, tanggal_diterima=?, updated_at=NOW() WHERE id=?'
        )->execute([$status, $tglTerima, $id]);

        $pdo->prepare('UPDATE barang SET stok_saat_ini = ? WHERE id = ?')->execute([$stokSesudah, $pesan['id_barang']]);

        insertTransaksiFull(
            $pdo,
            (int) $pesan['id_barang'],
            'masuk',
            $jumlah,
            $stokSebelum,
            $stokSesudah,
            (int) $_SESSION['user']['id'],
            'Otomatis dari penerimaan pesanan ' . $pesan['kode_pesanan']
        );

        hitung_rop($pdo, (int) $pesan['id_barang']);

        log_activity(
            $pdo,
            (int) $_SESSION['user']['id'],
            $_SESSION['user']['nama'],
            'transaksi',
            "Pesanan {$pesan['kode_pesanan']} diterima, stok +{$jumlah} unit"
        );
        setFlash('success', 'Status diperbarui. Stok barang bertambah otomatis.');
    } elseif ($status === 'dibatalkan') {
        $pdo->prepare('UPDATE pemesanan SET status=?, updated_at=NOW() WHERE id=?')->execute([$status, $id]);
        log_activity($pdo, (int)$_SESSION['user']['id'], $_SESSION['user']['nama'], 'edit', "Pesanan {$pesan['kode_pesanan']} dibatalkan");
        setFlash('success', 'Pesanan dibatalkan.');
    } else {
        $pdo->prepare('UPDATE pemesanan SET status=?, updated_at=NOW() WHERE id=?')->execute([$status, $id]);
        log_activity($pdo, (int)$_SESSION['user']['id'], $_SESSION['user']['nama'], 'edit', "Status pesanan {$pesan['kode_pesanan']} → $status");
        setFlash('success', 'Status pesanan diperbarui.');
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('error', $e->getMessage());
}

header('Location: index.php');
exit;

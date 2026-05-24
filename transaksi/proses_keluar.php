<?php
/* === transaksi/proses_keluar.php === */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rop.php';

requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: keluar.php');
    exit;
}

$id_barang = (int) ($_POST['id_barang'] ?? 0);
$jumlah = (int) ($_POST['jumlah'] ?? 0);
$keterangan = trim($_POST['keterangan'] ?? '');
$id_user = (int) $_SESSION['user']['id'];

if ($id_barang < 1 || $jumlah < 1) {
    setFlash('error', 'Barang dan jumlah wajib diisi.');
    header('Location: keluar.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, nama_barang, stok_saat_ini, rop FROM barang WHERE id = ? FOR UPDATE');
    $stmt->execute([$id_barang]);
    $barang = $stmt->fetch();

    if (!$barang) {
        throw new RuntimeException('Barang tidak ditemukan.');
    }

    $stokSebelum = (int) $barang['stok_saat_ini'];
    if ($stokSebelum < $jumlah) {
        throw new RuntimeException('Stok tidak mencukupi. Stok saat ini: ' . $stokSebelum . ' unit.');
    }

    $stokSesudah = $stokSebelum - $jumlah;

    $kode = insertTransaksiFull(
        $pdo,
        $id_barang,
        'keluar',
        $jumlah,
        $stokSebelum,
        $stokSesudah,
        $id_user,
        $keterangan ?: 'Barang keluar manual'
    );

    $pdo->prepare('UPDATE barang SET stok_saat_ini = ? WHERE id = ?')->execute([$stokSesudah, $id_barang]);

    $hasilRop = hitung_rop($pdo, $id_barang);

    log_activity(
        $pdo,
        $id_user,
        $_SESSION['user']['nama'],
        'transaksi',
        "Barang keluar: {$barang['nama_barang']} -{$jumlah} unit ($kode). ROP baru: {$hasilRop['rop']}"
    );

    $pdo->commit();

    $msg = "Barang keluar tercatat. Stok: {$stokSebelum} → {$stokSesudah}. ROP diperbarui: {$hasilRop['rop']} unit.";
    if (cek_stok_menipis($pdo, $id_barang)) {
        setFlash('warning', $msg . ' Perhatian: stok sudah di bawah ROP — segera pesan ulang.');
    } else {
        setFlash('success', $msg);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('error', $e->getMessage());
}

header('Location: keluar.php');
exit;

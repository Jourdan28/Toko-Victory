<?php
/* === transaksi/proses_masuk.php === */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rop.php';

requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: masuk.php');
    exit;
}

$id_barang = (int) ($_POST['id_barang'] ?? 0);
$jumlah = (int) ($_POST['jumlah'] ?? 0);
$keterangan = trim($_POST['keterangan'] ?? '');
$id_supplier = (int) ($_POST['id_supplier'] ?? 0);
$tanggal = $_POST['tanggal_masuk'] ?? date('Y-m-d');
$id_user = (int) $_SESSION['user']['id'];

if ($id_barang < 1) {
    setFlash('error', 'Pilih barang dari daftar terlebih dahulu.');
    header('Location: masuk.php');
    exit;
}
if ($jumlah < 1) {
    setFlash('error', 'Jumlah masuk minimal 1 unit.');
    header('Location: masuk.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, nama_barang, stok_saat_ini FROM barang WHERE id = ? FOR UPDATE');
    $stmt->execute([$id_barang]);
    $barang = $stmt->fetch();

    if (!$barang) {
        throw new RuntimeException('Barang tidak ditemukan.');
    }

    $stokSebelum = (int) $barang['stok_saat_ini'];
    $stokSesudah = $stokSebelum + $jumlah;

    $ketFinal = $keterangan ?: 'Barang masuk manual';
    if ($id_supplier > 0) {
        require_once __DIR__ . '/../includes/schema_master.php';
        ensureSupplierNamaSupplierColumn($pdo);
        $col = tableHasColumn($pdo, 'supplier', 'nama_supplier') ? 'nama_supplier' : 'nama';
        $sn = $pdo->prepare("SELECT `{$col}` FROM supplier WHERE id = ?");
        $sn->execute([$id_supplier]);
        $snama = $sn->fetchColumn();
        if ($snama) {
            $ketFinal .= ' | Supplier: ' . $snama;
        }
        $pdo->prepare('UPDATE barang SET id_supplier = ? WHERE id = ?')->execute([$id_supplier, $id_barang]);
    }

    $kode = insertTransaksiFull(
        $pdo,
        $id_barang,
        'masuk',
        $jumlah,
        $stokSebelum,
        $stokSesudah,
        $id_user,
        $ketFinal
    );

    $pdo->prepare('UPDATE barang SET stok_saat_ini = ? WHERE id = ?')->execute([$stokSesudah, $id_barang]);

    log_activity(
        $pdo,
        $id_user,
        $_SESSION['user']['nama'],
        'transaksi',
        "Barang masuk: {$barang['nama_barang']} +{$jumlah} unit ($kode)"
    );

    $pdo->commit();
    setFlash('success', "Barang masuk tercatat. Stok: {$stokSebelum} → {$stokSesudah} unit.");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('error', 'Gagal menyimpan: ' . $e->getMessage());
}

header('Location: masuk.php');
exit;

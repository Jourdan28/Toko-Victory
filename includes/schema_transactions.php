<?php

function ensureTransactionsSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS pemesanan (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kode_pesanan VARCHAR(20) NOT NULL,
        id_barang INT UNSIGNED NOT NULL,
        id_supplier INT UNSIGNED NOT NULL,
        jumlah_pesan INT NOT NULL,
        status ENUM('pending','diproses','diterima','dibatalkan') NOT NULL DEFAULT 'pending',
        catatan TEXT,
        tanggal_pesan DATE NOT NULL,
        tanggal_estimasi DATE DEFAULT NULL,
        tanggal_diterima DATE DEFAULT NULL,
        id_user INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_kode_pesanan (kode_pesanan),
        KEY idx_pemesanan_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $transCols = [
        'kode_transaksi' => "VARCHAR(20) DEFAULT NULL",
        'stok_sebelum' => 'INT NOT NULL DEFAULT 0',
        'stok_sesudah' => 'INT NOT NULL DEFAULT 0',
        'id_user' => 'INT UNSIGNED DEFAULT NULL',
    ];
    foreach ($transCols as $col => $def) {
        $chk = $pdo->query("SHOW COLUMNS FROM transaksi LIKE '$col'")->fetch();
        if (!$chk) {
            $pdo->exec("ALTER TABLE transaksi ADD COLUMN `$col` $def");
        }
    }

    $pdo->exec("UPDATE pemesanan SET status = 'diproses' WHERE status = 'pending'");

    $done = true;
}

function generateKodeTransaksi(PDO $pdo): string
{
    $prefix = 'TRX-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE kode_transaksi LIKE ?");
    $stmt->execute([$prefix . '%']);
    $n = (int) $stmt->fetchColumn() + 1;
    return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

function generateKodePesanan(PDO $pdo): string
{
    $prefix = 'PO-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pemesanan WHERE kode_pesanan LIKE ?");
    $stmt->execute([$prefix . '%']);
    $n = (int) $stmt->fetchColumn() + 1;
    return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

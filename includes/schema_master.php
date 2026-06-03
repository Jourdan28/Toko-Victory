<?php

function columnExists(PDO $pdo, string $table, string $column): bool
{
    // Jangan pakai SHOW COLUMNS LIKE — underscore (_) dianggap wildcard SQL
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Cek kolom dengan query nyata ke tabel (paling akurat). */
function tableHasColumn(PDO $pdo, string $table, string $column): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') {
        return false;
    }
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` WHERE Field = ?");
    $stmt->execute([$column]);
    return (bool) $stmt->fetch();
}

function ensureSupplierNamaSupplierColumn(PDO $pdo): void
{
    if (tableHasColumn($pdo, 'supplier', 'nama_supplier')) {
        return;
    }
    if (!tableHasColumn($pdo, 'supplier', 'nama')) {
        return;
    }
    try {
        $pdo->exec('ALTER TABLE `supplier` ADD COLUMN `nama_supplier` VARCHAR(150) NULL AFTER `id`');
        $pdo->exec('UPDATE `supplier` SET `nama_supplier` = `nama` WHERE `nama_supplier` IS NULL OR `nama_supplier` = ""');
    } catch (PDOException $e) {
        // Biarkan laporan tetap pakai kolom `nama` saja
    }
}

function ensureMasterDataSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS kategori (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nama_kategori VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS satuan (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nama_satuan VARCHAR(50) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS warna (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nama_warna VARCHAR(50) NOT NULL,
        kode_hex VARCHAR(7) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS merek (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nama_merek VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS lokasi (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nama_lokasi VARCHAR(100) NOT NULL,
        keterangan TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ensureSupplierNamaSupplierColumn($pdo);
    foreach ([
        'email' => 'VARCHAR(150) DEFAULT NULL',
        'no_telepon' => 'VARCHAR(20) DEFAULT NULL',
        'alamat' => 'TEXT',
        'keterangan' => 'TEXT',
        'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ] as $col => $def) {
        if (!columnExists($pdo, 'supplier', $col)) {
            $pdo->exec("ALTER TABLE supplier ADD COLUMN `$col` $def");
        }
    }

    $barangCols = [
        'id_kategori' => 'INT UNSIGNED DEFAULT NULL',
        'id_satuan' => 'INT UNSIGNED DEFAULT NULL',
        'id_warna' => 'INT UNSIGNED DEFAULT NULL',
        'id_merek' => 'INT UNSIGNED DEFAULT NULL',
        'id_lokasi' => 'INT UNSIGNED DEFAULT NULL',
        'id_supplier' => 'INT UNSIGNED DEFAULT NULL',
        'harga' => 'DECIMAL(15,2) DEFAULT 0',
        'demand_avg' => 'DECIMAL(10,2) DEFAULT 0',
        'demand_max' => 'INT DEFAULT 0',
        'delivery_avg' => 'DECIMAL(10,2) DEFAULT 0',
        'delivery_max' => 'INT DEFAULT 0',
        'safety_stock' => 'INT DEFAULT 0',
        'stok_catatan' => 'INT NOT NULL DEFAULT 0',
        'keterangan' => 'TEXT',
        'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ];
    foreach ($barangCols as $col => $def) {
        if (!columnExists($pdo, 'barang', $col)) {
            $pdo->exec("ALTER TABLE barang ADD COLUMN `$col` $def");
        }
    }

    if (!columnExists($pdo, 'users', 'no_telepon')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN no_telepon VARCHAR(20) DEFAULT NULL');
    }
    if (!columnExists($pdo, 'users', 'status')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif'");
    }

    $pdo->exec('UPDATE barang SET harga = harga * 1000 WHERE harga > 0 AND harga < 1000');

    $done = true;
}

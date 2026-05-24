<?php
/**
 * Konfigurasi & helper Toko Victory
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'project2');
define('DB_USER', 'root');
define('DB_PASS', '');

require_once __DIR__ . '/includes/inventory_helpers.php';

function appBasePath(): string
{
    static $base = null;
    if ($base === null) {
        $root = str_replace('\\', '/', __DIR__);
        $doc = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
        $rel = trim(str_replace($doc, '', $root), '/');
        $base = $rel === '' ? '/' : '/' . $rel . '/';
    }
    return $base;
}

function ensureDatabaseSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM(\'owner\',\'admin\',\'karyawan\') NOT NULL DEFAULT \'karyawan\',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_users_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $hasRole = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch();
    if (!$hasRole) {
        $pdo->exec(
            "ALTER TABLE `users` ADD COLUMN `role` ENUM('owner','admin','karyawan') NOT NULL DEFAULT 'karyawan' AFTER `password`"
        );
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `supplier` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `nama` VARCHAR(150) NOT NULL,
            `kontak` VARCHAR(100) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `barang` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `nama_barang` VARCHAR(150) NOT NULL,
            `kategori` VARCHAR(80) NOT NULL DEFAULT \'Umum\',
            `stok_saat_ini` INT NOT NULL DEFAULT 0,
            `rop` INT NOT NULL DEFAULT 10,
            `lokasi` VARCHAR(100) DEFAULT \'Gudang A\',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `transaksi` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_barang` INT UNSIGNED NOT NULL,
            `jenis` ENUM(\'masuk\',\'keluar\') NOT NULL,
            `jumlah` INT NOT NULL,
            `keterangan` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_transaksi_barang` (`id_barang`),
            KEY `idx_transaksi_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `log_aktivitas` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_user` INT UNSIGNED DEFAULT NULL,
            `nama_user` VARCHAR(100) NOT NULL,
            `aksi` VARCHAR(50) NOT NULL,
            `keterangan` TEXT,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    require_once __DIR__ . '/includes/schema_master.php';
    require_once __DIR__ . '/includes/schema_transactions.php';
    ensureMasterDataSchema($pdo);
    ensureTransactionsSchema($pdo);

    $done = true;
}

function setFlash(string $type, string $message): void
{
    startAppSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    startAppSession();
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Wajib login + role owner atau admin (akses penuh master data).
 */
function requireOwner(?string $redirectDashboard = null): void
{
    startAppSession();
    $base = appBasePath();
    if (empty($_SESSION['user']['id'])) {
        header('Location: ' . $base . 'login.php');
        exit;
    }
    if (!isFullAccess($_SESSION['user']['role'] ?? '')) {
        header('Location: ' . $base . ($redirectDashboard ?? 'dashboard.php'));
        exit;
    }
}

function requireFullAccess(string $redirectDashboard = 'dashboard.php'): void
{
    requireOwner($redirectDashboard);
}

/** Owner, admin, atau karyawan — untuk modul transaksi */
function requireStaff(?string $redirect = null): void
{
    startAppSession();
    $base = appBasePath();
    if (empty($_SESSION['user']['id'])) {
        header('Location: ' . $base . 'login.php');
        exit;
    }
    $role = $_SESSION['user']['role'] ?? '';
    if (!in_array($role, ['owner', 'admin', 'karyawan'], true)) {
        header('Location: ' . $base . ($redirect ?? 'dashboard.php'));
        exit;
    }
}

/** Owner & admin — pemesanan dan akses penuh lainnya */
function requireOwnerOnly(?string $redirect = null): void
{
    requireOwner($redirect);
}

function setFlashWarning(string $message): void
{
    startAppSession();
    $_SESSION['flash'] = ['type' => 'warning', 'message' => $message];
}

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown database') === false) {
                throw $e;
            }
            $boot = new PDO(
                'mysql:host=' . DB_HOST . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $boot->exec(
                'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        ensureDatabaseSchema($pdo);
    }

    return $pdo;
}

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function startAppSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function requireLogin(): void
{
    startAppSession();
    if (empty($_SESSION['user']['id'])) {
        header('Location: login.php');
        exit;
    }
}

function isFullAccess(?string $role): bool
{
    return in_array($role, ['owner', 'admin'], true);
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function log_activity(PDO $pdo, int $id_user, string $nama, string $aksi, string $keterangan): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO log_aktivitas (id_user, nama_user, aksi, keterangan) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$id_user, $nama, $aksi, $keterangan]);
}

function timeAgo(string $datetime): string
{
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return 'Baru saja';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ' menit lalu';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ' jam lalu';
    }
    if (date('Y-m-d', $time) === date('Y-m-d', strtotime('-1 day'))) {
        return 'Kemarin';
    }
    if (date('Y-m-d', $time) === date('Y-m-d')) {
        return 'Hari ini';
    }

    return date('d M', $time);
}

function formatActivityTime(string $datetime): string
{
    $time = strtotime($datetime);
    if (date('Y-m-d', $time) === date('Y-m-d')) {
        return date('H:i', $time) . ' · Hari ini';
    }
    return date('d M', $time);
}

function itemColor(string $name): string
{
    $colors = ['#3b82f6', '#22c55e', '#f59e0b', '#a855f7', '#ef4444', '#06b6d4'];
    return $colors[crc32($name) % count($colors)];
}

function itemInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $init = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $init .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $init ?: '?';
}

<?php
/* === includes/rop.php === ROP engine */

require_once __DIR__ . '/inventory_helpers.php';

function hitung_rop(PDO $pdo, int $id_barang): array
{
    $stmt = $pdo->prepare('SELECT * FROM barang WHERE id = ?');
    $stmt->execute([$id_barang]);
    $barang = $stmt->fetch();
    if (!$barang) {
        return ['rop' => 10, 'safety_stock' => 5, 'demand_avg' => 0, 'demand_max' => 0];
    }

    $hist = $pdo->prepare(
        "SELECT COALESCE(SUM(jumlah), 0) AS total
         FROM transaksi
         WHERE id_barang = ? AND jenis = 'keluar'
         AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY DATE(created_at)"
    );
    $hist->execute([$id_barang]);
    $daily = $hist->fetchAll(PDO::FETCH_COLUMN);

    $demand_avg = 0.0;
    $demand_max = 0;
    if (!empty($daily)) {
        $demand_avg = array_sum($daily) / max(1, count($daily));
        $demand_max = (int) max($daily);
    } elseif ((float) ($barang['demand_avg'] ?? 0) > 0) {
        $demand_avg = (float) $barang['demand_avg'];
        $demand_max = (int) ($barang['demand_max'] ?? 0);
    }

    $delivery_avg = (float) ($barang['delivery_avg'] ?? 0);
    $delivery_max = (int) ($barang['delivery_max'] ?? 0);
    if ($delivery_avg <= 0) {
        $delivery_avg = 3;
        $delivery_max = 5;
    }

    $calc = autoCalculateRop(
        (int) $barang['stok_saat_ini'],
        $demand_avg,
        $demand_max,
        $delivery_avg,
        $delivery_max
    );

    $upd = $pdo->prepare(
        'UPDATE barang SET demand_avg = ?, demand_max = ?, delivery_avg = ?, delivery_max = ?,
         safety_stock = ?, rop = ? WHERE id = ?'
    );
    $upd->execute([
        round($demand_avg, 2),
        $demand_max,
        $delivery_avg,
        $delivery_max,
        $calc['safety_stock'],
        $calc['rop'],
        $id_barang,
    ]);

    return array_merge($calc, [
        'demand_avg' => round($demand_avg, 2),
        'demand_max' => $demand_max,
    ]);
}

function cek_stok_menipis(PDO $pdo, int $id_barang): bool
{
    $stmt = $pdo->prepare('SELECT stok_saat_ini, rop FROM barang WHERE id = ?');
    $stmt->execute([$id_barang]);
    $b = $stmt->fetch();
    if (!$b) {
        return false;
    }
    return (int) $b['stok_saat_ini'] <= max(1, (int) $b['rop']);
}

function insertTransaksiFull(
    PDO $pdo,
    int $idBarang,
    string $jenis,
    int $jumlah,
    int $stokSebelum,
    int $stokSesudah,
    int $idUser,
    string $keterangan
): string {
    $kode = generateKodeTransaksi($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO transaksi (kode_transaksi, id_barang, jenis, jumlah, stok_sebelum, stok_sesudah, keterangan, id_user)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$kode, $idBarang, $jenis, $jumlah, $stokSebelum, $stokSesudah, $keterangan, $idUser]);
    return $kode;
}

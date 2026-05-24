<?php
/**
 * Helper inventori: ROP otomatis, transaksi stok, status menipis
 */

function autoCalculateRop(
    int $stok,
    float $demand_avg = 0,
    int $demand_max = 0,
    float $delivery_avg = 0,
    int $delivery_max = 0
): array {
    if ($demand_avg > 0 && $delivery_avg > 0) {
        $dm = $demand_max > 0 ? $demand_max : (int) ceil($demand_avg * 1.5);
        $lm = $delivery_max > 0 ? $delivery_max : (int) ceil($delivery_avg * 1.5);
        $safety = (int) max(0, round(($dm * $lm) - ($demand_avg * $delivery_avg)));
        $rop = (int) max(1, ceil(($demand_avg * $delivery_avg) + $safety));
        return ['safety_stock' => $safety, 'rop' => $rop];
    }

    if ($stok > 0) {
        $safety = max(3, (int) ceil($stok * 0.2));
        $rop = max(10, (int) ceil($stok * 0.4));
        return ['safety_stock' => $safety, 'rop' => $rop];
    }

    return ['safety_stock' => 5, 'rop' => 10];
}

function recordStockTransaction(
    PDO $pdo,
    int $idBarang,
    string $jenis,
    int $jumlah,
    string $keterangan
): void {
    if ($jumlah <= 0 || !in_array($jenis, ['masuk', 'keluar'], true)) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO transaksi (id_barang, jenis, jumlah, keterangan) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$idBarang, $jenis, $jumlah, $keterangan]);
}

function syncBarangRopDefaults(PDO $pdo): void
{
    $rows = $pdo->query(
        'SELECT id, stok_saat_ini, demand_avg, demand_max, delivery_avg, delivery_max, rop FROM barang'
    )->fetchAll();
    $upd = $pdo->prepare('UPDATE barang SET safety_stock = ?, rop = ? WHERE id = ?');

    foreach ($rows as $r) {
        $calc = autoCalculateRop(
            (int) $r['stok_saat_ini'],
            (float) $r['demand_avg'],
            (int) $r['demand_max'],
            (float) $r['delivery_avg'],
            (int) $r['delivery_max']
        );
        if ((int) $r['rop'] < 1) {
            $upd->execute([$calc['safety_stock'], $calc['rop'], $r['id']]);
        }
    }
}

function backfillInitialStockTransactions(PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT b.id, b.stok_saat_ini FROM barang b
         WHERE b.stok_saat_ini > 0
         AND NOT EXISTS (SELECT 1 FROM transaksi t WHERE t.id_barang = b.id)"
    );
    foreach ($stmt->fetchAll() as $b) {
        recordStockTransaction(
            $pdo,
            (int) $b['id'],
            'masuk',
            (int) $b['stok_saat_ini'],
            'Stok awal — sinkronisasi sistem'
        );
    }
}

function getStokStatus(int $stok, int $rop): array
{
    $rop = max(1, $rop);
    if ($stok <= 0) {
        return ['label' => 'Habis', 'class' => 'kritis', 'level' => 3];
    }
    if ($stok <= $rop) {
        $pct = ($stok / $rop) * 100;
        if ($pct < 30) {
            return ['label' => 'Kritis', 'class' => 'kritis', 'level' => 3];
        }
        return ['label' => 'Menipis', 'class' => 'menipis', 'level' => 2];
    }
    return ['label' => 'Aman', 'class' => 'aman', 'level' => 0];
}

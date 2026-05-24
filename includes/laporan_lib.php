<?php
/* === includes/laporan_lib.php === */

function ensureLaporanSchema(PDO $pdo): void
{
    require_once __DIR__ . '/schema_master.php';
    ensureMasterDataSchema($pdo);
    ensureSupplierNamaSupplierColumn($pdo);
    if (!tableHasColumn($pdo, 'barang', 'stok_catatan')) {
        try {
            $pdo->exec('ALTER TABLE `barang` ADD COLUMN `stok_catatan` INT NOT NULL DEFAULT 0 AFTER `stok_saat_ini`');
        } catch (PDOException $e) {
        }
    }
}

/**
 * Nama supplier di database project2: kolom `nama` (bukan nama_supplier).
 * Semua laporan memakai `nama` agar tidak error jika migrasi belum jalan.
 */
function supplierNameSql(PDO $pdo, string $alias = 's'): string
{
    unset($pdo);
    return $alias . '.nama';
}

function laporanIsOwner(): bool
{
    return ($_SESSION['user']['role'] ?? '') === 'owner';
}

function laporanFiltersFromRequest(): array
{
    return [
        'q' => trim($_GET['q'] ?? ''),
        'kategori' => (int) ($_GET['kategori'] ?? 0),
        'lokasi' => (int) ($_GET['lokasi'] ?? 0),
        'supplier' => (int) ($_GET['supplier'] ?? 0),
        'status' => trim($_GET['status'] ?? ''),
        'dari' => trim($_GET['dari'] ?? ''),
        'sampai' => trim($_GET['sampai'] ?? ''),
        'page' => max(1, (int) ($_GET['page'] ?? 1)),
    ];
}

function laporanBuildQuery(array $filters, array $extra = []): string
{
    $params = array_merge($filters, $extra);
    unset($params['page']);
    $q = http_build_query(array_filter($params, static fn($v) => $v !== '' && $v !== 0));
    return $q === '' ? '' : '?' . $q;
}

function laporanStokStatus(int $stok, int $rop): string
{
    $rop = max($rop, 1);
    if ($stok <= 0) {
        return 'habis';
    }
    if ($stok <= $rop) {
        return 'menipis';
    }
    return 'aman';
}

function laporanStatusBadge(string $status): string
{
    return match ($status) {
        'aman' => '<span class="tag tag-green">Aman</span>',
        'menipis' => '<span class="tag tag-amber">Menipis</span>',
        'habis' => '<span class="tag tag-red">Habis</span>',
        default => '<span class="tag tag-gray">—</span>',
    };
}

function laporanSelisihHtml(int $selisih): string
{
    if ($selisih === 0) {
        return '<span class="tag tag-gray">Sesuai</span> <span class="mono" style="color:var(--text-muted)">0</span>';
    }
    if ($selisih > 0) {
        return '<span class="tag tag-green">Lebih</span> <span class="mono" style="color:var(--green)">+' . number_format($selisih) . ' unit</span>';
    }
    return '<span class="tag tag-red">Kurang</span> <span class="mono" style="color:var(--red)">−' . number_format(abs($selisih)) . ' unit</span>';
}

function laporanFormatTanggal(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($dt);
    return date('d', $t) . ' ' . $months[(int) date('n', $t)] . ' ' . date('Y', $t);
}

function laporanFormatRupiah($n): string
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function laporanPeriodeLabel(array $f): string
{
    if ($f['dari'] !== '' && $f['sampai'] !== '') {
        return laporanFormatTanggal($f['dari']) . ' s.d. ' . laporanFormatTanggal($f['sampai']);
    }
    if ($f['dari'] !== '') {
        return 'Dari ' . laporanFormatTanggal($f['dari']);
    }
    if ($f['sampai'] !== '') {
        return 'Sampai ' . laporanFormatTanggal($f['sampai']);
    }
    return 'Semua data';
}

function laporanKategoriList(PDO $pdo): array
{
    return $pdo->query('SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori')->fetchAll();
}

function laporanLokasiList(PDO $pdo): array
{
    return $pdo->query('SELECT id, nama_lokasi FROM lokasi ORDER BY nama_lokasi')->fetchAll();
}

function laporanSupplierList(PDO $pdo): array
{
    return $pdo->query('SELECT id, nama FROM supplier ORDER BY nama')->fetchAll();
}

function laporanPaginate(PDO $pdo, string $countSql, array $params, int $page, int $perPage = 15): array
{
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    return [$total, $totalPages, $page, $offset];
}

function laporanRenderPageHead(string $title, string $subtitle = ''): void
{
    echo '<div class="page-head">';
    echo '<div class="page-head-main">';
    echo '<h2><i class="ti ti-report-analytics"></i> ' . h($title) . '</h2>';
    if ($subtitle !== '') {
        echo '<p class="page-head-desc">' . h($subtitle) . '</p>';
    }
    echo '</div></div>';
}

function laporanRenderTabs(string $active, string $base): void
{
    $tabs = [
        'barang_tersedia' => ['label' => 'Barang Tersedia', 'href' => 'barang_tersedia.php'],
        'barang_masuk' => ['label' => 'Barang Masuk', 'href' => 'barang_masuk.php'],
        'barang_keluar' => ['label' => 'Barang Keluar', 'href' => 'barang_keluar.php'],
        'stok' => ['label' => 'Stok', 'href' => 'stok.php'],
        'pemasok' => ['label' => 'Pemasok', 'href' => 'pemasok.php'],
    ];
    echo '<nav class="report-tabs laporan-tabs" role="tablist">';
    foreach ($tabs as $key => $tab) {
        $cls = $key === $active ? 'report-tab laporan-tab active' : 'report-tab laporan-tab';
        echo '<a class="' . $cls . '" href="' . h($base . '/laporan/' . $tab['href']) . '">' . h($tab['label']) . '</a>';
    }
    echo '</nav>';
}

function laporanRenderStyles(): void
{
    /* Gaya laporan ada di includes/styles.css */
}

function laporanRenderFooter(int $total, string $laporanKey, array $filters, string $base, bool $isOwner): void
{
    $qs = laporanBuildQuery($filters);
    $cetakUrl = $base . '/laporan/cetak/' . $laporanKey . '.php' . $qs;
    echo '<div class="laporan-footer">';
    echo '<span class="text-muted">Menampilkan <strong class="text-primary">' . number_format($total) . '</strong> data</span>';
    echo '<div class="laporan-footer-actions">';
    echo '<button type="button" class="btn-print-pdf" onclick="window.open(\'' . h($cetakUrl) . '\',\'_blank\')"><i class="ti ti-printer"></i> Cetak PDF</button>';
    if ($isOwner) {
        $exportUrl = $base . '/api/export_csv.php?laporan=' . urlencode($laporanKey) . ($qs ? '&' . ltrim($qs, '?') : '');
        echo '<a href="' . h($exportUrl) . '" class="btn-export"><i class="ti ti-table-export"></i> Export Excel</a>';
    }
    echo '</div></div>';
}

function laporanRenderPagination(int $page, int $totalPages, string $self, array $filters): void
{
    if ($totalPages <= 1) {
        return;
    }
    $filters['page'] = 0;
    echo '<div class="pagination">';
    if ($page > 1) {
        $filters['page'] = $page - 1;
        echo '<a href="' . h($self . laporanBuildQuery($filters)) . '">‹ Prev</a>';
    }
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    for ($i = $start; $i <= $end; $i++) {
        $filters['page'] = $i;
        $cls = $i === $page ? 'active' : '';
        echo '<a class="' . $cls . '" href="' . h($self . laporanBuildQuery($filters)) . '">' . $i . '</a>';
    }
    if ($page < $totalPages) {
        $filters['page'] = $page + 1;
        echo '<a href="' . h($self . laporanBuildQuery($filters)) . '">Next ›</a>';
    }
    echo '</div>';
}

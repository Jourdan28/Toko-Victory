<?php
/* === api/export_csv.php === */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/laporan_lib.php';
startAppSession();

if (empty($_SESSION['user']['id']) || ($_SESSION['user']['role'] ?? '') !== 'owner') {
    http_response_code(403);
    exit('Akses ditolak');
}

ensureLaporanSchema($pdo);
$laporan = $_GET['laporan'] ?? '';
$f = laporanFiltersFromRequest();
$supName = supplierNameSql($pdo, 's');
$filename = 'laporan_' . preg_replace('/[^a-z_]/', '', $laporan) . '_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');

switch ($laporan) {
    case 'barang_tersedia':
        [$whereSql, $params] = laporanBarangTersediaWhere($f);
        $joins = laporanBarangTersediaJoins();
        fputcsv($out, ['No', 'Nama Barang', 'Kategori', 'Satuan', 'Merek', 'Lokasi', 'Stok', 'ROP', 'Status']);
        $sql = "SELECT b.nama_barang, k.nama_kategori, s.nama_satuan, m.nama_merek, l.nama_lokasi,
                b.stok_saat_ini, b.rop
                $joins
                WHERE $whereSql ORDER BY b.stok_saat_ini DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $n = 1;
        while ($r = $stmt->fetch()) {
            $st = laporanStokStatus((int) $r['stok_saat_ini'], (int) $r['rop']);
            fputcsv($out, [
                $n++, $r['nama_barang'], $r['nama_kategori'] ?? '', $r['nama_satuan'] ?? '',
                $r['nama_merek'] ?? '', $r['nama_lokasi'] ?? '', $r['stok_saat_ini'], $r['rop'],
                ucfirst($st),
            ]);
        }
        break;

    case 'stok':
        $where = ['1=1'];
        $params = [];
        if ($f['q'] !== '') {
            $where[] = 'b.nama_barang LIKE ?';
            $params[] = '%' . $f['q'] . '%';
        }
        if ($f['kategori'] > 0) {
            $where[] = 'b.id_kategori = ?';
            $params[] = $f['kategori'];
        }
        $whereSql = implode(' AND ', $where);
        fputcsv($out, ['No', 'Nama Barang', 'Kategori', 'Stok Sistem', 'Stok Catatan', 'Selisih', 'ROP', 'Status']);
        $sql = "SELECT b.nama_barang, k.nama_kategori, b.stok_saat_ini, b.stok_catatan,
                (b.stok_saat_ini - b.stok_catatan) AS selisih, b.rop
                FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id
                WHERE $whereSql ORDER BY b.nama_barang";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $n = 1;
        while ($r = $stmt->fetch()) {
            $st = laporanStokStatus((int) $r['stok_saat_ini'], (int) $r['rop']);
            fputcsv($out, [$n++, $r['nama_barang'], $r['nama_kategori'] ?? '', $r['stok_saat_ini'], $r['stok_catatan'], $r['selisih'], $r['rop'], ucfirst($st)]);
        }
        break;

    case 'barang_masuk':
        $where = ["t.jenis = 'masuk'"];
        $params = [];
        if ($f['q'] !== '') {
            $where[] = '(b.nama_barang LIKE ? OR t.kode_transaksi LIKE ?)';
            $params[] = '%' . $f['q'] . '%';
            $params[] = '%' . $f['q'] . '%';
        }
        if ($f['dari'] !== '') {
            $where[] = 'DATE(t.created_at) >= ?';
            $params[] = $f['dari'];
        }
        if ($f['sampai'] !== '') {
            $where[] = 'DATE(t.created_at) <= ?';
            $params[] = $f['sampai'];
        }
        if ($f['supplier'] > 0) {
            $where[] = 'b.id_supplier = ?';
            $params[] = $f['supplier'];
        }
        $whereSql = implode(' AND ', $where);
        fputcsv($out, ['No', 'Kode', 'Barang', 'Kategori', 'Supplier', 'Jumlah', 'Stok Sesudah', 'Oleh', 'Tanggal']);
        $sql = "SELECT t.kode_transaksi, b.nama_barang, k.nama_kategori, {$supName} AS sup, t.jumlah, t.stok_sesudah, u.name, t.created_at
                FROM transaksi t JOIN barang b ON t.id_barang = b.id
                LEFT JOIN kategori k ON b.id_kategori = k.id LEFT JOIN supplier s ON b.id_supplier = s.id
                LEFT JOIN users u ON t.id_user = u.id WHERE $whereSql ORDER BY t.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $n = 1;
        while ($r = $stmt->fetch()) {
            fputcsv($out, [$n++, $r['kode_transaksi'], $r['nama_barang'], $r['nama_kategori'] ?? '', $r['sup'] ?? '', $r['jumlah'], $r['stok_sesudah'], $r['name'] ?? '', $r['created_at']]);
        }
        break;

    case 'barang_keluar':
        $where = ["t.jenis = 'keluar'"];
        $params = [];
        if ($f['q'] !== '') {
            $where[] = '(b.nama_barang LIKE ? OR t.kode_transaksi LIKE ?)';
            $params[] = '%' . $f['q'] . '%';
            $params[] = '%' . $f['q'] . '%';
        }
        if ($f['dari'] !== '') {
            $where[] = 'DATE(t.created_at) >= ?';
            $params[] = $f['dari'];
        }
        if ($f['sampai'] !== '') {
            $where[] = 'DATE(t.created_at) <= ?';
            $params[] = $f['sampai'];
        }
        if ($f['kategori'] > 0) {
            $where[] = 'b.id_kategori = ?';
            $params[] = $f['kategori'];
        }
        $whereSql = implode(' AND ', $where);
        fputcsv($out, ['No', 'Kode', 'Barang', 'Kategori', 'Keluar', 'Stok Sebelum', 'Stok Sesudah', 'Oleh', 'Tanggal']);
        $sql = "SELECT t.kode_transaksi, b.nama_barang, k.nama_kategori, t.jumlah, t.stok_sebelum, t.stok_sesudah, u.name, t.created_at
                FROM transaksi t JOIN barang b ON t.id_barang = b.id
                LEFT JOIN kategori k ON b.id_kategori = k.id LEFT JOIN users u ON t.id_user = u.id
                WHERE $whereSql ORDER BY t.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $n = 1;
        while ($r = $stmt->fetch()) {
            fputcsv($out, [$n++, $r['kode_transaksi'], $r['nama_barang'], $r['nama_kategori'] ?? '', $r['jumlah'], $r['stok_sebelum'], $r['stok_sesudah'], $r['name'] ?? '', $r['created_at']]);
        }
        break;

    case 'pemasok':
        $where = ['1=1'];
        $params = [];
        if ($f['q'] !== '') {
            $where[] = "({$supName} LIKE ? OR s.email LIKE ?)";
            $params[] = '%' . $f['q'] . '%';
            $params[] = '%' . $f['q'] . '%';
        }
        $whereSql = implode(' AND ', $where);
        fputcsv($out, ['No', 'Nama Pemasok', 'Telepon', 'Email', 'Barang', 'Total Stok', 'Lead Time']);
        $sql = "SELECT {$supName} AS nama, COALESCE(s.no_telepon, s.kontak) AS tel, s.email,
                COUNT(b.id) AS jb, COALESCE(SUM(b.stok_saat_ini),0) AS ts,
                ROUND(AVG(b.delivery_avg),0) AS da, MAX(b.delivery_max) AS dm
                FROM supplier s LEFT JOIN barang b ON b.id_supplier = s.id
                WHERE $whereSql GROUP BY s.id ORDER BY nama";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $n = 1;
        while ($r = $stmt->fetch()) {
            $lead = ($r['da'] || $r['dm']) ? $r['da'] . '-' . max((int) $r['dm'], (int) $r['da']) . ' hari' : '';
            fputcsv($out, [$n++, $r['nama'], $r['tel'] ?? '', $r['email'] ?? '', $r['jb'], $r['ts'], $lead]);
        }
        break;

    default:
        fputcsv($out, ['Error', 'Laporan tidak dikenali']);
}

fclose($out);
exit;

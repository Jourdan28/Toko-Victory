<?php
/* === includes/laporan_cetak.php === */

function cetakRequireLogin(): void
{
    require_once dirname(__DIR__) . '/config.php';
    startAppSession();
    if (empty($_SESSION['user']['id'])) {
        header('Location: ' . rtrim(appBasePath(), '/') . '/login.php');
        exit;
    }
}

function cetakHeader(string $judul, array $filters, array $ringkasan = []): void
{
    $user = $_SESSION['user'] ?? [];
    $nama = h($user['nama'] ?? 'Pengguna');
    $periode = laporanPeriodeLabel($filters);
    $now = date('d M Y H:i:s');
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title><?= h($judul) ?> — Toko Victory</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11pt; color: #111; background: #fff; padding: 20px; }
    .no-print { margin-bottom: 16px; }
    .no-print button { background: #3b82f6; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; margin-right: 8px; }
    .no-print a { color: #3b82f6; text-decoration: none; font-size: 14px; }
    .header-toko { text-align: center; margin-bottom: 16px; }
    .header-toko h1 { font-size: 18pt; letter-spacing: 0.05em; }
    .header-toko .sub { font-size: 10pt; color: #555; }
    .hr-thick { border: none; border-top: 2px solid #111; margin: 12px 0; }
    .hr-thin { border: none; border-top: 1px solid #ccc; margin: 10px 0; }
    .judul-laporan { text-align: center; font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 8px 0; }
    .meta { font-size: 10pt; color: #444; margin-bottom: 4px; }
    .ringkasan { width: 100%; margin: 12px 0; border-collapse: collapse; font-size: 10pt; }
    .ringkasan td { border: 1px solid #333; padding: 6px 10px; text-align: center; }
    .ringkasan th { border: 1px solid #333; padding: 6px; background: #f0f0f0; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 10pt; }
    table.data th, table.data td { border: 1px solid #333; padding: 6px 8px; }
    table.data thead { background: #f0f0f0; }
    table.data tbody tr:nth-child(even) { background: #fafafa; }
    .footer { margin-top: 24px; font-size: 9pt; color: #666; }
    .ttd { margin-top: 40px; text-align: right; }
    .ttd-box { display: inline-block; text-align: center; min-width: 200px; }
    .ttd-line { border-top: 1px solid #111; margin-top: 60px; padding-top: 6px; font-size: 10pt; }
    @media print {
      body { background: white; padding: 0; }
      .no-print { display: none !important; }
      @page { size: A4 portrait; margin: 20mm 15mm; }
      thead { background: #f0f0f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
  <div class="no-print">
    <button type="button" onclick="window.print()">🖨 Cetak / Simpan PDF</button>
    <a href="javascript:window.close()">Tutup</a>
  </div>
  <div class="header-toko">
    <h1>TOKO VICTORY</h1>
    <p class="sub">Aplikasi Inventori Pengelola Stok</p>
  </div>
  <hr class="hr-thick">
  <p class="judul-laporan"><?= h($judul) ?></p>
  <p class="meta">Periode: <?= h($periode) ?></p>
  <p class="meta">Dicetak pada: <?= h($now) ?> · Dicetak oleh: <?= $nama ?></p>
  <hr class="hr-thin">
  <?php if ($ringkasan): ?>
  <table class="ringkasan"><tr>
    <?php foreach ($ringkasan as $r): ?>
    <th><?= h($r['label']) ?></th>
    <?php endforeach; ?>
  </tr><tr>
    <?php foreach ($ringkasan as $r): ?>
    <td><?= h($r['value']) ?></td>
    <?php endforeach; ?>
  </tr></table>
  <?php endif;
}

function cetakFooter(): void
{
    ?>
  <div class="footer">
    <hr class="hr-thin">
    <p>Toko Victory — Laporan ini digenerate otomatis oleh sistem inventori.</p>
  </div>
  <div class="ttd">
    <div class="ttd-box">
      <p style="font-size:10pt;margin-bottom:4px">Mengetahui,</p>
      <p style="font-size:10pt;font-weight:bold">Owner Toko Victory</p>
      <div class="ttd-line">( _________________________ )</div>
    </div>
  </div>
  <script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 400); });</script>
</body>
</html>
    <?php
}

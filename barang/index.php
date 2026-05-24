<?php
/* === barang/index.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'barang';
$page_title = 'Daftar Barang';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$total = (int) $pdo->query('SELECT COUNT(*) FROM barang')->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$sql = "SELECT b.*, k.nama_kategori, m.nama_merek,
        COALESCE(k.nama_kategori, b.kategori) AS kategori_tampil
        FROM barang b
        LEFT JOIN kategori k ON b.id_kategori = k.id
        LEFT JOIN merek m ON b.id_merek = m.id
        ORDER BY b.id DESC LIMIT $perPage OFFSET $offset";
$rows = $pdo->query($sql)->fetchAll();

ob_start();
?>
<div class="page-head">
  <div class="page-head-main">
    <h2><i class="ti ti-package"></i> Daftar Barang</h2>
    <p class="page-head-desc">Kelola master data barang, stok, dan reorder point</p>
  </div>
  <a href="form.php" class="btn-primary"><i class="ti ti-plus"></i> Tambah Barang</a>
</div>
<div class="search-box">
  <i class="ti ti-search"></i>
  <input type="search" id="searchTable" placeholder="Cari barang...">
</div>
<?php if (empty($rows)): ?>
<div class="card-table empty-state">
  <i class="ti ti-package-off"></i>
  <p>Belum ada data barang. Tambah sekarang.</p>
  <a href="form.php" class="btn-primary" style="margin-top:12px">+ Tambah Barang</a>
</div>
<?php else: ?>
<div class="card-table">
  <table id="dataTable">
    <thead>
      <tr>
        <th>No</th><th>Nama Barang</th><th>Kategori</th><th>Merek</th>
        <th>Harga</th><th>Stok</th><th>ROP</th><th>Status</th><th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r):
        $stok = (int) $r['stok_saat_ini'];
        $rop = max(1, (int) $r['rop']);
        $status = getStokStatus($stok, $rop);
        $tagClass = $status['class'] === 'kritis' ? 'tag-red' : ($status['class'] === 'menipis' ? 'tag-amber' : 'tag-green');
      ?>
      <tr>
        <td><?= $offset + $i + 1 ?></td>
        <td><?= h($r['nama_barang']) ?></td>
        <td><?= h($r['kategori_tampil'] ?? '-') ?></td>
        <td><?= h($r['nama_merek'] ?? '-') ?></td>
        <td class="mono">Rp <?= number_format((float)($r['harga'] ?? 0), 0, ',', '.') ?></td>
        <td><?= $stok ?></td>
        <td><?= $rop ?></td>
        <td><span class="tag <?= $tagClass ?>"><?= h($status['label']) ?></span></td>
        <td>
          <div class="actions">
            <a href="form.php?id=<?= (int)$r['id'] ?>" class="btn-icon edit" title="Edit"><i class="ti ti-pencil"></i></a>
            <form method="post" action="hapus.php" style="display:inline" onsubmit="return confirmDelete('Yakin ingin menghapus barang ini?')">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="btn-icon del" title="Hapus"><i class="ti ti-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">Prev</a><?php endif; ?>
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a href="?page=<?= $p ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>">Next</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<script>liveSearch('searchTable','dataTable');</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

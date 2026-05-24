<?php
/* === pengguna/index.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'pengguna';
$page_title = 'Daftar Pengguna';
$selfId = (int) $_SESSION['user']['id'];

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$total = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$rows = $pdo->query("SELECT * FROM users ORDER BY id DESC LIMIT $perPage OFFSET $offset")->fetchAll();

ob_start();
?>
<div class="page-head">
  <h2>Daftar Pengguna</h2>
  <a href="form.php" class="btn-primary"><i class="ti ti-plus"></i> Tambah Pengguna</a>
</div>
<div class="search-box"><i class="ti ti-search"></i><input type="search" id="searchTable" placeholder="Cari pengguna..."></div>
<div class="card-table">
  <table id="dataTable">
    <thead><tr><th>No</th><th>Nama</th><th>Email</th><th>Telepon</th><th>Role</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $i => $r):
        $role = $r['role'] ?? 'karyawan';
        $status = $r['status'] ?? 'aktif';
      ?>
      <tr>
        <td><?= $offset + $i + 1 ?></td>
        <td><?= h($r['name']) ?></td>
        <td><?= h($r['email']) ?></td>
        <td><?= h($r['no_telepon'] ?? '-') ?></td>
        <td><span class="tag <?= $role === 'owner' ? 'tag-blue' : ($role === 'admin' ? 'tag-amber' : 'tag-green') ?>"><?= h(ucfirst($role)) ?></span></td>
        <td><span class="tag <?= $status === 'aktif' ? 'tag-green' : 'tag-gray' ?>"><?= h(ucfirst($status)) ?></span></td>
        <td><div class="actions">
          <a href="form.php?id=<?= (int)$r['id'] ?>" class="btn-icon edit" title="Edit"><i class="ti ti-pencil"></i></a>
          <?php if ((int)$r['id'] !== $selfId): ?>
          <form method="post" action="hapus.php" style="display:inline" onsubmit="return confirmDelete('Yakin hapus pengguna ini?')">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button type="submit" class="btn-icon del" title="Hapus"><i class="ti ti-trash"></i></button>
          </form>
          <?php endif; ?>
        </div></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($totalPages > 1): ?>
  <div class="pagination"><?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="?page=<?= $p ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
  <?php endfor; ?></div>
  <?php endif; ?>
</div>
<script>liveSearch('searchTable','dataTable');</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

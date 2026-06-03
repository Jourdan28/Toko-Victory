<?php
/* === supplier/index.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();

$BASE = rtrim(appBasePath(), '/');
$active_menu = 'supplier';
$page_title = 'Daftar Supplier';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$total = (int) $pdo->query('SELECT COUNT(*) FROM supplier')->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$stmt = $pdo->query(
    "SELECT s.*, COALESCE(s.nama_supplier, s.nama) AS nama_tampil,
     (SELECT COUNT(*) FROM barang b WHERE b.id_supplier = s.id) AS jumlah_barang
     FROM supplier s ORDER BY s.id DESC LIMIT $perPage OFFSET $offset"
);
$rows = $stmt->fetchAll();

ob_start();
?>
<div class="page-head">
  <h2>Daftar Supplier</h2>
  <a href="form.php" class="btn-primary"><i class="ti ti-plus"></i> Tambah Supplier</a>
</div>
<div class="search-box"><i class="ti ti-search"></i><input type="search" id="searchTable" placeholder="Cari supplier..."></div>
<?php if (empty($rows)): ?>
<div class="card-table empty-state"><i class="ti ti-truck-off"></i><p>Belum ada data supplier.</p><a href="form.php" class="btn-primary">+ Tambah Supplier</a></div>
<?php else: ?>
<div class="card-table">
  <table id="dataTable">
    <thead><tr><th>No</th><th>Nama</th><th>Telepon</th><th>Alamat</th><th>Barang</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $i => $r): ?>
      <tr>
        <td><?= $offset + $i + 1 ?></td>
        <td><?= h($r['nama_tampil']) ?></td>
        <td><?= h($r['no_telepon'] ?? $r['kontak'] ?? '-') ?></td>
        <td><?= h(mb_strimwidth($r['alamat'] ?? '-', 0, 50, '...')) ?></td>
        <td class="td-barang-supplier">
          <span class="barang-count"><?= (int)$r['jumlah_barang'] ?> barang</span>
          <?php if ($r['jumlah_barang'] > 0): ?>
          <button type="button" class="btn-lihat-barang" data-id="<?= (int)$r['id'] ?>" data-nama="<?= h($r['nama_tampil']) ?>" data-count="<?= (int)$r['jumlah_barang'] ?>">
            <i class="ti ti-list-details"></i> Lihat
          </button>
          <?php endif; ?>
        </td>
        <td><div class="actions">
          <a href="form.php?id=<?= (int)$r['id'] ?>" class="btn-icon edit" title="Edit"><i class="ti ti-pencil"></i></a>
          <form method="post" action="hapus.php" style="display:inline" onsubmit="return confirmDelete('Yakin hapus supplier ini?')">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button type="submit" class="btn-icon del" title="Hapus"><i class="ti ti-trash"></i></button>
          </form>
        </div></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="?page=<?= $p ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<div class="modal-overlay modal-supplier-barang" id="modalBarang" aria-hidden="true">
  <div class="modal-box" role="dialog" aria-labelledby="modalBarangTitle">
    <div class="modal-head">
      <div>
        <h3 id="modalBarangTitle">Barang Supplier</h3>
        <p class="modal-sub" id="modalBarangSub"></p>
      </div>
      <button type="button" class="btn-notif-close" id="closeModalBarang" aria-label="Tutup"><i class="ti ti-x"></i></button>
    </div>
    <div class="modal-body">
      <div class="supplier-barang-loading" id="modalBarangLoading">
        <i class="ti ti-loader-2"></i> Memuat daftar barang...
      </div>
      <ul class="supplier-barang-list" id="modalBarangList" hidden></ul>
      <p class="supplier-barang-empty" id="modalBarangEmpty" hidden>Tidak ada barang terhubung ke supplier ini.</p>
    </div>
    <div class="modal-foot">
      <a href="<?= $BASE ?>/barang/index.php" class="btn-outline btn-sm-link" id="modalBarangKeDaftar"><i class="ti ti-package"></i> Kelola Barang</a>
      <button type="button" class="btn-outline" id="btnTutupModalBarang">Tutup</button>
    </div>
  </div>
</div>
<script>
runPageInit(function () {
  liveSearch('searchTable', 'dataTable');

  const modal = document.getElementById('modalBarang');
  const listEl = document.getElementById('modalBarangList');
  const loadingEl = document.getElementById('modalBarangLoading');
  const emptyEl = document.getElementById('modalBarangEmpty');
  const titleEl = document.getElementById('modalBarangTitle');
  const subEl = document.getElementById('modalBarangSub');
  const base = '<?= $BASE ?>';

  if (modal.parentElement && modal.parentElement !== document.body) {
    document.body.appendChild(modal);
  }

  function closeModal() {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function openModal(btn) {
    const id = btn.dataset.id;
    const nama = btn.dataset.nama || 'Supplier';
    const count = btn.dataset.count || '0';
    titleEl.textContent = nama;
    subEl.textContent = count + ' barang terhubung';
    listEl.hidden = true;
    listEl.style.display = 'none';
    emptyEl.hidden = true;
    emptyEl.style.display = 'none';
    loadingEl.hidden = false;
    loadingEl.style.display = '';
    listEl.innerHTML = '';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    fetch(base + '/api/supplier_barang.php?id=' + encodeURIComponent(id))
      .then((r) => r.json())
      .then((data) => {
        loadingEl.hidden = true;
        loadingEl.style.display = 'none';
        const items = data.items || [];
        if (!items.length) {
          emptyEl.hidden = false;
          emptyEl.style.display = '';
          return;
        }
        listEl.innerHTML = items.map((b) => {
          const namaBarang = esc(b.nama_barang || '-');
          const kat = esc(b.kategori || 'Umum');
          const stok = parseInt(b.stok_saat_ini, 10) || 0;
          const bid = parseInt(b.id, 10);
          return `<li class="supplier-barang-item">
            <span class="supplier-barang-icon"><i class="ti ti-box"></i></span>
            <div class="supplier-barang-info">
              <strong>${namaBarang}</strong>
              <span class="supplier-barang-meta">${kat} · Stok ${stok.toLocaleString('id-ID')} unit</span>
            </div>
            <a href="${base}/barang/form.php?id=${bid}" class="btn-outline btn-xs" title="Edit barang"><i class="ti ti-pencil"></i></a>
          </li>`;
        }).join('');
        listEl.hidden = false;
        listEl.style.display = '';
      })
      .catch(() => {
        loadingEl.hidden = true;
        loadingEl.style.display = 'none';
        emptyEl.textContent = 'Gagal memuat data. Coba lagi.';
        emptyEl.hidden = false;
        emptyEl.style.display = '';
      });
  }

  document.querySelectorAll('.btn-lihat-barang').forEach((btn) => {
    btn.addEventListener('click', () => openModal(btn));
  });
  document.getElementById('closeModalBarang').addEventListener('click', closeModal);
  document.getElementById('btnTutupModalBarang').addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
  });
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../includes/layout.php';

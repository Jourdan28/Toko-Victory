<?php
require_once __DIR__ . '/config.php';

requireLogin();

$user = $_SESSION['user'];
$role = $user['role'] ?? 'karyawan';
$isOwner = isFullAccess($role);
$nama = $user['nama'] ?? 'Pengguna';
$userId = (int) $user['id'];

$pdo = getDbConnection();

syncBarangRopDefaults($pdo);
backfillInitialStockTransactions($pdo);

$total_stok = (int) $pdo->query('SELECT COALESCE(SUM(stok_saat_ini), 0) FROM barang')->fetchColumn();
$stok_aman = (int) $pdo->query(
    'SELECT COUNT(*) FROM barang WHERE stok_saat_ini > GREATEST(rop, 1)'
)->fetchColumn();

$count_menipis = (int) $pdo->query(
    'SELECT COUNT(*) FROM barang WHERE stok_saat_ini <= GREATEST(rop, 1)'
)->fetchColumn();

$stmtMenipis = $pdo->query(
    'SELECT * FROM barang WHERE stok_saat_ini <= GREATEST(rop, 1) ORDER BY (stok_saat_ini / GREATEST(rop,1)) ASC, stok_saat_ini ASC'
);
$stok_menipis = $stmtMenipis->fetchAll();

$transaksi = $pdo->query(
    'SELECT t.*, b.nama_barang, b.kategori
     FROM transaksi t
     JOIN barang b ON t.id_barang = b.id
     ORDER BY t.created_at DESC LIMIT 5'
)->fetchAll();

$trx_hari_ini = 0;
$trx_masuk_hari = 0;
$aktivitas = [];

if ($isOwner) {
    $trx_hari_ini = (int) $pdo->query(
        'SELECT COUNT(*) FROM transaksi WHERE DATE(created_at) = CURDATE()'
    )->fetchColumn();

    $aktivitas = $pdo->query(
        'SELECT * FROM log_aktivitas ORDER BY created_at DESC LIMIT 8'
    )->fetchAll();
} else {
    $trx_masuk_hari = (int) $pdo->query(
        "SELECT COALESCE(SUM(jumlah), 0) FROM transaksi
         WHERE jenis = 'masuk' AND DATE(created_at) = CURDATE()"
    )->fetchColumn();
}

$stmtAllTrx = $pdo->query(
    'SELECT t.*, b.nama_barang FROM transaksi t
     JOIN barang b ON t.id_barang = b.id
     ORDER BY t.created_at DESC LIMIT 20'
);
$all_transaksi = $stmtAllTrx->fetchAll();

$roleBadgeClass = match ($role) {
    'owner' => 'badge-blue',
    'admin' => 'badge-amber',
    default => 'badge-green',
};
$roleLabel = ucfirst($role);
$initials = itemInitials($nama);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <?php
  $tvBase = rtrim(appBasePath(), '/');
  $tvTitle = 'Dashboard — Toko Victory';
  include __DIR__ . '/includes/head.php';
  ?>
</head>
<body class="app-body dashboard-page">
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  <div class="search-backdrop" id="searchBackdrop"></div>

  <div class="app-shell layout">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-logo">
        <div class="logo-box">VY</div>
        <div class="logo-text">Toko Victory<span>Inventori</span></div>
      </div>
      <nav class="nav">
        <a href="<?= rtrim(appBasePath(), '/') ?>/dashboard.php" class="active"><i class="ti ti-layout-dashboard"></i><span>Dashboard</span></a>
        <?php $bp = rtrim(appBasePath(), '/'); ?>
        <?php if ($isOwner): ?>
        <a href="<?= $bp ?>/barang/index.php"><i class="ti ti-package"></i><span>Daftar barang</span></a>
        <a href="<?= $bp ?>/supplier/index.php"><i class="ti ti-truck"></i><span>Supplier</span></a>
        <a href="<?= $bp ?>/pengguna/index.php"><i class="ti ti-users"></i><span>Pengguna</span></a>
        <div class="nav-section">Master data</div>
        <button type="button" class="nav-toggle" id="masterToggle"><i class="ti ti-database"></i><span>Master data</span><i class="ti ti-chevron-down" style="margin-left:auto;font-size:14px"></i></button>
        <div class="nav-sub" id="masterSub">
          <a href="<?= $bp ?>/referensi/kategori.php">Kategori</a>
          <a href="<?= $bp ?>/referensi/satuan.php">Satuan</a>
          <a href="<?= $bp ?>/referensi/warna.php">Warna</a>
          <a href="<?= $bp ?>/referensi/merek.php">Merek</a>
          <a href="<?= $bp ?>/referensi/lokasi.php">Lokasi</a>
        </div>
        <a href="<?= $bp ?>/pemesanan/index.php"><i class="ti ti-shopping-cart"></i><span>Pemesanan</span></a>
        <?php endif; ?>
        <a href="<?= $bp ?>/transaksi/index.php"><i class="ti ti-arrows-exchange"></i><span>Transaksi</span></a>
        <a href="<?= $bp ?>/laporan/index.php"><i class="ti ti-report-analytics"></i><span>Laporan</span></a>
      </nav>
      <div class="sidebar-user">
        <div class="user-row">
          <div class="avatar"><?= h($initials) ?></div>
          <div class="user-info">
            <div class="name"><?= h($nama) ?></div>
            <span class="badge <?= $roleBadgeClass ?>"><?= h($roleLabel) ?></span>
          </div>
        </div>
        <a href="<?= rtrim(appBasePath(), '/') ?>/logout.php" class="btn-logout"><i class="ti ti-logout"></i> Keluar</a>
      </div>
    </aside>

    <div class="main">
      <header class="app-header header">
        <button type="button" class="icon-btn hamburger" id="btnMenu" aria-label="Menu"><i class="ti ti-menu-2"></i></button>
        <div class="header-titles">
          <div class="breadcrumb"><i class="ti ti-layout-dashboard"></i> Toko Victory <span class="breadcrumb-sep">/</span> <strong>Dashboard</strong></div>
        </div>
        <div class="header-right">
          <div class="search-wrap">
            <i class="ti ti-search"></i>
            <input type="search" id="globalSearch" placeholder="Cari barang, lokasi..." autocomplete="off">
            <div class="search-dropdown" id="searchDropdown"></div>
          </div>
          <div class="header-avatar" title="<?= h($nama) ?>"><?= h($initials) ?></div>
        </div>
      </header>

      <div class="app-content content">
        <div class="dashboard-intro animate-fade-up">
          <div class="dashboard-intro-text">
            <h2>Selamat datang, <?= h($nama) ?></h2>
            <p><?= date('l, d F Y') ?> · Ringkasan inventori hari ini</p>
          </div>
          <span class="badge badge-green badge-pulse"><i class="ti ti-activity"></i> Live</span>
        </div>
        <?php if ($isOwner): ?>
        <div class="stats stats-4">
          <div class="stat-card accent-blue stagger-1" data-count="<?= $total_stok ?>">
            <div class="stat-top"><div class="stat-icon blue"><i class="ti ti-building-warehouse"></i></div></div>
            <div class="stat-value count-up">0</div>
            <div class="stat-label">Total stok</div>
            <div class="stat-sub">unit semua lokasi</div>
          </div>
          <div class="stat-card accent-green stagger-2" data-count="<?= $stok_aman ?>">
            <div class="stat-top"><div class="stat-icon green"><i class="ti ti-shield-check"></i></div></div>
            <div class="stat-value count-up">0</div>
            <div class="stat-label">Stok aman</div>
            <div class="stat-sub">di atas batas ROP</div>
          </div>
          <div class="stat-card accent-red stagger-3" data-count="<?= $count_menipis ?>">
            <div class="stat-top"><div class="stat-icon red"><i class="ti ti-alert-triangle"></i></div></div>
            <div class="stat-value count-up">0</div>
            <div class="stat-label">Stok menipis<?php if ($count_menipis > 0): ?><span class="pulse-badge"></span><?php endif; ?></div>
            <div class="stat-sub">perlu restock segera</div>
          </div>
          <div class="stat-card accent-purple stagger-4" data-count="<?= $trx_hari_ini ?>">
            <div class="stat-top"><div class="stat-icon purple"><i class="ti ti-arrows-exchange"></i></div></div>
            <div class="stat-value count-up">0</div>
            <div class="stat-label">Transaksi hari ini</div>
            <div class="stat-sub">total transaksi hari ini</div>
          </div>
        </div>
        <?php else: ?>
        <div class="stats stats-3">
          <div class="stat-card accent-red stagger-1" data-count="<?= $count_menipis ?>">
            <div class="stat-top"><div class="stat-icon red"><i class="ti ti-alert-triangle"></i></div></div>
            <div class="stat-value count-up">0</div>
            <div class="stat-label">Stok menipis</div>
          </div>
          <div class="stat-card accent-green stagger-2" data-count="<?= $trx_masuk_hari ?>">
            <div class="stat-top"><div class="stat-icon green"><i class="ti ti-arrow-bar-to-down"></i></div></div>
            <div class="stat-value count-up">0</div>
            <div class="stat-label">Transaksi masuk hari ini</div>
          </div>
          <div class="stat-card accent-blue stagger-3" data-count="<?= $total_stok ?>">
            <div class="stat-top"><div class="stat-icon blue"><i class="ti ti-box"></i></div></div>
            <div class="stat-value count-up">0</div>
            <div class="stat-label">Total stok</div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($count_menipis > 0): ?>
        <?php
        $hasKritis = false;
        foreach ($stok_menipis as $bx) {
            if ((int) $bx['stok_saat_ini'] <= 0) {
                $hasKritis = true;
                break;
            }
        }
        ?>
        <div class="alert-stok <?= $hasKritis ? 'kritis' : '' ?>">
          <i class="ti ti-alert-triangle" style="font-size:22px;color:var(--amber)"></i>
          <div>
            <strong>Peringatan: <?= $count_menipis ?> barang perlu restock</strong>
            <p>Stok di bawah atau sama dengan batas ROP (Reorder Point).<?php if ($isOwner): ?> Segera tambah stok atau buat pemesanan.<?php else: ?> Segera catat barang masuk di menu Transaksi.<?php endif; ?></p>
          </div>
          <?php if ($isOwner): ?>
          <a href="<?= rtrim(appBasePath(), '/') ?>/barang/index.php" class="btn btn-primary btn-cta"><i class="ti ti-package"></i> Kelola Barang</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="panels-mid">
          <div class="panel panel-scroll stagger-2">
            <div class="panel-head">
              <h2>Stok menipis
                <span class="badge-count <?= $count_menipis > 0 ? 'pulse-red' : '' ?>"><?= $count_menipis ?></span>
              </h2>
              <a href="<?= rtrim(appBasePath(), '/') ?>/<?= $isOwner ? 'barang/index.php' : 'transaksi/index.php' ?>" class="btn-link">Lihat semua →</a>
            </div>
            <div class="panel-body panel-body-scroll">
              <?php if (empty($stok_menipis)): ?>
                <p class="empty-ok"><i class="ti ti-circle-check"></i> Semua stok aman ✓</p>
              <?php else: ?>
                <?php foreach ($stok_menipis as $b):
                  $rop = max(1, (int)$b['rop']);
                  $stok = (int)$b['stok_saat_ini'];
                  $status = getStokStatus($stok, $rop);
                  $pct = min(100, round(($stok / $rop) * 100));
                  $barColor = $status['level'] >= 3 ? 'var(--red)' : ($status['level'] >= 2 ? 'var(--amber)' : 'var(--green)');
                  $color = itemColor($b['nama_barang']);
                  $rowClass = $status['class'] === 'kritis' ? 'row-kritis' : ($status['class'] === 'menipis' ? 'row-menipis' : '');
                ?>
                <div class="row-item <?= $rowClass ?>">
                  <div class="item-box" style="background:<?= h($color) ?>"><?= h(itemInitials($b['nama_barang'])) ?></div>
                  <div class="row-main">
                    <div class="row-title">
                      <?= h($b['nama_barang']) ?>
                      <span class="warn-pill <?= h($status['class']) ?>"><?= h($status['label']) ?></span>
                    </div>
                    <div class="row-meta">ROP: <?= $rop ?> · <?= h($b['kategori'] ?? 'Umum') ?></div>
                    <div class="progress-wrap"><div class="progress-bar" style="background:<?= h($barColor) ?>" data-width="<?= $pct ?>%"></div></div>
                  </div>
                  <div class="row-right">
                    <div class="row-qty mono"><?= $stok ?> / <?= $rop ?></div>
                    <?php if ($isOwner): ?>
                    <a href="<?= rtrim(appBasePath(), '/') ?>/barang/form.php?id=<?= (int)$b['id'] ?>" class="btn-sm">Restock</a>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="panel panel-scroll stagger-3">
            <div class="panel-head">
              <h2>Transaksi terbaru</h2>
              <select class="filter-select" id="trxFilter">
                <option value="all">Semua</option>
                <option value="masuk">Masuk</option>
                <option value="keluar">Keluar</option>
              </select>
            </div>
            <div class="panel-body panel-body-scroll" id="trxList">
              <?php foreach ($transaksi as $t): ?>
              <div class="row-item trx-row" data-jenis="<?= h($t['jenis']) ?>">
                <div class="trx-icon <?= h($t['jenis']) ?>">
                  <i class="ti ti-arrow-<?= $t['jenis'] === 'masuk' ? 'down' : 'up' ?>"></i>
                </div>
                <div class="row-main">
                  <div class="row-title"><?= h($t['nama_barang']) ?></div>
                  <div class="row-meta"><?= h($t['keterangan'] ?? '-') ?></div>
                </div>
                <div class="row-right">
                  <div class="trx-amount <?= $t['jenis'] === 'masuk' ? 'plus' : 'minus' ?> mono">
                    <?= $t['jenis'] === 'masuk' ? '+' : '-' ?><?= (int)$t['jumlah'] ?> unit
                  </div>
                  <div class="row-meta"><?= h(timeAgo($t['created_at'])) ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <?php if ($isOwner): ?>
        <div class="panels-bottom owner">
          <div class="panel stagger-4">
            <div class="panel-head">
              <h2>Aktivitas pengguna</h2>
              <span class="live-badge"><span class="live-dot"></span> Live</span>
            </div>
            <div class="timeline">
              <?php
              $aksiIcon = [
                'login' => ['ti-key', 'login'],
                'tambah' => ['ti-plus', 'tambah'],
                'edit' => ['ti-pencil', 'edit'],
                'hapus' => ['ti-trash', 'hapus'],
                'transaksi' => ['ti-arrows-exchange', 'transaksi'],
                'logout' => ['ti-logout', 'login'],
              ];
              foreach ($aktivitas as $a):
                $ic = $aksiIcon[$a['aksi']] ?? ['ti-point', 'edit'];
              ?>
              <div class="tl-item">
                <div class="tl-dot <?= h($ic[1]) ?>"><i class="ti <?= h($ic[0]) ?>"></i></div>
                <div>
                  <div class="tl-text"><strong><?= h($a['nama_user']) ?></strong> <?= h($a['keterangan']) ?></div>
                  <div class="tl-time"><?= h(formatActivityTime($a['created_at'])) ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <div style="padding:0 20px 16px"><button type="button" class="btn-link">Lihat semua →</button></div>
          </div>
        </div>
        <?php else: ?>
        <div class="panels-bottom karyawan">
          <div class="panel panel-scroll">
            <div class="panel-head">
              <h2>Ringkasan transaksi</h2>
              <span class="badge-count" style="background:var(--blue-bg);color:var(--blue)"><?= date('d M Y') ?></span>
            </div>
            <div class="panel-body panel-body-scroll">
            <table class="data-table">
              <thead>
                <tr><th>No</th><th>Nama Barang</th><th>Jenis</th><th>Jumlah</th><th>Waktu</th></tr>
              </thead>
              <tbody id="karyawanTable">
                <?php foreach (array_slice($all_transaksi, 0, 5) as $i => $t): ?>
                <tr class="k-trx-row">
                  <td><?= $i + 1 ?></td>
                  <td><?= h($t['nama_barang']) ?></td>
                  <td><span class="tag <?= h($t['jenis']) ?>"><?= h(ucfirst($t['jenis'])) ?></span></td>
                  <td class="mono"><?= (int)$t['jumlah'] ?></td>
                  <td><?= h(timeAgo($t['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div class="pagination">
              <button type="button" class="btn-link" id="btnNextTrx">Selanjutnya →</button>
            </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    const allTrxRows = <?= json_encode(array_map(function ($t, $i) {
        return [
            'no' => $i + 1,
            'nama' => $t['nama_barang'],
            'jenis' => $t['jenis'],
            'jumlah' => (int) $t['jumlah'],
            'waktu' => timeAgo($t['created_at']),
        ];
    }, $all_transaksi, array_keys($all_transaksi)), JSON_UNESCAPED_UNICODE) ?>;

    /* Count-up */
    document.querySelectorAll('.stat-card').forEach(card => {
      const target = parseInt(card.dataset.count || '0', 10);
      const el = card.querySelector('.count-up');
      const start = performance.now();
      const dur = 800;
      function tick(now) {
        const p = Math.min(1, (now - start) / dur);
        const ease = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(target * ease).toLocaleString('id-ID');
        if (p < 1) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
    });

    /* Progress bars */
    document.querySelectorAll('.progress-bar').forEach(bar => {
      requestAnimationFrame(() => { bar.style.width = bar.dataset.width || '0%'; });
    });

    /* Transaksi filter */
    document.getElementById('trxFilter')?.addEventListener('change', e => {
      const v = e.target.value;
      document.querySelectorAll('.trx-row').forEach(row => {
        row.style.display = (v === 'all' || row.dataset.jenis === v) ? '' : 'none';
      });
    });

    /* Karyawan pagination */
    let trxPage = 0;
    const pageSize = 5;
    document.getElementById('btnNextTrx')?.addEventListener('click', () => {
      trxPage++;
      const start = trxPage * pageSize;
      const slice = allTrxRows.slice(start, start + pageSize);
      const tbody = document.getElementById('karyawanTable');
      if (!slice.length) { trxPage--; return; }
      tbody.innerHTML = slice.map((r, i) => `
        <tr><td>${start + i + 1}</td><td>${escapeHtml(r.nama)}</td>
        <td><span class="tag ${r.jenis}">${r.jenis.charAt(0).toUpperCase()+r.jenis.slice(1)}</span></td>
        <td class="mono">${r.jumlah}</td><td>${escapeHtml(r.waktu)}</td></tr>`).join('');
    });
    function escapeHtml(s) {
      const d = document.createElement('div');
      d.textContent = s;
      return d.innerHTML;
    }

    /* Search */
    const searchInput = document.getElementById('globalSearch');
    const searchDropdown = document.getElementById('searchDropdown');
    const searchBackdrop = document.getElementById('searchBackdrop');
    let searchTimer, activeIdx = -1, searchResults = [];

    searchInput?.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(async () => {
        const q = searchInput.value.trim();
        if (q.length < 1) { closeSearch(); return; }
        const res = await fetch('search.php?q=' + encodeURIComponent(q));
        const data = await res.json();
        searchResults = data.results || [];
        activeIdx = -1;
        renderSearch();
        openSearch();
      }, 300);
    });

    function renderSearch() {
      if (!searchResults.length) {
        searchDropdown.innerHTML = '<div class="search-item" style="color:var(--text-muted)">Tidak ada hasil</div>';
        return;
      }
      searchDropdown.innerHTML = searchResults.map((r, i) => `
        <div class="search-item${i===activeIdx?' active':''}" data-idx="${i}">
          <i class="ti ti-${r.icon==='box'?'package':r.icon==='truck'?'truck':'user'}"></i>
          <div><div>${escapeHtml(r.title)}</div><div class="meta">${escapeHtml(r.meta)}</div></div>
        </div>`).join('');
      searchDropdown.querySelectorAll('.search-item').forEach(el => {
        el.addEventListener('click', () => { window.location.href = el.dataset.idx ? searchResults[el.dataset.idx]?.url || '#' : '#'; });
      });
    }

    function openSearch() { searchDropdown.classList.add('open'); searchBackdrop.classList.add('open'); }
    function closeSearch() { searchDropdown.classList.remove('open'); searchBackdrop.classList.remove('open'); }
    searchBackdrop?.addEventListener('click', closeSearch);

    document.addEventListener('keydown', e => {
      if (e.key === '/' && document.activeElement !== searchInput) { e.preventDefault(); searchInput?.focus(); }
      if (e.key === 'Escape') {
        if (searchDropdown.classList.contains('open')) { closeSearch(); searchInput?.blur(); return; }
      }
      if (!searchDropdown.classList.contains('open')) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(activeIdx + 1, searchResults.length - 1); renderSearch(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); renderSearch(); }
      if (e.key === 'Enter' && activeIdx >= 0) { window.location.href = searchResults[activeIdx]?.url || '#'; }
    });

    /* Sidebar mobile */
    const sidebar = document.getElementById('sidebar');
    document.getElementById('btnMenu')?.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      document.getElementById('sidebarOverlay').classList.toggle('open');
    });
    document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
      sidebar.classList.remove('open');
      document.getElementById('sidebarOverlay').classList.remove('open');
    });
    document.getElementById('masterToggle')?.addEventListener('click', () => {
      document.getElementById('masterSub').classList.toggle('open');
    });
  </script>
<?php include __DIR__ . '/includes/app_scripts.php'; ?>
</body>
</html>

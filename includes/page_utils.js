/* === includes/page_utils.js — util halaman (load di head) === */
function confirmDelete(msg) {
  return confirm(msg || 'Yakin ingin menghapus data ini?');
}

function liveSearch(inputId, tableId) {
  const inp = document.getElementById(inputId);
  const tbl = document.getElementById(tableId);
  if (!inp || !tbl) return;
  inp.addEventListener('input', () => {
    const q = inp.value.toLowerCase();
    tbl.querySelectorAll('tbody tr').forEach((tr) => {
      if (tr.querySelector('.empty-state')) return;
      tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

/** Filter baris tabel berdasarkan data-* attribute (tanpa pindah halaman) */
function initTableTabs(tabRootId, attrName, options) {
  const root = document.getElementById(tabRootId);
  const tableId = options?.tableId || 'dataTable';
  const searchInputId = options?.searchInputId || 'searchTable';
  const tbl = document.getElementById(tableId);
  if (!root || !tbl) return;

  let activeVal = options?.initial || 'all';
  const syncSelect = options?.syncSelect || null;

  function rowMatchesTab(tr) {
    if (tr.querySelector('.empty-state')) return false;
    const rowVal = tr.getAttribute('data-' + attrName);
    return activeVal === 'all' || rowVal === activeVal;
  }

  function applyFilters() {
    const q = (document.getElementById(searchInputId)?.value || '').toLowerCase();
    let visible = 0;
    tbl.querySelectorAll('tbody tr').forEach((tr) => {
      if (tr.querySelector('.empty-state')) return;
      const tabOk = rowMatchesTab(tr);
      const textOk = !q || tr.textContent.toLowerCase().includes(q);
      const show = tabOk && textOk;
      tr.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    const hint = document.getElementById(tabRootId + '_hint');
    if (hint) {
      const labels = { all: 'semua transaksi', masuk: 'barang masuk', keluar: 'barang keluar' };
      hint.textContent = visible + ' data ' + (labels[activeVal] || activeVal);
    }
    return visible;
  }

  function setActive(val) {
    activeVal = val;
    root.querySelectorAll('button[data-' + attrName + ']').forEach((b) => {
      b.classList.toggle('active', b.getAttribute('data-' + attrName) === val);
    });
    if (syncSelect) {
      syncSelect.value = val === 'all' ? '' : val;
    }
    applyFilters();
  }

  root.querySelectorAll('button[data-' + attrName + ']').forEach((btn) => {
    btn.type = 'button';
    btn.style.cursor = 'pointer';
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      setActive(btn.getAttribute('data-' + attrName));
    });
  });

  const searchInp = document.getElementById(searchInputId);
  if (searchInp) {
    searchInp.addEventListener('input', applyFilters);
  }

  if (syncSelect) {
    syncSelect.addEventListener('change', () => {
      setActive(syncSelect.value || 'all');
    });
  }

  const urlVal = new URLSearchParams(window.location.search).get(attrName);
  if (urlVal) setActive(urlVal);
  else setActive(activeVal);

  return { setActive, applyFilters };
}

function runPageInit(fn) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fn);
  } else {
    fn();
  }
}

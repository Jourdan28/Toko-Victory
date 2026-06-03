<?php
/** Data barang JSON untuk dropdown search — set $barangList sebelum include */
if (!isset($barangList)) {
    $barangList = [];
}
?>
<script>
const barangData = <?= json_encode(array_values($barangList), JSON_UNESCAPED_UNICODE) ?>;
const basePath = '<?= rtrim(appBasePath(), '/') ?>';

function initBarangSelect(opts) {
  const { inputId, hiddenId, infoId, onSelect, onClear } = opts;
  const input = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenId);
  const list = document.getElementById(inputId + '_list');
  const info = infoId ? document.getElementById(infoId) : null;
  const wrap = input?.closest('.search-select-wrap');
  let clearBtn = wrap?.querySelector('.search-select-clear');
  if (wrap && !clearBtn) {
    clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'search-select-clear';
    clearBtn.title = 'Hapus pilihan';
    clearBtn.setAttribute('aria-label', 'Hapus pilihan barang');
    clearBtn.innerHTML = '<i class="ti ti-x"></i>';
    wrap.appendChild(clearBtn);
  }

  function statusRank(b) {
    const st = b.status?.class || (b.stok_saat_ini <= b.rop ? 'menipis' : 'aman');
    if (st === 'kritis') return 0;
    if (st === 'menipis') return 1;
    return 2;
  }

  function sortBarang(items) {
    return items.slice().sort((a, b) => {
      const dr = statusRank(a) - statusRank(b);
      if (dr !== 0) return dr;
      return a.nama_barang.localeCompare(b.nama_barang, 'id');
    });
  }

  function renderList(q) {
    const qq = (q || '').toLowerCase().trim();
    const filtered = sortBarang(barangData.filter(b =>
      !qq || b.nama_barang.toLowerCase().includes(qq) || String(b.id).includes(qq)
    ));
    const limit = qq ? 30 : filtered.length;
    list.innerHTML = filtered.slice(0, limit).map(b => {
      const st = b.status?.class || (b.stok_saat_ini <= b.rop ? 'menipis' : 'aman');
      const rowClass = st === 'kritis' ? 'kritis' : (st === 'menipis' ? 'menipis' : '');
      return `<div data-id="${b.id}" class="${rowClass}">${b.nama_barang} (Stok: ${b.stok_saat_ini})</div>`;
    }).join('') || '<div style="padding:8px;color:var(--text-muted)">Tidak ada</div>';
    list.classList.add('open');
    list.querySelectorAll('div[data-id]').forEach(el => {
      el.onclick = () => pick(+el.dataset.id);
    });
  }

  function clearInfo() {
    if (info) {
      info.classList.remove('show');
      info.innerHTML = '';
    }
  }

  function updateClearBtn() {
    if (!clearBtn) return;
    clearBtn.hidden = !(hidden.value || input.value.trim());
  }

  function clearSelection() {
    hidden.value = '';
    input.value = '';
    input.classList.remove('input-error');
    clearInfo();
    list.classList.remove('open');
    updateClearBtn();
    if (onClear) onClear();
    input.focus();
    renderList('');
  }

  function tryAutoPick() {
    const q = input.value.trim().toLowerCase();
    if (!q) return false;
    const exact = barangData.find((b) => b.nama_barang.toLowerCase() === q);
    if (exact) {
      pick(exact.id);
      return true;
    }
    const partial = barangData.filter((b) => b.nama_barang.toLowerCase().includes(q));
    if (partial.length === 1) {
      pick(partial[0].id);
      return true;
    }
    return false;
  }

  function pick(id) {
    const b = barangData.find(x => x.id === id);
    if (!b) return;
    hidden.value = b.id;
    input.value = b.nama_barang;
    input.classList.remove('input-error');
    list.classList.remove('open');
    if (info) {
      const st = b.status || {};
      info.classList.add('show');
      info.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center">
          <strong>${b.nama_barang}</strong>
          <span class="tag tag-${st.class === 'kritis' ? 'red' : (st.class === 'menipis' ? 'amber' : 'green')}">${st.label || 'Aman'}</span>
        </div>
        <div class="grid3">
          <div>Stok: <strong>${b.stok_saat_ini}</strong></div>
          <div>ROP: <strong>${b.rop}</strong></div>
          <div>Safety: <strong>${b.safety_stock || 0}</strong></div>
        </div>
        ${b.supplier_nama ? '<div style="margin-top:6px;color:var(--text-muted)">Supplier: '+b.supplier_nama+'</div>' : ''}
        ${b.stok_saat_ini <= b.rop ? '<div class="warn-banner">⚠ Stok di bawah ROP</div>' : ''}`;
    }
    if (onSelect) onSelect(b);
    updateClearBtn();
  }

  clearBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    clearSelection();
  });

  input.addEventListener('focus', () => renderList(''));
  input.addEventListener('input', () => {
    const current = barangData.find((x) => String(x.id) === String(hidden.value));
    if (!current || input.value.trim() !== current.nama_barang) {
      hidden.value = '';
      clearInfo();
    }
    renderList(input.value);
    updateClearBtn();
  });
  list.addEventListener('mousedown', (e) => {
    if (e.target.closest('div[data-id]')) e.preventDefault();
  });
  input.addEventListener('blur', () => {
    setTimeout(() => {
      if (!hidden.value) tryAutoPick();
      list.classList.remove('open');
    }, 200);
  });
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      const first = list.querySelector('div[data-id]');
      if (first) pick(+first.dataset.id);
      else tryAutoPick();
    }
  });
  document.addEventListener('click', (e) => {
    if (!wrap?.contains(e.target)) list.classList.remove('open');
  });
  updateClearBtn();
  return { pick, clearSelection, tryAutoPick, input, hidden };
}
</script>

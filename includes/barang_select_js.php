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
  const { inputId, hiddenId, infoId, onSelect } = opts;
  const input = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenId);
  const list = document.getElementById(inputId + '_list');
  const info = infoId ? document.getElementById(infoId) : null;

  function renderList(q) {
    const qq = (q || '').toLowerCase();
    const filtered = barangData.filter(b =>
      b.nama_barang.toLowerCase().includes(qq) || String(b.id).includes(qq)
    );
    list.innerHTML = filtered.slice(0, 20).map(b => {
      const menipis = b.stok_saat_ini <= b.rop;
      return `<div data-id="${b.id}" class="${menipis ? 'menipis' : ''}">${b.nama_barang} (Stok: ${b.stok_saat_ini})</div>`;
    }).join('') || '<div style="padding:8px;color:var(--text-muted)">Tidak ada</div>';
    list.classList.add('open');
    list.querySelectorAll('div[data-id]').forEach(el => {
      el.onclick = () => pick(+el.dataset.id);
    });
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
  }

  input.addEventListener('focus', () => renderList(input.value));
  input.addEventListener('input', () => {
    const current = barangData.find((x) => String(x.id) === String(hidden.value));
    if (current && input.value.trim() === current.nama_barang) {
      renderList(input.value);
      return;
    }
    hidden.value = '';
    renderList(input.value);
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
    if (!input.contains(e.target) && !list.contains(e.target)) list.classList.remove('open');
  });
  return { pick, tryAutoPick, input, hidden };
}
</script>

<?php
/** Dropdown pilih barang — butuh $barangList (array) */
if (!isset($barangList)) {
    $barangList = [];
}
$selectId = $selectId ?? 'id_barang';
$infoId = $infoId ?? 'infoBarang';
?>
<div class="form-group">
  <label>Pilih Barang *</label>
  <select name="<?= h($selectId) ?>" id="<?= h($selectId) ?>" required class="select-barang">
    <option value="">— Pilih barang —</option>
    <?php foreach ($barangList as $b): ?>
    <option value="<?= (int)$b['id'] ?>" data-supplier="<?= (int)($b['id_supplier'] ?? 0) ?>">
      <?= h($b['nama_barang']) ?> — Stok: <?= (int)$b['stok_saat_ini'] ?> unit
    </option>
    <?php endforeach; ?>
  </select>
  <div class="info-card-barang" id="<?= h($infoId) ?>"></div>
</div>
<script>
const barangData = <?= json_encode(array_values($barangList), JSON_UNESCAPED_UNICODE) ?>;

function renderInfoBarang(b, infoId) {
  const info = document.getElementById(infoId || 'infoBarang');
  if (!info || !b) {
    if (info) { info.classList.remove('show'); info.innerHTML = ''; }
    return;
  }
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
    ${b.supplier_nama ? '<div style="margin-top:6px;color:var(--text-secondary)">Supplier: ' + b.supplier_nama + '</div>' : ''}
    ${b.stok_saat_ini <= b.rop ? '<div class="warn-banner">Stok di bawah ROP</div>' : ''}`;
}

function onBarangSelectChange(selectEl, infoId, onPick) {
  const id = selectEl.value;
  const b = barangData.find((x) => String(x.id) === String(id));
  renderInfoBarang(b, infoId);
  if (b && typeof onPick === 'function') onPick(b);
  return b;
}
</script>

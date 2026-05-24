<?php
/* Template view referensi — variabel: $label, $rows, $cfg, $table */
$fields = $cfg['fields'];
?>
<div class="page-head"><h2><?= h($label) ?></h2></div>
<div class="ref-layout">
  <div class="ref-form">
    <div class="form-card">
      <h3 style="font-size:14px;margin-bottom:16px" id="formTitle">Tambah <?= h($label) ?></h3>
      <form method="post" id="refForm">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="refId" value="0">
        <?php foreach ($fields as $fname => $meta):
          if ($fname === 'id') continue;
          $type = $meta['type'] ?? 'text';
        ?>
        <div class="form-group">
          <label><?= h($meta['label']) ?><?= !empty($meta['required']) ? ' *' : '' ?></label>
          <?php if ($type === 'color'): ?>
          <input type="color" name="<?= h($fname) ?>" id="f_<?= h($fname) ?>" value="#3b82f6">
          <?php elseif ($type === 'textarea'): ?>
          <textarea name="<?= h($fname) ?>" id="f_<?= h($fname) ?>" rows="3"></textarea>
          <?php else: ?>
          <input type="text" name="<?= h($fname) ?>" id="f_<?= h($fname) ?>" <?= !empty($meta['required']) ? 'required' : '' ?> placeholder="<?= h($meta['placeholder'] ?? '') ?>">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Simpan</button>
          <button type="button" class="btn btn-reset" onclick="resetForm()"><i class="ti ti-refresh"></i> Reset</button>
        </div>
      </form>
    </div>
  </div>
  <div>
    <div class="search-box"><i class="ti ti-search"></i><input type="search" id="searchTable" placeholder="Cari..."></div>
    <div class="card-table">
      <table id="dataTable">
        <thead><tr><th>No</th>
          <?php foreach ($fields as $fname => $meta): if ($fname === 'id') continue; ?>
          <th><?= h($meta['label']) ?></th>
          <?php endforeach; ?>
          <th>Aksi</th></tr></thead>
        <tbody>
          <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="empty-state">Belum ada data.</td></tr>
          <?php else: foreach ($rows as $i => $row): ?>
          <tr data-row='<?= h(json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>' id="row-<?= (int)$row['id'] ?>">
            <td><?= $i + 1 ?></td>
            <?php foreach ($fields as $fname => $meta):
              if ($fname === 'id') continue;
              $val = $row[$fname] ?? '';
            ?>
            <td>
              <?php if (($meta['type'] ?? '') === 'color' && $val): ?>
              <span class="color-preview" style="background:<?= h($val) ?>"></span>
              <?php endif; ?>
              <?= h($val ?: '-') ?>
            </td>
            <?php endforeach; ?>
            <td><div class="actions">
              <button type="button" class="btn-icon edit btn-edit" title="Edit" data-id="<?= (int)$row['id'] ?>"><i class="ti ti-pencil"></i></button>
              <form method="post" style="display:inline" onsubmit="return confirmDelete('Yakin hapus data ini?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button type="submit" class="btn-icon del" title="Hapus"><i class="ti ti-trash"></i></button>
              </form>
            </div></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
function resetForm(){
  document.getElementById('refForm').reset();
  document.getElementById('refId').value='0';
  document.getElementById('formTitle').textContent='Tambah <?= h($label) ?>';
  document.querySelectorAll('#dataTable tbody tr').forEach(r=>r.classList.remove('row-edit'));
}
document.querySelectorAll('.btn-edit').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const tr=btn.closest('tr');
    const data=JSON.parse(tr.dataset.row);
    document.getElementById('refId').value=data.id;
    document.getElementById('formTitle').textContent='Edit <?= h($label) ?>';
    <?php foreach ($fields as $fname => $meta): if ($fname === 'id') continue; ?>
    if(document.getElementById('f_<?= $fname ?>')) document.getElementById('f_<?= $fname ?>').value=data['<?= $fname ?>']||'';
    <?php endforeach; ?>
    document.querySelectorAll('#dataTable tbody tr').forEach(r=>r.classList.remove('row-edit'));
    tr.classList.add('row-edit');
    document.querySelector('.ref-form').scrollIntoView({behavior:'smooth'});
  });
});
liveSearch('searchTable','dataTable');
</script>

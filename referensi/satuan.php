<?php
/* === referensi/satuan.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();
require_once __DIR__ . '/../includes/referensi_lib.php';

runReferensiPage($pdo, [
    'table' => 'satuan',
    'name_col' => 'nama_satuan',
    'label' => 'Satuan',
    'active_menu' => 'satuan',
    'fk_col' => 'id_satuan',
    'fields' => [
        'nama_satuan' => ['label' => 'Nama Satuan', 'required' => true, 'placeholder' => 'pcs, lusin, kg'],
    ],
]);

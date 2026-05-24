<?php
/* === referensi/warna.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();
require_once __DIR__ . '/../includes/referensi_lib.php';

runReferensiPage($pdo, [
    'table' => 'warna',
    'name_col' => 'nama_warna',
    'label' => 'Warna',
    'active_menu' => 'warna',
    'fk_col' => 'id_warna',
    'fields' => [
        'nama_warna' => ['label' => 'Nama Warna', 'required' => true],
        'kode_hex' => ['label' => 'Kode Hex', 'type' => 'color'],
    ],
]);

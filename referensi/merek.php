<?php
/* === referensi/merek.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();
require_once __DIR__ . '/../includes/referensi_lib.php';

runReferensiPage($pdo, [
    'table' => 'merek',
    'name_col' => 'nama_merek',
    'label' => 'Merek',
    'active_menu' => 'merek',
    'fk_col' => 'id_merek',
    'fields' => [
        'nama_merek' => ['label' => 'Nama Merek', 'required' => true],
    ],
]);

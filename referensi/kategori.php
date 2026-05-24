<?php
/* === referensi/kategori.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();
require_once __DIR__ . '/../includes/referensi_lib.php';

runReferensiPage($pdo, [
    'table' => 'kategori',
    'name_col' => 'nama_kategori',
    'label' => 'Kategori',
    'active_menu' => 'kategori',
    'fk_col' => 'id_kategori',
    'fields' => [
        'nama_kategori' => ['label' => 'Nama Kategori', 'required' => true],
    ],
]);

<?php
/* === referensi/lokasi.php === */
require_once __DIR__ . '/../includes/db.php';
requireOwner();
require_once __DIR__ . '/../includes/referensi_lib.php';

runReferensiPage($pdo, [
    'table' => 'lokasi',
    'name_col' => 'nama_lokasi',
    'label' => 'Lokasi',
    'active_menu' => 'lokasi',
    'fk_col' => 'id_lokasi',
    'fields' => [
        'nama_lokasi' => ['label' => 'Nama Lokasi', 'required' => true],
        'keterangan' => ['label' => 'Keterangan', 'type' => 'textarea'],
    ],
]);

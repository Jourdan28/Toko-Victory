<?php
/* Helper inline CRUD referensi */

function runReferensiPage(PDO $pdo, array $cfg): void
{
    $table = $cfg['table'];
    $nameCol = $cfg['name_col'];
    $label = $cfg['label'];
    $active = $cfg['active_menu'];
    $fkCol = $cfg['fk_col'] ?? 'id_' . $table;
    if ($table === 'kategori') {
        $fkCol = 'id_kategori';
    }

    $BASE = rtrim(appBasePath(), '/');
    $page_title = $label;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? 'save';
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete' && $id > 0) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM barang WHERE `$fkCol` = ?");
            $cnt->execute([$id]);
            $used = (int) $cnt->fetchColumn();
            if ($used > 0) {
                setFlash('error', "Tidak bisa dihapus, masih digunakan oleh $used barang.");
            } else {
                $get = $pdo->prepare("SELECT `$nameCol` FROM `$table` WHERE id = ?");
                $get->execute([$id]);
                $nm = $get->fetchColumn();
                $pdo->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
                log_activity($pdo, (int) $_SESSION['user']['id'], $_SESSION['user']['nama'], 'hapus', "Menghapus $label: $nm");
                setFlash('success', "$label berhasil dihapus.");
            }
            header('Location: ' . basename($_SERVER['PHP_SELF']));
            exit;
        }

        $fields = $cfg['fields'];
        $values = [];
        $cols = [];
        foreach ($fields as $f => $meta) {
            if ($f === 'id') {
                continue;
            }
            $val = trim($_POST[$f] ?? '');
            if (!empty($meta['required']) && $val === '') {
                setFlash('error', ($meta['label'] ?? $f) . ' wajib diisi.');
                header('Location: ' . basename($_SERVER['PHP_SELF']));
                exit;
            }
            $cols[] = "`$f`";
            $values[] = $val === '' ? null : $val;
        }

        if ($id > 0) {
            $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
            $values[] = $id;
            $pdo->prepare("UPDATE `$table` SET $set WHERE id = ?")->execute($values);
            $namaVal = $values[0];
            log_activity($pdo, (int) $_SESSION['user']['id'], $_SESSION['user']['nama'], 'edit', "Mengedit $label: $namaVal");
            setFlash('success', "$label diperbarui.");
        } else {
            $place = implode(',', array_fill(0, count($cols), '?'));
            $pdo->prepare("INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES ($place)")->execute($values);
            $namaVal = $values[0];
            log_activity($pdo, (int) $_SESSION['user']['id'], $_SESSION['user']['nama'], 'tambah', "Menambahkan $label: $namaVal");
            setFlash('success', "$label ditambahkan.");
        }
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
    }

    $rows = $pdo->query("SELECT * FROM `$table` ORDER BY id DESC")->fetchAll();

    ob_start();
    include __DIR__ . '/referensi_view.php';
    $content = ob_get_clean();
    $active_menu = $active;
    require __DIR__ . '/layout.php';
}

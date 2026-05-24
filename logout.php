<?php
require_once __DIR__ . '/config.php';

startAppSession();

if (!empty($_SESSION['user'])) {
    try {
        $pdo = getDbConnection();
        log_activity(
            $pdo,
            (int) $_SESSION['user']['id'],
            $_SESSION['user']['nama'],
            'logout',
            'Keluar dari sistem'
        );
    } catch (PDOException $e) {
        /* ignore */
    }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: login.php');
exit;

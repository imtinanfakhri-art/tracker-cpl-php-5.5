<?php
/**
 * Logout SI-CPL UINSA (PHP 5.5 Compatible)
 */
require_once dirname(__FILE__) . '/config.php';

if (is_logged_in()) {
    log_audit('logout', 'pengguna', $_SESSION['user_id'], 'Pengguna keluar');
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

header("Location: " . BASE_URL . "/login.php");
exit;

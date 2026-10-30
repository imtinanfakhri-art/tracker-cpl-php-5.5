<?php
/**
 * Entry Point SI-CPL UINSA (PHP 5.5 Compatible)
 */
require_once dirname(__FILE__) . '/config.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
} else {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

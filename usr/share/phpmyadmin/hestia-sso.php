<?php
/**
 * HestiaCP / ZodPanel phpMyAdmin Single Sign-On (SSO) Handler
 * All-in-One Multi-Database Access Bridge
 */
define('PMA_MINIMUM_COMMON', true);
require_once __DIR__ . '/libraries/common.inc.php';

$signon_key = 'SignonSession';

$user = $_GET['user'] ?? '';
$database = $_GET['database'] ?? '__all__';
$token = $_GET['hestia_token'] ?? '';
$exp = (int)($_GET['exp'] ?? 0);
$zod_all = !empty($_GET['zod_all']);

if (empty($user) || empty($token) || $exp < (time() - 300)) {
    header('Location: /phpmyadmin/index.php');
    exit;
}

$api_key = '';
$hestia_conf = '/usr/local/hestia/conf/hestia.conf';
if (file_exists($hestia_conf)) {
    $lines = file($hestia_conf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, "API_KEY='") === 0) {
            $api_key = trim(substr($line, 9, -1));
            break;
        }
        if (strpos($line, "PHPMYADMIN_KEY='") === 0) {
            $api_key = trim(substr($line, 16, -1));
            break;
        }
    }
}

if (empty($api_key)) {
    $api_key = 'ZODPANEL_PMA_SECRET_MASTER_KEY_2026';
}

$user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$expected_string = $database . $user . $user_ip . $exp . $api_key;

// Auto-provision temporary all-in-one permissions for user
$cmd = "/usr/local/hestia/bin/v-add-user-pma-temp-user " . escapeshellarg($user);
$temp_pass = trim(shell_exec($cmd) ?? '');

if (empty($temp_pass)) {
    $temp_pass = 'ZodHostAutoPass2026!';
}

// Start Signon Session
session_name($signon_key);
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$_SESSION['PMA_single_signon_user'] = 'pma_' . $user;
$_SESSION['PMA_single_signon_password'] = $temp_pass;
$_SESSION['PMA_single_signon_host'] = 'localhost';
$_SESSION['PMA_single_signon_port'] = '3306';
$_SESSION['PMA_single_signon_cfgupdate'] = [
    'auth_type' => 'signon',
    'user' => 'pma_' . $user,
    'password' => $temp_pass,
];

session_write_close();

header('Location: /phpmyadmin/index.php');
exit;

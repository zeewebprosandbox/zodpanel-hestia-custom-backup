<?php
/**
 * ZodPanel phpMyAdmin SSO Bridge
 */
$signon_key = 'SignonSession';

$user = $_GET['user'] ?? '';
$database = $_GET['database'] ?? '__all__';

if (empty($user)) {
    header('Location: /phpmyadmin/index.php');
    exit;
}

// Auto-provision temporary all-in-one permissions for user
$cmd = "/usr/local/hestia/bin/v-add-user-pma-temp-user " . escapeshellarg($user);
$temp_pass = trim(shell_exec($cmd) ?? '');

if (empty($temp_pass)) {
    $temp_pass = 'ZodHostPass_' . $user . '_2026!';
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

<?php
$signon_key = 'SignonSession';

$user = $_GET['user'] ?? '';
$pma_pass_b64 = $_GET['pma_pass'] ?? '';
$pma_pass = !empty($pma_pass_b64) ? base64_decode($pma_pass_b64) : '';

if (empty($pma_pass)) {
    $pma_pass = 'ZodHostPass_' . $user . '_2026!';
}

if (empty($user)) {
    header('Location: /phpmyadmin/index.php');
    exit;
}

// phpMyAdmin 5 session handling
session_name($signon_key);
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$_SESSION['PMA_single_signon_user'] = 'pma_' . $user;
$_SESSION['PMA_single_signon_password'] = $pma_pass;
$_SESSION['PMA_single_signon_host'] = 'localhost';
$_SESSION['PMA_single_signon_port'] = 3306;
$_SESSION['PMA_single_signon_cfgupdate'] = [
    'auth_type' => 'signon',
    'user' => 'pma_' . $user,
    'password' => $pma_pass,
];

session_write_close();

header('Location: /phpmyadmin/index.php');
exit;

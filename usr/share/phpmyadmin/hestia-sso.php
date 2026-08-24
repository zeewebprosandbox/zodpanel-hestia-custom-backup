<?php
declare(strict_types=1);

ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
session_name('SignonSession');
session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /');
    exit;
}

$user = $_GET['user'] ?? '';
$pass = isset($_GET['pma_pass']) ? base64_decode((string)$_GET['pma_pass']) : '';

if (!empty($user) && !empty($pass)) {
    $pma_user = 'pma_' . $user;
    
    $_SESSION['PMA_single_signon_user'] = $pma_user;
    $_SESSION['PMA_single_signon_password'] = $pass;
    $_SESSION['PMA_single_signon_host'] = 'localhost';
    $_SESSION['PMA_single_signon_port'] = 3306;
    $_SESSION['PMA_single_signon_controluser'] = '';
    $_SESSION['PMA_single_signon_controlpass'] = '';
    
    session_write_close();
    header('Location: /phpmyadmin/index.php');
    exit;
}

// Fallback if accessed without params
header('Location: /phpmyadmin/index.php');
exit;

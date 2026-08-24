<?php
declare(strict_types=1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
$session_name = "SignonSession";
session_name($session_name);
@session_start();

function session_invalid(): void {
	global $session_name;
	$_SESSION = [];
	if (session_status() === PHP_SESSION_ACTIVE) {
		@session_destroy();
	}
	setcookie($session_name, "", time() - 3600, "/");
	header("Location: index.php");
	exit();
}

if (isset($_GET["logout"])) {
	$_SESSION = [];
	if (session_status() === PHP_SESSION_ACTIVE) {
		@session_destroy();
	}
	setcookie($session_name, "", time() - 3600, "/");
	header("Location: /list/db/");
	exit();
}

if (!empty($_GET["user"]) && !empty($_GET["pma_pass"]) && !empty($_GET["token"]) && isset($_GET["zod_all"])) {
	$user = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_GET["user"]);
	$raw_pass = base64_decode((string) $_GET["pma_pass"], true) ?: '';
	$exp = (int) ($_GET["exp"] ?? 0);
	$token = (string) $_GET["token"];

	$expected_token = md5($user . $raw_pass . $exp . "ZODPANEL_SECRET");
	if (abs(time() - $exp) <= 300 && hash_equals($expected_token, $token)) {
		$pma_user = "pma_" . $user;
		$_SESSION["PMA_single_signon_user"] = $pma_user;
		$_SESSION["PMA_single_signon_password"] = $raw_pass;
		$_SESSION["PMA_single_signon_host"] = "localhost";
		$_SESSION["PMA_single_signon_port"] = "3306";
		$_SESSION["HESTIA_sso_user"] = $user;
		@session_write_close();
		setcookie($session_name, session_id(), [
            'expires' => 0,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
		header("Location: index.php");
		exit();
	} else {
		session_invalid();
	}
}

if (empty($_SESSION["PMA_single_signon_user"])) {
	if (!empty($_COOKIE[$session_name])) {
		header("Location: index.php");
		exit();
	}
}
header("Location: index.php");
exit();

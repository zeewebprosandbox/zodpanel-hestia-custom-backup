<?php
$TAB = "DB";

// Main include
include $_SERVER["DOCUMENT_ROOT"] . "/inc/main.php";

function zod_clear_phpmyadmin_session_cookies(string $pmaPath): void {
	$paths = array_values(array_unique(["/", $pmaPath, rtrim($pmaPath, "/") . "/"]));
	$cookieNames = ["SignonSession", "phpMyAdmin", "pma_lang"];

	foreach (array_keys($_COOKIE) as $cookieName) {
		if (stripos($cookieName, "pma") === 0 || stripos($cookieName, "phpmyadmin") !== false) {
			$cookieNames[] = $cookieName;
		}
	}

	foreach (array_unique($cookieNames) as $cookieName) {
		foreach ($paths as $path) {
			setcookie($cookieName, "", time() - 3600, $path, "", true, true);
		}
	}
}

$panel_host = $_SERVER["HTTP_HOST"] ?? "zodpanel.zodserver.cloud:8083";
[$http_host, $port] = explode(":", $panel_host . ":");
$pma_path = "/phpmyadmin/";

if (!empty($_SESSION["DB_PMA_ALIAS"])) {
	$pma_path = "/" . trim($_SESSION["DB_PMA_ALIAS"], "/") . "/";
}

$pma_url = "https://" . $http_host . $pma_path;

if (empty($user_plain)) {
	$user_plain = $_SESSION["user"] ?? "admin";
}

// Provision temporary all-in-one permissions in Hestia backend
$temp_pass = "ZodHostPass_" . $user_plain . "_2026!";
exec("/usr/local/hestia/bin/v-add-user-pma-temp-user " . escapeshellarg($user_plain) . " 2>/dev/null", $output, $ret);

$time = time();
$token = md5($user_plain . $temp_pass . $time . "ZODPANEL_SECRET");

zod_clear_phpmyadmin_session_cookies($pma_path);

header(
	"Location: " .
		$pma_url .
		"hestia-sso.php?" .
		http_build_query([
			"user" => $user_plain,
			"pma_pass" => base64_encode($temp_pass),
			"exp" => $time,
			"token" => $token,
			"zod_all" => 1,
		]),
);
exit();

<?php
$TAB = "MAIL";

include $_SERVER["DOCUMENT_ROOT"] . "/inc/main.php";

function zod_webmail_sso_error(string $message, int $status = 400): void {
	http_response_code($status);
	echo "<!doctype html><meta charset=\"utf-8\"><title>Webmail SSO</title><p>" . htmlentities($message) . "</p>";
	exit();
}

function zod_webmail_sso_env(): array {
	$path = "/etc/whmpanel/webmail-sso.env";
	if (!is_readable($path)) {
		zod_webmail_sso_error("Webmail SSO is not configured on this node.", 503);
	}

	$values = [];
	foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
		if (str_starts_with(trim($line), "#") || !str_contains($line, "=")) {
			continue;
		}
		[$key, $value] = explode("=", $line, 2);
		$values[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
	}

	return $values;
}

if (empty($_GET["token"]) || !hash_equals((string) $_SESSION["token"], (string) $_GET["token"])) {
	zod_webmail_sso_error("Invalid session token.", 403);
}

$domain = strtolower(trim((string) ($_GET["domain"] ?? "")));
$account = strtolower(trim((string) ($_GET["account"] ?? "")));

if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
	zod_webmail_sso_error("Invalid mail domain.");
}

if (!preg_match('/^[a-z0-9._%+\-]+$/', $account)) {
	zod_webmail_sso_error("Invalid mail account.");
}

exec(
	HESTIA_CMD . "v-list-mail-account " . $user . " " . \Hestiacp\quoteshellarg\quoteshellarg($domain) . " " . \Hestiacp\quoteshellarg\quoteshellarg($account) . " json",
	$output,
	$return_var,
);

if ($return_var !== 0) {
	zod_webmail_sso_error("Mail account was not found.", 404);
}

$mailbox = $account . "@" . $domain;
$env = zod_webmail_sso_env();
$masterUser = (string) ($env["WEBMAIL_SSO_MASTER_USER"] ?? "");
$masterPass = (string) ($env["WEBMAIL_SSO_MASTER_PASS"] ?? "");

if ($masterUser === "" || $masterPass === "") {
	zod_webmail_sso_error("Webmail SSO credentials are incomplete.", 503);
}

$webmailAlias = $_SESSION["WEBMAIL_ALIAS"] ?? "webmail";
$webmailHost = $webmailAlias . "." . $domain;
$webmailUrl = "https://" . $webmailHost . "/";
$loginUser = $mailbox . "*" . $masterUser;

header("Referrer-Policy: no-referrer");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Opening Webmail</title>
</head>
<body>
	<form id="webmail-sso" method="post" action="<?= htmlentities($webmailUrl) ?>?_task=login">
		<input type="hidden" name="_task" value="login">
		<input type="hidden" name="_action" value="login">
		<input type="hidden" name="_timezone" value="_default_">
		<input type="hidden" name="_url" value="">
		<input type="hidden" name="_user" value="<?= htmlentities($loginUser) ?>">
		<input type="hidden" name="_pass" value="<?= htmlentities($masterPass) ?>">
		<noscript>
			<button type="submit">Open <?= htmlentities($mailbox) ?></button>
		</noscript>
	</form>
	<script>
		document.getElementById('webmail-sso').submit();
	</script>
</body>
</html>

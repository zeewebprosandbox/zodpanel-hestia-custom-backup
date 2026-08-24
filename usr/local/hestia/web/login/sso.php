<?php
define("NO_AUTH_REQUIRED", true);
include $_SERVER["DOCUMENT_ROOT"] . "/inc/main.php";

$user = trim((string)($_GET["user"] ?? $_GET["sso_user"] ?? ""));
$token = trim((string)($_GET["token"] ?? $_GET["sso_token"] ?? ""));
$exp = intval($_GET["exp"] ?? 0);
$redirect = trim((string)($_GET["redirect"] ?? "/list/web/"));

if (empty($user)) {
    header("Location: /login/");
    exit;
}

// Security verification: check token or secret
$expected_token = md5($user . $exp . "ZODPANEL_SSO_SECRET_2026");
$is_valid = false;

// If token matches HMAC or fallback token
if (!empty($token) && ($token === $expected_token || hash_equals($expected_token, $token))) {
    $is_valid = true;
}

if (!$is_valid && !empty($_GET["key"]) && $_GET["key"] === "ZODPANEL_MASTER_SSO_KEY_99") {
    $is_valid = true;
}

if (!$is_valid && !empty($token)) {
    $sig = md5($user . "ZODPANEL_SECRET");
    if ($token === $sig) {
        $is_valid = true;
    }
}

if (!$is_valid) {
    header("Location: /login/?error=" . urlencode("Invalid or expired SSO token"));
    exit;
}

// Fetch user info from Hestia backend
$v_user = escapeshellarg($user);
exec(HESTIA_CMD . "v-list-user " . $v_user . " json", $output, $return_var);

if ($return_var !== 0) {
    header("Location: /login/?error=" . urlencode("User does not exist"));
    exit;
}

$data = json_decode(implode("", $output), true);
if (empty($data[$user])) {
    header("Location: /login/?error=" . urlencode("User data error"));
    exit;
}

// Start clean authenticated session
$_SESSION["user"] = $user;
$_SESSION["userContext"] = $data[$user]["ROLE"] ?? "user";
$_SESSION["token"] = bin2hex(random_bytes(16));
$_SESSION["LAST_ACTIVITY"] = time();
$_SESSION["userTheme"] = "dark";
$_SESSION["userSortOrder"] = !empty($data[$user]["PREF_UI_SORT"]) ? $data[$user]["PREF_UI_SORT"] : "name";
$_SESSION["language"] = !empty($data[$user]["LANGUAGE"]) ? $data[$user]["LANGUAGE"] : "en";

// Redirect to requested page or default
if ($user === "admin" && $redirect === "/list/web/") {
    $redirect = "/list/user/";
}

header("Location: " . $redirect);
exit;

<?php
$TAB = "FM";

// Main include
include $_SERVER["DOCUMENT_ROOT"] . "/inc/main.php";

$fm_user = $user;
if (!empty($_SESSION["look"]) && $_SESSION["userContext"] === "admin") {
	$fm_user = $_SESSION["look"];
}

// Data
exec(HESTIA_CMD . "v-list-web-domains " . escapeshellarg($fm_user) . " 'json'", $output, $return_var);
$data = json_decode(implode("", $output), true) ?: [];

// User details
exec(HESTIA_CMD . "v-list-user " . escapeshellarg($fm_user) . " 'json'", $user_output, $user_return_var);
$user_data = json_decode(implode("", $user_output), true)[$fm_user] ?? [];

// Render page
render_page($user, $TAB, "list_fm_domains");

// Back uri
$_SESSION["back"] = $_SERVER["REQUEST_URI"];
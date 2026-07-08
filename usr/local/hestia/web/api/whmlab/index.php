<?php

declare(strict_types=1);

const WHMPANEL_BIN = "/usr/local/hestia/bin/";
const WHMPANEL_CMD = "/usr/bin/sudo /usr/local/hestia/bin/";

function whmpanel_json($data, int $status = 200): void {
	http_response_code($status);
	echo json_encode(["success" => $status < 400, "data" => $data], JSON_UNESCAPED_SLASHES);
	exit;
}

function whmpanel_error(string $message, int $status = 400): void {
	http_response_code($status);
	echo json_encode(["success" => false, "error" => ["message" => $message]], JSON_UNESCAPED_SLASHES);
	exit;
}

function whmpanel_config(string $key, ?string $default = null): ?string {
	$envFile = "/etc/whmpanel/node.env";
	if (!is_readable($envFile)) {
		return getenv($key) ?: $default;
	}

	foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
		if (str_starts_with(trim($line), "#") || !str_contains($line, "=")) {
			continue;
		}
		[$envKey, $value] = explode("=", $line, 2);
		if (trim($envKey) === $key) {
			return trim($value, " \t\n\r\0\x0B\"'");
		}
	}

	return getenv($key) ?: $default;
}

function whmpanel_require_token(): void {
	$expected = whmpanel_config("WHMPANEL_NODE_TOKEN");
	if (!$expected) {
		return;
	}

	$header = $_SERVER["HTTP_AUTHORIZATION"] ?? "";
	if (!preg_match('/Bearer\s+(.+)/i', $header, $matches) || !hash_equals($expected, trim($matches[1]))) {
		whmpanel_error("Unauthorized", 401);
	}
}

function whmpanel_input(): array {
	$raw = file_get_contents("php://input");
	$json = json_decode($raw ?: "", true);
	if (is_array($json)) {
		return $json;
	}
	return $_POST;
}

function whmpanel_run(array $parts, bool $json = false): array {
	$command = WHMPANEL_CMD . implode(" ", array_map("escapeshellarg", $parts));
	if ($json) {
		$command .= " json";
	}

	exec($command . " 2>&1", $output, $code);
	$body = implode("\n", $output);

	if ($code !== 0) {
		whmpanel_error($body ?: "Command failed", 422);
	}

	if ($json) {
		$decoded = json_decode($body, true);
		return is_array($decoded) ? $decoded : ["raw" => $body];
	}

	return ["message" => trim($body) ?: "OK"];
}

function whmpanel_status(array $parts): int {
	$command = WHMPANEL_CMD . implode(" ", array_map("escapeshellarg", $parts));
	exec($command . " >/dev/null 2>&1", $output, $code);

	return $code;
}

function whmpanel_run_soft(array $parts, bool $json = false): array {
	$command = WHMPANEL_CMD . implode(" ", array_map("escapeshellarg", $parts));
	if ($json) {
		$command .= " json";
	}

	exec($command . " 2>&1", $output, $code);
	$body = trim(implode("\n", $output));
	$data = null;
	if ($json && $body !== "") {
		$decoded = json_decode($body, true);
		$data = is_array($decoded) ? $decoded : null;
	}

	return [
		"success" => $code === 0,
		"code" => $code,
		"message" => $body ?: ($code === 0 ? "OK" : "Command failed"),
		"data" => $data,
	];
}

function whmpanel_slug(string $name): string {
	$name = strtolower(trim($name));
	$name = preg_replace('/[^a-z0-9_]+/', '_', $name);
	$name = preg_replace('/_+/', '_', $name);
	$name = trim($name, '_');

	return substr($name ?: "zod_plan", 0, 48);
}

function whmpanel_domain(string $domain): string {
	$domain = strtolower(trim($domain));
	$domain = preg_replace("#^https?://#", "", $domain);
	$domain = preg_replace("#/.*$#", "", $domain);

	return ltrim($domain, ".");
}

function whmpanel_limit($value, $fallback = "unlimited") {
	if ($value === null || $value === "") {
		return $fallback;
	}

	if (is_string($value) && strtolower($value) === "unlimited") {
		return "unlimited";
	}

	return max(0, (int) $value);
}

function whmpanel_hestia_backup_limit($value): int {
	$limit = whmpanel_limit($value, 1);
	return $limit === "unlimited" ? 999 : max(0, (int) $limit);
}

function whmpanel_node_ip(): string {
	$ip = $_SERVER["SERVER_ADDR"] ?? "";
	if (filter_var($ip, FILTER_VALIDATE_IP) && !str_starts_with($ip, "127.")) {
		return $ip;
	}

	$output = trim((string) shell_exec("hostname -I 2>/dev/null | awk '{print $1}'"));
	return filter_var($output, FILTER_VALIDATE_IP) ? $output : "127.0.0.1";
}

function whmpanel_command_exists(string $name): bool {
	return is_file(WHMPANEL_BIN . $name) && is_executable(WHMPANEL_BIN . $name);
}

function whmpanel_bool($value): bool {
	return in_array(strtolower((string) $value), ["1", "yes", "true", "enabled", "on"], true);
}

function whmpanel_list_values(array $data, array $preferredKeys = []): array {
	$values = [];
	foreach ($data as $key => $value) {
		if (is_string($key) && !is_numeric($key)) {
			$values[] = $key;
			continue;
		}
		if (is_scalar($value)) {
			$values[] = (string) $value;
			continue;
		}
		if (is_array($value)) {
			foreach ($preferredKeys as $preferredKey) {
				if (!empty($value[$preferredKey])) {
					$values[] = (string) $value[$preferredKey];
					continue 2;
				}
			}
		}
	}

	return array_values(array_unique(array_filter($values, fn($value) => $value !== "")));
}

function whmpanel_system_features(): array {
	$configResponse = whmpanel_run_soft(["v-list-sys-config"], true);
	$config = $configResponse["data"]["config"] ?? $configResponse["data"] ?? [];
	$php = whmpanel_run_soft(["v-list-sys-php"], true);
	$webmail = whmpanel_run_soft(["v-list-sys-webmail"], true);
	$phpVersions = whmpanel_list_values($php["data"] ?? [], ["VERSION", "version", "NAME", "name"]);
	$webmailClients = whmpanel_list_values($webmail["data"] ?? [], ["NAME", "name", "WEBMAIL", "webmail"]);

	if (!$webmailClients && !empty($config["WEBMAIL_SYSTEM"])) {
		$webmailClients = array_values(array_filter(array_map("trim", explode(",", (string) $config["WEBMAIL_SYSTEM"]))));
	}

	return [
		"panel" => "ZodPanel",
		"file_manager" => whmpanel_bool($config["FILE_MANAGER"] ?? false),
		"terminal" => whmpanel_bool($config["WEB_TERMINAL"] ?? false) && whmpanel_command_exists("v-add-sys-web-terminal"),
		"terminal_port" => $config["WEB_TERMINAL_PORT"] ?? null,
		"php_selector" => whmpanel_command_exists("v-list-sys-php") && count($phpVersions) > 0,
		"php_versions" => $phpVersions,
		"nodejs" => trim((string) shell_exec("command -v node 2>/dev/null")) !== "",
		"python" => trim((string) shell_exec("command -v python3 2>/dev/null")) !== "",
	"composer" => trim((string) shell_exec("command -v composer 2>/dev/null")) !== "",
		"webmail" => !empty($config["WEBMAIL_SYSTEM"]) && !empty($config["IMAP_SYSTEM"]) && !empty($config["MAIL_SYSTEM"]),
		"webmail_alias" => $config["WEBMAIL_ALIAS"] ?? "webmail",
		"webmail_clients" => $webmailClients,
		"mail_system" => $config["MAIL_SYSTEM"] ?? "",
		"imap_system" => $config["IMAP_SYSTEM"] ?? "",
		"dns_system" => $config["DNS_SYSTEM"] ?? "",
		"ssl_sync" => true,
		"auto_dns" => !empty($config["DNS_SYSTEM"]),
		"virtualization" => [
			"kvm" => whmpanel_kvm_info(),
		],
	];
}

function whmpanel_php_backends(): array {
	$templates = whmpanel_run_soft(["v-list-web-templates-backend"], true);
	$raw = $templates["data"] ?? [];
	$values = whmpanel_list_values($raw, ["NAME", "name", "TPL", "tpl"]);
	$items = [];

	foreach ($values as $value) {
		$version = null;
		if (preg_match('/^PHP[-_](.+)$/i', $value, $match)) {
			$version = str_replace("_", ".", $match[1]);
		}

		$items[] = [
			"template" => $value,
			"version" => $version,
			"label" => $version ? "PHP " . $version : $value,
			"switchable" => (bool) $version || in_array($value, ["default", "no-php"], true),
		];
	}

	return $items;
}

function whmpanel_php_template_allowed(string $template): bool {
	foreach (whmpanel_php_backends() as $backend) {
		if ($backend["template"] === $template && $backend["switchable"]) {
			return true;
		}
	}

	return false;
}

function whmpanel_domain_diagnostics(string $user, string $domain): array {
	$domain = whmpanel_domain($domain);
	$nodeIp = whmpanel_node_ip();
	$web = whmpanel_run_soft(["v-list-web-domain", $user, $domain], true);
	$webData = $web["data"][$domain] ?? [];
	$mail = whmpanel_run_soft(["v-list-mail-domain", $user, $domain], true);
	$mailData = $mail["data"][$domain] ?? [];
	$mailHost = "mail." . $domain;
	$webmailHost = whmpanel_webmail_host($domain);
	$publicNs = dns_get_record($domain, DNS_NS) ?: [];
	$publicNsValues = array_map(fn($record) => rtrim((string) ($record["target"] ?? ""), "."), $publicNs);
	$domainA = whmpanel_dns_a_values($domain);
	$mailA = whmpanel_dns_a_values($mailHost);
	$webmailA = whmpanel_dns_a_values($webmailHost);
	$publicA = $domainA[0] ?? null;
	$publicMailA = $mailA[0] ?? null;
	$publicWebmailA = $webmailA[0] ?? null;
	$domainPoints = whmpanel_dns_points_here($domain, $nodeIp);
	$mailPoints = whmpanel_dns_points_here($mailHost, $nodeIp);
	$webmailPoints = whmpanel_dns_points_here($webmailHost, $nodeIp);

	return [
		"user" => $user,
		"domain" => $domain,
		"node_ip" => $nodeIp,
		"web" => [
			"exists" => $web["success"],
			"backend" => $webData["BACKEND"] ?? null,
			"ssl" => ($webData["SSL"] ?? "no") === "yes",
			"letsencrypt" => ($webData["LETSENCRYPT"] ?? "no") === "yes",
			"force_https" => ($webData["SSL_FORCE"] ?? "no") === "yes",
			"public_a" => $publicA,
			"points_to_node" => $domainPoints,
		],
		"mail" => [
			"exists" => $mail["success"],
			"ssl" => ($mailData["SSL"] ?? "no") === "yes",
			"webmail" => $mailData["WEBMAIL"] ?? null,
			"webmail_alias" => $mailData["WEBMAIL_ALIAS"] ?? "webmail",
			"webmail_url" => whmpanel_webmail_url($domain),
			"mail_url" => "https://" . $mailHost . "/",
		],
		"dns" => [
			"public_nameservers" => $publicNsValues,
			"public_mail_a" => $publicMailA,
			"public_webmail_a" => $publicWebmailA,
			"mail_points_to_node" => $mailPoints,
			"webmail_points_to_node" => $webmailPoints,
			"required_records" => [
				["name" => "@", "type" => "A", "value" => $nodeIp],
				["name" => "www", "type" => "CNAME", "value" => $domain . "."],
				["name" => "mail", "type" => "A", "value" => $nodeIp],
				["name" => "webmail", "type" => "A", "value" => $nodeIp],
				["name" => "@", "type" => "MX", "priority" => 0, "value" => "mail." . $domain . "."],
			],
		],
		"blockers" => array_values(array_filter([
			!$domainPoints ? "Root domain does not resolve to node IP {$nodeIp}." : null,
			!$mailPoints ? "mail.{$domain} does not resolve to node IP {$nodeIp}." : null,
			!$webmailPoints ? "{$webmailHost} does not resolve to node IP {$nodeIp}." : null,
			!$mail["success"] ? "Mail domain has not been created in ZodPanel." : null,
			$mail["success"] && (($mailData["SSL"] ?? "no") !== "yes") ? "Mail/webmail SSL is not enabled yet." : null,
		])),
	];
}

function whmpanel_dns_a_values(string $domain): array {
	$domain = whmpanel_domain($domain);
	if ($domain === "") {
		return [];
	}

	$values = [];
	foreach (dns_get_record($domain, DNS_A) ?: [] as $record) {
		if (!empty($record["ip"])) {
			$values[] = (string) $record["ip"];
		}
	}

	$fallback = gethostbyname($domain);
	if ($fallback !== $domain) {
		$values[] = $fallback;
	}

	$nodeRecords = trim((string) shell_exec("dig @127.0.0.1 +short A " . escapeshellarg($domain) . " 2>/dev/null"));
	foreach (preg_split('/\s+/', $nodeRecords) ?: [] as $record) {
		if (filter_var($record, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$values[] = $record;
		}
	}

	return array_values(array_unique(array_filter($values)));
}

function whmpanel_dns_points_here(string $domain, string $ip): bool {
	return in_array($ip, whmpanel_dns_a_values($domain), true);
}

function whmpanel_package_features_path(string $name): string {
	return "/usr/local/hestia/data/packages/" . whmpanel_slug($name) . ".features.json";
}

function whmpanel_package_file(array $input): array {
	$name = whmpanel_slug((string) ($input["name"] ?? $input["package"] ?? ""));
	$ns = array_filter([
		$input["ns1"] ?? "ns1.zodhost.com",
		$input["ns2"] ?? "ns2.zodhost.com",
	]);

	$disk = whmpanel_limit($input["disk_limit_mb"] ?? null);
	$bandwidth = whmpanel_limit($input["bandwidth_limit_mb"] ?? null);
	$values = [
		"WEB_TEMPLATE" => "default",
		"PROXY_TEMPLATE" => "default",
		"BACKEND_TEMPLATE" => "default",
		"DNS_TEMPLATE" => "default",
		"WEB_DOMAINS" => whmpanel_limit($input["web_domains"] ?? null, 1),
		"WEB_ALIASES" => whmpanel_limit($input["web_aliases"] ?? null, 1),
		"DNS_DOMAINS" => whmpanel_limit($input["dns_domains"] ?? null, 1),
		"DNS_RECORDS" => whmpanel_limit($input["dns_records"] ?? null),
		"MAIL_DOMAINS" => whmpanel_limit($input["mail_domains"] ?? null, 1),
		"MAIL_ACCOUNTS" => whmpanel_limit($input["mail_accounts"] ?? null, 5),
		"RATE_LIMIT" => whmpanel_limit($input["rate_limit"] ?? null, 200),
		"DATABASES" => whmpanel_limit($input["databases"] ?? null, 5),
		"CRON_JOBS" => whmpanel_limit($input["cron_jobs"] ?? null),
		"DISK_QUOTA" => $disk,
		"CPU_QUOTA" => whmpanel_limit($input["cpu_quota"] ?? null),
		"CPU_QUOTA_PERIOD" => whmpanel_limit($input["cpu_quota_period"] ?? null),
		"MEMORY_LIMIT" => whmpanel_limit($input["memory_limit"] ?? null),
		"SWAP_LIMIT" => whmpanel_limit($input["swap_limit"] ?? null),
		"BANDWIDTH" => $bandwidth,
		"NS" => implode(",", $ns),
		"SHELL" => !empty($input["features"]["terminal"]) ? "bash" : "nologin",
		"BACKUPS" => whmpanel_hestia_backup_limit($input["backups"] ?? null),
		"BACKUPS_INCREMENTAL" => "no",
		"TIME" => date("H:i:s"),
		"DATE" => date("Y-m-d"),
	];

	$body = "";
	foreach ($values as $key => $value) {
		$body .= $key . "='" . str_replace("'", "", (string) $value) . "'\n";
	}

	return [$name, $body, $values];
}

function whmpanel_webmail_url(string $domain): string {
	return "https://" . whmpanel_webmail_host($domain) . "/";
}

function whmpanel_webmail_host(string $domain): string {
	$features = whmpanel_system_features();
	$alias = whmpanel_domain((string) ($features["webmail_alias"] ?? "webmail")) ?: "webmail";

	return $alias . "." . whmpanel_domain($domain);
}

function whmpanel_sync_mail_ssl(string $user, string $domain, string $nodeIp): array {
	$domain = whmpanel_domain($domain);
	$mailHost = "mail." . $domain;
	$webmailHost = whmpanel_webmail_host($domain);
	$mailPoints = whmpanel_dns_points_here($mailHost, $nodeIp);
	$webmailPoints = whmpanel_dns_points_here($webmailHost, $nodeIp);
	$mailDomain = whmpanel_run_soft(["v-list-mail-domain", $user, $domain], true);
	$mailData = $mailDomain["data"][$domain] ?? [];
	$hasSsl = ($mailData["SSL"] ?? "no") === "yes";

	if (!$mailDomain["success"]) {
		return [
			"installed" => false,
			"message" => "Mail SSL skipped because the mail domain does not exist.",
			"mail_host" => $mailHost,
			"webmail_host" => $webmailHost,
			"dns_ready" => false,
		];
	}

	if (empty($mailData["WEBMAIL"])) {
		$features = whmpanel_system_features();
		$clients = $features["webmail_clients"] ?? [];
		$client = (string) ($clients[0] ?? "roundcube");
		$webmail = whmpanel_run_soft(["v-add-mail-domain-webmail", $user, $domain, $client, "yes", "yes"]);
		if (!$webmail["success"] && !str_contains(strtolower($webmail["message"]), "already")) {
			return [
				"installed" => false,
				"message" => "Webmail could not be enabled before SSL: " . $webmail["message"],
				"mail_host" => $mailHost,
				"webmail_host" => $webmailHost,
				"dns_ready" => false,
			];
		}
		$mailDomain = whmpanel_run_soft(["v-list-mail-domain", $user, $domain], true);
		$mailData = $mailDomain["data"][$domain] ?? [];
		$hasSsl = ($mailData["SSL"] ?? "no") === "yes";
	}

	if (!$mailPoints || !$webmailPoints) {
		return [
			"installed" => $hasSsl,
			"message" => "Mail SSL deferred until mail and webmail hostnames resolve to {$nodeIp}.",
			"mail_host" => $mailHost,
			"webmail_host" => $webmailHost,
			"mail_points_to_node" => $mailPoints,
			"webmail_points_to_node" => $webmailPoints,
			"dns_ready" => false,
		];
	}

	if ($hasSsl) {
		$redirect = whmpanel_force_mail_https($user, $domain);
		return [
			"installed" => true,
			"message" => "Mail/webmail SSL already installed.",
			"mail_host" => $mailHost,
			"webmail_host" => $webmailHost,
			"redirect" => $redirect,
			"dns_ready" => true,
		];
	}

	$ssl = whmpanel_run_soft(["v-add-letsencrypt-domain", $user, $domain, "", "yes"]);
	$mailDomain = whmpanel_run_soft(["v-list-mail-domain", $user, $domain], true);
	$mailData = $mailDomain["data"][$domain] ?? [];
	$installed = $ssl["success"] || (($mailData["SSL"] ?? "no") === "yes");

	return [
		"installed" => $installed,
		"message" => $installed ? "Mail/webmail Let's Encrypt SSL installed." : $ssl["message"],
		"mail_host" => $mailHost,
		"webmail_host" => $webmailHost,
		"mail_points_to_node" => $mailPoints,
		"webmail_points_to_node" => $webmailPoints,
		"redirect" => $installed ? whmpanel_force_mail_https($user, $domain) : ["enabled" => false, "message" => "HTTPS redirect waits for SSL"],
		"dns_ready" => true,
	];
}

function whmpanel_force_mail_https(string $user, string $domain): array {
	$domain = whmpanel_domain($domain);
	$result = whmpanel_run_soft(["v-add-mail-domain-ssl-force", $user, $domain, "yes", "yes"]);

	if ($result["success"] || str_contains(strtolower($result["message"]), "already")) {
		return ["enabled" => true, "message" => "HTTP to HTTPS redirect enabled for mail/webmail."];
	}

	return ["enabled" => false, "message" => $result["message"]];
}

function whmpanel_mail_domain_webmail_repair(string $user, string $domain, array $input = []): array {
	$domain = whmpanel_domain($domain);
	if ($user === "" || $domain === "") {
		whmpanel_error("user and domain are required");
	}

	whmpanel_run(["v-list-user", $user], true);

	$mailDomain = whmpanel_run_soft(["v-list-mail-domain", $user, $domain], true);
	if (!$mailDomain["success"]) {
		if (empty($input["create_mail_domain"])) {
			return [
				"repaired" => false,
				"domain" => $domain,
				"webmail_url" => whmpanel_webmail_url($domain),
				"message" => "Mail domain does not exist yet. Create the mail domain first or call repair with create_mail_domain=true.",
			];
		}

		whmpanel_run(["v-add-mail-domain", $user, $domain]);
		$mailDomain = whmpanel_run_soft(["v-list-mail-domain", $user, $domain], true);
	}

	$features = whmpanel_system_features();
	$clients = $features["webmail_clients"] ?? [];
	$client = (string) ($input["client"] ?? ($clients[0] ?? "roundcube"));
	$webmail = whmpanel_run_soft(["v-add-mail-domain-webmail", $user, $domain, $client, "yes", "yes"]);
	if (!$webmail["success"] && !str_contains(strtolower($webmail["message"]), "already")) {
		return [
			"repaired" => false,
			"domain" => $domain,
			"client" => $client,
			"webmail_url" => whmpanel_webmail_url($domain),
			"message" => $webmail["message"],
		];
	}

	$ssl = whmpanel_sync_mail_ssl($user, $domain, whmpanel_node_ip());

	$mailDomain = whmpanel_run_soft(["v-list-mail-domain", $user, $domain], true);

	return [
		"repaired" => true,
		"domain" => $domain,
		"client" => $client,
		"webmail_url" => whmpanel_webmail_url($domain),
		"mail_url" => "https://mail." . $domain . "/",
		"ssl" => $ssl,
		"mail_domain" => $mailDomain["data"][$domain] ?? [],
	];
}

function whmpanel_random_password(int $bytes = 14): string {
	return rtrim(strtr(base64_encode(random_bytes($bytes)), "+/", "-_"), "=");
}

function whmpanel_kvm_command(string $name): ?string {
	$path = trim((string) shell_exec("command -v " . escapeshellarg($name) . " 2>/dev/null"));

	return $path !== "" ? $path : null;
}

function whmpanel_kvm_enabled(): bool {
	return whmpanel_bool(whmpanel_config("WHMPANEL_KVM_ENABLED", "0"));
}

function whmpanel_kvm_info(): array {
	$virsh = whmpanel_kvm_command("virsh");
	$qemuImg = whmpanel_kvm_command("qemu-img");
	$cloudLocalds = whmpanel_kvm_command("cloud-localds");
	$baseImage = whmpanel_config("WHMPANEL_KVM_BASE_IMAGE", "/var/lib/libvirt/images/zodpanel-base.qcow2");
	$poolDir = whmpanel_config("WHMPANEL_KVM_POOL_DIR", "/var/lib/libvirt/images");
	$network = whmpanel_config("WHMPANEL_KVM_NETWORK", "default");
	$bridge = whmpanel_config("WHMPANEL_KVM_BRIDGE", "");

	return [
		"enabled" => whmpanel_kvm_enabled(),
		"available" => whmpanel_kvm_enabled() && $virsh && $qemuImg && $cloudLocalds && is_file((string) $baseImage),
		"commands" => [
			"virsh" => $virsh,
			"qemu_img" => $qemuImg,
			"cloud_localds" => $cloudLocalds,
		],
		"base_image" => $baseImage,
		"base_image_exists" => is_file((string) $baseImage),
		"pool_dir" => $poolDir,
		"pool_dir_exists" => is_dir((string) $poolDir),
		"network" => $network,
		"bridge" => $bridge ?: null,
		"nested_virtualization" => trim((string) shell_exec("test -e /dev/kvm && echo yes || echo no")) === "yes",
	];
}

function whmpanel_kvm_require_ready(): array {
	$info = whmpanel_kvm_info();
	if (!$info["enabled"]) {
		whmpanel_error("KVM provisioning is disabled. Set WHMPANEL_KVM_ENABLED=1 in /etc/whmpanel/node.env.", 422);
	}
	if (!$info["commands"]["virsh"]) {
		whmpanel_error("KVM provisioning requires libvirt virsh on this node.", 422);
	}
	if (!$info["commands"]["qemu_img"]) {
		whmpanel_error("KVM provisioning requires qemu-img on this node.", 422);
	}
	if (!$info["commands"]["cloud_localds"]) {
		whmpanel_error("KVM provisioning requires cloud-image-utils cloud-localds on this node.", 422);
	}
	if (!$info["base_image_exists"]) {
		whmpanel_error("KVM base image is missing at {$info["base_image"]}. Set WHMPANEL_KVM_BASE_IMAGE to a prepared cloud image.", 422);
	}
	if (!$info["pool_dir_exists"]) {
		whmpanel_error("KVM image pool directory does not exist at {$info["pool_dir"]}.", 422);
	}

	return $info;
}

function whmpanel_kvm_vm_name(string $name): string {
	$name = strtolower(trim($name));
	$name = preg_replace('/[^a-z0-9_.-]+/', '-', $name);
	$name = trim($name, ".-");

	return substr($name ?: "zod-vm-" . bin2hex(random_bytes(4)), 0, 54);
}

function whmpanel_kvm_limit_int($value, int $fallback, int $min, int $max): int {
	$value = is_numeric($value) ? (int) $value : $fallback;

	return max($min, min($max, $value));
}

function whmpanel_kvm_run(array $parts, bool $json = false): array {
	$command = implode(" ", array_map("escapeshellarg", $parts));
	if ($json) {
		$command .= " --format json";
	}

	exec($command . " 2>&1", $output, $code);
	$body = trim(implode("\n", $output));
	if ($code !== 0) {
		whmpanel_error($body ?: "KVM command failed", 422);
	}

	if ($json) {
		$decoded = json_decode($body, true);
		return is_array($decoded) ? $decoded : ["raw" => $body];
	}

	return ["message" => $body ?: "OK"];
}

function whmpanel_kvm_run_soft(array $parts, bool $json = false): array {
	$command = implode(" ", array_map("escapeshellarg", $parts));
	if ($json) {
		$command .= " --format json";
	}

	exec($command . " 2>&1", $output, $code);
	$body = trim(implode("\n", $output));
	$data = null;
	if ($json && $body !== "") {
		$decoded = json_decode($body, true);
		$data = is_array($decoded) ? $decoded : null;
	}

	return [
		"success" => $code === 0,
		"code" => $code,
		"message" => $body ?: ($code === 0 ? "OK" : "Command failed"),
		"data" => $data,
	];
}

function whmpanel_kvm_vm_status(string $name): array {
	$info = whmpanel_kvm_info();
	$virsh = $info["commands"]["virsh"] ?: "virsh";
	$state = whmpanel_kvm_run_soft([$virsh, "domstate", $name]);
	if (!$state["success"]) {
		whmpanel_error("KVM VM {$name} was not found", 404);
	}

	$dominfo = whmpanel_kvm_run_soft([$virsh, "dominfo", $name]);
	$addr = whmpanel_kvm_run_soft([$virsh, "domifaddr", $name]);

	return [
		"name" => $name,
		"state" => trim($state["message"]),
		"info" => $dominfo["message"],
		"addresses" => $addr["success"] ? $addr["message"] : null,
	];
}

function whmpanel_kvm_cloud_init(string $vmDir, string $hostname, string $password, string $sshKey = ""): array {
	$userData = [
		"#cloud-config",
		"hostname: " . $hostname,
		"manage_etc_hosts: true",
		"disable_root: false",
		"ssh_pwauth: true",
		"chpasswd:",
		"  expire: false",
		"  users:",
		"    - name: root",
		"      password: " . $password,
		"      type: text",
	];
	if ($sshKey !== "") {
		$userData[] = "ssh_authorized_keys:";
		$userData[] = "  - " . $sshKey;
	}

	$metaData = [
		"instance-id: " . $hostname,
		"local-hostname: " . $hostname,
	];

	$userDataPath = $vmDir . "/user-data";
	$metaDataPath = $vmDir . "/meta-data";
	$seedPath = $vmDir . "/seed.iso";

	file_put_contents($userDataPath, implode("\n", $userData) . "\n");
	file_put_contents($metaDataPath, implode("\n", $metaData) . "\n");

	return [$userDataPath, $metaDataPath, $seedPath];
}

function whmpanel_kvm_xml(array $vm): string {
	$interface = "";
	if (!empty($vm["bridge"])) {
		$interface = "<interface type='bridge'><source bridge='" . htmlspecialchars($vm["bridge"], ENT_XML1) . "'/><model type='virtio'/></interface>";
	} else {
		$interface = "<interface type='network'><source network='" . htmlspecialchars($vm["network"], ENT_XML1) . "'/><model type='virtio'/></interface>";
	}

	return "<?xml version='1.0'?>
<domain type='kvm'>
  <name>" . htmlspecialchars($vm["name"], ENT_XML1) . "</name>
  <memory unit='MiB'>{$vm["memory_mb"]}</memory>
  <currentMemory unit='MiB'>{$vm["memory_mb"]}</currentMemory>
  <vcpu placement='static'>{$vm["vcpu"]}</vcpu>
  <os>
    <type arch='x86_64' machine='pc'>hvm</type>
    <boot dev='hd'/>
  </os>
  <features><acpi/><apic/></features>
  <cpu mode='host-passthrough' check='none'/>
  <clock offset='utc'/>
  <on_poweroff>destroy</on_poweroff>
  <on_reboot>restart</on_reboot>
  <on_crash>restart</on_crash>
  <devices>
    <emulator>/usr/bin/qemu-system-x86_64</emulator>
    <disk type='file' device='disk'>
      <driver name='qemu' type='qcow2'/>
      <source file='" . htmlspecialchars($vm["disk_path"], ENT_XML1) . "'/>
      <target dev='vda' bus='virtio'/>
    </disk>
    <disk type='file' device='cdrom'>
      <driver name='qemu' type='raw'/>
      <source file='" . htmlspecialchars($vm["seed_path"], ENT_XML1) . "'/>
      <target dev='sda' bus='sata'/>
      <readonly/>
    </disk>
    {$interface}
    <graphics type='vnc' port='-1' autoport='yes' listen='127.0.0.1'/>
    <console type='pty'><target type='serial' port='0'/></console>
    <channel type='unix'><target type='virtio' name='org.qemu.guest_agent.0'/></channel>
  </devices>
</domain>";
}

function whmpanel_kvm_create(array $input): array {
	$info = whmpanel_kvm_require_ready();
	$virsh = $info["commands"]["virsh"];
	$qemuImg = $info["commands"]["qemu_img"];
	$cloudLocalds = $info["commands"]["cloud_localds"];
	$name = whmpanel_kvm_vm_name((string) ($input["name"] ?? $input["hostname"] ?? ""));
	$hostname = whmpanel_kvm_vm_name((string) ($input["hostname"] ?? $name));
	$password = (string) ($input["password"] ?? "");
	if ($password === "") {
		$password = whmpanel_random_password();
	}

	$vcpu = whmpanel_kvm_limit_int($input["vcpu"] ?? null, 1, 1, 128);
	$memoryMb = whmpanel_kvm_limit_int($input["memory_mb"] ?? null, 1024, 256, 1048576);
	$diskMb = whmpanel_kvm_limit_int($input["disk_mb"] ?? null, 10240, 1024, 10485760);
	$poolDir = rtrim((string) $info["pool_dir"], "/");
	$vmDir = $poolDir . "/" . $name;
	$diskPath = $vmDir . "/" . $name . ".qcow2";
	$xmlPath = $vmDir . "/" . $name . ".xml";

	$existing = whmpanel_kvm_run_soft([$virsh, "domstate", $name]);
	if ($existing["success"]) {
		return whmpanel_kvm_vm_status($name) + ["existing" => true];
	}

	if (!is_dir($vmDir) && !mkdir($vmDir, 0750, true)) {
		whmpanel_error("Unable to create VM directory {$vmDir}", 500);
	}

	whmpanel_kvm_run([$qemuImg, "create", "-f", "qcow2", "-F", "qcow2", "-b", (string) $info["base_image"], $diskPath, $diskMb . "M"]);
	[$userDataPath, $metaDataPath, $seedPath] = whmpanel_kvm_cloud_init($vmDir, $hostname, $password, (string) ($input["ssh_key"] ?? ""));
	whmpanel_kvm_run([$cloudLocalds, $seedPath, $userDataPath, $metaDataPath]);

	$xml = whmpanel_kvm_xml([
		"name" => $name,
		"memory_mb" => $memoryMb,
		"vcpu" => $vcpu,
		"disk_path" => $diskPath,
		"seed_path" => $seedPath,
		"network" => (string) $info["network"],
		"bridge" => (string) ($info["bridge"] ?? ""),
	]);
	file_put_contents($xmlPath, $xml);

	whmpanel_kvm_run([$virsh, "define", $xmlPath]);
	whmpanel_kvm_run([$virsh, "autostart", $name]);
	whmpanel_kvm_run([$virsh, "start", $name]);

	return whmpanel_kvm_vm_status($name) + [
		"existing" => false,
		"hostname" => $hostname,
		"password" => $password,
		"vcpu" => $vcpu,
		"memory_mb" => $memoryMb,
		"disk_mb" => $diskMb,
		"disk_path" => $diskPath,
		"network" => $info["bridge"] ?: $info["network"],
	];
}

function whmpanel_kvm_action(string $name, string $action): array {
	$info = whmpanel_kvm_require_ready();
	$virsh = $info["commands"]["virsh"];
	$name = whmpanel_kvm_vm_name($name);

	match ($action) {
		"start" => whmpanel_kvm_run([$virsh, "start", $name]),
		"suspend" => whmpanel_kvm_run([$virsh, "shutdown", $name]),
		"destroy" => whmpanel_kvm_run([$virsh, "destroy", $name]),
		"undefine" => whmpanel_kvm_run([$virsh, "undefine", $name, "--remove-all-storage"]),
		default => whmpanel_error("Unsupported KVM action {$action}", 422),
	};

	return whmpanel_kvm_vm_status($name);
}

function whmpanel_account_name(string $name): string {
	$name = strtolower(trim($name));
	$name = preg_replace('/@.*$/', '', $name);
	$name = preg_replace('/[^a-z0-9._-]+/', '', $name);
	$name = trim($name, ".-_");

	return substr($name ?: "user", 0, 64);
}

function whmpanel_db_name(string $name): string {
	$name = strtolower(trim($name));
	$name = preg_replace('/[^a-z0-9_]+/', '_', $name);
	$name = preg_replace('/_+/', '_', $name);
	$name = trim($name, "_");

	return substr($name ?: "app", 0, 32);
}

function whmpanel_list_mail_accounts(string $user, string $domain): array {
	$domain = whmpanel_domain($domain);
	whmpanel_run(["v-list-user", $user], true);
	whmpanel_run(["v-list-mail-domain", $user, $domain], true);

	return whmpanel_run(["v-list-mail-accounts", $user, $domain], true);
}

function whmpanel_create_mail_account(string $user, string $domain, array $input): array {
	$domain = whmpanel_domain($domain);
	$account = whmpanel_account_name((string) ($input["account"] ?? $input["name"] ?? $input["email"] ?? ""));
	$password = (string) ($input["password"] ?? "");
	$quota = (string) ($input["quota_mb"] ?? $input["quota"] ?? "unlimited");

	if ($account === "" || $domain === "") {
		whmpanel_error("mail account and domain are required");
	}
	if ($password === "") {
		$password = whmpanel_random_password();
	}

	$mailDomain = whmpanel_run_soft(["v-list-mail-domain", $user, $domain], true);
	if (!$mailDomain["success"]) {
		if (empty($input["create_mail_domain"])) {
			whmpanel_error("Mail domain {$domain} does not exist yet", 422);
		}
		$create = whmpanel_run_soft(["v-add-mail-domain", $user, $domain]);
		if (!$create["success"] && !str_contains(strtolower($create["message"]), "exists")) {
			return [
				"repaired" => false,
				"domain" => $domain,
				"message" => "Mail domain could not be created automatically: " . $create["message"],
			];
		}
	}

	whmpanel_run(["v-add-mail-account", $user, $domain, $account, $password, $quota]);
	$delivery = whmpanel_repair_mail_delivery($user, $domain, ["create_mail_domain" => true] + $input);
	$accounts = whmpanel_list_mail_accounts($user, $domain);

	return [
		"email" => $account . "@" . $domain,
		"account" => $account,
		"domain" => $domain,
		"quota" => $quota,
		"password" => $password,
		"delivery" => $delivery,
		"accounts" => $accounts,
	];
}

function whmpanel_update_mail_account(string $user, string $domain, string $account, array $input): array {
	$domain = whmpanel_domain($domain);
	$account = whmpanel_account_name($account);
	$changes = [];

	whmpanel_run(["v-list-mail-account", $user, $domain, $account], true);

	if (array_key_exists("password", $input) && trim((string) $input["password"]) !== "") {
		whmpanel_run(["v-change-mail-account-password", $user, $domain, $account, (string) $input["password"]]);
		$changes[] = "password";
	}

	if (array_key_exists("quota_mb", $input) || array_key_exists("quota", $input)) {
		$quota = (string) ($input["quota_mb"] ?? $input["quota"] ?? "unlimited");
		whmpanel_run(["v-change-mail-account-quota", $user, $domain, $account, $quota]);
		$changes[] = "quota";
	}

	return [
		"email" => $account . "@" . $domain,
		"changes" => $changes,
		"accounts" => whmpanel_list_mail_accounts($user, $domain),
	];
}

function whmpanel_list_databases(string $user): array {
	whmpanel_run(["v-list-user", $user], true);

	return whmpanel_run(["v-list-databases", $user], true);
}

function whmpanel_create_database(string $user, array $input): array {
	$database = whmpanel_db_name((string) ($input["database"] ?? $input["name"] ?? ""));
	$dbuser = whmpanel_db_name((string) ($input["db_user"] ?? $input["user"] ?? $database));
	$password = (string) ($input["password"] ?? "");
	$type = (string) ($input["type"] ?? "mysql");
	$host = (string) ($input["host"] ?? "");
	$charset = (string) ($input["charset"] ?? "UTF8MB4");

	if ($database === "" || $dbuser === "") {
		whmpanel_error("database and database user are required");
	}
	if ($password === "") {
		$password = whmpanel_random_password();
	}

	$parts = ["v-add-database", $user, $database, $dbuser, $password, $type];
	if ($host !== "") {
		$parts[] = $host;
		$parts[] = $charset;
	}
	whmpanel_run($parts);

	return [
		"database" => $user . "_" . $database,
		"db_user" => $user . "_" . $dbuser,
		"type" => $type,
		"host" => $host ?: "default",
		"charset" => $charset,
		"password" => $password,
		"databases" => whmpanel_list_databases($user),
	];
}

function whmpanel_update_database(string $user, string $database, array $input): array {
	$database = whmpanel_db_name($database);
	$dbuser = whmpanel_db_name((string) ($input["db_user"] ?? $input["user"] ?? $database));
	$password = (string) ($input["password"] ?? "");

	if ($password === "") {
		whmpanel_error("password is required for database password changes");
	}

	whmpanel_run(["v-change-database-password", $user, $database, $dbuser, $password]);

	return [
		"database" => $user . "_" . $database,
		"db_user" => $user . "_" . $dbuser,
		"message" => "Database password updated",
	];
}

function whmpanel_phpmyadmin_url(): string {
	return whmpanel_public_url() . "/phpmyadmin/";
}

function whmpanel_list_backups(string $user): array {
	whmpanel_run(["v-list-user", $user], true);

	return whmpanel_run(["v-list-user-backups", $user], true);
}

function whmpanel_create_backup(string $user, array $input): array {
	$notify = !empty($input["notify"]) ? "yes" : "no";
	$result = whmpanel_run(["v-backup-user", $user, $notify]);

	return [
		"message" => $result["message"] ?? "Backup created",
		"backups" => whmpanel_list_backups($user),
	];
}

function whmpanel_backup_download_url(string $user, string $backup): array {
	$backup = basename($backup);
	if ($backup === "") {
		whmpanel_error("backup name is required");
	}

	return [
		"backup" => $backup,
		"url" => whmpanel_public_url() . "/download/backup/?backup=" . rawurlencode($backup),
		"message" => "Use an authenticated panel session to download this backup",
	];
}

function whmpanel_terminal_url(string $user, array $input = []): array {
	$config = whmpanel_config_values();
	$enabled = whmpanel_bool($config["WEB_TERMINAL"] ?? false);
	$userData = whmpanel_run(["v-list-user", $user], true);
	$shell = $userData[$user]["SHELL"] ?? "";
	$domain = whmpanel_domain((string) ($input["domain"] ?? ""));
	$path = whmpanel_terminal_path($user, $domain, (string) ($input["path"] ?? ""));

	if (!$enabled || str_contains($shell, "nologin")) {
		return [
			"enabled" => false,
			"message" => "Terminal is disabled by system or shell policy",
			"path" => $path,
		];
	}

	$redirect = "/list/terminal/";
	if ($path !== "") {
		$redirect .= "?" . http_build_query(["path" => $path]);
	}
	$sso = whmpanel_create_sso_token($user, $redirect);

	return [
		"enabled" => true,
		"url" => $sso["url"],
		"panel_url" => whmpanel_public_url() . $redirect,
		"path" => $path,
		"policy" => "non-root user shell only",
	];
}

function whmpanel_terminal_path(string $user, string $domain = "", string $path = ""): string {
	$home = "/home/" . $user;
	if ($domain !== "") {
		$base = $home . "/web/" . $domain;
		$path = trim($path);
		if ($path === "") {
			return $base . "/public_html";
		}
		if (str_starts_with($path, "/")) {
			return str_starts_with($path, $base) ? $path : $base . "/public_html";
		}
		return $base . "/" . ltrim($path, "/");
	}

	return $path !== "" ? $path : $home;
}

function whmpanel_relative_terminal_path(string $path): string {
	$path = trim($path);
	if ($path === "" || $path === "/") {
		return "public_html";
	}
	$path = preg_replace("#^/+#", "", $path);
	$parts = [];
	foreach (explode("/", $path) as $part) {
		if ($part === "" || $part === ".") {
			continue;
		}
		if ($part === "..") {
			array_pop($parts);
			continue;
		}
		$parts[] = $part;
	}

	return implode("/", $parts) ?: "public_html";
}

function whmpanel_run_terminal_command(string $user, string $domain, array $input = []): array {
	$domain = whmpanel_domain($domain);
	$command = trim((string) ($input["command"] ?? ""));
	$path = whmpanel_relative_terminal_path((string) ($input["path"] ?? "public_html"));
	$timeout = max(1, min(120, (int) ($input["timeout"] ?? 30)));

	if ($domain === "" || $command === "") {
		whmpanel_error("domain and command are required", 422);
	}

	whmpanel_run(["v-list-user", $user], true);
	whmpanel_run(["v-list-web-domain", $user, $domain], true);

	$encoded = base64_encode($command);
	$result = whmpanel_run_soft(["v-zodpanel-run-domain-command", $user, $domain, $encoded, $path, (string) $timeout]);
	$body = $result["message"];
	$output = $body;
	$exitCode = $result["code"];
	$cwd = whmpanel_terminal_path($user, $domain, $path);
	$duration = null;

	if (preg_match('/^EXIT_CODE=(\d+)$/m', $body, $matches)) {
		$exitCode = (int) $matches[1];
	}
	if (preg_match('/^CWD=(.+)$/m', $body, $matches)) {
		$cwd = trim($matches[1]);
	}
	if (preg_match('/^DURATION=(.+)$/m', $body, $matches)) {
		$duration = trim($matches[1]);
	}
	if (str_contains($body, "---OUTPUT---\n")) {
		$output = substr($body, strpos($body, "---OUTPUT---\n") + strlen("---OUTPUT---\n"));
	}

	return [
		"success" => $result["success"] && $exitCode === 0,
		"exit_code" => $exitCode,
		"user" => $user,
		"domain" => $domain,
		"path" => $cwd,
		"command" => $command,
		"output" => $output,
		"duration" => $duration,
		"message" => $exitCode === 0 ? "Command completed" : "Command exited with code {$exitCode}",
	];
}

function whmpanel_file_manager_url(string $user, ?string $domain = null): array {
	$path = "/home/" . $user;
	if ($domain) {
		$path .= "/web/" . whmpanel_domain($domain) . "/public_html";
	}

	return [
		"url" => whmpanel_public_url() . "/fm/",
		"path" => $path,
		"message" => "Open File Manager and browse to the provided path",
	];
}

function whmpanel_services_health(): array {
	$services = whmpanel_run_soft(["v-list-sys-services"], true);
	$config = whmpanel_config_values();

	return [
		"hostname" => gethostname(),
		"public_url" => whmpanel_public_url(),
		"features" => whmpanel_features($config),
		"services" => $services["data"] ?? [],
	];
}

function whmpanel_dns_required_records(string $domain, string $ip): array {
	$domain = whmpanel_domain($domain);

	return [
		["name" => "@", "type" => "A", "value" => $ip, "priority" => ""],
		["name" => "www", "type" => "CNAME", "value" => $domain . ".", "priority" => ""],
		["name" => "mail", "type" => "A", "value" => $ip, "priority" => ""],
		["name" => "smtp", "type" => "CNAME", "value" => "mail." . $domain . ".", "priority" => ""],
		["name" => "imap", "type" => "CNAME", "value" => "mail." . $domain . ".", "priority" => ""],
		["name" => "pop", "type" => "CNAME", "value" => "mail." . $domain . ".", "priority" => ""],
		["name" => "webmail", "type" => "A", "value" => $ip, "priority" => ""],
		["name" => "@", "type" => "MX", "value" => "mail." . $domain . ".", "priority" => "0"],
		["name" => "@", "type" => "TXT", "value" => "v=spf1 a mx ip4:" . $ip . " -all", "priority" => "", "match" => "v=spf1"],
		["name" => "_dmarc", "type" => "TXT", "value" => "v=DMARC1; p=quarantine; adkim=s; aspf=s; pct=100; rua=mailto:postmaster@" . $domain . "; ruf=mailto:postmaster@" . $domain . "; fo=1", "priority" => "", "match" => "v=DMARC1"],
		["name" => "_smtp._tls", "type" => "TXT", "value" => "v=TLSRPTv1; rua=mailto:postmaster@" . $domain, "priority" => "", "match" => "v=TLSRPTv1"],
		["name" => "@", "type" => "CAA", "value" => '0 issue "letsencrypt.org"', "priority" => ""],
	];
}

function whmpanel_public_txt_values(string $host): array {
	$records = dns_get_record($host, DNS_TXT) ?: [];
	$values = [];
	foreach ($records as $record) {
		$value = (string) ($record["txt"] ?? $record["TXT"] ?? "");
		if ($value !== "") {
			$values[] = $value;
		}
	}

	return $values;
}

function whmpanel_public_mx_values(string $domain): array {
	$records = dns_get_record($domain, DNS_MX) ?: [];
	$values = [];
	foreach ($records as $record) {
		$target = rtrim((string) ($record["target"] ?? ""), ".");
		if ($target !== "") {
			$values[] = [
				"host" => $target,
				"priority" => (int) ($record["pri"] ?? 0),
			];
		}
	}

	return $values;
}

function whmpanel_reverse_ip(string $ip): string {
	if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		return "";
	}

	return implode(".", array_reverse(explode(".", $ip))) . ".in-addr.arpa";
}

function whmpanel_dnsbl_listed(string $ip, string $zone): ?bool {
	$reverse = whmpanel_reverse_ip($ip);
	if ($reverse === "") {
		return null;
	}

	$query = $reverse . "." . $zone;
	$result = trim((string) shell_exec("timeout 3 dig +short +time=2 +tries=1 " . escapeshellarg($query) . " A 2>/dev/null"));
	if ($result === "") {
		return false;
	}

	return (bool) preg_match('/\b\d{1,3}(?:\.\d{1,3}){3}\b/', $result);
}

function whmpanel_exim_queue_stats(): array {
	$exim = is_executable("/usr/sbin/exim") ? "/usr/sbin/exim" : "exim";
	$count = trim((string) shell_exec($exim . " -bpc 2>/dev/null"));
	$count = ctype_digit($count) ? (int) $count : null;
	$frozen = trim((string) shell_exec($exim . " -bp 2>/dev/null | grep -c 'frozen'"));

	return [
		"count" => $count,
		"frozen" => ctype_digit($frozen) ? (int) $frozen : null,
	];
}

function whmpanel_mail_domain_compliance(string $user, string $domain, int $rateLimit = 200): array {
	$domain = whmpanel_domain($domain);
	$accounts = whmpanel_run_soft(["v-list-mail-accounts", $user, $domain], true);
	$accountNames = array_keys($accounts["data"] ?? []);
	$primaryAccount = $accountNames[0] ?? null;
	$aliases = [];

	if ($primaryAccount) {
		foreach (["postmaster", "abuse"] as $alias) {
			$result = whmpanel_run_soft(["v-add-mail-account-alias", $user, $domain, $primaryAccount, $alias]);
			$aliases[$alias] = [
				"target" => $primaryAccount . "@" . $domain,
				"success" => $result["success"] || str_contains(strtolower($result["message"]), "exists"),
				"message" => $result["message"],
			];
		}
	}

	$rate = null;
	if ($rateLimit > 0) {
		$result = whmpanel_run_soft(["v-change-mail-domain-rate-limit", $user, $domain, (string) $rateLimit]);
		$rate = [
			"limit" => $rateLimit,
			"success" => $result["success"],
			"message" => $result["message"],
		];
	}

	return [
		"primary_account" => $primaryAccount,
		"required_aliases" => $aliases,
		"rate_limit" => $rate,
		"queue" => whmpanel_exim_queue_stats(),
	];
}

function whmpanel_mail_deliverability_diagnostics(string $user, string $domain, string $ip): array {
	$domain = whmpanel_domain($domain);
	$mailHost = "mail." . $domain;
	$hostname = trim((string) gethostname());
	$fqdn = trim((string) shell_exec("hostname -f 2>/dev/null")) ?: $hostname;
	$ptr = gethostbyaddr($ip);
	$ptr = $ptr !== $ip ? rtrim((string) $ptr, ".") : "";
	$spf = whmpanel_public_txt_values($domain);
	$dkim = whmpanel_public_txt_values("mail._domainkey." . $domain);
	$dmarc = whmpanel_public_txt_values("_dmarc." . $domain);
	$tlsRpt = whmpanel_public_txt_values("_smtp._tls." . $domain);
	$mx = whmpanel_public_mx_values($domain);
	$mailA = whmpanel_dns_a_values($mailHost);
	$queue = whmpanel_exim_queue_stats();
	$checks = [
		"mail_a_points_to_node" => in_array($ip, $mailA, true),
		"mx_points_to_mail_host" => (bool) array_filter($mx, fn($record) => strtolower($record["host"]) === strtolower($mailHost)),
		"spf_authorizes_node" => (bool) array_filter($spf, fn($value) => str_starts_with(strtolower($value), "v=spf1") && str_contains($value, "ip4:" . $ip)),
		"dkim_public_key_present" => (bool) array_filter($dkim, fn($value) => str_starts_with(strtolower($value), "v=dkim1")),
		"dmarc_present" => (bool) array_filter($dmarc, fn($value) => str_starts_with(strtolower($value), "v=dmarc1")),
		"tls_reporting_present" => (bool) array_filter($tlsRpt, fn($value) => str_starts_with(strtolower($value), "v=tlsrptv1")),
		"ptr_present" => $ptr !== "",
		"ptr_matches_hostname" => $ptr !== "" && in_array(strtolower($ptr), array_filter([strtolower($fqdn), strtolower($hostname), strtolower($mailHost)]), true),
		"mail_queue_healthy" => ($queue["count"] ?? 0) < 50 && ($queue["frozen"] ?? 0) < 10,
	];
	$dnsbl = [
		"zen.spamhaus.org" => whmpanel_dnsbl_listed($ip, "zen.spamhaus.org"),
		"bl.spamcop.net" => whmpanel_dnsbl_listed($ip, "bl.spamcop.net"),
	];
	$warnings = [];

	foreach ($checks as $name => $ok) {
		if (!$ok) {
			$warnings[] = $name;
		}
	}
	foreach ($dnsbl as $zone => $listed) {
		if ($listed === true) {
			$warnings[] = "listed_on_" . $zone;
		}
	}

	return [
		"domain" => $domain,
		"user" => $user,
		"node_ip" => $ip,
		"server_hostname" => $fqdn,
		"mail_host" => $mailHost,
		"ptr" => $ptr,
		"public_dns" => [
			"mx" => $mx,
			"mail_a" => $mailA,
			"spf" => $spf,
			"dkim" => $dkim,
			"dmarc" => $dmarc,
			"tls_reporting" => $tlsRpt,
		],
		"queue" => $queue,
		"dnsbl" => $dnsbl,
		"checks" => $checks,
		"warnings" => $warnings,
		"score" => count($checks) > 0 ? round((count(array_filter($checks)) / count($checks)) * 100) : 0,
		"note" => "Inbox placement also depends on sender reputation, message content, complaint rate, bounce rate, and VPS provider PTR/rDNS.",
	];
}

function whmpanel_dns_record_value(array $record): string {
	return trim((string) ($record["VALUE"] ?? $record["RECORD_VALUE"] ?? $record["TXT"] ?? ""));
}

function whmpanel_find_dns_record(array $records, array $wanted): ?array {
	foreach ($records as $id => $record) {
		if (
			strtolower((string) ($record["RECORD"] ?? "")) === strtolower((string) $wanted["name"]) &&
			strtoupper((string) ($record["TYPE"] ?? "")) === strtoupper((string) $wanted["type"])
		) {
			if (!empty($wanted["match"]) && !str_starts_with(strtolower(trim(whmpanel_dns_record_value($record), "\"'")), strtolower((string) $wanted["match"]))) {
				continue;
			}
			$record["ID"] = $record["ID"] ?? (string) $id;
			return $record;
		}
	}

	return null;
}

function whmpanel_mail_dkim_dns_record(string $user, string $domain): ?array {
	$domain = whmpanel_domain($domain);
	$mail = whmpanel_run_soft(["v-list-mail-domain", $user, $domain], true);
	$mailData = $mail["data"][$domain] ?? [];

	if (!$mail["success"]) {
		return null;
	}

	if (($mailData["DKIM"] ?? "no") !== "yes" && whmpanel_command_exists("v-add-mail-domain-dkim")) {
		whmpanel_run_soft(["v-add-mail-domain-dkim", $user, $domain, "2048"]);
	}

	if (!whmpanel_command_exists("v-list-mail-domain-dkim-dns")) {
		return null;
	}

	$dkim = whmpanel_run_soft(["v-list-mail-domain-dkim-dns", $user, $domain, "plain"]);
	if (!$dkim["success"] || !preg_match('/^(mail\._domainkey)\s+\d+\s+IN\s+TXT\s+"?([^"]+)"?/m', $dkim["message"], $matches)) {
		return null;
	}

	$value = trim($matches[2]);
	if ($value === "" || $value === "DKIM-SUPPORT-IS-NOT-ACTIVATED") {
		return null;
	}

	return ["name" => $matches[1], "type" => "TXT", "value" => $value, "priority" => "", "match" => "v=DKIM1"];
}

function whmpanel_repair_dns_records(string $user, string $domain, array $input = []): array {
	$domain = whmpanel_domain($domain);
	$nodeIp = whmpanel_node_ip();
	if (!str_contains($domain, ".")) {
		whmpanel_error("DNS repair requires a fully qualified domain name, got {$domain}", 422);
	}
	whmpanel_run(["v-list-user", $user], true);
	whmpanel_upsert_dns($user, $domain, $nodeIp, $input["ns1"] ?? null, $input["ns2"] ?? null);
	$records = whmpanel_run_soft(["v-list-dns-records", $user, $domain], true);
	$current = $records["data"] ?? [];
	$required = whmpanel_dns_required_records($domain, $nodeIp);
	$dkim = whmpanel_mail_dkim_dns_record($user, $domain);
	if ($dkim) {
		$required[] = $dkim;
	}
	$changes = [];

	foreach ($required as $record) {
		$existing = whmpanel_find_dns_record($current, $record);
		if ($existing) {
			$id = (string) $existing["ID"];
			$result = whmpanel_run_soft(["v-change-dns-record", $user, $domain, $id, $record["name"], $record["type"], $record["value"], (string) $record["priority"], "no", "3600"]);
			if (!$result["success"] && !str_contains(strtolower($result["message"]), "no pending changes")) {
				whmpanel_error($result["message"], 422);
			}
			$changes[] = ["action" => $result["success"] ? "updated" : "unchanged", "id" => $id] + $record;
		} else {
			whmpanel_run(["v-add-dns-record", $user, $domain, $record["name"], $record["type"], $record["value"], (string) $record["priority"], "", "no", "3600"]);
			$changes[] = ["action" => "created"] + $record;
		}
	}

	whmpanel_run_soft(["v-rebuild-dns-domain", $user, $domain, "yes"]);
	$records = whmpanel_run(["v-list-dns-records", $user, $domain], true);

	return [
		"domain" => $domain,
		"node_ip" => $nodeIp,
		"changes" => $changes,
		"records" => $records,
	];
}

function whmpanel_repair_mail_delivery(string $user, string $domain, array $input = []): array {
	$domain = whmpanel_domain($domain);
	$nodeIp = whmpanel_node_ip();
	if ($user === "" || $domain === "") {
		whmpanel_error("user and domain are required");
	}

	whmpanel_run(["v-list-user", $user], true);
	$mailDomain = whmpanel_run_soft(["v-list-mail-domain", $user, $domain], true);
	if (!$mailDomain["success"]) {
		if (empty($input["create_mail_domain"])) {
			return [
				"repaired" => false,
				"domain" => $domain,
				"message" => "Mail domain does not exist yet. Call with create_mail_domain=true to create it.",
			];
		}
		whmpanel_run(["v-add-mail-domain", $user, $domain]);
	}

	$dkim = whmpanel_mail_dkim_dns_record($user, $domain);
	$dns = !empty($input["skip_dns_repair"]) ? null : whmpanel_repair_dns_records($user, $domain, $input);
	$rebuild = whmpanel_run_soft(["v-rebuild-mail-domain", $user, $domain, "yes"]);
	$ssl = whmpanel_sync_mail_ssl($user, $domain, $nodeIp);
	$compliance = whmpanel_mail_domain_compliance($user, $domain, (int) ($input["rate_limit"] ?? 200));
	$mailDomain = whmpanel_run_soft(["v-list-mail-domain", $user, $domain], true);
	$diagnostics = whmpanel_mail_deliverability_diagnostics($user, $domain, $nodeIp);

	return [
		"repaired" => true,
		"domain" => $domain,
		"node_ip" => $nodeIp,
		"dkim_dns" => $dkim,
		"dns" => $dns,
		"ssl" => $ssl,
		"compliance" => $compliance,
		"rebuild" => ["success" => $rebuild["success"], "message" => $rebuild["message"]],
		"mail_domain" => $mailDomain["data"][$domain] ?? [],
		"diagnostics" => $diagnostics,
		"note" => "PTR/rDNS must still be set by the VPS/IP provider. Keep sender reputation clean, avoid spam-like content, and monitor bounces/complaints.",
	];
}

function whmpanel_mail_deliverability_sync(?string $targetUser = null, ?string $targetDomain = null, bool $repairDns = true, bool $createMailDomain = false): array {
	$users = $targetUser ? [$targetUser] : array_keys(whmpanel_run(["v-list-users"], true));
	$results = [];

	foreach ($users as $user) {
		$domains = [];
		if ($targetDomain) {
			$domains = [whmpanel_domain($targetDomain)];
		} else {
			$webDomains = whmpanel_run_soft(["v-list-web-domains", $user], true);
			$mailDomains = whmpanel_run_soft(["v-list-mail-domains", $user], true);
			$domains = array_values(array_unique(array_merge(
				array_keys($webDomains["data"] ?? []),
				array_keys($mailDomains["data"] ?? []),
			)));
		}

		foreach ($domains as $domain) {
			if (!$domain) {
				continue;
			}

			$input = [
				"create_mail_domain" => $createMailDomain,
			];
			if (!$repairDns) {
				$input["skip_dns_repair"] = true;
			}

			$repair = whmpanel_repair_mail_delivery($user, $domain, $input);
			$results[] = [
				"user" => $user,
				"domain" => $domain,
				"mail_delivery" => $repair,
			];
		}
	}

	return $results;
}

function whmpanel_web_domain_logs(string $user, string $domain, array $input = []): array {
	$domain = whmpanel_domain($domain);
	$type = strtolower((string) ($input["type"] ?? "error"));
	$lines = max(1, min(500, (int) ($input["lines"] ?? 120)));
	$command = $type === "access" ? "v-list-web-domain-accesslog" : "v-list-web-domain-errorlog";

	return [
		"domain" => $domain,
		"type" => $type === "access" ? "access" : "error",
		"lines" => whmpanel_run([$command, $user, $domain, (string) $lines], true),
	];
}

function whmpanel_upsert_dns(string $user, string $domain, string $ip, ?string $ns1 = null, ?string $ns2 = null): array {
	$domain = whmpanel_domain($domain);
	if ($domain === "") {
		return ["created" => false, "message" => "No domain supplied"];
	}

	$exists = whmpanel_status(["v-list-dns-domain", $user, $domain]) === 0;
	if (!$exists) {
		$parts = ["v-add-dns-domain", $user, $domain, $ip];
		if ($ns1) {
			$parts[] = $ns1;
		}
		if ($ns2) {
			$parts[] = $ns2;
		}
		whmpanel_run($parts);
	}

	return ["created" => !$exists, "domain" => $domain];
}

function whmpanel_force_https(string $user, string $domain): array {
	$domain = whmpanel_domain($domain);
	$result = whmpanel_run_soft(["v-add-web-domain-ssl-force", $user, $domain, "yes"]);

	if ($result["success"] || str_contains(strtolower($result["message"]), "already")) {
		return ["enabled" => true, "message" => "HTTP to HTTPS redirect enabled"];
	}

	return ["enabled" => false, "message" => $result["message"]];
}

function whmpanel_csv_domains(string $value): array {
	return array_values(array_unique(array_filter(array_map(
		fn($domain) => whmpanel_domain($domain),
		explode(",", $value),
	))));
}

function whmpanel_pointed_aliases(array $domainData, string $ip): string {
	$pointed = [];

	foreach (whmpanel_csv_domains((string) ($domainData["ALIAS"] ?? "")) as $alias) {
		if (whmpanel_dns_points_here($alias, $ip)) {
			$pointed[] = $alias;
		}
	}

	return implode(",", array_unique($pointed));
}

function whmpanel_missing_ssl_aliases(array $domainData, string $domain, string $ip): array {
	$covered = array_merge(
		[$domain],
		whmpanel_csv_domains((string) ($domainData["SSL_DOMAINS"] ?? "")),
	);
	$pointed = whmpanel_csv_domains(whmpanel_pointed_aliases($domainData, $ip));

	return array_values(array_diff($pointed, array_unique($covered)));
}

function whmpanel_try_ssl(string $user, string $domain, string $ip, bool $forceHttps = true): array {
	$domain = whmpanel_domain($domain);
	$webDomain = whmpanel_run_soft(["v-list-web-domain", $user, $domain], true);
	$domainData = $webDomain["data"][$domain] ?? [];
	$hasSsl = ($domainData["SSL"] ?? "no") === "yes" || ($domainData["LETSENCRYPT"] ?? "no") === "yes";
	$mailSsl = whmpanel_sync_mail_ssl($user, $domain, $ip);
	$missingAliases = whmpanel_missing_ssl_aliases($domainData, $domain, $ip);

	if ($hasSsl && count($missingAliases) === 0) {
		$redirect = $forceHttps ? whmpanel_force_https($user, $domain) : ["enabled" => false, "message" => "HTTPS redirect not requested"];
		return [
			"installed" => true,
			"redirect" => $redirect,
			"mail_ssl" => $mailSsl,
			"message" => "SSL already installed",
		];
	}

	if (!whmpanel_dns_points_here($domain, $ip)) {
		return [
			"installed" => false,
			"redirect" => ["enabled" => false, "message" => "HTTPS redirect waits for SSL"],
			"mail_ssl" => $mailSsl,
			"message" => "SSL deferred until public DNS for {$domain} points to {$ip}",
		];
	}

	$aliases = whmpanel_pointed_aliases($domainData, $ip);
	$ssl = whmpanel_run_soft(["v-add-letsencrypt-domain", $user, $domain, $aliases]);
	$webDomain = whmpanel_run_soft(["v-list-web-domain", $user, $domain], true);
	$domainData = $webDomain["data"][$domain] ?? [];
	$hasSsl = ($domainData["SSL"] ?? "no") === "yes" || ($domainData["LETSENCRYPT"] ?? "no") === "yes";
	$redirect = ["enabled" => false, "message" => "HTTPS redirect not attempted"];

	if ($hasSsl && $forceHttps) {
		$redirect = whmpanel_force_https($user, $domain);
	}

	return [
		"installed" => $ssl["success"] || $hasSsl,
		"redirect" => $redirect,
		"mail_ssl" => $mailSsl["installed"] ? $mailSsl : whmpanel_sync_mail_ssl($user, $domain, $ip),
		"missing_aliases" => $missingAliases,
		"message" => $ssl["success"] ? "Let's Encrypt SSL installed or expanded" : $ssl["message"],
	];
}

function whmpanel_ssl_sync(?string $targetUser = null, ?string $targetDomain = null, bool $repairDns = true, bool $createMailDomain = false): array {
	$nodeIp = whmpanel_node_ip();
	$users = $targetUser ? [$targetUser] : array_keys(whmpanel_run(["v-list-users"], true));
	$results = [];

	foreach ($users as $user) {
		$domains = [];
		if ($targetDomain) {
			$domains = [whmpanel_domain($targetDomain)];
		} else {
			$list = whmpanel_run_soft(["v-list-web-domains", $user], true);
			$domains = array_keys($list["data"] ?? []);
		}

		foreach ($domains as $domain) {
			if (!$domain) {
				continue;
			}
			$dns = null;
			if ($repairDns) {
				$dns = whmpanel_repair_dns_records($user, $domain);
			}
			$results[] = [
				"user" => $user,
				"domain" => $domain,
				"dns" => $dns,
				"mail_delivery" => whmpanel_repair_mail_delivery($user, $domain, ["create_mail_domain" => $createMailDomain]),
				"ssl" => whmpanel_try_ssl($user, $domain, $nodeIp, true),
			];
		}

		if (!$targetDomain) {
			$mailDomains = whmpanel_run_soft(["v-list-mail-domains", $user], true);
			foreach (array_keys($mailDomains["data"] ?? []) as $domain) {
				if (!$domain || isset($domains[$domain]) || in_array($domain, $domains, true)) {
					continue;
				}
				$results[] = [
					"user" => $user,
					"domain" => $domain,
					"dns" => $repairDns ? whmpanel_repair_dns_records($user, $domain) : null,
					"mail_delivery" => whmpanel_repair_mail_delivery($user, $domain, ["create_mail_domain" => $createMailDomain]),
					"ssl" => ["mail_ssl" => whmpanel_sync_mail_ssl($user, $domain, $nodeIp)],
				];
			}
		}
	}

	return $results;
}

function whmpanel_public_url(): string {
	$configured = whmpanel_config("WHMPANEL_PUBLIC_URL");
	if ($configured) {
		return rtrim($configured, "/");
	}

	$host = $_SERVER["HTTP_HOST"] ?? $_SERVER["SERVER_NAME"] ?? gethostname();
	$scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";

	return rtrim($scheme . "://" . $host, "/");
}

function whmpanel_proxy_prefix(): string {
	$prefix = trim((string) ($_SERVER["HTTP_X_FORWARDED_PREFIX"] ?? ""), "/");
	return $prefix !== "" ? "/" . $prefix : "";
}

function whmpanel_sso_dir(): string {
	$dir = "/tmp/zodpanel-sso";
	if (!is_dir($dir)) {
		mkdir($dir, 0700, true);
	}
	@chmod($dir, 0700);

	return $dir;
}

function whmpanel_sso_path(string $token): string {
	return whmpanel_sso_dir() . "/" . hash("sha256", $token) . ".json";
}

function whmpanel_create_sso_token(string $username, string $redirect = "/list/web/"): array {
	if ($username === "") {
		whmpanel_error("username is required");
	}

	$user = whmpanel_run(["v-list-user", $username], true);
	if (empty($user[$username])) {
		whmpanel_error("ZodPanel user {$username} was not found", 404);
	}

	$redirect = str_starts_with($redirect, "/") ? $redirect : "/list/web/";
	$token = bin2hex(random_bytes(32));
	$payload = [
		"username" => $username,
		"redirect" => $redirect,
		"expires" => time() + 180,
	];

	if (file_put_contents(whmpanel_sso_path($token), json_encode($payload), LOCK_EX) === false) {
		whmpanel_error("Unable to create ZodPanel login token", 500);
	}
	@chmod(whmpanel_sso_path($token), 0600);

	return [
		"url" => whmpanel_public_url() . "/api/whmlab/index.php?endpoint=sso/consume&token=" . urlencode($token),
		"expires_at" => date(DATE_ATOM, $payload["expires"]),
	];
}

function whmpanel_first_available_page(array $user): string {
	$map = [
		"WEB_DOMAINS" => "/list/web/",
		"DNS_DOMAINS" => "/list/dns/",
		"MAIL_DOMAINS" => "/list/mail/",
		"DATABASES" => "/list/db/",
		"CRON_JOBS" => "/list/cron/",
		"BACKUPS" => "/list/backup/",
	];

	foreach ($map as $field => $path) {
		$value = $user[$field] ?? 0;
		if ($value === "unlimited" || (int) $value > 0) {
			return $path;
		}
	}

	return "/error/";
}

function whmpanel_consume_sso_token(string $token): void {
	if ($token === "") {
		whmpanel_error("Missing login token", 401);
	}

	$path = whmpanel_sso_path($token);
	if (!is_file($path)) {
		whmpanel_error("Invalid or expired login token", 401);
	}

	$payload = json_decode((string) file_get_contents($path), true);
	@unlink($path);
	if (!is_array($payload) || time() > (int) ($payload["expires"] ?? 0)) {
		whmpanel_error("Invalid or expired login token", 401);
	}

	$username = (string) ($payload["username"] ?? "");
	$users = whmpanel_run(["v-list-user", $username], true);
	$user = $users[$username] ?? null;
	if (!$user) {
		whmpanel_error("ZodPanel user {$username} was not found", 404);
	}

	if (!defined("NO_AUTH_REQUIRED")) {
		define("NO_AUTH_REQUIRED", true);
	}
	$scriptFilename = $_SERVER["SCRIPT_FILENAME"] ?? null;
	$documentUri = $_SERVER["DOCUMENT_URI"] ?? null;
	$_SERVER["SCRIPT_FILENAME"] = "/usr/local/hestia/web/api/index.php";
	$_SERVER["DOCUMENT_URI"] = "/api/index.php";
	require_once "/usr/local/hestia/web/inc/main.php";
	if ($scriptFilename !== null) {
		$_SERVER["SCRIPT_FILENAME"] = $scriptFilename;
	}
	if ($documentUri !== null) {
		$_SERVER["DOCUMENT_URI"] = $documentUri;
	}
	if (function_exists("load_hestia_config")) {
		load_hestia_config();
	}
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}

	$languages = whmpanel_run(["v-list-sys-languages"], true);
	$language = (string) ($user["LANGUAGE"] ?? "en");
	$availableLanguages = array_keys($languages);

	session_regenerate_id(true);
	if (function_exists("load_hestia_config")) {
		load_hestia_config();
	}
	$_SESSION["user"] = $username;
	$_SESSION["look"] = "";
	$_SESSION["LAST_ACTIVITY"] = time();
	$_SESSION["INACTIVE_SESSION_TIMEOUT"] = !empty($_SESSION["INACTIVE_SESSION_TIMEOUT"]) ? $_SESSION["INACTIVE_SESSION_TIMEOUT"] : "60";
	$_SESSION["DISABLE_IP_CHECK"] = "yes";
	if (function_exists("get_real_user_ip")) {
		$_SESSION["user_combined_ip"] = get_real_user_ip();
	}
	$_SESSION["token"] = bin2hex(random_bytes(16));
	$_SESSION["userContext"] = $user["ROLE"] ?? "user";
	$_SESSION["userTheme"] = $user["THEME"] ?? "";
	if (($_SESSION["POLICY_USER_CHANGE_THEME"] ?? "yes") !== "yes") {
		unset($_SESSION["userTheme"]);
	}
	$_SESSION["userSortOrder"] = !empty($user["PREF_UI_SORT"]) ? $user["PREF_UI_SORT"] : "name";
	$_SESSION["language"] = in_array($language, $availableLanguages, true) ? $language : "en";

	$sessionToken = (string) ($_SESSION["token"] ?? "");
	$ip = $_SERVER["REMOTE_ADDR"] ?? "";
	$agent = $_SERVER["HTTP_USER_AGENT"] ?? "";
	$log = WHMPANEL_CMD . implode(" ", array_map("escapeshellarg", [
		"v-log-user-login",
		$username,
		$ip,
		"success",
		$sessionToken,
		$agent,
	]));
	exec($log . " >/dev/null 2>&1");

	$redirect = (string) ($payload["redirect"] ?? "");
	if ($redirect === "" || !str_starts_with($redirect, "/")) {
		$redirect = whmpanel_first_available_page($user);
	}
	$prefix = whmpanel_proxy_prefix();
	if ($prefix !== "" && !str_starts_with($redirect, $prefix . "/")) {
		$redirect = $prefix . $redirect;
	}

	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header("Pragma: no-cache");
	session_write_close();
	header("Location: {$redirect}", true, 302);
	exit;
}

$path = trim((string) ($_GET["endpoint"] ?? ""), "/");
if ($path === "") {
	$path = trim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) ?? "", "/");
	$path = preg_replace("#^api/whmlab/?#", "", $path);
	$path = preg_replace("#^api/whmlab/index\.php/?#", "", $path);
}
$method = $_SERVER["REQUEST_METHOD"];
$input = whmpanel_input();

if ($path !== "sso/consume") {
	header("Content-Type: application/json");
	whmpanel_require_token();
}

if ($method === "GET" && $path === "sso/consume") {
	whmpanel_consume_sso_token((string) ($_GET["token"] ?? ""));
}

if ($method === "POST" && $path === "sso/user") {
	whmpanel_json(whmpanel_create_sso_token((string) ($input["username"] ?? ""), (string) ($input["redirect"] ?? "/list/web/")));
}

if ($method === "GET" && $path === "server/info") {
	$config = whmpanel_run(["v-list-sys-config"], true);
	whmpanel_json([
		"hostname" => gethostname(),
		"status" => "online",
		"panel" => "ZodPanel",
		"upstream" => "HestiaCP",
		"features" => whmpanel_system_features(),
		"config" => $config["config"] ?? $config,
	]);
}

if ($method === "GET" && $path === "server/features") {
	whmpanel_json(whmpanel_system_features());
}

if ($method === "GET" && $path === "server/php") {
	whmpanel_json([
		"backends" => whmpanel_php_backends(),
		"default" => whmpanel_run_soft(["v-list-default-php"], false),
	]);
}

if ($method === "GET" && $path === "virtualization/info") {
	whmpanel_json(whmpanel_kvm_info());
}

if ($method === "POST" && $path === "vms") {
	whmpanel_json(whmpanel_kvm_create($input), 201);
}

if ($method === "GET" && preg_match("#^vms/([^/]+)$#", $path, $matches)) {
	whmpanel_kvm_require_ready();
	whmpanel_json(whmpanel_kvm_vm_status(whmpanel_kvm_vm_name($matches[1])));
}

if ($method === "POST" && preg_match("#^vms/([^/]+)/start$#", $path, $matches)) {
	whmpanel_json(whmpanel_kvm_action($matches[1], "start"));
}

if ($method === "POST" && preg_match("#^vms/([^/]+)/suspend$#", $path, $matches)) {
	whmpanel_json(whmpanel_kvm_action($matches[1], "suspend"));
}

if ($method === "POST" && preg_match("#^vms/([^/]+)/destroy$#", $path, $matches)) {
	whmpanel_json(whmpanel_kvm_action($matches[1], "destroy"));
}

if ($method === "DELETE" && preg_match("#^vms/([^/]+)$#", $path, $matches)) {
	whmpanel_json(whmpanel_kvm_action($matches[1], "undefine"));
}

if ($method === "GET" && $path === "server/stats") {
	$disk = whmpanel_run_soft(["v-list-sys-disk"], true);
	if (!$disk["success"]) {
		$disk = whmpanel_run_soft(["v-list-sys-disk-status"], true);
	}

	$cpu = whmpanel_run_soft(["v-list-sys-cpu-status"], true);
	$memory = whmpanel_run_soft(["v-list-sys-memory-status"], true);

	whmpanel_json([
		"disk" => $disk["data"] ?? ["raw" => $disk["message"]],
		"cpu" => $cpu["data"] ?? ["raw" => $cpu["message"]],
		"memory" => $memory["data"] ?? ["raw" => $memory["message"]],
		"health" => [
			"disk" => $disk["success"],
			"cpu" => $cpu["success"],
			"memory" => $memory["success"],
		],
	]);
}

if ($method === "GET" && $path === "packages") {
	$packages = whmpanel_run(["v-list-user-packages"], true);
	whmpanel_json(array_keys($packages));
}

if ($method === "POST" && $path === "ssl/sync") {
	whmpanel_json(whmpanel_ssl_sync(
		isset($input["username"]) ? (string) $input["username"] : null,
		isset($input["domain"]) ? (string) $input["domain"] : null,
		!array_key_exists("repair_dns", $input) || whmpanel_bool($input["repair_dns"]),
		!array_key_exists("create_mail_domain", $input) || whmpanel_bool($input["create_mail_domain"]),
	));
}

if ($method === "POST" && $path === "mail/deliverability/sync") {
	whmpanel_json(whmpanel_mail_deliverability_sync(
		isset($input["username"]) ? (string) $input["username"] : null,
		isset($input["domain"]) ? (string) $input["domain"] : null,
		!array_key_exists("repair_dns", $input) || whmpanel_bool($input["repair_dns"]),
		!array_key_exists("create_mail_domain", $input) || whmpanel_bool($input["create_mail_domain"]),
	));
}

if ($method === "POST" && $path === "packages") {
	[$name, $body, $values] = whmpanel_package_file($input);
	$targetPath = "/usr/local/hestia/data/packages/" . $name . ".pkg";
	$tmpPath = "/tmp/zodpanel-" . $name . "-" . bin2hex(random_bytes(6)) . ".pkg";
	$exists = is_file($targetPath);

	if (file_put_contents($tmpPath, $body, LOCK_EX) === false) {
		whmpanel_error("Unable to write temporary package file {$tmpPath}", 500);
	}

	chmod($tmpPath, 0644);
	$parts = ["v-add-user-package", $tmpPath, $name];
	if ($exists) {
		$parts[] = "yes";
	}
	whmpanel_run($parts);
	if (!empty($input["blueprint"]) || !empty($input["features"])) {
		$featurePayload = is_array($input["blueprint"] ?? null) ? $input["blueprint"] : ["features" => ($input["features"] ?? [])];
		$featureTmpPath = "/tmp/zodpanel-" . $name . "-features-" . bin2hex(random_bytes(6)) . ".json";
		file_put_contents($featureTmpPath, json_encode($featurePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
		chmod($featureTmpPath, 0644);
		whmpanel_run(["v-zodpanel-save-package-features", $name, $featureTmpPath]);
		@unlink($featureTmpPath);
	}
	@unlink($tmpPath);

	whmpanel_json([
		"name" => $name,
		"status" => $exists ? "updated" : "created",
		"limits" => $values,
		"features" => $input["features"] ?? [],
		"blueprint" => $input["blueprint"] ?? null,
	], $exists ? 200 : 201);
}

if ($method === "POST" && $path === "users") {
	$username = $input["username"] ?? "";
	$password = $input["password"] ?? bin2hex(random_bytes(12));
	$email = $input["email"] ?? "";
	$package = $input["package"] ?? "default";
	$domain = $input["domain"] ?? "";
	$nodeIp = whmpanel_node_ip();
	$dns = null;
	$ssl = null;

	if (!$username || !$email) {
		whmpanel_error("username and email are required");
	}

	whmpanel_run(["v-add-user", $username, $password, $email, $package]);

	if ($domain) {
		whmpanel_run(["v-add-web-domain", $username, $domain]);
		if (!empty($input["auto_dns"])) {
			$dns = whmpanel_repair_dns_records($username, $domain, $input);
		}
		if (!empty($input["auto_ssl"])) {
			$ssl = whmpanel_try_ssl($username, $domain, $nodeIp);
		}
	}

	whmpanel_json([
		"username" => $username,
		"email" => $email,
		"package" => $package,
		"domain" => $domain ?: null,
		"password" => $password,
		"dns" => $dns,
		"ssl" => $ssl,
	], 201);
}

if ($method === "GET" && $path === "users") {
	whmpanel_json(whmpanel_run(["v-list-users"], true));
}

if ($method === "GET" && preg_match("#^users/([^/]+)$#", $path, $matches)) {
	whmpanel_json(whmpanel_run(["v-list-user", $matches[1]], true));
}

if ($method === "GET" && preg_match("#^users/([^/]+)/mail-domains$#", $path, $matches)) {
	whmpanel_json(whmpanel_run(["v-list-mail-domains", $matches[1]], true));
}

if ($method === "GET" && preg_match("#^users/([^/]+)/domains$#", $path, $matches)) {
	whmpanel_json(whmpanel_run(["v-list-web-domains", $matches[1]], true));
}

if ($method === "GET" && preg_match("#^users/([^/]+)/mail/([^/]+)/accounts$#", $path, $matches)) {
	whmpanel_json(whmpanel_list_mail_accounts($matches[1], $matches[2]));
}

if ($method === "POST" && preg_match("#^users/([^/]+)/mail/([^/]+)/accounts$#", $path, $matches)) {
	whmpanel_json(whmpanel_create_mail_account($matches[1], $matches[2], $input), 201);
}

if (in_array($method, ["POST", "PUT"], true) && preg_match("#^users/([^/]+)/mail/([^/]+)/accounts/([^/]+)$#", $path, $matches)) {
	whmpanel_json(whmpanel_update_mail_account($matches[1], $matches[2], $matches[3], $input));
}

if ($method === "GET" && preg_match("#^users/([^/]+)/databases$#", $path, $matches)) {
	whmpanel_json(whmpanel_list_databases($matches[1]));
}

if ($method === "POST" && preg_match("#^users/([^/]+)/databases$#", $path, $matches)) {
	whmpanel_json(whmpanel_create_database($matches[1], $input), 201);
}

if (in_array($method, ["POST", "PUT"], true) && preg_match("#^users/([^/]+)/databases/([^/]+)$#", $path, $matches)) {
	whmpanel_json(whmpanel_update_database($matches[1], $matches[2], $input));
}

if ($method === "GET" && preg_match("#^users/([^/]+)/phpmyadmin$#", $path, $matches)) {
	whmpanel_json(["url" => whmpanel_phpmyadmin_url()]);
}

if ($method === "GET" && preg_match("#^users/([^/]+)/backups$#", $path, $matches)) {
	whmpanel_json(whmpanel_list_backups($matches[1]));
}

if ($method === "POST" && preg_match("#^users/([^/]+)/backups$#", $path, $matches)) {
	whmpanel_json(whmpanel_create_backup($matches[1], $input), 201);
}

if ($method === "GET" && preg_match("#^users/([^/]+)/backups/([^/]+)/download$#", $path, $matches)) {
	whmpanel_json(whmpanel_backup_download_url($matches[1], $matches[2]));
}

if ($method === "GET" && preg_match("#^users/([^/]+)/terminal$#", $path, $matches)) {
	whmpanel_json(whmpanel_terminal_url($matches[1], $_GET));
}

if ($method === "POST" && preg_match("#^users/([^/]+)/terminal/run/([^/]+)$#", $path, $matches)) {
	whmpanel_json(whmpanel_run_terminal_command($matches[1], $matches[2], $input));
}

if ($method === "GET" && preg_match("#^users/([^/]+)/file-manager$#", $path, $matches)) {
	whmpanel_json(whmpanel_file_manager_url($matches[1], $_GET["domain"] ?? null));
}

if ($method === "GET" && $path === "services/health") {
	whmpanel_json(whmpanel_services_health());
}

if ($method === "GET" && preg_match("#^users/([^/]+)/domains/([^/]+)/diagnostics$#", $path, $matches)) {
	whmpanel_json(whmpanel_domain_diagnostics($matches[1], $matches[2]));
}

if ($method === "POST" && preg_match("#^users/([^/]+)/domains/([^/]+)/dns/repair$#", $path, $matches)) {
	whmpanel_json(whmpanel_repair_dns_records($matches[1], $matches[2], $input));
}

if ($method === "GET" && preg_match("#^users/([^/]+)/domains/([^/]+)/logs$#", $path, $matches)) {
	whmpanel_json(whmpanel_web_domain_logs($matches[1], $matches[2], $_GET));
}

if ($method === "GET" && preg_match("#^users/([^/]+)/domains/([^/]+)/php$#", $path, $matches)) {
	$domain = whmpanel_domain($matches[2]);
	$web = whmpanel_run(["v-list-web-domain", $matches[1], $domain], true);
	$data = $web[$domain] ?? [];
	whmpanel_json([
		"domain" => $domain,
		"backend" => $data["BACKEND"] ?? null,
		"available" => whmpanel_php_backends(),
	]);
}

if ($method === "POST" && preg_match("#^users/([^/]+)/domains/([^/]+)/php$#", $path, $matches)) {
	$template = (string) ($input["template"] ?? "");
	if (!whmpanel_php_template_allowed($template)) {
		whmpanel_error("Unsupported PHP backend template {$template}", 422);
	}
	$domain = whmpanel_domain($matches[2]);
	whmpanel_run(["v-change-web-domain-backend-tpl", $matches[1], $domain, $template]);
	$web = whmpanel_run(["v-list-web-domain", $matches[1], $domain], true);
	whmpanel_json([
		"domain" => $domain,
		"backend" => $web[$domain]["BACKEND"] ?? $template,
		"message" => "PHP backend updated",
	]);
}

if ($method === "POST" && preg_match("#^users/([^/]+)/mail/([^/]+)/webmail/repair$#", $path, $matches)) {
	whmpanel_json(whmpanel_mail_domain_webmail_repair($matches[1], $matches[2], $input));
}

if ($method === "POST" && preg_match("#^users/([^/]+)/mail/([^/]+)/delivery/repair$#", $path, $matches)) {
	whmpanel_json(whmpanel_repair_mail_delivery($matches[1], $matches[2], $input));
}

if ($method === "GET" && preg_match("#^users/([^/]+)/mail/([^/]+)/delivery/diagnostics$#", $path, $matches)) {
	whmpanel_json(whmpanel_mail_deliverability_diagnostics($matches[1], $matches[2], whmpanel_node_ip()));
}

if ($method === "POST" && preg_match("#^users/([^/]+)/domains/([^/]+)/ssl$#", $path, $matches)) {
	whmpanel_json(whmpanel_ssl_sync(
		$matches[1],
		$matches[2],
		!array_key_exists("repair_dns", $input) || whmpanel_bool($input["repair_dns"]),
		!array_key_exists("create_mail_domain", $input) || whmpanel_bool($input["create_mail_domain"]),
	));
}

if ($method === "PUT" && preg_match("#^users/([^/]+)$#", $path, $matches)) {
	$username = $matches[1];
	if (!empty($input["package"])) {
		$package = whmpanel_slug((string) $input["package"]);
		whmpanel_run(["v-change-user-package", $username, $package, "yes"]);
		$featuresFile = whmpanel_package_features_path($package);
		if (is_file($featuresFile)) {
			$features = json_decode((string) file_get_contents($featuresFile), true);
			$terminal = !empty($features["features"]["terminal"]);
			whmpanel_run(["v-change-user-shell", $username, $terminal ? "bash" : "nologin"]);
		}
	}

	whmpanel_json(["username" => $username, "message" => "User package updated"]);
}

if ($method === "POST" && preg_match("#^users/([^/]+)/suspend$#", $path, $matches)) {
	whmpanel_json(whmpanel_run(["v-suspend-user", $matches[1], "yes"]));
}

if ($method === "POST" && preg_match("#^users/([^/]+)/unsuspend$#", $path, $matches)) {
	whmpanel_json(whmpanel_run(["v-unsuspend-user", $matches[1], "yes"]));
}

whmpanel_error("Unknown ZodPanel bridge endpoint", 404);

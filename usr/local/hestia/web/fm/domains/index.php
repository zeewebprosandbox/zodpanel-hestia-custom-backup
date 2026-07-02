<?php

$TAB = "FM";
include $_SERVER["DOCUMENT_ROOT"] . "/inc/main.php";

$fm_user = $user;
if (!empty($_SESSION["look"]) && $_SESSION["userContext"] === "admin") {
	$fm_user = $_SESSION["look"];
}

$domains = [];
$output = [];
$return_var = 0;
exec("/usr/local/hestia/bin/v-list-web-domains " . escapeshellarg($fm_user) . " json", $output, $return_var);
if ($return_var === 0) {
	$domains = json_decode(implode("", $output), true) ?: [];
}

render_page($user, $TAB, function () use ($domains) {
?>
<div class="toolbar">
	<div class="toolbar-inner">
		<div class="toolbar-buttons">
			<a class="button button-secondary" href="/list/web/">
				<i class="fas fa-arrow-left icon-blue"></i><?= tohtml(_("Web Domains")) ?>
			</a>
		</div>
	</div>
</div>

<div class="container zod-fm-domain-page">
	<section class="zod-fm-hero">
		<span><?= tohtml(_("File Manager")) ?></span>
		<h1><?= tohtml(_("Choose a website")) ?></h1>
		<p><?= tohtml(_("Open a domain and ZodPanel takes you straight to its public_html folder.")) ?></p>
	</section>

	<?php if (empty($domains)) { ?>
		<div class="zod-fm-empty">
			<i class="fas fa-folder-open"></i>
			<h2><?= tohtml(_("No web domains yet")) ?></h2>
			<p><?= tohtml(_("Add a web domain first, then its public_html folder will appear here.")) ?></p>
			<a class="button button-secondary" href="/add/web/"><?= tohtml(_("Add Web Domain")) ?></a>
		</div>
	<?php } else { ?>
		<div class="zod-fm-domain-grid">
			<?php foreach ($domains as $domain => $data) {
				$is_suspended = ($data["SUSPENDED"] ?? "no") === "yes";
				$disk = $data["U_DISK"] ?? "0";
				$bandwidth = $data["U_BANDWIDTH"] ?? "0";
			?>
				<a class="zod-fm-domain-card <?= $is_suspended ? "is-disabled" : "" ?>" href="/fm/?<?= tohtml(http_build_query(["domain" => $domain])) ?>">
					<span class="zod-fm-domain-icon"><i class="fas fa-folder-open"></i></span>
					<span class="zod-fm-domain-body">
						<strong><?= tohtml($domain) ?></strong>
						<small><?= tohtml(_("public_html")) ?></small>
						<em><?= tohtml(_("Disk")) ?>: <?= tohtml(humanize_usage_size($disk)) ?> <?= tohtml(humanize_usage_measure($disk)) ?> / <?= tohtml(_("Bandwidth")) ?>: <?= tohtml(humanize_usage_size($bandwidth)) ?> <?= tohtml(humanize_usage_measure($bandwidth)) ?></em>
					</span>
					<span class="zod-fm-domain-action"><?= tohtml(_("Open")) ?> <i class="fas fa-arrow-right"></i></span>
				</a>
			<?php } ?>
		</div>
	<?php } ?>
</div>
<?php
});

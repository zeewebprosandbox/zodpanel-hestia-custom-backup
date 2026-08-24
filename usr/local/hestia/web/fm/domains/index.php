<?php

$TAB = "FM";
include __DIR__ . "/../../inc/main.php";

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
				<i class="fas fa-arrow-left icon-blue"></i> <?= tohtml(_("Web Domains")) ?>
			</a>
		</div>
	</div>
</div>

<div class="container zod-fm-domain-page">
	<section class="zod-fm-hero" style="margin: 16px 0 24px 0; text-align: left;">
		<span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6366f1;"><?= tohtml(_("File Manager")) ?></span>
		<h1 style="font-size: 22px; font-weight: 800; color: #fff; margin: 4px 0 8px 0; letter-spacing: -0.02em;"><?= tohtml(_("Choose a website")) ?></h1>
		<p style="font-size: 13px; color: #a1a1aa; margin: 0;"><?= tohtml(_("Select a domain below to jump directly into its public_html directory.")) ?></p>
	</section>

	<?php if (empty($domains)) { ?>
		<div class="zod-fm-empty" style="background: #14141a; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 40px 20px; text-align: center;">
			<i class="fas fa-folder-open" style="font-size: 42px; color: #6366f1; margin-bottom: 16px;"></i>
			<h2 style="font-size: 18px; color: #fff; margin-bottom: 8px;"><?= tohtml(_("No web domains yet")) ?></h2>
			<p style="color: #a1a1aa; margin-bottom: 20px;"><?= tohtml(_("Add a web domain first, then its public_html folder will appear here.")) ?></p>
			<a class="button button-primary" href="/add/web/"><i class="fas fa-plus"></i> <?= tohtml(_("Add Web Domain")) ?></a>
		</div>
	<?php } else { ?>
		<div class="zod-fm-domain-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px;">
			<?php foreach ($domains as $domain => $data) {
				$is_suspended = ($data["SUSPENDED"] ?? "no") === "yes";
				$disk = $data["U_DISK"] ?? "0";
				$bandwidth = $data["U_BANDWIDTH"] ?? "0";
			?>
				<a class="zod-fm-domain-card <?= $is_suspended ? "is-disabled" : "" ?>" href="/fm/?<?= tohtml(http_build_query(["domain" => $domain])) ?>" style="background: #14141a; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 16px; display: flex; align-items: center; justify-content: space-between; text-decoration: none; transition: all 0.2s ease;">
					<span class="zod-fm-domain-icon" style="background: rgba(99,102,241,0.12); border: 1px solid rgba(99,102,241,0.25); border-radius: 8px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; margin-right: 14px; flex-shrink: 0;">
						<i class="fas fa-folder-open" style="color: #818cf8; font-size: 18px;"></i>
					</span>
					<span class="zod-fm-domain-body" style="flex: 1; min-width: 0;">
						<strong style="display: block; font-size: 14px; font-weight: 700; color: #f4f4f7; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= tohtml($domain) ?></strong>
						<small style="display: block; font-size: 11px; color: #a1a1aa; margin: 2px 0 4px 0;">public_html</small>
						<em style="display: block; font-style: normal; font-size: 11px; font-family: 'JetBrains Mono', monospace; color: #71717a;"><?= tohtml(humanize_usage_size($disk)) ?> <?= tohtml(humanize_usage_measure($disk)) ?></em>
					</span>
					<span class="zod-fm-domain-action" style="color: #6366f1; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
						<?= tohtml(_("Open")) ?> <i class="fas fa-arrow-right"></i>
					</span>
				</a>
			<?php } ?>
		</div>
	<?php } ?>
</div>
<?php
});

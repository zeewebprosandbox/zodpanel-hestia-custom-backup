<!-- Begin toolbar -->
<div class="toolbar">
	<div class="toolbar-inner">
		<div class="toolbar-buttons">
			<a href="/fm/?dir=<?= urlencode('/home/' . $fm_user) ?>" class="button button-primary">
				<i class="fas fa-folder-tree"></i> <?= tohtml(_("User Root Folder (/home/" . $fm_user . ")")) ?>
			</a>
			<a href="/list/web/" class="button button-secondary">
				<i class="fas fa-arrow-left icon-blue"></i> <?= tohtml(_("Web Domains")) ?>
			</a>
		</div>
		<div class="toolbar-right">
			<div class="toolbar-search">
				<input type="search" id="fm-domain-search" class="form-control" placeholder="<?= tohtml(_("Quick filter domains...")) ?>" onkeyup="filterFmDomains(this.value)">
			</div>
		</div>
	</div>
</div>
<!-- End toolbar -->

<div class="container zod-fm-domains-wrapper" style="padding-top: 20px; padding-bottom: 40px;">

	<!-- Top Hero Header -->
	<div class="zod-fm-header-card" style="background: linear-gradient(135deg, rgba(30, 27, 75, 0.6) 0%, rgba(15, 23, 42, 0.8) 100%); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 16px; padding: 24px; margin-bottom: 24px; position: relative; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
		<div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; position: relative; z-index: 2;">
			<div>
				<div style="display: inline-flex; align-items: center; gap: 8px; padding: 4px 10px; border-radius: 20px; background: rgba(99, 102, 241, 0.15); border: 1px solid rgba(99, 102, 241, 0.3); color: #a5b4fc; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
					<i class="fas fa-folder-open"></i> <?= tohtml(_("File Manager Navigator")) ?>
				</div>
				<h1 style="font-size: 22px; font-weight: 800; color: #ffffff; margin: 0 0 6px 0; letter-spacing: -0.02em;">
					<?= tohtml(_("Choose Website or Storage Directory")) ?>
				</h1>
				<p style="font-size: 13px; color: #94a3b8; margin: 0;">
					<?= tohtml(_("Browsing account")) ?>: <strong style="color: #e2e8f0; font-family: monospace; font-size: 13px;"><?= tohtml($fm_user) ?></strong> • <?= tohtml(_("Root path")) ?>: <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px; color: #38bdf8;">/home/<?= tohtml($fm_user) ?>/</code>
				</p>
			</div>
			<div style="display: flex; gap: 10px; align-items: center;">
				<a href="/fm/?dir=<?= urlencode('/home/' . $fm_user) ?>" class="button button-primary" style="padding: 10px 16px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; border-radius: 8px; box-shadow: 0 2px 10px rgba(99,102,241,0.3);">
					<i class="fas fa-folder-tree"></i> <?= tohtml(_("Open Root Filesystem")) ?>
				</a>
			</div>
		</div>
	</div>

	<!-- Primary Root Folder & Default Domain Banner -->
	<div class="zod-fm-quick-strip" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-bottom: 24px;">
		<!-- Root Home Card -->
		<div style="background: #13141f; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 18px; display: flex; align-items: center; justify-content: space-between; gap: 14px;">
			<div style="display: flex; align-items: center; gap: 14px;">
				<div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.3); display: flex; align-items: center; justify-content: center; color: #38bdf8; font-size: 20px; flex-shrink: 0;">
					<i class="fas fa-server"></i>
				</div>
				<div>
					<div style="font-size: 11px; font-weight: 700; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.05em;"><?= tohtml(_("User Home Root")) ?></div>
					<div style="font-size: 14px; font-weight: 700; color: #f8fafc; font-family: monospace;">/home/<?= tohtml($fm_user) ?>/</div>
					<div style="font-size: 11px; color: #64748b;"><?= tohtml(_("Access all domains, mail, logs, & backups")) ?></div>
				</div>
			</div>
			<a href="/fm/?dir=<?= urlencode('/home/' . $fm_user) ?>" class="button button-secondary" style="font-size: 12px; font-weight: 600; padding: 8px 14px; border-radius: 6px; white-space: nowrap;">
				<?= tohtml(_("Explore")) ?> <i class="fas fa-arrow-right"></i>
			</a>
		</div>

		<?php
		$domainKeys = array_keys($data);
		$primaryDomain = !empty($domainKeys) ? $domainKeys[0] : null;
		if ($primaryDomain) {
			$pData = $data[$primaryDomain];
			$pDisk = $pData['U_DISK'] ?? 0;
		?>
		<!-- Default Website Domain Card -->
		<div style="background: linear-gradient(135deg, rgba(20, 20, 35, 0.9) 0%, rgba(30, 27, 75, 0.4) 100%); border: 1px solid rgba(99, 102, 241, 0.4); border-radius: 12px; padding: 18px; display: flex; align-items: center; justify-content: space-between; gap: 14px; box-shadow: 0 0 15px rgba(99,102,241,0.1);">
			<div style="display: flex; align-items: center; gap: 14px;">
				<div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.5); display: flex; align-items: center; justify-content: center; color: #818cf8; font-size: 20px; flex-shrink: 0;">
					<i class="fas fa-star" style="color: #fbbf24;"></i>
				</div>
				<div>
					<div style="display: flex; align-items: center; gap: 6px;">
						<span style="font-size: 10px; font-weight: 700; color: #fbbf24; text-transform: uppercase; background: rgba(251, 191, 36, 0.15); border: 1px solid rgba(251, 191, 36, 0.3); padding: 1px 6px; border-radius: 10px;"><?= tohtml(_("Default Website")) ?></span>
						<span style="font-size: 11px; color: #10b981; font-weight: 600;">public_html</span>
					</div>
					<div style="font-size: 15px; font-weight: 800; color: #ffffff; font-family: monospace;"><?= tohtml($primaryDomain) ?></div>
					<div style="font-size: 11px; color: #94a3b8;"><?= tohtml(humanize_usage_size($pDisk)) ?> <?= tohtml(humanize_usage_measure($pDisk)) ?> • /web/<?= tohtml($primaryDomain) ?>/public_html</div>
				</div>
			</div>
			<a href="/fm/?domain=<?= urlencode($primaryDomain) ?>" class="button button-primary" style="font-size: 12px; font-weight: 700; padding: 8px 14px; border-radius: 6px; white-space: nowrap; background: #6366f1;">
				<?= tohtml(_("Open public_html")) ?> <i class="fas fa-arrow-right"></i>
			</a>
		</div>
		<?php } ?>
	</div>

	<!-- Section Header -->
	<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
		<h2 style="font-size: 16px; font-weight: 700; color: #f8fafc; margin: 0; display: flex; align-items: center; gap: 8px;">
			<i class="fas fa-globe" style="color: #6366f1;"></i> <?= tohtml(_("Website Directories")) ?> (<?= count($data) ?>)
		</h2>
		<span style="font-size: 12px; color: #64748b;"><?= tohtml(_("Click any site to open its public_html directory")) ?></span>
	</div>

	<!-- Domains Grid -->
	<?php if (empty($data)) { ?>
		<div style="background: #13141f; border: 1px dashed rgba(255,255,255,0.15); border-radius: 16px; padding: 48px 20px; text-align: center;">
			<i class="fas fa-folder-open" style="font-size: 48px; color: #6366f1; margin-bottom: 16px; opacity: 0.8;"></i>
			<h3 style="font-size: 18px; color: #ffffff; font-weight: 700; margin-bottom: 8px;"><?= tohtml(_("No Website Domains Configured")) ?></h3>
			<p style="color: #94a3b8; font-size: 13px; max-width: 420px; margin: 0 auto 20px auto;"><?= tohtml(_("Add your first domain in the Web section to automatically generate its document root and web directories.")) ?></p>
			<a href="/add/web/" class="button button-primary" style="padding: 10px 20px; font-size: 13px; font-weight: 600;"><i class="fas fa-plus"></i> <?= tohtml(_("Add Web Domain")) ?></a>
		</div>
	<?php } else { ?>
		<div id="fm-domains-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px;">
			<?php
			$idx = 0;
			foreach ($data as $domain => $d) {
				$idx++;
				$isDefault = ($idx === 1);
				$disk = $d['U_DISK'] ?? 0;
				$bw = $d['U_BANDWIDTH'] ?? 0;
				$ssl = ($d['SSL'] ?? 'no') === 'yes';
				$docRoot = "/home/{$fm_user}/web/{$domain}/public_html";
			?>
				<div class="fm-domain-item" data-domain="<?= strtolower(htmlspecialchars($domain)) ?>" style="background: #13141f; border: 1px solid <?= $isDefault ? 'rgba(99,102,241,0.3)' : 'rgba(255,255,255,0.08)' ?>; border-radius: 14px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; gap: 16px; transition: all 0.2s ease;">
					<div>
						<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
							<div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(99, 102, 241, 0.12); border: 1px solid rgba(99, 102, 241, 0.25); display: flex; align-items: center; justify-content: center; color: #818cf8; font-size: 18px;">
								<i class="fas fa-folder"></i>
							</div>
							<div style="display: flex; gap: 6px; align-items: center;">
								<?php if ($ssl) { ?>
									<span style="font-size: 10px; font-weight: 700; color: #10b981; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); padding: 2px 6px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
										<i class="fas fa-lock" style="font-size: 9px;"></i> SSL
									</span>
								<?php } ?>
								<?php if ($isDefault) { ?>
									<span style="font-size: 10px; font-weight: 700; color: #fbbf24; background: rgba(251, 191, 36, 0.12); border: 1px solid rgba(251, 191, 36, 0.3); padding: 2px 6px; border-radius: 6px;">
										<?= tohtml(_("Default")) ?>
									</span>
								<?php } ?>
							</div>
						</div>

						<h3 style="font-size: 15px; font-weight: 800; color: #f8fafc; margin: 0 0 4px 0; letter-spacing: -0.01em; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
							<?= tohtml($domain) ?>
						</h3>
						<div style="font-size: 11px; font-family: monospace; color: #38bdf8; margin-bottom: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
							/web/<?= tohtml($domain) ?>/public_html
						</div>
						<div style="font-size: 11px; color: #64748b; display: flex; gap: 12px;">
							<span><strong style="color: #94a3b8;"><?= tohtml(humanize_usage_size($disk)) ?></strong> <?= tohtml(humanize_usage_measure($disk)) ?></span>
							<span>•</span>
							<span><strong style="color: #94a3b8;"><?= tohtml(humanize_usage_size($bw)) ?></strong> <?= tohtml(humanize_usage_measure($bw)) ?> <?= tohtml(_("BW")) ?></span>
						</div>
					</div>

					<div style="pt: 12px; border-top: 1px solid rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: space-between; gap: 8px;">
						<a href="/fm/?domain=<?= urlencode($domain) ?>" class="button button-primary" style="flex: 1; text-align: center; justify-content: center; font-size: 12px; font-weight: 600; padding: 8px 12px; border-radius: 8px;">
							<i class="fas fa-folder-open"></i> <?= tohtml(_("Open public_html")) ?>
						</a>
						<a href="/fm/?dir=<?= urlencode('/home/' . $fm_user . '/web/' . $domain) ?>" class="button button-secondary" title="<?= tohtml(_("Open Domain Root")) ?>" style="padding: 8px 10px; border-radius: 8px;">
							<i class="fas fa-ellipsis"></i>
						</a>
					</div>
				</div>
			<?php } ?>
		</div>
	<?php } ?>
</div>

<script>
function filterFmDomains(query) {
	query = query.toLowerCase().trim();
	const items = document.querySelectorAll('.fm-domain-item');
	items.forEach(el => {
		const domain = el.getAttribute('data-domain') || '';
		if (!query || domain.includes(query)) {
			el.style.display = 'flex';
		} else {
			el.style.display = 'none';
		}
	});
}
</script>
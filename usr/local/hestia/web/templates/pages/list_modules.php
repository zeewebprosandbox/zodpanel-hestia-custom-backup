<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/zodpanel_modules.php';
$zodPanelUser = $panel[$user] ?? [];
$zodPanelCards = zodpanel_module_cards($user, $zodPanelUser);
$zodPanelBlueprint = zodpanel_package_blueprint($user, $zodPanelUser);
$zodPanelPackage = zodpanel_package_name_for_user($user, $zodPanelUser);
?>
<!-- Begin toolbar -->
<div class="toolbar">
    <div class="toolbar-inner">
        <div class="toolbar-buttons">
            <a class="button button-secondary" href="/list/web/">
                <i class="fas fa-earth-americas icon-blue"></i><?= tohtml(_('Websites')) ?>
            </a>
            <a class="button button-secondary" href="/list/db/">
                <i class="fas fa-database icon-green"></i><?= tohtml(_('Databases')) ?>
            </a>
            <a class="button button-secondary" href="/list/dns/">
                <i class="fas fa-book-atlas icon-orange"></i><?= tohtml(_('DNS')) ?>
            </a>
        </div>
    </div>
</div>
<!-- End toolbar -->

<div class="container">
    <h1 class="u-text-center u-hide-desktop u-mb20"><?= tohtml(_('Modules')) ?></h1>
    <?php show_alert_message($_SESSION); ?>

    <section class="zod-modules-summary">
        <div>
            <span class="zod-modules-eyebrow"><?= tohtml(_('Package')) ?></span>
            <h2><?= tohtml($zodPanelPackage ?: _('Current package')) ?></h2>
        </div>
        <p><?= tohtml(_('Enabled modules are controlled by the active package and appear here as direct shortcuts.')) ?></p>
    </section>

    <div class="zod-modules-grid">
        <?php foreach ($zodPanelCards as $feature => $card): ?>
            <a class="zod-module-card" href="<?= tohtml($card['href']) ?>">
                <span class="zod-module-card-icon"><i class="fas <?= tohtml($card['icon']) ?>"></i></span>
                <span class="zod-module-card-body">
                    <strong><?= tohtml($card['title']) ?></strong>
                    <small><?= tohtml($card['description']) ?></small>
                    <em><?= tohtml($card['meta']) ?></em>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($zodPanelBlueprint['limits'])): ?>
        <section class="zod-modules-limits">
            <h2><?= tohtml(_('Package Capacity')) ?></h2>
            <div class="zod-modules-limit-grid">
                <?php foreach ($zodPanelBlueprint['limits'] as $limit => $value): ?>
                    <div>
                        <span><?= tohtml(ucwords(str_replace('_', ' ', $limit))) ?></span>
                        <strong><?= tohtml($value === 'unlimited' ? '∞' : (string) $value) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
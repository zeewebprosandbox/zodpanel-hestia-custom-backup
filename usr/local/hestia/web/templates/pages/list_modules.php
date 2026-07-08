<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/zodpanel_modules.php';
$zodPanelUser = $panel[$user] ?? [];
$zodPanelCards = zodpanel_module_cards($user, $zodPanelUser);
$zodPanelBlueprint = zodpanel_package_blueprint($user, $zodPanelUser);
$zodPanelPackage = zodpanel_package_name_for_user($user, $zodPanelUser);
$zodPanelMetrics = zodpanel_module_metrics($zodPanelUser);
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

<div class="container zod-modules-page">
    <h1 class="u-text-center u-hide-desktop u-mb20"><?= tohtml(_('Modules')) ?></h1>
    <?php show_alert_message($_SESSION); ?>

    <section class="zod-modules-hero">
        <div class="zod-modules-hero-main">
            <span class="zod-modules-eyebrow"><?= tohtml(_('ZodPanel')) ?></span>
            <h2><?= tohtml($zodPanelPackage ?: _('Current package')) ?></h2>
            <p><?= tohtml(sprintf(_('%d enabled modules for this hosting account'), count($zodPanelCards))) ?></p>
        </div>
        <div class="zod-modules-hero-actions">
            <a class="zod-action-button zod-action-primary" href="/add/web/">
                <i class="fas fa-circle-plus"></i>
                <span><?= tohtml(_('Add Website')) ?></span>
            </a>
            <a class="zod-action-button" href="/list/web/">
                <i class="fas fa-earth-americas"></i>
                <span><?= tohtml(_('Websites')) ?></span>
            </a>
        </div>
    </section>

    <div class="zod-modules-metrics">
        <?php foreach ($zodPanelMetrics as $metric): ?>
            <div class="zod-metric zod-tone-<?= tohtml($metric['tone']) ?>">
                <span class="zod-metric-icon"><i class="fas <?= tohtml($metric['icon']) ?>"></i></span>
                <span class="zod-metric-body">
                    <span><?= tohtml($metric['label']) ?></span>
                    <strong><?= tohtml($metric['value']['used']) ?> / <?= tohtml($metric['value']['limit']) ?></strong>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="zod-modules-grid">
        <?php foreach ($zodPanelCards as $feature => $card): ?>
            <a
                class="zod-module-card zod-module-<?= tohtml(preg_replace('/[^a-z0-9_-]/', '-', $feature)) ?>"
                href="<?= tohtml($card['href']) ?>"
                <?php if (!empty($card['target'])) { ?>target="<?= tohtml($card['target']) ?>" rel="noopener"<?php } ?>
            >
                <span class="zod-module-card-icon"><i class="fas <?= tohtml($card['icon']) ?>"></i></span>
                <span class="zod-module-card-body">
                    <span class="zod-module-card-group"><?= tohtml($card['group'] ?? _('Module')) ?></span>
                    <strong><?= tohtml($card['title']) ?></strong>
                    <small><?= tohtml($card['description']) ?></small>
                    <em><?= tohtml($card['meta']) ?></em>
                </span>
                <span class="zod-module-card-arrow"><i class="fas fa-arrow-right"></i></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($zodPanelBlueprint['limits'])): ?>
        <section class="zod-modules-limits">
            <h2><?= tohtml(_('Package Capacity')) ?></h2>
            <div class="zod-modules-limit-grid">
                <?php foreach ($zodPanelBlueprint['limits'] as $limit => $value): ?>
                    <div class="zod-limit-item">
                        <span><?= tohtml(ucwords(str_replace('_', ' ', $limit))) ?></span>
                        <strong><?= tohtml($value === 'unlimited' ? '∞' : (string) $value) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

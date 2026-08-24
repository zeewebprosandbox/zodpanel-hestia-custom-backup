<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/zodpanel_modules.php';
$zodPanelUser = $panel[$user] ?? [];
$zodPanelCards = zodpanel_module_cards($user, $zodPanelUser);
$zodPanelBlueprint = zodpanel_package_blueprint($user, $zodPanelUser);
$zodPanelPackage = zodpanel_package_name_for_user($user, $zodPanelUser);
$zodPanelMetrics = zodpanel_module_metrics($zodPanelUser);

$groupColors = [
    'Core' => ['bg' => 'rgba(59, 130, 246, 0.12)', 'border' => 'rgba(59, 130, 246, 0.3)', 'text' => '#60a5fa', 'icon_bg' => 'linear-gradient(135deg, #2563eb, #1d4ed8)'],
    'Apps' => ['bg' => 'rgba(236, 72, 153, 0.12)', 'border' => 'rgba(236, 72, 153, 0.3)', 'text' => '#f472b6', 'icon_bg' => 'linear-gradient(135deg, #db2777, #be185d)'],
    'Runtime' => ['bg' => 'rgba(168, 85, 247, 0.12)', 'border' => 'rgba(168, 85, 247, 0.3)', 'text' => '#c084fc', 'icon_bg' => 'linear-gradient(135deg, #9333ea, #7e22ce)'],
    'Files' => ['bg' => 'rgba(234, 179, 8, 0.12)', 'border' => 'rgba(234, 179, 8, 0.3)', 'text' => '#facc15', 'icon_bg' => 'linear-gradient(135deg, #ca8a04, #a16207)'],
    'Email' => ['bg' => 'rgba(34, 197, 94, 0.12)', 'border' => 'rgba(34, 197, 94, 0.3)', 'text' => '#4ade80', 'icon_bg' => 'linear-gradient(135deg, #16a34a, #15803d)'],
    'Data' => ['bg' => 'rgba(6, 182, 212, 0.12)', 'border' => 'rgba(6, 182, 212, 0.3)', 'text' => '#22d3ee', 'icon_bg' => 'linear-gradient(135deg, #0891b2, #0e7490)'],
    'Developer' => ['bg' => 'rgba(249, 115, 22, 0.12)', 'border' => 'rgba(249, 115, 22, 0.3)', 'text' => '#fb923c', 'icon_bg' => 'linear-gradient(135deg, #ea580c, #c2410c)'],
    'DNS' => ['bg' => 'rgba(14, 165, 233, 0.12)', 'border' => 'rgba(14, 165, 233, 0.3)', 'text' => '#38bdf8', 'icon_bg' => 'linear-gradient(135deg, #0284c7, #0369a1)'],
    'Security' => ['bg' => 'rgba(239, 68, 68, 0.12)', 'border' => 'rgba(239, 68, 68, 0.3)', 'text' => '#f87171', 'icon_bg' => 'linear-gradient(135deg, #dc2626, #b91c1c)'],
    'Operations' => ['bg' => 'rgba(99, 102, 241, 0.12)', 'border' => 'rgba(99, 102, 241, 0.3)', 'text' => '#818cf8', 'icon_bg' => 'linear-gradient(135deg, #4f46e5, #4338ca)'],
];
?>

<style>
/* ==========================================================================
   ZodPanel Modules Page Dedicated Self-Contained Styles
   ========================================================================== */
.zod-modules-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 10px 20px 50px 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #e2e8f0;
}

/* ── Hero Banner ────────────────────────────────────────────────────────── */
.zod-modules-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
    background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.15), transparent 50%), linear-gradient(135deg, #13111f 0%, #0d0c15 100%);
    border: 1px solid rgba(139, 92, 246, 0.25);
    border-radius: 16px;
    padding: 26px 32px;
    margin-bottom: 24px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45), 0 0 25px rgba(139, 92, 246, 0.08);
}

.zod-modules-hero-main {
    flex: 1;
    min-width: 260px;
}

.zod-modules-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #a78bfa;
    background: rgba(139, 92, 246, 0.14);
    border: 1px solid rgba(139, 92, 246, 0.3);
    padding: 4px 10px;
    border-radius: 20px;
    margin-bottom: 10px;
}

.zod-modules-hero h2 {
    font-size: 26px;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: #ffffff;
    margin: 0 0 6px 0;
    line-height: 1.2;
}

.zod-modules-hero p {
    font-size: 14px;
    color: #94a3b8;
    margin: 0;
    line-height: 1.5;
}

.zod-modules-hero-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.zod-action-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    white-space: nowrap;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #ffffff !important;
}

.zod-action-button:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.25);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.3);
}

.zod-action-primary {
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
    border: 1px solid rgba(167, 139, 250, 0.4);
    box-shadow: 0 4px 16px rgba(139, 92, 246, 0.4);
}

.zod-action-primary:hover {
    background: linear-gradient(135deg, #9f75ff 0%, #7c3aed 100%);
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.6);
}

/* ── Metrics Bar ────────────────────────────────────────────────────────── */
.zod-modules-metrics {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}

.zod-metric {
    display: flex;
    align-items: center;
    gap: 14px;
    background: #110f1c;
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 12px;
    padding: 16px 18px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
    transition: all 0.2s ease;
}

.zod-metric:hover {
    border-color: rgba(139, 92, 246, 0.3);
    background: #151322;
    transform: translateY(-2px);
}

.zod-metric-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.zod-tone-blue .zod-metric-icon { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
.zod-tone-green .zod-metric-icon { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
.zod-tone-violet .zod-metric-icon { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); }
.zod-tone-orange .zod-metric-icon { background: rgba(249, 115, 22, 0.15); color: #fb923c; border: 1px solid rgba(249, 115, 22, 0.3); }

.zod-metric-body {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.zod-metric-body span {
    font-size: 12px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.zod-metric-body strong {
    font-size: 17px;
    font-weight: 800;
    color: #ffffff;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace;
}

/* ── Section Title ──────────────────────────────────────────────────────── */
.zod-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 30px 0 16px 0;
}

.zod-section-heading h3 {
    font-size: 18px;
    font-weight: 800;
    color: #ffffff;
    margin: 0;
    letter-spacing: -0.01em;
    display: flex;
    align-items: center;
    gap: 8px;
}

.zod-badge-count {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
    background: rgba(139, 92, 246, 0.18);
    color: #c4b5fd;
    border: 1px solid rgba(139, 92, 246, 0.3);
}

/* ── Modules Grid ───────────────────────────────────────────────────────── */
.zod-modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 16px;
}

.zod-module-card {
    background: #110f1c;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    padding: 20px;
    text-decoration: none !important;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.3);
    position: relative;
    overflow: hidden;
}

.zod-module-card:hover {
    background: #161324;
    border-color: rgba(139, 92, 246, 0.45);
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5), 0 0 25px rgba(139, 92, 246, 0.15);
}

.zod-module-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}

.zod-module-card-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    flex-shrink: 0;
}

.zod-module-group-chip {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 3px 8px;
    border-radius: 6px;
    border: 1px solid transparent;
}

.zod-module-card-body {
    flex: 1;
    margin-bottom: 14px;
}

.zod-module-card-body strong {
    display: block;
    font-size: 16px;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 6px;
    letter-spacing: -0.01em;
}

.zod-module-card-body small {
    display: block;
    font-size: 12.5px;
    color: #94a3b8;
    line-height: 1.5;
    min-height: 38px;
}

.zod-module-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.zod-module-card-meta {
    font-size: 11px;
    font-weight: 600;
    color: #cbd5e1;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    max-width: 80%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.zod-module-card-arrow {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    color: #cbd5e1;
    transition: all 0.2s ease;
}

.zod-module-card:hover .zod-module-card-arrow {
    background: var(--xtb-purple-primary, #8b5cf6);
    color: #ffffff;
    transform: translateX(3px);
}

/* ── Limits Grid ────────────────────────────────────────────────────────── */
.zod-modules-limits {
    background: #110f1c;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 24px 28px;
    margin-top: 32px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}

.zod-modules-limits h2 {
    font-size: 18px;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 16px 0;
    letter-spacing: -0.01em;
}

.zod-modules-limit-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
}

.zod-limit-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 10px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.zod-limit-item span {
    font-size: 11px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.zod-limit-item strong {
    font-size: 15px;
    font-weight: 700;
    color: #ffffff;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace;
}

/* ── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width: 992px) {
    .zod-modules-metrics {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .zod-modules-hero {
        padding: 20px;
        flex-direction: column;
        align-items: stretch;
    }
    .zod-modules-metrics {
        grid-template-columns: 1fr;
    }
    .zod-modules-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Toolbar Start -->
<div class="toolbar">
    <div class="toolbar-inner">
        <div class="toolbar-buttons">
            <a class="button button-secondary" href="/list/web/">
                <i class="fas fa-earth-americas icon-blue"></i> <?= tohtml(_('Websites')) ?>
            </a>
            <a class="button button-secondary" href="/list/db/">
                <i class="fas fa-database icon-green"></i> <?= tohtml(_('Databases')) ?>
            </a>
            <a class="button button-secondary" href="/list/dns/">
                <i class="fas fa-book-atlas icon-orange"></i> <?= tohtml(_('DNS')) ?>
            </a>
        </div>
    </div>
</div>
<!-- Toolbar End -->

<div class="zod-modules-page">
    <?php show_alert_message($_SESSION); ?>

    <!-- Hero Banner -->
    <section class="zod-modules-hero">
        <div class="zod-modules-hero-main">
            <span class="zod-modules-eyebrow"><i class="fas fa-cubes"></i> <?= tohtml(_('ZodPanel Platform')) ?></span>
            <h2><?= tohtml($zodPanelPackage ?: _('Current Package')) ?></h2>
            <p><?= tohtml(sprintf(_('%d enabled modules & developer tools for this hosting account'), count($zodPanelCards))) ?></p>
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

    <!-- Metrics Bar -->
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

    <!-- Section Heading -->
    <div class="zod-section-heading">
        <h3><i class="fas fa-grid-2"></i> <?= tohtml(_('Application & Service Modules')) ?></h3>
        <span class="zod-badge-count"><?= count($zodPanelCards) ?> <?= tohtml(_('Modules Active')) ?></span>
    </div>

    <!-- Modules Grid -->
    <div class="zod-modules-grid">
        <?php foreach ($zodPanelCards as $feature => $card): 
            $groupName = $card['group'] ?? 'Module';
            $styling = $groupColors[$groupName] ?? $groupColors['Operations'];
        ?>
            <a
                class="zod-module-card zod-module-<?= tohtml(preg_replace('/[^a-z0-9_-]/', '-', $feature)) ?>"
                href="<?= tohtml($card['href']) ?>"
                <?php if (!empty($card['target'])) { ?>target="<?= tohtml($card['target']) ?>" rel="noopener"<?php } ?>
            >
                <div class="zod-module-card-header">
                    <span class="zod-module-card-icon" style="background: <?= $styling['icon_bg'] ?>;">
                        <i class="fas <?= tohtml($card['icon']) ?>"></i>
                    </span>
                    <span class="zod-module-group-chip" style="background: <?= $styling['bg'] ?>; color: <?= $styling['text'] ?>; border-color: <?= $styling['border'] ?>;">
                        <?= tohtml($groupName) ?>
                    </span>
                </div>
                
                <div class="zod-module-card-body">
                    <strong><?= tohtml($card['title']) ?></strong>
                    <small><?= tohtml($card['description']) ?></small>
                </div>

                <div class="zod-module-card-footer">
                    <span class="zod-module-card-meta" title="<?= tohtml($card['meta']) ?>">
                        <i class="fas fa-info-circle" style="font-size: 10px; opacity: 0.7;"></i> <?= tohtml($card['meta']) ?>
                    </span>
                    <span class="zod-module-card-arrow"><i class="fas fa-arrow-right"></i></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Package Limits Section -->
    <?php if (!empty($zodPanelBlueprint['limits'])): ?>
        <section class="zod-modules-limits">
            <h2><i class="fas fa-sliders me-2"></i> <?= tohtml(_('Package Capacity & Limits')) ?></h2>
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

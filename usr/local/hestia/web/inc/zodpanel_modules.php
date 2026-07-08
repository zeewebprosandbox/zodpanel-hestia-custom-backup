<?php
function zodpanel_package_name_for_user(string $user, ?array $panelUser = null): string {
    $package = (string) ($panelUser['PACKAGE'] ?? '');
    if ($package !== '') {
        return preg_replace('/[^a-zA-Z0-9_.-]/', '', $package);
    }

    $output = [];
    exec(HESTIA_CMD . 'v-list-user ' . escapeshellarg($user) . ' json', $output, $return_var);
    if ($return_var !== 0) {
        return '';
    }

    $data = json_decode(implode('', $output), true) ?: [];
    $package = (string) ($data[$user]['PACKAGE'] ?? '');
    return preg_replace('/[^a-zA-Z0-9_.-]/', '', $package);
}

function zodpanel_package_blueprint(string $user, ?array $panelUser = null): array {
    $package = zodpanel_package_name_for_user($user, $panelUser);
    if ($package === '') {
        return [];
    }

    $file = '/usr/local/hestia/data/packages/' . $package . '.features.json';
    if (!is_file($file)) {
        return [];
    }

    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function zodpanel_package_features(string $user, ?array $panelUser = null): array {
    $features = zodpanel_package_blueprint($user, $panelUser)['features'] ?? [];
    return is_array($features) ? $features : [];
}

function zodpanel_command_available(string $command): bool {
    $command = preg_replace('/[^a-zA-Z0-9_.-]/', '', $command);
    if ($command === '') {
        return false;
    }

    return trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null')) !== '';
}

function zodpanel_system_feature_available(string $feature): bool {
    return match ($feature) {
        'nodejs' => zodpanel_command_available('node'),
        'python' => zodpanel_command_available('python3'),
        'composer' => zodpanel_command_available('composer'),
        default => true,
    };
}

function zodpanel_feature_enabled(string $user, string $feature, ?array $panelUser = null): bool {
    $features = zodpanel_package_features($user, $panelUser);
    if (array_key_exists($feature, $features)) {
        return (bool) $features[$feature] && zodpanel_system_feature_available($feature);
    }

    $fallbacks = [
        'websites' => (($panelUser['WEB_DOMAINS'] ?? '0') !== '0'),
        'file_manager' => !empty($_SESSION['FILE_MANAGER']) && $_SESSION['FILE_MANAGER'] === 'true',
        'php_selector' => true,
        'quick_apps' => true,
        'webmail' => (($panelUser['MAIL_DOMAINS'] ?? '0') !== '0'),
        'databases' => (($panelUser['DATABASES'] ?? '0') !== '0'),
        'phpmyadmin' => (($panelUser['DATABASES'] ?? '0') !== '0'),
        'backups' => (($panelUser['BACKUPS'] ?? '0') !== '0'),
        'cron' => (($panelUser['CRON_JOBS'] ?? '0') !== '0'),
        'terminal' => (($panelUser['SHELL'] ?? 'nologin') !== 'nologin'),
        'auto_dns' => (($panelUser['DNS_DOMAINS'] ?? '0') !== '0'),
        'auto_ssl' => (($panelUser['WEB_DOMAINS'] ?? '0') !== '0'),
        'force_https' => (($panelUser['WEB_DOMAINS'] ?? '0') !== '0'),
        'logs' => (($panelUser['WEB_DOMAINS'] ?? '0') !== '0'),
        'stats' => (($panelUser['WEB_DOMAINS'] ?? '0') !== '0'),
        'nodejs' => zodpanel_system_feature_available('nodejs'),
        'python' => zodpanel_system_feature_available('python'),
        'composer' => zodpanel_system_feature_available('composer'),
    ];

    return (bool) ($fallbacks[$feature] ?? false);
}

function zodpanel_first_web_domain(string $user): ?string {
    $entry = zodpanel_first_web_domain_entry($user);
    return $entry['domain'] ?? null;
}

function zodpanel_first_web_domain_entry(string $user): ?array {
    $output = [];
    exec(HESTIA_CMD . 'v-list-web-domains ' . escapeshellarg($user) . ' json', $output, $return_var);
    if ($return_var !== 0) {
        return null;
    }

    $domains = json_decode(implode('', $output), true) ?: [];
    foreach ($domains as $domain => $data) {
        if ($domain !== '') {
            return [
                'domain' => (string) $domain,
                'data' => is_array($data) ? $data : [],
            ];
        }
    }

    return null;
}

function zodpanel_limit_value(array $panelUser, string $usedKey, string $limitKey): array {
    $used = (string) ($panelUser[$usedKey] ?? '0');
    $limit = (string) ($panelUser[$limitKey] ?? '0');

    return [
        'used' => $used,
        'limit' => $limit === 'unlimited' ? '∞' : $limit,
        'is_unlimited' => $limit === 'unlimited',
    ];
}

function zodpanel_module_metrics(array $panelUser): array {
    return [
        'web' => [
            'label' => _('Websites'),
            'icon' => 'fa-earth-americas',
            'tone' => 'blue',
            'value' => zodpanel_limit_value($panelUser, 'U_WEB_DOMAINS', 'WEB_DOMAINS'),
        ],
        'mail' => [
            'label' => _('Mail'),
            'icon' => 'fa-envelope',
            'tone' => 'green',
            'value' => zodpanel_limit_value($panelUser, 'U_MAIL_DOMAINS', 'MAIL_DOMAINS'),
        ],
        'databases' => [
            'label' => _('Databases'),
            'icon' => 'fa-database',
            'tone' => 'violet',
            'value' => zodpanel_limit_value($panelUser, 'U_DATABASES', 'DATABASES'),
        ],
        'backups' => [
            'label' => _('Backups'),
            'icon' => 'fa-file-zipper',
            'tone' => 'orange',
            'value' => zodpanel_limit_value($panelUser, 'U_BACKUPS', 'BACKUPS'),
        ],
    ];
}

function zodpanel_module_cards(string $user, array $panelUser): array {
    $domainEntry = zodpanel_first_web_domain_entry($user);
    $domain = $domainEntry['domain'] ?? null;
    $domainData = $domainEntry['data'] ?? [];
    $webappUrl = $domain ? '/add/webapp/?' . http_build_query(['domain' => $domain]) : '/list/web/';
    $editWebUrl = $domain ? '/edit/web/?' . http_build_query(['domain' => $domain]) : '/list/web/';
    $logsUrl = $domain ? '/list/web-log/?' . http_build_query(['domain' => $domain, 'type' => 'error']) : '/list/web/';
    $statsEnabled = $domain && !empty($domainData['STATS']) && $domainData['STATS'] !== 'no';
    $statsScheme = (($domainData['SSL'] ?? 'no') === 'yes') ? 'https' : 'http';
    $statsUrl = $statsEnabled ? $statsScheme . '://' . $domain . '/vstats/' : '/list/web/';

    $cards = [
        'websites' => [
            'title' => _('Websites'),
            'description' => _('Create and manage hosted domains, aliases, templates, redirects, and document roots.'),
            'icon' => 'fa-earth-americas',
            'href' => '/list/web/',
            'meta' => _('Web domains and aliases'),
            'group' => _('Core'),
        ],
        'quick_apps' => [
            'title' => _('Quick Apps'),
            'description' => _('Install WordPress, Laravel, Drupal, Joomla, Nextcloud, and more using Hestia WebApp installers.'),
            'icon' => 'fa-rocket',
            'href' => $webappUrl,
            'meta' => $domain ? sprintf(_('Target: %s'), $domain) : _('Add a web domain first'),
            'group' => _('Apps'),
        ],
        'php_selector' => [
            'title' => _('PHP Selector'),
            'description' => _('Switch each website between available PHP-FPM templates.'),
            'icon' => 'fa-code',
            'href' => $editWebUrl,
            'meta' => _('PHP 7.0 - 8.4 templates available'),
            'group' => _('Runtime'),
        ],
        'file_manager' => [
            'title' => _('File Manager'),
            'description' => _('Browse, upload, edit, compress, and manage website files.'),
            'icon' => 'fa-folder-open',
            'href' => '/fm/domains/',
            'meta' => _('Browser file workspace'),
            'group' => _('Files'),
            'target' => '_blank',
        ],
        'webmail' => [
            'title' => _('Webmail'),
            'description' => _('Manage mail domains and open webmail for hosted inboxes.'),
            'icon' => 'fa-envelope-open-text',
            'href' => '/list/mail/',
            'meta' => _('Mail domains and accounts'),
            'group' => _('Email'),
        ],
        'databases' => [
            'title' => _('Databases'),
            'description' => _('Create databases, users, and open phpMyAdmin.'),
            'icon' => 'fa-database',
            'href' => '/list/db/',
            'meta' => _('MySQL and phpMyAdmin'),
            'group' => _('Data'),
        ],
        'phpmyadmin' => [
            'title' => _('phpMyAdmin'),
            'description' => _('Open phpMyAdmin with ZodPanel single sign-on for database work.'),
            'icon' => 'fa-table-cells',
            'href' => '/open/phpmyadmin/',
            'meta' => _('Database SSO'),
            'group' => _('Data'),
            'target' => '_blank',
        ],
        'terminal' => [
            'title' => _('Terminal'),
            'description' => _('Open the browser terminal for eligible developer packages.'),
            'icon' => 'fa-square-terminal',
            'href' => '/list/terminal/',
            'meta' => _('Shell access enabled by package'),
            'group' => _('Developer'),
        ],
        'nodejs' => [
            'title' => _('Node.js'),
            'description' => _('Prepare Node.js application hosting and npm workflows.'),
            'icon' => 'fa-server',
            'href' => $editWebUrl,
            'meta' => _('Application runtime module'),
            'group' => _('Runtime'),
        ],
        'python' => [
            'title' => _('Python'),
            'description' => _('Prepare Python application hosting for Django, Flask, and FastAPI projects.'),
            'icon' => 'fa-code-branch',
            'href' => $editWebUrl,
            'meta' => _('Application runtime module'),
            'group' => _('Runtime'),
        ],
        'composer' => [
            'title' => _('Composer'),
            'description' => _('Manage PHP dependencies for Laravel and Composer based applications.'),
            'icon' => 'fa-cubes',
            'href' => $webappUrl,
            'meta' => _('Laravel and PHP packages'),
            'group' => _('Apps'),
        ],
        'auto_dns' => [
            'title' => _('DNS Automation'),
            'description' => _('DNS zones and records are available for this plan.'),
            'icon' => 'fa-book-atlas',
            'href' => '/list/dns/',
            'meta' => _('Zones and records'),
            'group' => _('DNS'),
        ],
        'auto_ssl' => [
            'title' => _('SSL Automation'),
            'description' => _('Issue and repair SSL certificates for hosted websites.'),
            'icon' => 'fa-shield-halved',
            'href' => '/list/web/',
            'meta' => _('Let\'s Encrypt workflow'),
            'group' => _('Security'),
        ],
        'force_https' => [
            'title' => _('Force HTTPS'),
            'description' => _('Keep hosted sites on HTTPS after certificates are installed.'),
            'icon' => 'fa-lock',
            'href' => $editWebUrl,
            'meta' => _('Redirect policy'),
            'group' => _('Security'),
        ],
        'logs' => [
            'title' => _('Logs'),
            'description' => _('Review website access and error logs for the selected domain.'),
            'icon' => 'fa-binoculars',
            'href' => $logsUrl,
            'meta' => $domain ? sprintf(_('Target: %s'), $domain) : _('Add a web domain first'),
            'group' => _('Operations'),
        ],
        'stats' => [
            'title' => _('Web Statistics'),
            'description' => _('Open traffic statistics for hosted websites that have stats enabled.'),
            'icon' => 'fa-chart-line',
            'href' => $statsUrl,
            'meta' => $statsEnabled ? _('Visitor analytics') : _('Enable stats on a web domain'),
            'group' => _('Operations'),
            'target' => $statsEnabled ? '_blank' : null,
        ],
        'cron' => [
            'title' => _('Cron Jobs'),
            'description' => _('Schedule recurring commands for maintenance and automation.'),
            'icon' => 'fa-clock',
            'href' => '/list/cron/',
            'meta' => _('Scheduled tasks'),
            'group' => _('Operations'),
        ],
        'backups' => [
            'title' => _('Backups'),
            'description' => _('Create, browse, restore, and download service backups.'),
            'icon' => 'fa-file-zipper',
            'href' => '/list/backup/',
            'meta' => _('Backup restore points'),
            'group' => _('Operations'),
        ],
    ];

    return array_filter($cards, fn ($card, $feature) => zodpanel_feature_enabled($user, $feature, $panelUser), ARRAY_FILTER_USE_BOTH);
}

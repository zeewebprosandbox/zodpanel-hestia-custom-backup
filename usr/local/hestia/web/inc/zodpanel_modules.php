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

function zodpanel_feature_enabled(string $user, string $feature, ?array $panelUser = null): bool {
    $features = zodpanel_package_features($user, $panelUser);
    if (array_key_exists($feature, $features)) {
        return (bool) $features[$feature];
    }

    $fallbacks = [
        'file_manager' => !empty($_SESSION['FILE_MANAGER']) && $_SESSION['FILE_MANAGER'] === 'true',
        'php_selector' => true,
        'quick_apps' => true,
        'webmail' => (($panelUser['MAIL_DOMAINS'] ?? '0') !== '0'),
        'databases' => (($panelUser['DATABASES'] ?? '0') !== '0'),
        'backups' => (($panelUser['BACKUPS'] ?? '0') !== '0'),
        'terminal' => (($panelUser['SHELL'] ?? 'nologin') !== 'nologin'),
        'auto_dns' => (($panelUser['DNS_DOMAINS'] ?? '0') !== '0'),
        'auto_ssl' => (($panelUser['WEB_DOMAINS'] ?? '0') !== '0'),
        'force_https' => (($panelUser['WEB_DOMAINS'] ?? '0') !== '0'),
        'nodejs' => false,
        'python' => false,
        'composer' => false,
    ];

    return (bool) ($fallbacks[$feature] ?? false);
}

function zodpanel_first_web_domain(string $user): ?string {
    $output = [];
    exec(HESTIA_CMD . 'v-list-web-domains ' . escapeshellarg($user) . ' json', $output, $return_var);
    if ($return_var !== 0) {
        return null;
    }

    $domains = json_decode(implode('', $output), true) ?: [];
    foreach (array_keys($domains) as $domain) {
        if ($domain !== '') {
            return $domain;
        }
    }

    return null;
}

function zodpanel_module_cards(string $user, array $panelUser): array {
    $domain = zodpanel_first_web_domain($user);
    $domainQuery = $domain ? '?' . http_build_query(['domain' => $domain]) : '';
    $phpQuery = $domain ? '?' . http_build_query(['domain' => $domain]) : '';

    $cards = [
        'quick_apps' => [
            'title' => _('Quick Apps'),
            'description' => _('Install WordPress, Laravel, Drupal, Joomla, Nextcloud, and more using Hestia WebApp installers.'),
            'icon' => 'fa-rocket',
            'href' => $domain ? '/add/webapp/' . $domainQuery : '/list/web/',
            'meta' => $domain ? sprintf(_('Target: %s'), $domain) : _('Add a web domain first'),
        ],
        'php_selector' => [
            'title' => _('PHP Selector'),
            'description' => _('Switch each website between available PHP-FPM templates.'),
            'icon' => 'fa-code',
            'href' => $domain ? '/edit/web/' . $phpQuery : '/list/web/',
            'meta' => _('PHP 7.0 - 8.4 templates available'),
        ],
        'file_manager' => [
            'title' => _('File Manager'),
            'description' => _('Browse, upload, edit, compress, and manage website files.'),
            'icon' => 'fa-folder-open',
            'href' => '/fm/',
            'meta' => _('Browser file workspace'),
        ],
        'webmail' => [
            'title' => _('Webmail'),
            'description' => _('Manage mail domains and open webmail for hosted inboxes.'),
            'icon' => 'fa-envelope-open-text',
            'href' => '/list/mail/',
            'meta' => _('Mail domains and accounts'),
        ],
        'databases' => [
            'title' => _('Databases'),
            'description' => _('Create databases, users, and open phpMyAdmin.'),
            'icon' => 'fa-database',
            'href' => '/list/db/',
            'meta' => _('MySQL and phpMyAdmin'),
        ],
        'terminal' => [
            'title' => _('Terminal'),
            'description' => _('Open the browser terminal for eligible developer packages.'),
            'icon' => 'fa-square-terminal',
            'href' => '/list/terminal/',
            'meta' => _('Shell access enabled by package'),
        ],
        'nodejs' => [
            'title' => _('Node.js'),
            'description' => _('Prepare Node.js application hosting and npm workflows.'),
            'icon' => 'fa-server',
            'href' => $domain ? '/edit/web/' . $phpQuery : '/list/web/',
            'meta' => _('Application runtime module'),
        ],
        'python' => [
            'title' => _('Python'),
            'description' => _('Prepare Python application hosting for Django, Flask, and FastAPI projects.'),
            'icon' => 'fa-code-branch',
            'href' => $domain ? '/edit/web/' . $phpQuery : '/list/web/',
            'meta' => _('Application runtime module'),
        ],
        'composer' => [
            'title' => _('Composer'),
            'description' => _('Manage PHP dependencies for Laravel and Composer based applications.'),
            'icon' => 'fa-cubes',
            'href' => $domain ? '/add/webapp/?' . http_build_query(['app' => 'Laravel', 'domain' => $domain]) : '/list/web/',
            'meta' => _('Laravel and PHP packages'),
        ],
        'auto_dns' => [
            'title' => _('DNS Automation'),
            'description' => _('DNS zones and records are available for this plan.'),
            'icon' => 'fa-book-atlas',
            'href' => '/list/dns/',
            'meta' => _('Zones and records'),
        ],
        'auto_ssl' => [
            'title' => _('SSL Automation'),
            'description' => _('Issue and repair SSL certificates for hosted websites.'),
            'icon' => 'fa-shield-halved',
            'href' => '/list/web/',
            'meta' => _('Let\'s Encrypt workflow'),
        ],
        'backups' => [
            'title' => _('Backups'),
            'description' => _('Create, browse, restore, and download service backups.'),
            'icon' => 'fa-file-zipper',
            'href' => '/list/backup/',
            'meta' => _('Backup restore points'),
        ],
    ];

    return array_filter($cards, fn ($card, $feature) => zodpanel_feature_enabled($user, $feature, $panelUser), ARRAY_FILTER_USE_BOTH);
}
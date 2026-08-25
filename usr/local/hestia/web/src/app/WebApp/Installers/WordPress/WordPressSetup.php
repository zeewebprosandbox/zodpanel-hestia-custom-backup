<?php

declare(strict_types=1);

namespace Hestia\WebApp\Installers\WordPress;

use Hestia\System\Util;
use Hestia\WebApp\BaseSetup;
use Hestia\WebApp\InstallationTarget\InstallationTarget;

class WordPressSetup extends BaseSetup {
    protected array $info = [
        "name" => "WordPress",
        "group" => "cms",
        "version" => "latest",
        "thumbnail" => "wordpress-logo.svg",
    ];

    protected array $config = [
        "form" => [
            "site_name" => ["type" => "text", "value" => "WordPress Blog"],
            "username" => ["value" => "wpadmin"],
            "email" => "text",
            "password" => "password",
            "language" => [
                "type" => "select",
                "value" => "en_US",
                "options" => [
                    "cs_CZ" => "Czech",
                    "de_DE" => "German",
                    "es_ES" => "Spanish",
                    "en_US" => "English",
                    "fr_FR" => "French",
                    "hu_HU" => "Hungarian",
                    "it_IT" => "Italian",
                    "ja" => "Japanese",
                    "nl_NL" => "Dutch",
                    "pt_PT" => "Portuguese",
                    "pt_BR" => "Portuguese (Brazil)",
                    "sk_SK" => "Slovak",
                    "sr_RS" => "Serbian",
                    "sv_SE" => "Swedish",
                    "tr_TR" => "Turkish",
                    "ru_RU" => "Russian",
                    "uk" => "Ukrainian",
                    "zh-CN" => "Simplified Chinese (China)",
                    "zh_TW" => "Traditional Chinese",
                ],
            ],
        ],
        "database" => true,
        "resources" => [
            "wp" => ["src" => "https://wordpress.org/latest.tar.gz"],
        ],
        "server" => [
            "nginx" => [
                "template" => "wordpress",
            ],
            "php" => [
                "supported" => ["7.4", "8.0", "8.1", "8.2", "8.3", "8.4", "8.5"],
            ],
        ],
    ];

    protected function setupApplication(InstallationTarget $target, array $options = null): void {
        $this->appcontext->runWp($options["php_version"], [
            "config",
            "create",
            "--dbname=" . $target->database->name,
            "--dbuser=" . $target->database->user,
            "--dbpass=" . $target->database->password,
            "--dbhost=" . $target->database->host,
            "--dbprefix=" . "wp_" . Util::generateString(5, false) . "_",
            "--dbcharset=utf8mb4",
            "--locale=" . $options["language"],
            "--path=" . $target->getDocRoot(),
            "--force",
        ]);

        $siteUrl = $target->getUrl() . (!empty($options["install_directory"]) ? "/" . $options["install_directory"] : "");

        try {
            $this->appcontext->runWp($options["php_version"], [
                "core",
                "install",
                "--url=" . $siteUrl,
                "--title=" . $options["site_name"],
                "--admin_user=" . $options["username"],
                "--admin_password=" . $options["password"],
                "--admin_email=" . $options["email"],
                "--path=" . $target->getDocRoot(),
                "--skip-email",
            ]);
        } catch (\Throwable $e) {
            $this->appcontext->sendPostRequest(
                $siteUrl . "/wp-admin/install.php?step=2",
                [
                    "weblog_title" => $options["site_name"],
                    "user_name" => $options["username"],
                    "admin_password" => $options["password"],
                    "admin_password2" => $options["password"],
                    "admin_email" => $options["email"],
                ],
            );
        }
    }
}
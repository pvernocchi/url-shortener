<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;

class Upgrade
{
    public static function getCodeVersion(): string
    {
        if (defined('APP_VERSION')) {
            return (string)APP_VERSION;
        }

        $versionFile = ROOT_PATH . '/VERSION';
        if (!file_exists($versionFile)) {
            return '0.0.0';
        }

        $version = trim((string)file_get_contents($versionFile));
        return $version !== '' ? $version : '0.0.0';
    }

    public static function needsVersionUpdate(string $codeVersion, ?string $storedVersion): bool
    {
        return trim($codeVersion) !== trim((string)$storedVersion);
    }

    public static function getStoredVersion(): string
    {
        try {
            $settingModel = new Setting();
            return (string)$settingModel->get('app_version', '');
        } catch (\Throwable) {
            return '';
        }
    }

    public static function syncVersion(): void
    {
        $codeVersion   = self::getCodeVersion();
        $storedVersion = self::getStoredVersion();

        if (!self::needsVersionUpdate($codeVersion, $storedVersion)) {
            return;
        }

        $settingModel = new Setting();
        $settingModel->set('app_version', $codeVersion);
    }

    public static function getLastMigrationFilename(): string
    {
        try {
            $prefix = Config::get('db.prefix', 'us_');
            $row = Database::getInstance()->fetch(
                "SELECT `filename` FROM `{$prefix}migrations` ORDER BY `id` DESC LIMIT 1"
            );
            return (string)($row['filename'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    public static function runPendingMigrations(): array
    {
        $db       = Database::getInstance();
        $executed = (new Migration())->run($db, ROOT_PATH . '/migrations');
        self::syncVersion();
        return $executed;
    }
}

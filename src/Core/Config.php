<?php
declare(strict_types=1);

namespace App\Core;

class Config
{
    private static ?array $data = null;
    private static bool $installMode = false;

    public static function setInstallMode(bool $mode): void
    {
        self::$installMode = $mode;
    }

    public static function load(): void
    {
        $path = ROOT_PATH . '/config/config.php';
        if (!file_exists($path)) {
            if (self::$installMode) {
                self::$data = [];
                return;
            }
            throw new \RuntimeException('Config file not found. Please run the installer.');
        }
        self::$data = require $path;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$data === null) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$data;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        if (self::$data === null) {
            self::$data = [];
        }

        $keys = explode('.', $key);
        $data = &self::$data;

        foreach ($keys as $segment) {
            if (!isset($data[$segment]) || !is_array($data[$segment])) {
                $data[$segment] = [];
            }
            $data = &$data[$segment];
        }

        $data = $value;
    }

    public static function all(): array
    {
        if (self::$data === null) {
            self::load();
        }
        return self::$data ?? [];
    }

    public static function reload(): void
    {
        self::$data = null;
    }
}

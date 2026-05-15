<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Config;

class Setting
{
    private Database $db;
    private string $table;
    private static ?array $cache = null;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->table = Config::get('db.prefix', 'us_') . 'settings';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (self::$cache === null) {
            $this->loadAll();
        }
        return self::$cache[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $strValue = is_array($value) ? json_encode($value) : (string)$value;
        $this->db->query(
            "INSERT INTO `{$this->table}` (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()",
            [$key, $strValue, $strValue]
        );
        // Invalidate cache
        self::$cache[$key] = $strValue;
    }

    public function all(): array
    {
        if (self::$cache === null) {
            $this->loadAll();
        }
        return self::$cache ?? [];
    }

    private function loadAll(): void
    {
        $rows = $this->db->fetchAll("SELECT `key`, `value` FROM `{$this->table}`");
        self::$cache = [];
        foreach ($rows as $row) {
            self::$cache[$row['key']] = $row['value'];
        }
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}

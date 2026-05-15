<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class UserSetting
{
    private Database $db;
    private string $table;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->table = Config::get('db.prefix', 'us_') . 'user_settings';
    }

    public function get(int $userId, string $key, mixed $default = null): mixed
    {
        $row = $this->db->fetch(
            "SELECT `value` FROM `{$this->table}` WHERE `user_id` = ? AND `key` = ?",
            [$userId, $key]
        );
        return $row['value'] ?? $default;
    }

    public function set(int $userId, string $key, string $value): void
    {
        $this->db->query(
            "INSERT INTO `{$this->table}` (`user_id`, `key`, `value`) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()",
            [$userId, $key, $value]
        );
    }

    public function isEnabled(int $userId, string $key, bool $default = false): bool
    {
        $defaultValue = $default ? '1' : '0';
        return (string)$this->get($userId, $key, $defaultValue) === '1';
    }

    public function ensureDefaultsForUser(int $userId): void
    {
        $this->set($userId, 'mfa_totp_enabled', '0');
        $this->set($userId, 'profile_edit_enabled', '1');
    }
}

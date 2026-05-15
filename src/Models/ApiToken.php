<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Config;

class ApiToken
{
    public const TOKEN_PREFIX = 'usk_';

    private Database $db;
    private string $table;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->table = Config::get('db.prefix', 'us_') . 'api_tokens';
    }

    /**
     * Create a new API token. Returns ['id' => int, 'token' => raw_token_string].
     */
    public function create(int $userId, string $name, array $scopes = ['read', 'write']): array
    {
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = self::hashToken($rawToken);

        $id = $this->db->insert($this->table, [
            'user_id'    => $userId,
            'name'       => $name,
            'token_hash' => $tokenHash,
            'scopes'     => implode(',', $scopes),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['id' => $id, 'token' => $rawToken];
    }

    public function findByToken(string $rawToken): ?array
    {
        $hash = self::hashToken($rawToken);
        $row  = $this->db->fetch(
            "SELECT * FROM `{$this->table}` WHERE `token_hash` = ? AND `revoked_at` IS NULL",
            [$hash]
        );
        if ($row) {
            $this->db->update($this->table, ['last_used_at' => date('Y-m-d H:i:s')], '`id` = ?', [$row['id']]);
        }
        return $row ?: null;
    }

    public function revokeByUser(int $userId, int $tokenId): bool
    {
        return $this->db->update(
            $this->table,
            ['revoked_at' => date('Y-m-d H:i:s')],
            '`id` = ? AND `user_id` = ?',
            [$tokenId, $userId]
        ) > 0;
    }

    public function allByUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->table}` WHERE `user_id` = ? ORDER BY `created_at` DESC",
            [$userId]
        );
    }

    public function allWithUsers(): array
    {
        $userTable = Config::get('db.prefix', 'us_') . 'users';
        return $this->db->fetchAll(
            "SELECT t.`id`, t.`name`, t.`scopes`, t.`last_used_at`, t.`revoked_at`, t.`created_at`,
                    u.`email` AS `user_email`, u.`name` AS `user_name`
             FROM `{$this->table}` t
             LEFT JOIN `{$userTable}` u ON u.`id` = t.`user_id`
             ORDER BY t.`created_at` DESC"
        );
    }

    public function createHashed(int $userId, string $name, string $tokenHash, string $scopes): int
    {
        return $this->db->insert($this->table, [
            'user_id'    => $userId,
            'name'       => $name,
            'token_hash' => $tokenHash,
            'scopes'     => $scopes,
            'created_at' => date('Y-m-d H:i:s'),
            'revoked_at' => null,
        ]);
    }

    public function revoke(int $id): bool
    {
        return $this->db->update(
            $this->table,
            ['revoked_at' => date('Y-m-d H:i:s')],
            '`id` = ?',
            [$id]
        ) > 0;
    }

    public function deleteById(int $id): bool
    {
        return $this->db->delete($this->table, '`id` = ?', [$id]) > 0;
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}

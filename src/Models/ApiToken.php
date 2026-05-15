<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Config;

class ApiToken
{
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
        $tokenHash = hash('sha256', $rawToken);

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
        $hash = hash('sha256', $rawToken);
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
}

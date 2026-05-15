<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class WebAuthnCredential
{
    private Database $db;
    private string $table;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->table = Config::get('db.prefix', 'us_') . 'webauthn_credentials';
    }

    public function findByCredentialId(string $credentialId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM `{$this->table}` WHERE `credential_id` = ?",
            [$credentialId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function findByUserId(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->table}` WHERE `user_id` = ? ORDER BY `created_at` ASC",
            [$userId]
        );
    }

    public function create(array $data): int
    {
        return $this->db->insert($this->table, $data);
    }

    public function delete(int $id, int $userId): bool
    {
        return $this->db->delete(
            $this->table,
            '`id` = ? AND `user_id` = ?',
            [$id, $userId]
        ) > 0;
    }

    public function updateSignCount(int $id, int $signCount): void
    {
        $this->db->update(
            $this->table,
            ['sign_count' => $signCount],
            '`id` = ?',
            [$id]
        );
    }

    /** Return all credential IDs (base64url) for a user. */
    public function credentialIdsForUser(int $userId): array
    {
        $rows = $this->findByUserId($userId);
        return array_column($rows, 'credential_id');
    }
}

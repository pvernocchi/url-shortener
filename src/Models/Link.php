<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Config;

class Link
{
    private Database $db;
    private string $table;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->table = Config::get('db.prefix', 'us_') . 'links';
    }

    public function create(array $data): int
    {
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');
        return $this->db->insert($this->table, $data);
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM `{$this->table}` WHERE `id` = ?", [$id]);
    }

    public function findByCode(string $code): ?array
    {
        return $this->db->fetch("SELECT * FROM `{$this->table}` WHERE `short_code` = ?", [$code]);
    }

    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update($this->table, $data, '`id` = ?', [$id]) > 0;
    }

    public function delete(int $id): bool
    {
        return $this->db->delete($this->table, '`id` = ?', [$id]) > 0;
    }

    public function allByOwner(int $ownerId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->table}` WHERE `owner_id` = ? ORDER BY `created_at` DESC LIMIT ? OFFSET ?",
            [$ownerId, $perPage, $offset]
        );
    }

    public function all(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->table}` ORDER BY `created_at` DESC LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
    }

    public function countByOwner(int $ownerId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM `{$this->table}` WHERE `owner_id` = ?",
            [$ownerId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function countAll(): int
    {
        $row = $this->db->fetch("SELECT COUNT(*) as cnt FROM `{$this->table}`");
        return (int)($row['cnt'] ?? 0);
    }

    public function countActive(): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM `{$this->table}` WHERE `is_active` = 1"
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function codeExists(string $code): bool
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM `{$this->table}` WHERE `short_code` = ?",
            [$code]
        );
        return (int)($row['cnt'] ?? 0) > 0;
    }

    public function incrementClickCount(int $id): void
    {
        $this->db->query(
            "UPDATE `{$this->table}` SET `click_count` = `click_count` + 1, `updated_at` = ? WHERE `id` = ?",
            [date('Y-m-d H:i:s'), $id]
        );
    }

    public function totalClicks(): int
    {
        $row = $this->db->fetch("SELECT SUM(`click_count`) as total FROM `{$this->table}`");
        return (int)($row['total'] ?? 0);
    }

    public function recent(int $limit = 5): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->table}` ORDER BY `created_at` DESC LIMIT ?",
            [$limit]
        );
    }

    public function deactivateExpired(): int
    {
        $stmt = $this->db->query(
            "UPDATE `{$this->table}` SET `is_active` = 0, `updated_at` = ? 
             WHERE `is_active` = 1 AND `expires_at` IS NOT NULL AND `expires_at` < ?",
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        return $stmt->rowCount();
    }
}

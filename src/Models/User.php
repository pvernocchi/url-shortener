<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Config;

class User
{
    private Database $db;
    private string $table;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->table = Config::get('db.prefix', 'us_') . 'users';
    }

    public function create(array $data): int
    {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
            unset($data['password']);
        }
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');
        return $this->db->insert($this->table, $data);
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM `{$this->table}` WHERE `id` = ?", [$id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch("SELECT * FROM `{$this->table}` WHERE `email` = ?", [$email]);
    }

    public function update(int $id, array $data): bool
    {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
            unset($data['password']);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update($this->table, $data, '`id` = ?', [$id]) > 0;
    }

    public function all(): array
    {
        return $this->db->fetchAll("SELECT * FROM `{$this->table}` ORDER BY `created_at` DESC");
    }

    public function countAdmins(): int
    {
        $row = $this->db->fetch("SELECT COUNT(*) AS `c` FROM `{$this->table}` WHERE `role` = 'admin'");
        return (int)($row['c'] ?? 0);
    }

    /**
     * Run $fn inside a transaction that holds row locks on every admin row and
     * on the target user, so admin-count checks and the following mutation are
     * serialized against concurrent demotions/deletions.
     * $fn receives the freshly locked user row (or null) and the admin count.
     */
    public function withAdminLock(int $id, callable $fn): mixed
    {
        $this->db->beginTransaction();
        try {
            $admins = $this->db->fetchAll(
                "SELECT `id` FROM `{$this->table}` WHERE `role` = 'admin' ORDER BY `id` FOR UPDATE"
            );
            $user = $this->db->fetch("SELECT * FROM `{$this->table}` WHERE `id` = ? FOR UPDATE", [$id]);
            $result = $fn($user, count($admins));
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        return $this->db->delete($this->table, '`id` = ?', [$id]) > 0;
    }

    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function incrementLoginAttempts(int $id): void
    {
        $this->db->query(
            "UPDATE `{$this->table}` SET `login_attempts` = `login_attempts` + 1, `updated_at` = ? WHERE `id` = ?",
            [date('Y-m-d H:i:s'), $id]
        );
    }

    public function resetLoginAttempts(int $id): void
    {
        $this->db->update($this->table, [
            'login_attempts' => 0,
            'locked_until'   => null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ], '`id` = ?', [$id]);
    }

    public function lockUser(int $id, int $minutes): void
    {
        $this->db->update($this->table, [
            'locked_until' => date('Y-m-d H:i:s', time() + $minutes * 60),
            'updated_at'   => date('Y-m-d H:i:s'),
        ], '`id` = ?', [$id]);
    }

    public function setTotpSecret(int $id, string $secret): void
    {
        $this->db->update($this->table, [
            'mfa_totp_secret' => $secret,
            'updated_at'      => date('Y-m-d H:i:s'),
        ], '`id` = ?', [$id]);
    }

    public function clearTotpSecret(int $id): void
    {
        $this->db->update($this->table, [
            'mfa_totp_secret' => null,
            'updated_at'      => date('Y-m-d H:i:s'),
        ], '`id` = ?', [$id]);
    }
}

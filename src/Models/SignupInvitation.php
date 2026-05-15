<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class SignupInvitation
{
    private Database $db;
    private string $table;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->table = Config::get('db.prefix', 'us_') . 'signup_invitations';
    }

    public function create(array $data): int
    {
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        return $this->db->insert($this->table, $data);
    }

    public function findValidByToken(string $token): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM `{$this->table}` WHERE `token` = ? AND `used_at` IS NULL AND `expires_at` > ?",
            [$token, date('Y-m-d H:i:s')]
        );
    }

    public function markUsed(int $id): void
    {
        $this->db->update($this->table, ['used_at' => date('Y-m-d H:i:s')], '`id` = ?', [$id]);
    }
}

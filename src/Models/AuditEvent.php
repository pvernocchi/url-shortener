<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

class AuditEvent
{
    private Database $db;
    private string $table;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->table = Config::get('db.prefix', 'us_') . 'audit_events';
    }

    public function recordSettingChange(
        Request $req,
        string $scope,
        string $key,
        ?string $oldValue,
        ?string $newValue,
        ?int $targetUserId = null
    ): void {
        if ($oldValue === $newValue) {
            return;
        }

        $actorUserId = (int)Session::get('user_id', 0);
        $this->db->insert($this->table, [
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'target_user_id'=> $targetUserId,
            'scope'         => $scope,
            'event_key'     => $key,
            'old_value'     => $oldValue,
            'new_value'     => $newValue,
            'ip_address'    => substr($req->ip(), 0, 45),
            'user_agent'    => substr((string)$req->userAgent(), 0, 500),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }
}

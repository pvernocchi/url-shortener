<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Config;

class ClickEvent
{
    private Database $db;
    private string $table;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->table = Config::get('db.prefix', 'us_') . 'click_events';
    }

    public function record(array $data): int
    {
        $data['clicked_at'] = $data['clicked_at'] ?? date('Y-m-d H:i:s');
        return $this->db->insert($this->table, $data);
    }

    public function countByLink(int $linkId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM `{$this->table}` WHERE `link_id` = ?",
            [$linkId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function recentByLink(int $linkId, int $limit = 100): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->table}` WHERE `link_id` = ? ORDER BY `clicked_at` DESC LIMIT ?",
            [$linkId, $limit]
        );
    }

    public function referrerSummary(int $linkId): array
    {
        return $this->db->fetchAll(
            "SELECT `referer_domain`, COUNT(*) as cnt 
             FROM `{$this->table}` 
             WHERE `link_id` = ? AND `referer_domain` IS NOT NULL AND `referer_domain` != ''
             GROUP BY `referer_domain` 
             ORDER BY cnt DESC 
             LIMIT 20",
            [$linkId]
        );
    }

    public function dailyCountsByLink(int $linkId, int $days = 30): array
    {
        return $this->db->fetchAll(
            "SELECT DATE(`clicked_at`) as day, COUNT(*) as cnt 
             FROM `{$this->table}` 
             WHERE `link_id` = ? AND `clicked_at` >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(`clicked_at`) 
             ORDER BY day ASC",
            [$linkId, $days]
        );
    }

    public function deleteOlderThan(int $days): int
    {
        $stmt = $this->db->query(
            "DELETE FROM `{$this->table}` WHERE `clicked_at` < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        return $stmt->rowCount();
    }
}

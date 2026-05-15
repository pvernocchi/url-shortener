<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Upgrade;
use App\Core\Request;
use App\Core\Response;
use App\Models\ClickEvent;
use App\Models\Link;

class CronController
{
    public function cleanup(Request $req, Response $res): void
    {
        $cronKey     = Config::get('cron.key', '');
        $providedKey = $req->get('key', '');

        if (empty($cronKey) || !hash_equals($cronKey, $providedKey)) {
            $res->json(['error' => 'Unauthorized.'], 401);
        }

        $results = [];

        // Delete old click events
        $retentionDays = (int)Config::get('analytics.retention_days', 365);
        $clickModel    = new ClickEvent();
        $deletedClicks = $clickModel->deleteOlderThan($retentionDays);
        $results['deleted_click_events'] = $deletedClicks;

        // Deactivate expired links
        $linkModel      = new Link();
        $deactivated    = $linkModel->deactivateExpired();
        $results['deactivated_links'] = $deactivated;

        $results['executed_at'] = date('Y-m-d H:i:s');
        $res->json(['success' => true, 'results' => $results]);
    }

    public function upgrade(Request $req, Response $res): void
    {
        $cronKey     = Config::get('cron.key', '');
        $providedKey = (string)$req->get('key', '');

        if (empty($cronKey) || !hash_equals($cronKey, $providedKey)) {
            $res->json(['error' => 'Forbidden.'], 403);
        }

        try {
            $executed = Upgrade::runPendingMigrations();
            $res->json([
                'executed' => $executed,
                'version'  => Upgrade::getCodeVersion(),
            ]);
        } catch (\Throwable $e) {
            $res->json(['error' => $e->getMessage()], 500);
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Models\ClickEvent;
use App\Models\Link;
use App\Services\Analytics;

class RedirectController
{
    public function redirect(Request $req, Response $res, array $params): void
    {
        $code = $params['code'] ?? '';
        if (empty($code)) {
            $res->notFound();
        }

        $linkModel = new Link();
        $link      = $linkModel->findByCode($code);

        if (!$link) {
            $res->notFound();
        }

        // Check active
        if (!(bool)$link['is_active']) {
            $res->notFound();
        }

        // Check expiry
        if (!empty($link['expires_at']) && strtotime($link['expires_at']) < time()) {
            $res->gone();
        }

        // Check max clicks
        if (!empty($link['max_clicks']) && (int)$link['click_count'] >= (int)$link['max_clicks']) {
            $res->gone();
        }

        // Rate limit for redirects
        $rateLimiter = new RateLimiter();
        $rateLimit   = (int)Config::get('security.rate_limit_redirect', 300);
        if (!$rateLimiter->check('redirect', $req->ip(), $rateLimit, 3600)) {
            // Just continue – rate limiting redirects would break legitimate users
        }
        $rateLimiter->increment('redirect', $req->ip());

        // Record analytics
        try {
            $analytics  = new Analytics();
            $clickModel = new ClickEvent();
            $analytics->record($req, $linkModel, $clickModel, (int)$link['id'], Config::all());
        } catch (\Throwable $e) {
            // Never fail a redirect due to analytics
        }

        // Increment click count
        $linkModel->incrementClickCount((int)$link['id']);

        // Redirect
        $redirectType = in_array((int)$link['redirect_type'], [301, 302], true)
            ? (int)$link['redirect_type']
            : 302;

        $res->redirect($link['original_url'], $redirectType);
    }
}

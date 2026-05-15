<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Models\ClickEvent;
use App\Models\Link;

class Analytics
{
    public function record(
        Request    $request,
        Link       $linkModel,
        ClickEvent $clickModel,
        int        $linkId,
        array      $config
    ): void {
        $analyticsConfig = $config['analytics'] ?? [];
        if (!($analyticsConfig['enabled'] ?? true)) {
            return;
        }

        $ip = $request->ip();

        // Anonymize IP
        $ipHash = null;
        if ($analyticsConfig['anonymize_ip'] ?? true) {
            $ipHash = hash('sha256', $ip . ($config['app']['secret'] ?? ''));
        } else {
            $ipHash = hash('sha256', $ip);
        }

        $ua     = substr($request->userAgent(), 0, 500);
        $isBot  = $this->detectBot($ua);

        $referer = $request->referer();
        $refererDomain = '';
        if ($referer) {
            $parsed = parse_url($referer);
            $refererDomain = strtolower($parsed['host'] ?? '');
        }

        $clickModel->record([
            'link_id'        => $linkId,
            'clicked_at'     => date('Y-m-d H:i:s'),
            'ip_hash'        => $ipHash,
            'user_agent'     => $ua ?: null,
            'referer'        => substr($referer, 0, 500) ?: null,
            'referer_domain' => $refererDomain ?: null,
            'country'        => null, // GeoIP not included in base package
            'is_bot'         => $isBot ? 1 : 0,
        ]);
    }

    private function detectBot(string $userAgent): bool
    {
        if (empty($userAgent)) {
            return false;
        }
        $ua = strtolower($userAgent);
        $botPatterns = ['bot', 'crawler', 'spider', 'slurp', 'facebookexternalhit', 'ia_archiver', 'wget', 'curl'];
        foreach ($botPatterns as $pattern) {
            if (str_contains($ua, $pattern)) {
                return true;
            }
        }
        return false;
    }
}

<?php
declare(strict_types=1);

namespace App\Core;

class RateLimiter
{
    private string $cacheDir;

    public function __construct()
    {
        $this->cacheDir = ROOT_PATH . '/storage/cache';
    }

    private function filePath(string $action, string $ip): string
    {
        $ipHash = substr(hash('sha256', $ip), 0, 16);
        return $this->cacheDir . '/rl_' . preg_replace('/[^a-z0-9_]/', '_', $action) . '_' . $ipHash . '.json';
    }

    public function check(string $action, string $ip, int $limit, int $windowSeconds): bool
    {
        $data = $this->load($action, $ip);
        $now  = time();
        $data = array_filter($data, fn($ts) => $ts > $now - $windowSeconds);
        return count($data) < $limit;
    }

    public function increment(string $action, string $ip): void
    {
        $data  = $this->load($action, $ip);
        $now   = time();
        $data[] = $now;
        // prune old entries (keep last 24h)
        $data = array_filter($data, fn($ts) => $ts > $now - 86400);
        $this->save($action, $ip, array_values($data));
    }

    private function load(string $action, string $ip): array
    {
        $file = $this->filePath($action, $ip);
        if (!file_exists($file)) {
            return [];
        }

        $fp = fopen($file, 'r');
        if ($fp === false) {
            return [];
        }

        $data = [];
        if (flock($fp, LOCK_SH)) {
            $content = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            if ($content !== false && $content !== '') {
                $decoded = json_decode($content, true);
                $data    = is_array($decoded) ? $decoded : [];
            }
        }
        fclose($fp);
        return $data;
    }

    private function save(string $action, string $ip, array $data): void
    {
        $file = $this->filePath($action, $ip);
        $fp   = fopen($file, 'c');
        if ($fp === false) {
            return;
        }
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode(array_values($data)));
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    public function prune(int $maxAgeSeconds = 86400): void
    {
        foreach (glob($this->cacheDir . '/rl_*.json') ?: [] as $file) {
            if (filemtime($file) < time() - $maxAgeSeconds) {
                @unlink($file);
            }
        }
    }
}

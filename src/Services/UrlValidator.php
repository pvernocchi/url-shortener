<?php
declare(strict_types=1);

namespace App\Services;

class UrlValidator
{
    public function validate(string $url, array $config): bool|string
    {
        $maxLength = $config['max_url_length'] ?? 2048;
        if (strlen($url) > $maxLength) {
            return "URL exceeds maximum length of $maxLength characters.";
        }

        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            return 'Invalid URL format.';
        }

        $allowedProtocols = $config['allowed_protocols'] ?? ['http', 'https'];
        if (!in_array(strtolower($parsed['scheme']), $allowedProtocols, true)) {
            return 'URL protocol not allowed. Allowed: ' . implode(', ', $allowedProtocols);
        }

        $host = strtolower($parsed['host']);

        // Reject localhost and private IPs
        if ($this->isPrivateOrLocal($host)) {
            return 'URLs pointing to localhost or private networks are not allowed.';
        }

        $blockedDomains = $config['blocked_domains'] ?? [];
        foreach ($blockedDomains as $blocked) {
            if ($host === strtolower($blocked) || str_ends_with($host, '.' . strtolower($blocked))) {
                return "Domain '$host' is blocked.";
            }
        }

        return true;
    }

    public function normalize(string $url): string
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return $url;
        }

        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $host   = strtolower($parsed['host'] ?? '');
        $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path   = $parsed['path'] ?? '';
        $query  = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $frag   = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

        return $scheme . '://' . $host . $port . $path . $query . $frag;
    }

    private function isPrivateOrLocal(string $host): bool
    {
        if (in_array($host, ['localhost', '::1', '0.0.0.0'], true)) {
            return true;
        }

        // Strip trailing dots
        $host = rtrim($host, '.');

        // Check for .local TLD
        if (str_ends_with($host, '.local')) {
            return true;
        }

        // Check if it's an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Check for private/loopback ranges
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        return false;
    }
}

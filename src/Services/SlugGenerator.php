<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Link;

class SlugGenerator
{
    private const CHARS    = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const RESERVED = [
        'admin', 'install', 'api', 'cron', 'login', 'logout', 'register',
        'assets', 'favicon.ico', 'robots.txt', 'sitemap.xml', 'health',
    ];

    public function generate(int $length = 6): string
    {
        $chars  = self::CHARS;
        $max    = strlen($chars) - 1;
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }
        return $result;
    }

    public function isReserved(string $code): bool
    {
        return in_array(strtolower($code), self::RESERVED, true);
    }

    public function isValid(string $code): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9_-]{3,64}$/', $code);
    }

    public function generateUnique(Link $linkModel, int $length = 6): string
    {
        $attempts = 0;
        do {
            $code = $this->generate($length);
            $attempts++;
            // Increase length after many collisions
            if ($attempts > 10) {
                $length++;
                $attempts = 0;
            }
        } while ($this->isReserved($code) || $linkModel->codeExists($code));

        return $code;
    }
}

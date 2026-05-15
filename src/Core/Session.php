<?php
declare(strict_types=1);

namespace App\Core;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $secure   = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $lifetime = 0;
        $path     = '/';
        $domain   = '';
        $httpOnly = true;
        $sameSite = 'Lax';

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ]);

        session_name('URLSHORTENER_SESS');
        session_start();
        self::$started = true;
    }

    public static function set(string $key, mixed $val): void
    {
        self::start();
        $_SESSION[$key] = $val;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Flash: if $val is null, get-and-clear; otherwise set.
     */
    public static function flash(string $key, mixed $val = null): mixed
    {
        self::start();
        if ($val !== null) {
            $_SESSION['_flash'][$key] = $val;
            return null;
        }
        $stored = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $stored;
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }
        session_destroy();
        self::$started = false;
    }
}

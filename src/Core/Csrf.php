<?php
declare(strict_types=1);

namespace App\Core;

class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        Session::start();
        if (!Session::has(self::SESSION_KEY)) {
            Session::set(self::SESSION_KEY, bin2hex(random_bytes(32)));
        }
        return Session::get(self::SESSION_KEY);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . e(self::token()) . '">';
    }

    public static function verify(string $token): bool
    {
        $stored = Session::get(self::SESSION_KEY);
        if ($stored === null || $token === '') {
            return false;
        }
        return hash_equals($stored, $token);
    }
}

<?php

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verify(?string $token): bool
    {
        return is_string($token) && hash_equals(self::token(), $token);
    }
}


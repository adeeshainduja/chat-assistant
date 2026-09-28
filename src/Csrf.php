<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        AdminAuth::start();

        if (empty($_SESSION['ai_csrf_token'])) {
            $_SESSION['ai_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['ai_csrf_token'];
    }

    public static function verify(?string $token): bool
    {
        AdminAuth::start();

        $expected = $_SESSION['ai_csrf_token'] ?? '';

        return is_string($token)
            && $expected !== ''
            && hash_equals($expected, $token);
    }
}

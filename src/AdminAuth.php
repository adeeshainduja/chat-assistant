<?php
declare(strict_types=1);

final class AdminAuth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('GETMOREAIADMIN');

        $basePath = function_exists('ai_base_path') ? ai_base_path() : '';
        $cookiePath = ($basePath !== '' ? $basePath : '') . '/admin';

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                   (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_set_cookie_params([
            'httponly' => true,
            'secure' => $isHttps,
            'samesite' => 'Lax',
            'path' => $cookiePath !== '' ? $cookiePath : '/admin',
        ]);

        session_start();
    }

    public static function attempt(string $username, string $password): bool
    {
        self::start();

        $expectedUser = Env::get('ADMIN_USERNAME', '');
        $passwordHash = Env::get('ADMIN_PASSWORD_HASH', '');

        if ($expectedUser === '' || $passwordHash === '') {
            return false;
        }

        if (
            hash_equals($expectedUser, $username) &&
            password_verify($password, $passwordHash)
        ) {
            session_regenerate_id(true);
            $_SESSION['ai_admin_authenticated'] = true;
            return true;
        }

        return false;
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['ai_admin_authenticated']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            $base = function_exists('ai_base_path') ? ai_base_path() : '';
            header('Location: ' . $base . '/admin/login.php');
            exit;
        }
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }
}

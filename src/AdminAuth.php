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

        session_set_cookie_params([
            'httponly' => true,
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'samesite' => 'Lax',
            'path' => rtrim(Env::get('APP_BASE_PATH', '/ai-assistant'), '/') . '/admin',
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
            $base = rtrim(Env::get('APP_BASE_PATH', '/ai-assistant'), '/');
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

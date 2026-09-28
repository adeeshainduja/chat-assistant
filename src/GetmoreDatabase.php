<?php
declare(strict_types=1);

final class GetmoreDatabase
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = Env::get('GETMORE_DB_HOST', '127.0.0.1');
        $port = Env::get('GETMORE_DB_PORT', '3306');
        $name = Env::get('GETMORE_DB_NAME', '');
        $user = Env::get('GETMORE_DB_USER', 'root');
        $password = Env::get('GETMORE_DB_PASSWORD', '');

        if ($name === '') {
            throw new RuntimeException(
                'GETMORE_DB_NAME is not configured.'
            );
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $name
        );

        self::$connection = new PDO(
            $dsn,
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return self::$connection;
    }
}
<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__);

require_once APP_ROOT . '/src/Env.php';

Env::load(APP_ROOT . '/.env');

date_default_timezone_set(
    Env::get('APP_TIMEZONE', 'Asia/Colombo') ?: 'Asia/Colombo'
);

require_once APP_ROOT . '/src/Database.php';
if (is_file(APP_ROOT . '/src/GetmoreDatabase.php')) {
    require_once APP_ROOT . '/src/GetmoreDatabase.php';
}
require_once APP_ROOT . '/src/Token.php';
require_once APP_ROOT . '/src/Crypto.php';
require_once APP_ROOT . '/src/InstituteRepository.php';
require_once APP_ROOT . '/src/InstituteIntegrationRepository.php';
require_once APP_ROOT . '/src/AttendanceVerification.php';
require_once APP_ROOT . '/src/AssistantRepository.php';
require_once APP_ROOT . '/src/AdminAuth.php';
require_once APP_ROOT . '/src/Csrf.php';
require_once APP_ROOT . '/src/RateLimiter.php';
require_once APP_ROOT . '/src/GetmoreConnector.php';
require_once APP_ROOT . '/src/GeminiClient.php';
require_once APP_ROOT . '/src/AiService.php';

function ai_base_path(): string
{
    $base = Env::get('APP_BASE_PATH', '/');
    if ($base === '/' || $base === '') {
        return '';
    }
    return rtrim($base, '/');
}

function ai_app_url(string $path = ''): string
{
    $configured = rtrim((string) Env::get('APP_URL', ''), '/');
    if ($configured !== '') {
        $baseUrl = $configured . ai_base_path();
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                  (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                  ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'ai.getmore.lk';
        $baseUrl = $scheme . '://' . $host . ai_base_path();
    }
    $cleanPath = '/' . ltrim($path, '/');
    return $path !== '' ? rtrim($baseUrl, '/') . $cleanPath : rtrim($baseUrl, '/');
}

function ai_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

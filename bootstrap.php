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
require_once APP_ROOT . '/src/AssistantRepository.php';
require_once APP_ROOT . '/src/AdminAuth.php';
require_once APP_ROOT . '/src/Csrf.php';
require_once APP_ROOT . '/src/RateLimiter.php';
require_once APP_ROOT . '/src/GetmoreConnector.php';
require_once APP_ROOT . '/src/GeminiClient.php';
require_once APP_ROOT . '/src/AiService.php';

function ai_base_path(): string
{
    return rtrim(Env::get('APP_BASE_PATH', '/ai-assistant') ?: '/ai-assistant', '/');
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

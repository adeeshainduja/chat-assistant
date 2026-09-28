<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$checks = [
    'PHP >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'cURL extension' => extension_loaded('curl'),
    'PDO MySQL extension' => extension_loaded('pdo_mysql'),
    '.env exists' => is_file(APP_ROOT . '/.env'),
    'GEMINI_API_KEY configured' => (trim((string) Env::get('GEMINI_API_KEY', '')) !== ''),
    'GEMINI_MODEL configured' => (trim((string) Env::get('GEMINI_MODEL', '')) !== ''),
    'ADMIN_PASSWORD_HASH configured' => (trim((string) Env::get('ADMIN_PASSWORD_HASH', '')) !== ''),
];

try {
    Database::connection()->query('SELECT 1');
    $checks['AI database connection'] = true;
} catch (Throwable $e) {
    $checks['AI database connection'] = false;
}

if (class_exists('GetmoreDatabase')) {
    try {
        GetmoreDatabase::connection()->query('SELECT 1');
        $checks['GETMORE database connection'] = true;
    } catch (Throwable $e) {
        $checks['GETMORE database connection'] = false;
    }
}

foreach ($checks as $name => $ok) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $name . PHP_EOL;
}

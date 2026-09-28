<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

AdminAuth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit('Invalid CSRF token.');
}

$settings = [
    'name' => mb_substr(trim((string) ($_POST['name'] ?? 'GETMORE AI')), 0, 255),
    'welcome_message' => mb_substr(trim((string) ($_POST['welcome_message'] ?? '')), 0, 5000),
    'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 10000),
    'purpose' => mb_substr(trim((string) ($_POST['purpose'] ?? '')), 0, 10000),
    'enabled' => isset($_POST['enabled']) ? 1 : 0,
];

$permissions = is_array($_POST['permissions'] ?? null)
    ? $_POST['permissions']
    : [];

$repository = new AssistantRepository(Database::connection());
$repository->save(1, $settings, $permissions);

header('Location: ' . ai_base_path() . '/admin/index.php?saved=1');
exit;

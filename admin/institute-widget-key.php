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

$id = isset($_POST['institute_id']) ? (int) $_POST['institute_id'] : 0;
$base = ai_base_path();

if ($id > 0) {
    try {
        $pdo = Database::connection();
        $instituteRepo = new InstituteRepository($pdo);
        $instituteRepo->regenerateWidgetKey($id);

        header('Location: ' . $base . '/admin/institute-edit.php?id=' . $id . '&key_regenerated=1');
        exit;
    } catch (Throwable $e) {
        header('Location: ' . $base . '/admin/institute-edit.php?id=' . $id . '&error=' . urlencode('Failed to regenerate key: ' . $e->getMessage()));
        exit;
    }
}

header('Location: ' . $base . '/admin/institutes.php');
exit;

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
$status = isset($_POST['status']) && (int) $_POST['status'] === 1;

if ($id > 0) {
    $pdo = Database::connection();
    $instituteRepo = new InstituteRepository($pdo);
    $instituteRepo->setActive($id, $status);
}

header('Location: ' . ai_base_path() . '/admin/institutes.php?status_updated=1');
exit;

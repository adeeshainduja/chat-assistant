<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

AdminAuth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!Csrf::verify(is_string($csrfToken) ? $csrfToken : null)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF token. Please refresh the page.']);
    exit;
}

$instituteId = isset($_POST['institute_id']) ? (int) $_POST['institute_id'] : 0;
$action = trim((string) ($_POST['action'] ?? 'test_connection'));

try {
    $pdo = Database::connection();
    $repo = new InstituteIntegrationRepository($pdo);
    $existing = $instituteId > 0 ? $repo->getByInstituteId($instituteId) : null;
    $effective = $repo->getEffectiveConfig($instituteId);

    // Allow testing form inputs before saving
    $postBaseUrl = trim((string) ($_POST['api_base_url'] ?? ''));
    $postApiKey = trim((string) ($_POST['api_key'] ?? ''));
    $postClassesEndpoint = trim((string) ($_POST['classes_endpoint'] ?? ''));
    $postLecturersEndpoint = trim((string) ($_POST['lecturers_endpoint'] ?? ''));
    $postExtraClassesEndpoint = trim((string) ($_POST['extra_classes_endpoint'] ?? ''));
    $postAttendanceEndpoint = trim((string) ($_POST['attendance_endpoint'] ?? ''));

    $config = [
        'source' => 'custom_test',
        'is_active' => true,
        'base_url' => $postBaseUrl !== '' ? rtrim($postBaseUrl, '/') : $effective['base_url'],
        'api_key' => $postApiKey !== '' ? $postApiKey : $effective['api_key'],
        'classes_endpoint' => $postClassesEndpoint !== '' ? $postClassesEndpoint : $effective['classes_endpoint'],
        'lecturers_endpoint' => $postLecturersEndpoint !== '' ? $postLecturersEndpoint : $effective['lecturers_endpoint'],
        'extra_classes_endpoint' => $postExtraClassesEndpoint !== '' ? $postExtraClassesEndpoint : $effective['extra_classes_endpoint'],
        'attendance_endpoint' => $postAttendanceEndpoint !== '' ? $postAttendanceEndpoint : $effective['attendance_endpoint'],
    ];

    if (empty($config['base_url'])) {
        echo json_encode(['success' => false, 'error' => 'API Base URL is required to test.']);
        exit;
    }

    if (empty($config['api_key'])) {
        echo json_encode(['success' => false, 'error' => 'API Secret Key is required to test.']);
        exit;
    }

    $connector = new GetmoreConnector($config, 'api');

    switch ($action) {
        case 'test_connection':
            $res = $connector->request('GET', $config['classes_endpoint'], [], $config);
            $classCount = count($res['classes'] ?? []);
            $courseCount = count($res['courses'] ?? []);
            echo json_encode([
                'success' => true,
                'http_code' => 200,
                'message' => "Connection successful! GETMORE API responded with HTTP 200 OK. ($classCount classes, $courseCount courses found)",
            ]);
            break;

        case 'test_classes':
            $classesData = $connector->publicClasses($instituteId);
            $classes = $classesData['classes'] ?? [];
            $count = count($classes);
            echo json_encode([
                'success' => true,
                'http_code' => 200,
                'message' => "Connection successful. HTTP 200 OK: $count public class/course records returned.",
                'count' => $count,
            ]);
            break;

        case 'test_lecturers':
            $teachersData = $connector->publicTeachers($instituteId);
            $teachers = $teachersData['teachers'] ?? [];
            $count = count($teachers);
            echo json_encode([
                'success' => true,
                'http_code' => 200,
                'message' => "Connection successful. HTTP 200 OK: $count lecturer records returned.",
                'count' => $count,
            ]);
            break;

        case 'test_extra_classes':
            $extraData = $connector->newClasses($instituteId);
            $classes = $extraData['classes'] ?? [];
            $count = count($classes);
            echo json_encode([
                'success' => true,
                'http_code' => 200,
                'message' => "Connection successful. HTTP 200 OK: $count new/extra class records returned.",
                'count' => $count,
            ]);
            break;

        case 'test_attendance':
            // Attendance endpoint requires student parameters. Test reaching the endpoint.
            try {
                $res = $connector->request('GET', $config['attendance_endpoint'], [], $config);
                echo json_encode([
                    'success' => true,
                    'http_code' => 200,
                    'message' => "Attendance endpoint reached with HTTP 200 OK.",
                ]);
            } catch (RuntimeException $re) {
                // If it returns HTTP 400 because student_id is required, that confirms endpoint is alive!
                $msg = $re->getMessage();
                if (str_contains($msg, 'HTTP 400')) {
                    echo json_encode([
                        'success' => true,
                        'http_code' => 400,
                        'message' => "Attendance endpoint reached (HTTP 400 as expected: student parameters required). The GETMORE Attendance API is active.",
                    ]);
                } else {
                    throw $re;
                }
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => "Unknown test action: $action"]);
            break;
    }
} catch (Throwable $e) {
    // Never expose secret keys in error messages
    $safeError = preg_replace('/(gme_[a-zA-Z0-9]+|X-API-KEY:[^\r\n]+)/i', '[REDACTED]', $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $safeError,
    ]);
}

<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

AdminAuth::start();
$_SESSION['ai_admin_authenticated'] = true;
$token = Csrf::token();

$actions = [
    'test_connection',
    'test_classes',
    'test_lecturers',
    'test_extra_classes',
    'test_attendance',
];

echo "=========================================================\n";
echo "TESTING ADMIN API-TEST ENDPOINT LOGIC\n";
echo "=========================================================\n";

foreach ($actions as $act) {
    // Run each action by invoking admin/api-test.php in an isolated sub-process
    $cmd = sprintf(
        'C:\xampp\php\php.exe -r "require \'bootstrap.php\'; AdminAuth::start(); $_SESSION[\'ai_admin_authenticated\'] = true; $_SESSION[\'ai_csrf_token\'] = \'%s\'; $_SERVER[\'REQUEST_METHOD\'] = \'POST\'; $_POST = [\'csrf_token\' => \'%s\', \'institute_id\' => 1, \'action\' => \'%s\', \'api_base_url\' => \'https://demo.getmore.lk\', \'api_key\' => \'\', \'classes_endpoint\' => \'/api/v1/classes\', \'lecturers_endpoint\' => \'/api/v1/lecturers\', \'extra_classes_endpoint\' => \'/api/v1/extra-classes\', \'attendance_endpoint\' => \'/api/v1/student/attendance/today\']; include \'admin/api-test.php\';"',
        $token,
        $token,
        $act
    );

    $output = shell_exec($cmd);
    $json = json_decode((string)$output, true);

    echo "Action [$act]:\n";
    if (is_array($json)) {
        echo "  Success:   " . ($json['success'] ? 'YES' : 'NO') . "\n";
        echo "  HTTP Code: " . ($json['http_code'] ?? 'N/A') . "\n";
        echo "  Message:   " . ($json['message'] ?? $json['error'] ?? '') . "\n";
    } else {
        echo "  Raw output: " . trim((string)$output) . "\n";
    }
    echo "\n";
}

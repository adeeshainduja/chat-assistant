<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$baseUrl = rtrim((string) (Env::get('GETMORE_API_BASE_URL') ?: Env::get('GETMORE_API_URL') ?: 'https://demo.getmore.lk'), '/');
$apiKey = (string) Env::get('GETMORE_API_KEY');

function callApi(string $url, string $apiKey, string $method = 'GET', array $params = []): array {
    $ch = curl_init();
    $fullUrl = $url;
    if ($method === 'GET' && !empty($params)) {
        $fullUrl .= '?' . http_build_query($params);
    }

    $headers = [
        'Accept: application/json',
        'X-API-KEY: ' . $apiKey,
    ];

    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'error' => $error,
        'body' => $body,
        'json' => json_decode((string)$body, true),
    ];
}

echo "=== 1. FULL CLASSES RESPONSE ===\n";
$classesRes = callApi($baseUrl . '/api/v1/classes', $apiKey);
echo json_encode($classesRes['json'], JSON_PRETTY_PRINT) . "\n\n";

echo "=== 2. FULL LECTURERS RESPONSE ===\n";
$lecturersRes = callApi($baseUrl . '/api/v1/lecturers', $apiKey);
echo json_encode($lecturersRes['json'], JSON_PRETTY_PRINT) . "\n\n";

echo "=== 3. FULL EXTRA CLASSES RESPONSE ===\n";
$extraRes = callApi($baseUrl . '/api/v1/extra-classes', $apiKey);
echo json_encode($extraRes['json'], JSON_PRETTY_PRINT) . "\n\n";

echo "=== 4. ATTENDANCE RESPONSE DETAILS ===\n";
// Let's test with various parameter combinations:
$attnParams = [
    'student_id' => 1,
    'parent_phone' => '0771234567',
];
$attnRes1 = callApi($baseUrl . '/api/v1/student/attendance/today', $apiKey, 'GET', $attnParams);
echo "Attendance with student_id + parent_phone (GET):\n";
echo "HTTP {$attnRes1['code']}\n";
echo json_encode($attnRes1['json'], JSON_PRETTY_PRINT) . "\n\n";

// Let's test if it accepts student_name & parent_name:
$attnNameParams = [
    'student_name' => 'Saman Bandara',
    'parent_name' => 'Sunil Bandara',
];
$attnResName = callApi($baseUrl . '/api/v1/student/attendance/today', $apiKey, 'GET', $attnNameParams);
echo "Attendance with student_name + parent_name (GET):\n";
echo "HTTP {$attnResName['code']}\n";
echo json_encode($attnResName['json'], JSON_PRETTY_PRINT) . "\n\n";

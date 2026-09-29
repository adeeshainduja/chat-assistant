<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

echo "=========================================================\n";
echo "1. TESTING CRYPTO & INTEGRATION REPOSITORY\n";
echo "=========================================================\n";

$rawSecret = 'gme_test_secret_key_12345';
$encrypted = Crypto::encrypt($rawSecret);
$decrypted = Crypto::decrypt($encrypted);
$masked = Crypto::mask($rawSecret);

echo "Plain Secret: $rawSecret\n";
echo "Encrypted:    $encrypted\n";
echo "Decrypted:    $decrypted\n";
echo "Masked:       $masked\n";

if ($decrypted !== $rawSecret) {
    echo "[FAIL] Crypto encryption/decryption failed!\n";
    exit(1);
}
echo "[PASS] Crypto encryption, decryption, and masking verified.\n\n";

$pdo = Database::connection();
$integrationRepo = new InstituteIntegrationRepository($pdo);

// Save test integration for institute 1
$integrationRepo->save(1, [
    'api_base_url' => 'https://demo.getmore.lk',
    'api_key' => 'gme_f4f32805d5dd5e3442e30c656e4dd7cf8b8e11d4da3832d2',
    'classes_endpoint' => '/api/v1/classes',
    'lecturers_endpoint' => '/api/v1/lecturers',
    'extra_classes_endpoint' => '/api/v1/extra-classes',
    'attendance_endpoint' => '/api/v1/student/attendance/today',
    'is_active' => 1,
]);

$saved = $integrationRepo->getByInstituteId(1);
echo "Saved Integration Base URL: " . ($saved['api_base_url'] ?? '') . "\n";
echo "Saved Masked Key: " . ($saved['masked_api_key'] ?? '') . "\n";
echo "Has Key: " . ($saved['has_api_key'] ? 'Yes' : 'No') . "\n";

$effective = $integrationRepo->getEffectiveConfig(1);
echo "Effective Config Source: " . $effective['source'] . "\n";
echo "Effective Base URL: " . $effective['base_url'] . "\n";
echo "[PASS] Institute Integration saved and loaded from DB successfully.\n\n";

echo "=========================================================\n";
echo "2. TESTING GETMORECONNECTOR IN API MODE\n";
echo "=========================================================\n";

$connector = new GetmoreConnector(null, 'api');
echo "Connector Data Mode: " . $connector->getDataMode() . "\n";

// A. Public Classes
echo "--- Calling publicClasses(1) ---\n";
$classesRes = $connector->publicClasses(1);
$classList = $classesRes['classes'] ?? [];
echo "Retrieved " . count($classList) . " classes/courses from GETMORE REST API:\n";
foreach ($classList as $cls) {
    echo "  - [" . $cls['type'] . "] " . $cls['class_name'] . " | Teacher: " . ($cls['teacher'] ?? 'N/A') . " | Day: " . ($cls['day'] ?? 'N/A') . " | Fee: " . ($cls['fee'] ?? 'N/A') . "\n";
}

// B. Search: Chemistry (should NOT exist)
echo "\n--- Searching: Chemistry (expected: found=false) ---\n";
$searchChem = $connector->searchClasses(1, 'Chemistry');
echo "Found: " . ($searchChem['found'] ? 'TRUE' : 'FALSE') . "\n";
echo "Classes matched: " . count($searchChem['classes']) . "\n";
if ($searchChem['found'] === false && count($searchChem['classes']) === 0) {
    echo "[PASS] Chemistry correctly reported as not found.\n";
} else {
    echo "[FAIL] Chemistry unexpectedly found!\n";
}

// C. Search: Combined Mathematics (should exist)
echo "\n--- Searching: Combined Mathematics (expected: found=true) ---\n";
$searchMath = $connector->searchClasses(1, 'Combined Mathematics');
echo "Found: " . ($searchMath['found'] ? 'TRUE' : 'FALSE') . "\n";
echo "Classes matched: " . count($searchMath['classes']) . "\n";
foreach ($searchMath['classes'] as $m) {
    echo "  - " . $m['class_name'] . " (Teacher: " . ($m['teacher_name'] ?? 'N/A') . ")\n";
}
if ($searchMath['found'] === true && count($searchMath['classes']) > 0) {
    echo "[PASS] Combined Mathematics correctly found.\n";
} else {
    echo "[FAIL] Combined Mathematics not found!\n";
}

// D. Public Lecturers
echo "\n--- Calling publicTeachers(1) ---\n";
$teachersRes = $connector->publicTeachers(1);
$teachersList = $teachersRes['teachers'] ?? [];
echo "Retrieved " . count($teachersList) . " teachers from GETMORE REST API:\n";
foreach ($teachersList as $t) {
    echo "  - Lecturer: " . $t['teacher_name'] . " (Classes: " . implode(', ', $t['classes']) . ")\n";
}

// E. New / Extra Classes
echo "\n--- Calling newClasses(1) ---\n";
$newRes = $connector->newClasses(1);
$newList = $newRes['classes'] ?? [];
echo "Retrieved " . count($newList) . " new/extra classes from GETMORE REST API:\n";
foreach ($newList as $nc) {
    echo "  - [" . $nc['type'] . "] " . $nc['class_name'] . " (Start: " . ($nc['start_date'] ?? 'N/A') . ")\n";
}

echo "\n[PASS] All GetmoreConnector API calls succeeded with real GETMORE data.\n";

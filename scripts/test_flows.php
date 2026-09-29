<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

session_start();

$pdo = Database::connection();
$repository = new AssistantRepository($pdo);
$connector = new GetmoreConnector();
$service = new AiService(
    new GeminiClient(),
    $connector,
    $repository
);

echo "=== TEST 1: Greeting ===\n";
$reply = $service->reply("Hi", [], 'pk_achieve_72af8391');
echo "AI: $reply\n\n";

echo "=== TEST 2: Public Classes ===\n";
$reply = $service->reply("What classes do you offer?", [], 'pk_achieve_72af8391');
echo "AI: $reply\n\n";

echo "=== TEST 3: Public Teacher Details ===\n";
$reply = $service->reply("Who teaches Combined Mathematics?", [], 'pk_achieve_72af8391');
echo "AI: $reply\n\n";

echo "=== TEST 4: Attendance Request (Prompt for details) ===\n";
$history = [];
$reply = $service->reply("Show my attendance", $history, 'pk_achieve_72af8391');
echo "AI: $reply\n\n";
$history[] = ['role' => 'user', 'content' => 'Show my attendance'];
$history[] = ['role' => 'assistant', 'content' => $reply];

echo "=== TEST 5: Failed Verification ===\n";
$failReply = $service->reply("Student: Saman Bandara\nParent: Wrong Parent Name", $history, 'pk_achieve_72af8391');
echo "AI: $failReply\n\n";

echo "=== TEST 6: Successful Verification ===\n";
// Student 1: Saman Bandara, Guardian: Sunil Bandara
$successReply = $service->reply("Student: Saman Bandara\nParent: Sunil Bandara", $history, 'pk_achieve_72af8391');
echo "AI: $successReply\n\n";
$history[] = ['role' => 'user', 'content' => "Student: Saman Bandara\nParent: Sunil Bandara"];
$history[] = ['role' => 'assistant', 'content' => $successReply];

echo "Session State after verification:\n";
echo "verified: " . var_export($_SESSION['attendance_verified'] ?? null, true) . "\n";
echo "student_id: " . var_export($_SESSION['verified_student_id'] ?? null, true) . "\n";
echo "institute_id: " . var_export($_SESSION['verified_institute_id'] ?? null, true) . "\n\n";

echo "=== TEST 7: Retrieve Verified Attendance ===\n";
$attReply = $service->reply("Show my recent attendance", $history, 'pk_achieve_72af8391');
echo "AI: $attReply\n\n";

echo "=== TEST 8: Out of scope ===\n";
$oosReply = $service->reply("How do I learn Python?", [], 'pk_achieve_72af8391');
echo "AI: $oosReply\n\n";

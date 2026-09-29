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

$widgetKey = 'pk_achieve_72af8391';

echo "==================================================\n";
echo "TEST 1: Tell me about this institute\n";
echo "==================================================\n";
$reply1 = $service->reply("Tell me about this institute", [], $widgetKey);
echo "AI: $reply1\n\n";
sleep(4);

echo "==================================================\n";
echo "TEST 2: What classes do you have?\n";
echo "==================================================\n";
$reply2 = $service->reply("What classes do you have?", [], $widgetKey);
echo "AI: $reply2\n\n";
sleep(4);

echo "==================================================\n";
echo "TEST 3: Do you have Chemistry? (NOT FOUND EXPECTED)\n";
echo "==================================================\n";
$reply3 = $service->reply("Do you have Chemistry classes?", [], $widgetKey);
echo "AI: $reply3\n\n";
sleep(4);

echo "==================================================\n";
echo "TEST 4: Show me your Mathematics classes (FOUND EXPECTED)\n";
echo "==================================================\n";
$reply5 = $service->reply("Show me your Mathematics classes", [], $widgetKey);
echo "AI: $reply5\n\n";
sleep(4);

echo "==================================================\n";
echo "TEST 5: Any new courses? / What are your new courses?\n";
echo "==================================================\n";
$reply6 = $service->reply("What are your new courses?", [], $widgetKey);
echo "AI: $reply6\n\n";
sleep(4);

echo "==================================================\n";
echo "TEST 6: Who teaches Mathematics?\n";
echo "==================================================\n";
$reply7 = $service->reply("Who teaches Mathematics?", [], $widgetKey);
echo "AI: $reply7\n\n";

echo "==================================================\n";
echo "TEST 8: Show my attendance (Verification Prompt)\n";
echo "==================================================\n";
$history = [];
$reply8 = $service->reply("Show my attendance", $history, $widgetKey);
echo "AI: $reply8\n\n";
$history[] = ['role' => 'user', 'content' => 'Show my attendance'];
$history[] = ['role' => 'assistant', 'content' => $reply8];

echo "==================================================\n";
echo "TEST 9: Attendance Verification + Attendance Query\n";
echo "==================================================\n";
$reply9 = $service->reply("Student ID: STU0001\nParent Mobile: 0719876543", $history, $widgetKey);
echo "AI (Verification): $reply9\n\n";
$history[] = ['role' => 'user', 'content' => "Student ID: STU0001\nParent Mobile: 0719876543"];
$history[] = ['role' => 'assistant', 'content' => $reply9];

$reply10 = $service->reply("Show my recent attendance", $history, $widgetKey);
echo "AI (Attendance Record): $reply10\n\n";

echo "==================================================\n";
echo "TEST 10: Out-of-scope redirection\n";
echo "==================================================\n";
$reply11 = $service->reply("How do I make chocolate cake?", [], $widgetKey);
echo "AI: $reply11\n\n";

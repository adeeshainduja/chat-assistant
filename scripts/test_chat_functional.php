<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

echo "=========================================================\n";
echo "FUNCTIONAL CHAT TESTS WITH GEMINI & GETMORE REST API\n";
echo "=========================================================\n\n";

$pdo = Database::connection();
$assistantRepo = new AssistantRepository($pdo);
$connector = new GetmoreConnector(null, 'api');
$aiService = new AiService(new GeminiClient(), $connector, $assistantRepo);

$assistantKey = 'pk_achieve_72af8391';

$questions = [
    'What classes do you offer?',
    'Do you have Chemistry?',
    'Do you have Combined Mathematics?',
    'Who are your teachers?',
    'Any new classes?',
    'Tell me about this institute.',
    'Show my attendance.',
];

foreach ($questions as $q) {
    echo "USER: $q\n";
    try {
        $reply = $aiService->reply($q, [], $assistantKey);
        echo "ASSISTANT:\n" . trim($reply) . "\n";
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
    echo "---------------------------------------------------------\n\n";
    sleep(1); // prevent Gemini rate limits
}

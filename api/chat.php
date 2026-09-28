<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ai_json(['error' => 'Method not allowed.'], 405);
}

$bearer = Token::bearerFromHeaders();
$isDev = Env::bool('DEV_MODE', false);

if ($isDev) {
    $studentId = (int) (Env::get('DEV_STUDENT_ID', '1') ?: '1');
    if ($bearer === null || $bearer === '') {
        $bearer = 'dev-token';
    }
} else {
    if ($bearer === null) {
        ai_json(['error' => 'Authentication required.'], 401);
    }

    try {
        $identity = Token::verify($bearer);
    } catch (Throwable $e) {
        ai_json(['error' => 'AI authentication is not configured.'], 500);
    }

    if ($identity === null) {
        ai_json(['error' => 'Your AI session has expired. Please refresh the login token.'], 401);
    }

    $studentId = (int) $identity['student_id'];
}

if (!RateLimiter::allow('student:' . $studentId, 30, 60)) {
    ai_json(['error' => 'Too many messages. Please wait a moment and try again.'], 429);
}

$body = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($body)) {
    ai_json(['error' => 'Invalid JSON request.'], 400);
}

$message = trim((string) ($body['message'] ?? ''));

if ($message === '') {
    ai_json(['error' => 'Message is required.'], 422);
}

if (mb_strlen($message) > 2000) {
    ai_json(['error' => 'Message is too long.'], 422);
}

$history = is_array($body['history'] ?? null)
    ? $body['history']
    : [];

try {
    $pdo = Database::connection();
    $repository = new AssistantRepository($pdo);
    $connector = new GetmoreConnector($bearer);
    $service = new AiService(
        new GeminiClient(),
        $connector,
        $repository
    );

    $reply = $service->reply($message, $history, 1);

    ai_json([
        'reply' => $reply,
    ]);
} catch (Throwable $e) {
    $debug = Env::bool('APP_DEBUG', false);

    ai_json([
        'error' => $debug
            ? $e->getMessage()
            : 'The AI assistant could not process your message. Please try again.',
    ], 500);
}

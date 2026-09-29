<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    @session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ai_json(['error' => 'Method not allowed.'], 405);
}

// IP-based rate limiting for public endpoints
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (!RateLimiter::allow('public_ip:' . $clientIp, 45, 60)) {
    ai_json(['error' => 'Too many messages. Please wait a moment and try again.'], 429);
}

$body = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($body)) {
    ai_json(['error' => 'Invalid JSON request.'], 400);
}

$assistantKey = trim((string) ($body['assistant_key'] ?? $_GET['assistant'] ?? ''));
$isDev = Env::bool('DEV_MODE', false);

// In development mode, fallback to default assistant key if omitted
if ($assistantKey === '' && $isDev) {
    $assistantKey = 'pk_achieve_72af8391';
}

if ($assistantKey === '') {
    ai_json(['error' => 'Assistant key is required.'], 400);
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

    $assistant = $repository->getByWidgetKey($assistantKey);
    if (!$assistant) {
        ai_json(['error' => 'This assistant is currently unavailable.'], 404);
    }

    if (
        !(bool) ($assistant['enabled'] ?? 0) ||
        (isset($assistant['institute_active']) && !(bool) $assistant['institute_active'])
    ) {
        ai_json(['error' => 'This assistant is currently unavailable.'], 403);
    }

    // Validate origin / referer against allowed domains
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    $referer = $_SERVER['HTTP_REFERER'] ?? null;
    if (!InstituteRepository::validateDomain($assistant['allowed_domains'] ?? null, $origin, $referer, $isDev)) {
        ai_json(['error' => 'This domain is not authorized to use this assistant.'], 403);
    }

    $connector = new GetmoreConnector();
    $service = new AiService(
        new GeminiClient(),
        $connector,
        $repository
    );

    $reply = $service->reply($message, $history, $assistantKey, (int) $assistant['id']);

    ai_json([
        'reply' => $reply,
    ]);
} catch (Throwable $e) {
    $debug = Env::bool('APP_DEBUG', false);
    $msg = $e->getMessage();

    if (
        str_contains($msg, '503') ||
        str_contains($msg, '429') ||
        str_contains($msg, 'quota') ||
        str_contains($msg, 'busy') ||
        str_contains($msg, 'demand') ||
        str_contains($msg, 'connection failed')
    ) {
        $friendlyError = 'The assistant is temporarily busy. Please try again in a moment.';
    } else {
        $friendlyError = 'I couldn\'t retrieve that information right now. Please try again shortly.';
    }

    $response = [
        'error' => $friendlyError,
    ];
    if ($debug) {
        $response['debug_detail'] = $msg;
    }

    ai_json($response, 500);
}

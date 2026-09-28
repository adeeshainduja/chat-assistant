<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$assistant = [
    'name' => 'GETMORE AI',
    'welcome_message' => 'Hi! How can I help you with your classes today?',
    'enabled' => 1,
];

try {
    $repository = new AssistantRepository(Database::connection());
    $assistant = $repository->getAssistant(1);
} catch (Throwable $e) {
    // Keep a safe fallback UI if configuration/database is temporarily unavailable.
}

$base = ai_base_path();
$enabled = !empty($assistant['enabled']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) $assistant['name']) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/chat.css">
</head>
<body>
<div
    class="chat-shell"
    data-api-url="<?= htmlspecialchars($base) ?>/api/chat.php"
    data-enabled="<?= $enabled ? '1' : '0' ?>"
>
    <header class="chat-header">
        <div class="avatar">AI</div>
        <div>
            <strong><?= htmlspecialchars((string) $assistant['name']) ?></strong>
            <small><?= $enabled ? 'Student Assistant' : 'Currently unavailable' ?></small>
        </div>
    </header>

    <main id="messages" class="messages">
        <div class="message assistant">
            <div class="bubble">
                <?= nl2br(htmlspecialchars((string) $assistant['welcome_message'])) ?>
            </div>
        </div>
    </main>

    <div id="auth-status" class="auth-status">
        Connecting securely to GETMORE…
    </div>

    <form id="chat-form" class="composer" autocomplete="off">
        <input
            id="chat-input"
            maxlength="2000"
            placeholder="Ask about your classes…"
            <?= $enabled ? '' : 'disabled' ?>
        >
        <button type="submit" <?= $enabled ? '' : 'disabled' ?>>Send</button>
    </form>
</div>

<script src="<?= htmlspecialchars($base) ?>/assets/chat.js"></script>
<?php if (Env::bool('DEV_MODE', false)): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
    window.postMessage({ type: 'GETMORE_AI_AUTH', token: 'dev-token' }, window.location.origin);
});
</script>
<?php endif; ?>
</body>
</html>

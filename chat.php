<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$assistant = [
    'name' => 'GETMORE AI',
    'welcome_message' => 'Hi! How can I help you with your classes today?',
    'enabled' => 1,
    'theme_primary_color' => '#00B957',
    'theme_secondary_color' => '#F3F4F6',
    'theme_text_color' => '#111827',
    'theme_header_text_color' => '#FFFFFF',
    'user_bubble_color' => '#ECFDF3',
    'assistant_bubble_color' => '#EAEAEA',
    'chat_background_color' => '#FFFFFF',
    'starter_messages' => '["What classes do I have?","Show my attendance","Who are my teachers?","When is my next class?"]',
    'header_subtitle' => 'AI Assistant',
];

try {
    $repository = new AssistantRepository(Database::connection());
    $assistant = $repository->getAssistant(1);
} catch (Throwable $e) {
    // Keep a safe fallback UI if configuration/database is temporarily unavailable.
}

$base = ai_base_path();
$enabled = !empty($assistant['enabled']);

$themePrimaryColor = (string) ($assistant['theme_primary_color'] ?? '#00B957');
$themeSecondaryColor = (string) ($assistant['theme_secondary_color'] ?? '#F3F4F6');
$themeTextColor = (string) ($assistant['theme_text_color'] ?? '#111827');
$themeHeaderTextColor = (string) ($assistant['theme_header_text_color'] ?? '#FFFFFF');
$userBubbleColor = (string) ($assistant['user_bubble_color'] ?? '#ECFDF3');
$assistantBubbleColor = (string) ($assistant['assistant_bubble_color'] ?? '#EAEAEA');
$chatBgColor = (string) ($assistant['chat_background_color'] ?? '#FFFFFF');
$headerSubtitle = (string) ($assistant['header_subtitle'] ?? 'AI Assistant');

$starterMessages = [];
if (!empty($assistant['starter_messages'])) {
    $decoded = json_decode((string) $assistant['starter_messages'], true);
    if (is_array($decoded)) {
        $starterMessages = $decoded;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) $assistant['name']) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/chat.css">
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($themePrimaryColor) ?>;
            --secondary-color: <?= htmlspecialchars($themeSecondaryColor) ?>;
            --header-text-color: <?= htmlspecialchars($themeHeaderTextColor) ?>;
            --assistant-bubble-color: <?= htmlspecialchars($assistantBubbleColor) ?>;
            --user-bubble-color: <?= htmlspecialchars($userBubbleColor) ?>;
            --chat-bg-color: <?= htmlspecialchars($chatBgColor) ?>;
            --text-color: <?= htmlspecialchars($themeTextColor) ?>;
        }
    </style>
</head>
<body>
<div
    class="chat-shell"
    data-api-url="<?= htmlspecialchars($base) ?>/api/chat.php"
    data-enabled="<?= $enabled ? '1' : '0' ?>"
    data-primary-color="<?= htmlspecialchars($themePrimaryColor) ?>"
>
    <header class="chat-header">
        <button type="button" class="header-icon-btn" id="header-back-btn" aria-label="Back">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </button>

        <div class="header-info">
            <strong class="header-name"><?= htmlspecialchars((string) $assistant['name']) ?></strong>
            <span class="header-subtitle"><?= htmlspecialchars($enabled ? $headerSubtitle : 'Currently unavailable') ?></span>
        </div>

        <button type="button" class="header-icon-btn" id="header-menu-btn" aria-label="Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="1.5"/>
                <circle cx="12" cy="5" r="1.5"/>
                <circle cx="12" cy="19" r="1.5"/>
            </svg>
        </button>
    </header>

    <div id="auth-status" class="auth-status">
        Connecting securely to GETMORE…
    </div>

    <main id="messages" class="messages">
        <div class="assistant-intro">
            <div class="assistant-avatar" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2 2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/>
                    <rect x="4" y="8" width="16" height="12" rx="3"/>
                    <circle cx="9" cy="13" r="1.5" fill="currentColor"/>
                    <circle cx="15" cy="13" r="1.5" fill="currentColor"/>
                    <path d="M9 17h6"/>
                </svg>
            </div>
            <div class="assistant-intro-name"><?= htmlspecialchars((string) $assistant['name']) ?></div>
            <div class="bubble assistant-bubble welcome-bubble">
                <?= nl2br(htmlspecialchars((string) $assistant['welcome_message'])) ?>
            </div>
        </div>

        <?php if ($starterMessages !== []): ?>
            <div id="starter-buttons" class="starter-buttons">
                <?php foreach ($starterMessages as $starter): ?>
                    <button type="button" class="starter-btn" data-message="<?= htmlspecialchars((string) $starter) ?>">
                        <?= htmlspecialchars((string) $starter) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <form id="chat-form" class="composer" autocomplete="off">
        <input
            id="chat-input"
            maxlength="2000"
            placeholder="Ask about your classes…"
            <?= $enabled ? '' : 'disabled' ?>
        >
        <button type="submit" id="chat-send-btn" aria-label="Send message" <?= $enabled ? '' : 'disabled' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"/>
                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
        </button>
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

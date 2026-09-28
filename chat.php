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
            --user-bubble-text-color: <?= htmlspecialchars($themeTextColor) ?>;
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
    data-admin-primary="<?= htmlspecialchars($themePrimaryColor) ?>"
    data-admin-header-text="<?= htmlspecialchars($themeHeaderTextColor) ?>"
    data-admin-bg="<?= htmlspecialchars($chatBgColor) ?>"
    data-admin-user-bubble="<?= htmlspecialchars($userBubbleColor) ?>"
    data-admin-assistant-bubble="<?= htmlspecialchars($assistantBubbleColor) ?>"
    data-admin-text="<?= htmlspecialchars($themeTextColor) ?>"
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

        <div class="header-menu-wrap">
            <button type="button" class="header-icon-btn" id="header-menu-btn" aria-label="Menu" aria-expanded="false" aria-haspopup="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="1.5"/>
                    <circle cx="12" cy="5" r="1.5"/>
                    <circle cx="12" cy="19" r="1.5"/>
                </svg>
            </button>

            <div id="header-dropdown" class="header-dropdown" role="menu" hidden>
                <button type="button" class="dropdown-item" id="menu-appearance-btn" role="menuitem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/>
                        <circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/>
                        <circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>
                        <circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/>
                        <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.555C21.965 6.012 17.461 2 12 2z"/>
                    </svg>
                    Appearance
                </button>
                <div class="dropdown-divider"></div>
                <button type="button" class="dropdown-item" id="menu-reset-btn" role="menuitem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                        <path d="M3 3v5h5"/>
                    </svg>
                    Reset Theme
                </button>
            </div>
        </div>
    </header>

    <!-- Appearance / Theme Panel Modal -->
    <div id="theme-panel-backdrop" class="theme-panel-backdrop" hidden></div>
    <div id="theme-panel" class="theme-panel" role="dialog" aria-labelledby="theme-panel-title" aria-modal="true" hidden>
        <div class="theme-panel-header">
            <div class="theme-panel-title-wrap">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/>
                    <circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/>
                    <circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>
                    <circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/>
                    <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.555C21.965 6.012 17.461 2 12 2z"/>
                </svg>
                <h3 id="theme-panel-title" class="theme-panel-title">Appearance</h3>
            </div>
            <button type="button" id="theme-panel-close-btn" class="theme-panel-close-btn" aria-label="Close appearance panel">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="theme-panel-body">
            <!-- Theme Color Section -->
            <div class="theme-section">
                <label class="theme-section-label">Theme Color</label>
                <div class="theme-color-grid" id="theme-color-presets">
                    <button type="button" class="color-preset-btn" data-color="#00B957" title="Green" style="--btn-color: #00B957;" aria-label="Green"></button>
                    <button type="button" class="color-preset-btn" data-color="#2563EB" title="Blue" style="--btn-color: #2563EB;" aria-label="Blue"></button>
                    <button type="button" class="color-preset-btn" data-color="#7C3AED" title="Purple" style="--btn-color: #7C3AED;" aria-label="Purple"></button>
                    <button type="button" class="color-preset-btn" data-color="#EA580C" title="Orange" style="--btn-color: #EA580C;" aria-label="Orange"></button>
                    <button type="button" class="color-preset-btn" data-color="#DB2777" title="Pink" style="--btn-color: #DB2777;" aria-label="Pink"></button>
                    <button type="button" class="color-preset-btn" data-color="#0D9488" title="Teal" style="--btn-color: #0D9488;" aria-label="Teal"></button>
                    <button type="button" class="color-preset-btn" data-color="#B48A00" title="Gold" style="--btn-color: #B48A00;" aria-label="Gold"></button>
                    <button type="button" class="color-preset-btn" data-color="#1F2937" title="Dark" style="--btn-color: #1F2937;" aria-label="Dark"></button>
                </div>

                <div class="theme-custom-row">
                    <span class="theme-custom-label">Custom Color</span>
                    <label class="color-picker-label" title="Choose custom primary color">
                        <input type="color" id="theme-custom-primary" class="color-picker-input">
                        <span id="custom-primary-preview" class="color-picker-swatch"></span>
                    </label>
                </div>
            </div>

            <!-- Chat Background Section -->
            <div class="theme-section">
                <label class="theme-section-label">Chat Background</label>
                <div class="theme-bg-grid" id="theme-bg-presets">
                    <button type="button" class="bg-preset-btn" data-bg="#FFFFFF" title="White">
                        <span class="bg-swatch" style="background: #FFFFFF; border: 1px solid #e2e8f0;"></span>
                        <span class="bg-name">White</span>
                    </button>
                    <button type="button" class="bg-preset-btn" data-bg="#F8FAFC" title="Soft Gray">
                        <span class="bg-swatch" style="background: #F8FAFC; border: 1px solid #e2e8f0;"></span>
                        <span class="bg-name">Soft Gray</span>
                    </button>
                    <button type="button" class="bg-preset-btn" data-bg="#F0FDF4" title="Soft Green">
                        <span class="bg-swatch" style="background: #F0FDF4; border: 1px solid #dcfce7;"></span>
                        <span class="bg-name">Soft Green</span>
                    </button>
                    <button type="button" class="bg-preset-btn" data-bg="#EFF6FF" title="Soft Blue">
                        <span class="bg-swatch" style="background: #EFF6FF; border: 1px solid #dbeafe;"></span>
                        <span class="bg-name">Soft Blue</span>
                    </button>
                    <button type="button" class="bg-preset-btn" data-bg="#FFFBEB" title="Warm">
                        <span class="bg-swatch" style="background: #FFFBEB; border: 1px solid #fef3c7;"></span>
                        <span class="bg-name">Warm</span>
                    </button>
                    <button type="button" class="bg-preset-btn" data-bg="#111827" title="Dark">
                        <span class="bg-swatch" style="background: #111827; border: 1px solid #374151;"></span>
                        <span class="bg-name">Dark</span>
                    </button>
                </div>
            </div>

            <!-- Message Bubble Option -->
            <div class="theme-section">
                <label class="theme-section-label">User Bubble Color</label>
                <div class="theme-custom-row">
                    <span class="theme-custom-label">User Bubble</span>
                    <label class="color-picker-label" title="Choose user message bubble color">
                        <input type="color" id="theme-user-bubble" class="color-picker-input">
                        <span id="user-bubble-preview" class="color-picker-swatch"></span>
                    </label>
                </div>
            </div>

            <!-- Reset to Default Button -->
            <div class="theme-section theme-footer">
                <button type="button" id="theme-reset-btn" class="theme-reset-btn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                        <path d="M3 3v5h5"/>
                    </svg>
                    Reset to Default
                </button>
            </div>
        </div>
    </div>

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

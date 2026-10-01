<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

AdminAuth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit('Invalid CSRF token.');
}

$instituteId = isset($_POST['institute_id']) ? (int) $_POST['institute_id'] : 1;
$assistantId = isset($_POST['assistant_id']) ? (int) $_POST['assistant_id'] : 1;

$colorRegex = '/^#[0-9A-Fa-f]{6}$/';
$sanitizeColor = static function (?string $value, string $default) use ($colorRegex): string {
    $value = trim((string) $value);
    if (preg_match($colorRegex, $value)) {
        return strtoupper($value);
    }
    return $default;
};

$themePrimary = $sanitizeColor($_POST['theme_primary_color'] ?? null, '#00B957');
$themeSecondary = $sanitizeColor($_POST['theme_secondary_color'] ?? null, '#F3F4F6');
$themeText = $sanitizeColor($_POST['theme_text_color'] ?? null, '#111827');
$themeHeaderText = $sanitizeColor($_POST['theme_header_text_color'] ?? null, '#FFFFFF');
$userBubble = $sanitizeColor($_POST['user_bubble_color'] ?? null, '#ECFDF3');
$assistantBubble = $sanitizeColor($_POST['assistant_bubble_color'] ?? null, '#EAEAEA');
$chatBg = $sanitizeColor($_POST['chat_background_color'] ?? null, '#FFFFFF');

$headerSubtitle = mb_substr(trim((string) ($_POST['header_subtitle'] ?? 'AI Assistant')), 0, 255);
if ($headerSubtitle === '') {
    $headerSubtitle = 'AI Assistant';
}

$rawStarters = (string) ($_POST['starter_messages'] ?? '');
$starterLines = preg_split('/\r\n|\r|\n/', $rawStarters);
$starters = [];

if (is_array($starterLines)) {
    foreach ($starterLines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $line = mb_substr($line, 0, 150);
        $starters[] = $line;
        if (count($starters) >= 10) {
            break;
        }
    }
}

$starterMessagesJson = $starters !== []
    ? json_encode($starters, JSON_UNESCAPED_UNICODE)
    : null;

$instituteData = [
    'name' => mb_substr(trim((string) ($_POST['institute_name'] ?? 'Achieve Institute')), 0, 255),
    'allowed_domains' => trim((string) ($_POST['allowed_domains'] ?? '')),
    'short_description' => mb_substr(trim((string) ($_POST['short_description'] ?? '')), 0, 500),
    'about_institute' => mb_substr(trim((string) ($_POST['about_institute'] ?? '')), 0, 10000),
    'public_address' => mb_substr(trim((string) ($_POST['public_address'] ?? '')), 0, 500),
    'public_phone' => mb_substr(trim((string) ($_POST['public_phone'] ?? '')), 0, 100),
    'public_email' => mb_substr(trim((string) ($_POST['public_email'] ?? '')), 0, 255),
    'website' => mb_substr(trim((string) ($_POST['website'] ?? '')), 0, 255),
    'opening_hours' => mb_substr(trim((string) ($_POST['opening_hours'] ?? '')), 0, 2000),
    'registration_info' => mb_substr(trim((string) ($_POST['registration_info'] ?? '')), 0, 5000),
    'facilities_services' => mb_substr(trim((string) ($_POST['facilities_services'] ?? '')), 0, 5000),
    'public_notes' => mb_substr(trim((string) ($_POST['public_notes'] ?? '')), 0, 5000),
];

$preChatEnabled = isset($_POST['pre_chat_enabled']) ? 1 : 0;
$preChatMessage = mb_substr(trim((string) ($_POST['pre_chat_message'] ?? '')), 0, 2000);
$preChatDelay = isset($_POST['pre_chat_delay']) ? max(0, min(300, (int) $_POST['pre_chat_delay'])) : 3;
$preChatDisplayMode = (string) ($_POST['pre_chat_display_mode'] ?? 'always');
if (!in_array($preChatDisplayMode, ['always', 'once_session', 'once_visitor'], true)) {
    $preChatDisplayMode = 'always';
}

$assistantSettings = [
    'name' => mb_substr(trim((string) ($_POST['name'] ?? 'Achieve AI')), 0, 255),
    'welcome_message' => mb_substr(trim((string) ($_POST['welcome_message'] ?? '')), 0, 5000),
    'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 10000),
    'purpose' => mb_substr(trim((string) ($_POST['purpose'] ?? '')), 0, 10000),
    'enabled' => isset($_POST['enabled']) ? 1 : 0,
    'theme_primary_color' => $themePrimary,
    'theme_secondary_color' => $themeSecondary,
    'theme_text_color' => $themeText,
    'theme_header_text_color' => $themeHeaderText,
    'user_bubble_color' => $userBubble,
    'assistant_bubble_color' => $assistantBubble,
    'chat_background_color' => $chatBg,
    'starter_messages' => $starterMessagesJson,
    'header_subtitle' => $headerSubtitle,
    'pre_chat_enabled' => $preChatEnabled,
    'pre_chat_message' => $preChatMessage !== '' ? $preChatMessage : null,
    'pre_chat_delay' => $preChatDelay,
    'pre_chat_display_mode' => $preChatDisplayMode,
];

$permissions = is_array($_POST['permissions'] ?? null)
    ? $_POST['permissions']
    : [];

$repository = new AssistantRepository(Database::connection());
$repository->saveAssistantAndInstitute($assistantId, $instituteId, $instituteData, $assistantSettings, $permissions);

header('Location: ' . ai_base_path() . '/admin/index.php?institute_id=' . $instituteId . '&saved=1');
exit;

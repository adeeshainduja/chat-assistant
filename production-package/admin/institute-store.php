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

$base = ai_base_path();
$name = trim((string) ($_POST['name'] ?? ''));

if ($name === '') {
    header('Location: ' . $base . '/admin/institute-create.php?error=' . urlencode('Institute name is required.'));
    exit;
}

$email = trim((string) ($_POST['public_email'] ?? ''));
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $base . '/admin/institute-create.php?error=' . urlencode('Please enter a valid email address.'));
    exit;
}

$website = trim((string) ($_POST['website'] ?? ''));
if ($website !== '') {
    if (!str_starts_with($website, 'http://') && !str_starts_with($website, 'https://')) {
        $website = 'https://' . $website;
    }
    if (!filter_var($website, FILTER_VALIDATE_URL)) {
        header('Location: ' . $base . '/admin/institute-create.php?error=' . urlencode('Please enter a valid website URL.'));
        exit;
    }
}

// Format allowed domains (normalize newlines/commas)
$rawAllowed = trim((string) ($_POST['allowed_domains'] ?? ''));
$domainLines = preg_split('/[\r\n,]+/', $rawAllowed, -1, PREG_SPLIT_NO_EMPTY);
$cleanedDomains = [];
if (is_array($domainLines)) {
    foreach ($domainLines as $d) {
        $d = strtolower(trim($d));
        $d = preg_replace('#^https?://#', '', $d);
        $d = preg_replace('#/.*$#', '', $d);
        if ($d !== '') {
            $cleanedDomains[] = $d;
        }
    }
}
$allowedDomainsStr = $cleanedDomains !== [] ? implode(', ', array_unique($cleanedDomains)) : null;

try {
    $pdo = Database::connection();
    $instituteRepo = new InstituteRepository($pdo);
    $assistantRepo = new AssistantRepository($pdo);

    // Cryptographically secure widget key
    $widgetKey = InstituteRepository::generateWidgetKey();

    $instituteId = $instituteRepo->create([
        'name' => $name,
        'public_widget_key' => $widgetKey,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'allowed_domains' => $allowedDomainsStr,
        'short_description' => mb_substr(trim((string) ($_POST['short_description'] ?? '')), 0, 500),
        'about' => mb_substr(trim((string) ($_POST['about'] ?? '')), 0, 10000),
        'public_address' => mb_substr(trim((string) ($_POST['public_address'] ?? '')), 0, 500),
        'public_phone' => mb_substr(trim((string) ($_POST['public_phone'] ?? '')), 0, 100),
        'public_email' => $email !== '' ? $email : null,
        'website' => $website !== '' ? $website : null,
        'opening_hours' => mb_substr(trim((string) ($_POST['opening_hours'] ?? '')), 0, 2000),
        'registration_information' => mb_substr(trim((string) ($_POST['registration_information'] ?? '')), 0, 5000),
        'facilities' => mb_substr(trim((string) ($_POST['facilities'] ?? '')), 0, 5000),
        'public_notes' => mb_substr(trim((string) ($_POST['public_notes'] ?? '')), 0, 5000),
    ]);

    // Automatically create default AI assistant for this institute
    $assistantRepo->createDefaultAssistant($instituteId, 'AI Assistant');

    header('Location: ' . $base . '/admin/institute-edit.php?id=' . $instituteId . '&created=1');
    exit;
} catch (Throwable $e) {
    header('Location: ' . $base . '/admin/institute-create.php?error=' . urlencode('Failed to create institute: ' . $e->getMessage()));
    exit;
}

<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

AdminAuth::requireLogin();

$pdo = Database::connection();
$instituteRepo = new InstituteRepository($pdo);
$assistantRepo = new AssistantRepository($pdo);

$institutes = $instituteRepo->getAll();

// Selected institute
$selectedInstituteId = isset($_GET['institute_id']) ? (int) $_GET['institute_id'] : (isset($_GET['institute']) ? (int) $_GET['institute'] : 0);
if ($selectedInstituteId < 1 && !empty($institutes)) {
    $selectedInstituteId = (int) $institutes[0]['id'];
}

$activeInstitute = null;
foreach ($institutes as $inst) {
    if ((int) $inst['id'] === $selectedInstituteId) {
        $activeInstitute = $inst;
        break;
    }
}

if (!$activeInstitute && !empty($institutes)) {
    $activeInstitute = $institutes[0];
    $selectedInstituteId = (int) $activeInstitute['id'];
}

$assistant = null;
if ($activeInstitute) {
    $assistant = $assistantRepo->getByInstituteId($selectedInstituteId);
    if (!$assistant) {
        $assistant = $assistantRepo->getAssistant(1);
    }
}

$assistantId = (int) ($assistant['id'] ?? 1);
$permissions = $assistantRepo->getPermissions($assistantId);

$saved = isset($_GET['saved']);
$base = ai_base_path();
$activeNav = 'assistants';

$currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$widgetUrl = $scheme . '://' . $currentHost . $base . '/widget.js';
$publicWidgetKey = (string) ($activeInstitute['public_widget_key'] ?? 'pk_achieve_72af8391');
$embedCode = '<script' . "\n" .
    '    src="' . htmlspecialchars($widgetUrl) . '"' . "\n" .
    '    data-assistant="' . htmlspecialchars($publicWidgetKey) . '">' . "\n" .
    '</script>';

$previewUrl = $base . '/chat.php?assistant=' . urlencode($publicWidgetKey);

$starterLines = '';
if (!empty($assistant['starter_messages'])) {
    $decoded = json_decode((string) $assistant['starter_messages'], true);
    if (is_array($decoded)) {
        $starterLines = implode("\n", $decoded);
    }
} else {
    $starterLines = "What classes do you offer?\nTell me about this institute\nWho are your teachers?\nDo you have any new courses?\nCheck my attendance";
}

$permissionHelp = [
    'institute_details' => 'Allow the AI to answer questions about this institute (about, location, address, phone, email, website, opening hours, facilities, registration info).',
    'class_details' => 'Allow visitors to view all public class schedules and search for specific courses/subjects (e.g. Chemistry, Physics, Mathematics).',
    'teacher_details' => 'Expose approved teacher names and their assigned public classes to website visitors.',
    'new_courses' => 'Allow visitors to discover upcoming classes and new courses currently open for enrollment.',
    'attendance_details' => 'Allow parents/students to look up private attendance records via student name + parent/guardian verification (NO OTP).',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GETMORE AI Admin</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/admin.css">
    <style>
        .permission{display:flex;gap:12px;padding:15px 0;border-top:1px solid #efefef;align-items:flex-start}
        .permission:first-of-type{border-top:0}
        .permission strong{display:block;margin-bottom:4px;font-size:14px}
        .permission small{color:#6f6f6f;line-height:1.45;font-size:13px}
        .save{border:0;background:#111;color:#fff;border-radius:10px;padding:14px 28px;font-size:15px;font-weight:700;cursor:pointer}
        .save:hover{background:#222}
        .note{background:#fff7e7;padding:13px 14px;border-radius:10px;line-height:1.5;color:#694c12;font-size:13px;margin-bottom:14px}

        .institute-nav{display:flex;gap:12px;align-items:center;margin-bottom:24px;background:#fff;border:1px solid #e8e8e8;border-radius:12px;padding:12px 18px}
        .institute-nav label{font-weight:700;font-size:14px}
        .institute-nav select{width:auto;min-width:240px;padding:8px 12px}

        .color-grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-top:10px}
        .color-item{display:flex;flex-direction:column;gap:6px}
        .color-item span{font-size:13px;font-weight:700;color:#333}
        .color-control{display:flex;align-items:center;gap:10px;background:#fbfbfb;border:1px solid #e2e2e2;border-radius:10px;padding:6px 10px}
        .color-control input[type=color]{-webkit-appearance:none;border:none;width:34px;height:34px;border-radius:8px;cursor:pointer;padding:0;background:transparent}
        .color-control input[type=color]::-webkit-color-swatch-wrapper{padding:0}
        .color-control input[type=color]::-webkit-color-swatch{border:1px solid #ccc;border-radius:6px}
        .color-hex{font-family:Consolas,monospace;font-size:13px;color:#555}
    </style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>

<div class="wrap">
    <h1>Institute Assistant Settings</h1>
    <p class="muted" style="margin-bottom:20px;">Configure institute public information, feature permissions, course search, appearance, and widget embed.</p>

    <?php if ($saved): ?>
        <div class="success">AI assistant settings were saved successfully.</div>
    <?php endif; ?>

    <div class="institute-nav">
        <label for="inst-select">Active Institute:</label>
        <select id="inst-select" onchange="window.location.href='?institute_id=' + this.value;">
            <?php foreach ($institutes as $inst): ?>
                <option value="<?= (int) $inst['id'] ?>" <?= ((int) $inst['id'] === $selectedInstituteId) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $inst['name']) ?> (<?= htmlspecialchars((string) $inst['public_widget_key']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Embed Script Widget Card -->
    <div class="card">
        <h2>Embed AI Assistant Widget</h2>
        <p class="muted" style="margin-top:-6px;margin-bottom:12px;">Place this script tag on the institute's website before the closing <code>&lt;/body&gt;</code> tag:</p>
        <div class="embed-box" id="embed-code-block"><?= htmlspecialchars($embedCode) ?></div>
        <div class="embed-actions">
            <button type="button" class="btn-secondary" id="copy-script-btn" onclick="copyEmbedScript()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
                <span id="copy-btn-text">Copy Script</span>
            </button>

            <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" class="btn-outline">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                    <polyline points="15 3 21 3 21 9"/>
                    <line x1="10" y1="14" x2="21" y2="3"/>
                </svg>
                Preview Assistant
            </a>
        </div>
    </div>

    <form method="post" action="<?= htmlspecialchars($base) ?>/admin/save.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <input type="hidden" name="institute_id" value="<?= (int) $selectedInstituteId ?>">
        <input type="hidden" name="assistant_id" value="<?= (int) $assistantId ?>">

        <!-- Section 1: Institute Public Information -->
        <div class="card">
            <h2>Institute Public Information</h2>
            <p class="muted" style="margin-top:-8px;margin-bottom:14px;">These details are returned when visitors ask about the institute, location, contact info, hours, facilities, and registration.</p>

            <label class="toggle" style="margin-bottom:14px;">
                <input type="checkbox" name="enabled" value="1" <?= !empty($assistant['enabled']) ? 'checked' : '' ?>>
                Enable AI Assistant for this Institute
            </label>

            <div class="row">
                <div>
                    <label class="field">Institute Name</label>
                    <input type="text" name="institute_name" maxlength="255" required
                           value="<?= htmlspecialchars((string) ($activeInstitute['name'] ?? 'Achieve Institute')) ?>">
                </div>
                <div>
                    <label class="field">Public Widget Key</label>
                    <input type="text" readonly style="background:#f8fafc;color:#64748b;font-family:Consolas,monospace;"
                           value="<?= htmlspecialchars($publicWidgetKey) ?>">
                </div>
            </div>

            <label class="field">Short Description</label>
            <input type="text" name="short_description" maxlength="500" placeholder="e.g. Leading higher education and tuition institute"
                   value="<?= htmlspecialchars((string) ($activeInstitute['short_description'] ?? '')) ?>">

            <label class="field">About Institute</label>
            <textarea name="about_institute" placeholder="Detailed background, philosophy, and academic excellence of the institute"><?= htmlspecialchars((string) ($activeInstitute['about_institute'] ?? '')) ?></textarea>

            <div class="row">
                <div>
                    <label class="field">Public Address</label>
                    <input type="text" name="public_address" maxlength="255" placeholder="e.g. No. 124, High Level Road, Nugegoda"
                           value="<?= htmlspecialchars((string) ($activeInstitute['public_address'] ?? '')) ?>">
                </div>
                <div>
                    <label class="field">Public Phone</label>
                    <input type="text" name="public_phone" maxlength="100" placeholder="e.g. +94 11 282 9900 / 077 712 3456"
                           value="<?= htmlspecialchars((string) ($activeInstitute['public_phone'] ?? '')) ?>">
                </div>
            </div>

            <div class="row">
                <div>
                    <label class="field">Public Email</label>
                    <input type="email" name="public_email" maxlength="255" placeholder="e.g. info@institute.lk"
                           value="<?= htmlspecialchars((string) ($activeInstitute['public_email'] ?? '')) ?>">
                </div>
                <div>
                    <label class="field">Website</label>
                    <input type="text" name="website" maxlength="255" placeholder="e.g. https://institute.lk"
                           value="<?= htmlspecialchars((string) ($activeInstitute['website'] ?? '')) ?>">
                </div>
            </div>

            <label class="field">Opening Hours</label>
            <textarea name="opening_hours" placeholder="e.g. Monday – Saturday: 7:30 AM – 7:00 PM&#10;Sunday: 8:00 AM – 5:00 PM"><?= htmlspecialchars((string) ($activeInstitute['opening_hours'] ?? '')) ?></textarea>

            <label class="field">Registration Information</label>
            <textarea name="registration_info" placeholder="How prospective students or parents can register or enroll in classes"><?= htmlspecialchars((string) ($activeInstitute['registration_info'] ?? '')) ?></textarea>

            <label class="field">Facilities / Services</label>
            <textarea name="facilities_services" placeholder="e.g. AC lecture halls, library, cafeteria, digital attendance tracking"><?= htmlspecialchars((string) ($activeInstitute['facilities_services'] ?? '')) ?></textarea>

            <label class="field">Public Notes (Optional announcements or guidance)</label>
            <textarea name="public_notes" placeholder="Any special public guidance for website visitors"><?= htmlspecialchars((string) ($activeInstitute['public_notes'] ?? '')) ?></textarea>

            <label class="field">Allowed Website Domains</label>
            <input type="text" name="allowed_domains" maxlength="500" placeholder="e.g. academy.lk, www.academy.lk, localhost"
                   value="<?= htmlspecialchars((string) ($activeInstitute['allowed_domains'] ?? '')) ?>">
            <small class="muted">Comma-separated domains where the assistant widget may be embedded. (Localhost allowed in DEV_MODE).</small>
        </div>

        <!-- Section 2: Public AI Features -->
        <div class="card">
            <h2>Public AI Features</h2>
            <div class="note">
                Toggle which capabilities Gemini is allowed to use for this institute. Disabled categories will never expose corresponding AI tools to Gemini.
            </div>

            <?php
            $permissionOrder = ['institute_details', 'class_details', 'teacher_details', 'new_courses', 'attendance_details'];
            foreach ($permissionOrder as $key):
                $p = $permissions[$key] ?? ['name' => ucfirst(str_replace('_', ' ', $key)), 'enabled' => true];
            ?>
                <label class="permission">
                    <input
                        type="checkbox"
                        name="permissions[]"
                        value="<?= htmlspecialchars($key) ?>"
                        <?= !empty($p['enabled']) ? 'checked' : '' ?>
                    >
                    <span>
                        <strong><?= htmlspecialchars((string) $p['name']) ?></strong>
                        <small><?= htmlspecialchars($permissionHelp[$key] ?? '') ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <!-- Section 3: Assistant Identity & Prompting -->
        <div class="card">
            <h2>Assistant Identity</h2>

            <label class="field">Assistant Name</label>
            <input type="text" name="name" maxlength="255" required
                   value="<?= htmlspecialchars((string) ($assistant['name'] ?? 'Achieve AI')) ?>">

            <label class="field">Header Subtitle</label>
            <input type="text" name="header_subtitle" maxlength="255" placeholder="e.g. AI Assistant"
                   value="<?= htmlspecialchars((string) ($assistant['header_subtitle'] ?? 'AI Assistant')) ?>">

            <label class="field">Welcome Message</label>
            <textarea name="welcome_message" required><?= htmlspecialchars((string) ($assistant['welcome_message'] ?? 'Hello! How can I help you today?')) ?></textarea>

            <label class="field">Description</label>
            <textarea name="description"><?= htmlspecialchars((string) ($assistant['description'] ?? '')) ?></textarea>

            <label class="field">Purpose</label>
            <textarea name="purpose"><?= htmlspecialchars((string) ($assistant['purpose'] ?? '')) ?></textarea>
        </div>

        <!-- Section 4: Chat Appearance & Design -->
        <div class="card">
            <h2>Chat Appearance & Design</h2>

            <label class="field" style="margin-bottom:4px;">Custom Theme Colors</label>
            <div class="color-grid">
                <div class="color-item">
                    <span>Primary Color</span>
                    <div class="color-control">
                        <input type="color" name="theme_primary_color" id="primaryColorInput"
                               value="<?= htmlspecialchars((string) ($assistant['theme_primary_color'] ?? '#00B957')) ?>">
                        <span class="color-hex"><?= htmlspecialchars((string) ($assistant['theme_primary_color'] ?? '#00B957')) ?></span>
                    </div>
                </div>

                <div class="color-item">
                    <span>Header Text Color</span>
                    <div class="color-control">
                        <input type="color" name="theme_header_text_color"
                               value="<?= htmlspecialchars((string) ($assistant['theme_header_text_color'] ?? '#FFFFFF')) ?>">
                        <span class="color-hex"><?= htmlspecialchars((string) ($assistant['theme_header_text_color'] ?? '#FFFFFF')) ?></span>
                    </div>
                </div>

                <div class="color-item">
                    <span>Assistant Bubble Color</span>
                    <div class="color-control">
                        <input type="color" name="assistant_bubble_color"
                               value="<?= htmlspecialchars((string) ($assistant['assistant_bubble_color'] ?? '#EAEAEA')) ?>">
                        <span class="color-hex"><?= htmlspecialchars((string) ($assistant['assistant_bubble_color'] ?? '#EAEAEA')) ?></span>
                    </div>
                </div>

                <div class="color-item">
                    <span>User Bubble Color</span>
                    <div class="color-control">
                        <input type="color" name="user_bubble_color"
                               value="<?= htmlspecialchars((string) ($assistant['user_bubble_color'] ?? '#ECFDF3')) ?>">
                        <span class="color-hex"><?= htmlspecialchars((string) ($assistant['user_bubble_color'] ?? '#ECFDF3')) ?></span>
                    </div>
                </div>

                <div class="color-item">
                    <span>Chat Background Color</span>
                    <div class="color-control">
                        <input type="color" name="chat_background_color"
                               value="<?= htmlspecialchars((string) ($assistant['chat_background_color'] ?? '#FFFFFF')) ?>">
                        <span class="color-hex"><?= htmlspecialchars((string) ($assistant['chat_background_color'] ?? '#FFFFFF')) ?></span>
                    </div>
                </div>

                <div class="color-item">
                    <span>Text Color</span>
                    <div class="color-control">
                        <input type="color" name="theme_text_color"
                               value="<?= htmlspecialchars((string) ($assistant['theme_text_color'] ?? '#111827')) ?>">
                        <span class="color-hex"><?= htmlspecialchars((string) ($assistant['theme_text_color'] ?? '#111827')) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 5: Starter Messages -->
        <div class="card">
            <h2>Starter Messages</h2>
            <label class="field">Suggested Question Buttons</label>
            <textarea name="starter_messages" rows="6"><?= htmlspecialchars($starterLines) ?></textarea>
            <small class="muted" style="display:block;margin-top:6px;">Enter one suggested question per line.</small>
        </div>

        <button class="save" type="submit">Save Institute Settings</button>
    </form>
</div>

<script>
document.querySelectorAll('.color-control input[type=color]').forEach(input => {
    input.addEventListener('input', (e) => {
        const hex = e.target.parentElement.querySelector('.color-hex');
        if (hex) hex.textContent = e.target.value.toUpperCase();
    });
});

function copyEmbedScript() {
    const code = document.getElementById('embed-code-block').innerText;
    navigator.clipboard.writeText(code).then(() => {
        const textSpan = document.getElementById('copy-btn-text');
        const oldText = textSpan.textContent;
        textSpan.textContent = 'Copied!';
        setTimeout(() => {
            textSpan.textContent = oldText;
        }, 2000);
    }).catch(err => {
        alert('Could not copy automatically. Please copy the code manually.');
    });
}
</script>
</body>
</html>

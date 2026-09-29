<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

AdminAuth::requireLogin();

$pdo = Database::connection();
$instituteRepo = new InstituteRepository($pdo);
$assistantRepo = new AssistantRepository($pdo);

$institutes = $instituteRepo->getAll();

// Selected institute
$selectedInstituteId = isset($_GET['institute_id']) ? (int) $_GET['institute_id'] : 0;
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
        // Fallback to assistant 1
        $assistant = $assistantRepo->getAssistant(1);
    }
}

$assistantId = (int) ($assistant['id'] ?? 1);
$permissions = $assistantRepo->getPermissions($assistantId);

$saved = isset($_GET['saved']);
$base = ai_base_path();

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
}

$permissionHelp = [
    'class_details' => 'Expose public class schedules, subjects, grades, mediums, and times to website visitors.',
    'teacher_details' => 'Expose approved teacher names and their assigned public classes to website visitors.',
    'attendance_details' => 'Allow parents/students to look up attendance records via student name + parent/guardian verification (NO OTP).',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GETMORE AI Admin</title>
    <style>
        *{box-sizing:border-box}
        body{font-family:Arial,sans-serif;background:#f5f6f8;margin:0;color:#181818}
        .top{background:#111;color:#fff;padding:18px 28px;display:flex;justify-content:space-between;align-items:center}
        .top a{color:#fff;text-decoration:none;font-size:14px}
        .top a:hover{text-decoration:underline}
        .wrap{max-width:980px;margin:32px auto;padding:0 18px}
        .card{background:#fff;border:1px solid #e8e8e8;border-radius:16px;padding:24px;margin-bottom:18px}
        h1{font-size:26px;margin:0 0 6px}
        h2{font-size:18px;margin:0 0 16px;color:#111}
        .muted{color:#707070;font-size:13px}
        label.field{display:block;font-weight:700;margin:16px 0 7px;font-size:14px}
        input[type=text],textarea,select{width:100%;padding:12px;border:1px solid #d5d5d5;border-radius:10px;font:inherit;background:#fff}
        textarea{min-height:100px;resize:vertical}
        .toggle{display:flex;align-items:center;gap:10px;font-weight:700;font-size:14px}
        .permission{display:flex;gap:12px;padding:15px 0;border-top:1px solid #efefef;align-items:flex-start}
        .permission:first-of-type{border-top:0}
        .permission strong{display:block;margin-bottom:4px;font-size:14px}
        .permission small{color:#6f6f6f;line-height:1.45;font-size:13px}
        .save{border:0;background:#111;color:#fff;border-radius:10px;padding:14px 28px;font-size:15px;font-weight:700;cursor:pointer}
        .save:hover{background:#222}
        .success{background:#ecfaef;color:#246832;padding:12px 14px;border-radius:10px;margin-bottom:18px;font-weight:700}
        .note{background:#fff7e7;padding:13px 14px;border-radius:10px;line-height:1.5;color:#694c12;font-size:13px;margin-bottom:14px}

        .institute-nav{display:flex;gap:12px;align-items:center;margin-bottom:24px;background:#fff;border:1px solid #e8e8e8;border-radius:12px;padding:12px 18px}
        .institute-nav label{font-weight:700;font-size:14px}
        .institute-nav select{width:auto;min-width:240px;padding:8px 12px}

        .embed-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;font-family:Consolas,monospace;font-size:13px;color:#1e293b;white-space:pre-wrap;word-break:break-all}
        .embed-actions{display:flex;gap:12px;margin-top:12px;align-items:center}
        .btn-secondary{background:#00B957;color:#fff;border:0;border-radius:8px;padding:10px 18px;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
        .btn-secondary:hover{background:#009e4a}
        .btn-outline{background:#fff;color:#111;border:1px solid #ccc;border-radius:8px;padding:9px 16px;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
        .btn-outline:hover{background:#f0f0f0}

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
<div class="top">
    <strong>GETMORE AI Assistant Platform</strong>
    <a href="<?= htmlspecialchars($base) ?>/admin/logout.php">Logout</a>
</div>

<div class="wrap">
    <h1>Institute Assistant Settings</h1>
    <p class="muted" style="margin-bottom:20px;">Configure institute-specific AI assistants, security switches, theme appearance, and embed scripts.</p>

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
        <p class="muted" style="margin-top:-6px;margin-bottom:12px;">Place this script on the institute's website before the closing <code>&lt;/body&gt;</code> tag:</p>
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

        <!-- Institute Information -->
        <div class="card">
            <h2>Institute Configuration</h2>

            <label class="toggle">
                <input type="checkbox" name="enabled" value="1" <?= !empty($assistant['enabled']) ? 'checked' : '' ?>>
                Enable AI Assistant for this Institute
            </label>

            <label class="field">Institute Name</label>
            <input type="text" name="institute_name" maxlength="255" required
                   value="<?= htmlspecialchars((string) ($activeInstitute['name'] ?? 'Achieve Institute')) ?>">

            <label class="field">Public Widget Key</label>
            <input type="text" readonly style="background:#f8fafc;color:#64748b;font-family:Consolas,monospace;"
                   value="<?= htmlspecialchars($publicWidgetKey) ?>">
            <small class="muted">This unique public key identifies the assistant in widget.js embeds and chat requests.</small>

            <label class="field">Allowed Website Domains</label>
            <input type="text" name="allowed_domains" maxlength="500" placeholder="e.g. academy.lk, www.academy.lk, localhost"
                   value="<?= htmlspecialchars((string) ($activeInstitute['allowed_domains'] ?? '')) ?>">
            <small class="muted">Comma-separated domains where the assistant widget may be embedded. (Localhost allowed in DEV_MODE).</small>
        </div>

        <!-- Assistant Settings -->
        <div class="card">
            <h2>Assistant Identity</h2>

            <label class="field">Assistant Name</label>
            <input type="text" name="name" maxlength="255" required
                   value="<?= htmlspecialchars((string) ($assistant['name'] ?? 'Achieve AI')) ?>">

            <label class="field">Header Subtitle</label>
            <input type="text" name="header_subtitle" maxlength="255" placeholder="e.g. Virtual Assistant"
                   value="<?= htmlspecialchars((string) ($assistant['header_subtitle'] ?? 'AI Assistant')) ?>">

            <label class="field">Welcome Message</label>
            <textarea name="welcome_message" required><?= htmlspecialchars((string) ($assistant['welcome_message'] ?? 'Hello! How can I help you today?')) ?></textarea>
        </div>

        <!-- Feature Switches -->
        <div class="card">
            <h2>Public Feature Permissions</h2>
            <div class="note">
                Toggle which capabilities Gemini is allowed to use for this institute. Disabled categories will never expose corresponding AI tools.
            </div>

            <?php foreach ($permissions as $key => $permission): ?>
                <label class="permission">
                    <input
                        type="checkbox"
                        name="permissions[]"
                        value="<?= htmlspecialchars($key) ?>"
                        <?= !empty($permission['enabled']) ? 'checked' : '' ?>
                    >
                    <span>
                        <strong><?= htmlspecialchars((string) $permission['name']) ?></strong>
                        <small><?= htmlspecialchars($permissionHelp[$key] ?? '') ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <!-- Appearance & Theme -->
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

        <!-- Starter Messages -->
        <div class="card">
            <h2>Starter Messages</h2>
            <label class="field">Suggested Question Buttons</label>
            <textarea name="starter_messages" rows="5" placeholder="What classes do you offer?&#10;Who teaches Combined Mathematics?&#10;Show my attendance&#10;What time is Mathematics class?"><?= htmlspecialchars($starterLines) ?></textarea>
            <small class="muted" style="display:block;margin-top:6px;">Enter one suggested question per line.</small>
        </div>

        <!-- Purpose & Description -->
        <div class="card">
            <h2>Purpose & Instructions for Gemini</h2>

            <label class="field">Description</label>
            <textarea name="description"><?= htmlspecialchars((string) ($assistant['description'] ?? '')) ?></textarea>

            <label class="field">Purpose</label>
            <textarea name="purpose"><?= htmlspecialchars((string) ($assistant['purpose'] ?? '')) ?></textarea>
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

<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

AdminAuth::requireLogin();

$repository = new AssistantRepository(Database::connection());
$assistant = $repository->getAssistant(1);
$permissions = $repository->getPermissions(1);

$saved = isset($_GET['saved']);
$base = ai_base_path();

$permissionHelp = [
    'class_details' => 'Allows the AI to read only the logged-in student’s enrolled class details.',
    'attendance_details' => 'Allows the AI to read only the logged-in student’s attendance records.',
    'teacher_details' => 'Allows the AI to read approved teacher names for the logged-in student’s enrolled classes.',
];

$starterLines = '';
if (!empty($assistant['starter_messages'])) {
    $decoded = json_decode((string) $assistant['starter_messages'], true);
    if (is_array($decoded)) {
        $starterLines = implode("\n", $decoded);
    }
}
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
        .top a{color:#fff;text-decoration:none}
        .wrap{max-width:980px;margin:32px auto;padding:0 18px}
        .card{background:#fff;border:1px solid #e8e8e8;border-radius:16px;padding:24px;margin-bottom:18px}
        h1{font-size:26px;margin:0 0 6px}
        h2{font-size:18px;margin:0 0 18px}
        .muted{color:#707070}
        label.field{display:block;font-weight:700;margin:16px 0 7px}
        input[type=text],textarea{width:100%;padding:12px;border:1px solid #d5d5d5;border-radius:10px;font:inherit}
        textarea{min-height:110px;resize:vertical}
        .toggle{display:flex;align-items:center;gap:10px;font-weight:700}
        .permission{display:flex;gap:12px;padding:15px 0;border-top:1px solid #efefef}
        .permission:first-of-type{border-top:0}
        .permission strong{display:block;margin-bottom:5px}
        .permission small{color:#6f6f6f;line-height:1.45}
        .save{border:0;background:#111;color:#fff;border-radius:10px;padding:14px 28px;font-size:15px;font-weight:700;cursor:pointer}
        .save:hover{background:#222}
        .success{background:#ecfaef;color:#246832;padding:12px 14px;border-radius:10px;margin-bottom:18px}
        .note{background:#fff7e7;padding:13px 14px;border-radius:10px;line-height:1.5;color:#694c12}
        
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
    <strong>GETMORE AI Assistant</strong>
    <a href="<?= htmlspecialchars($base) ?>/admin/logout.php">Logout</a>
</div>

<div class="wrap">
    <h1>AI Assistant Settings</h1>
    <p class="muted">Control the student assistant without changing PHP code.</p>

    <?php if ($saved): ?>
        <div class="success">AI assistant settings were saved successfully.</div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($base) ?>/admin/save.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">

        <div class="card">
            <h2>General</h2>

            <label class="toggle">
                <input type="checkbox" name="enabled" value="1" <?= !empty($assistant['enabled']) ? 'checked' : '' ?>>
                Enable AI Assistant
            </label>

            <label class="field">Assistant Name</label>
            <input type="text" name="name" maxlength="255" required
                   value="<?= htmlspecialchars((string) $assistant['name']) ?>">

            <label class="field">Welcome Message</label>
            <textarea name="welcome_message" required><?= htmlspecialchars((string) $assistant['welcome_message']) ?></textarea>
        </div>

        <div class="card">
            <h2>Chat Appearance</h2>

            <label class="field">Header Subtitle</label>
            <input type="text" name="header_subtitle" maxlength="255" placeholder="e.g. AI Student Assistant"
                   value="<?= htmlspecialchars((string) ($assistant['header_subtitle'] ?? 'AI Assistant')) ?>">

            <label class="field" style="margin-bottom:4px;">Theme Colors</label>
            <div class="color-grid">
                <div class="color-item">
                    <span>Primary Color</span>
                    <div class="color-control">
                        <input type="color" name="theme_primary_color" id="primaryColorInput"
                               value="<?= htmlspecialchars((string) ($assistant['theme_primary_color'] ?? '#00B957')) ?>">
                        <span class="color-hex" id="primaryColorHex"><?= htmlspecialchars((string) ($assistant['theme_primary_color'] ?? '#00B957')) ?></span>
                    </div>
                </div>

                <div class="color-item">
                    <span>Header Text Color</span>
                    <div class="color-control">
                        <input type="color" name="theme_header_text_color" id="headerTextColInput"
                               value="<?= htmlspecialchars((string) ($assistant['theme_header_text_color'] ?? '#FFFFFF')) ?>">
                        <span class="color-hex" id="headerTextColHex"><?= htmlspecialchars((string) ($assistant['theme_header_text_color'] ?? '#FFFFFF')) ?></span>
                    </div>
                </div>

                <div class="color-item">
                    <span>Assistant Bubble Color</span>
                    <div class="color-control">
                        <input type="color" name="assistant_bubble_color" id="asstBubbleColInput"
                               value="<?= htmlspecialchars((string) ($assistant['assistant_bubble_color'] ?? '#EAEAEA')) ?>">
                        <span class="color-hex" id="asstBubbleColHex"><?= htmlspecialchars((string) ($assistant['assistant_bubble_color'] ?? '#EAEAEA')) ?></span>
                    </div>
                </div>

                <div class="color-item">
                    <span>User Bubble Color</span>
                    <div class="color-control">
                        <input type="color" name="user_bubble_color" id="userBubbleColInput"
                               value="<?= htmlspecialchars((string) ($assistant['user_bubble_color'] ?? '#ECFDF3')) ?>">
                        <span class="color-hex" id="userBubbleColHex"><?= htmlspecialchars((string) ($assistant['user_bubble_color'] ?? '#ECFDF3')) ?></span>
                    </div>
                </div>

                <div class="color-item">
                    <span>Chat Background Color</span>
                    <div class="color-control">
                        <input type="color" name="chat_background_color" id="chatBgColInput"
                               value="<?= htmlspecialchars((string) ($assistant['chat_background_color'] ?? '#FFFFFF')) ?>">
                        <span class="color-hex" id="chatBgColHex"><?= htmlspecialchars((string) ($assistant['chat_background_color'] ?? '#FFFFFF')) ?></span>
                    </div>
                </div>

                <div class="color-item">
                    <span>Main Text Color</span>
                    <div class="color-control">
                        <input type="color" name="theme_text_color" id="mainTextColInput"
                               value="<?= htmlspecialchars((string) ($assistant['theme_text_color'] ?? '#111827')) ?>">
                        <span class="color-hex" id="mainTextColHex"><?= htmlspecialchars((string) ($assistant['theme_text_color'] ?? '#111827')) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Starter Messages</h2>
            <label class="field">Suggested Question Buttons</label>
            <textarea name="starter_messages" rows="5" placeholder="What classes do I have?&#10;Show my attendance&#10;Who are my teachers?&#10;When is my next class?"><?= htmlspecialchars($starterLines) ?></textarea>
            <small class="muted" style="display:block;margin-top:6px;">Enter one suggested question per line.</small>
        </div>

        <div class="card">
            <h2>Purpose & Description</h2>

            <label class="field">Description</label>
            <textarea name="description"><?= htmlspecialchars((string) $assistant['description']) ?></textarea>

            <label class="field">Purpose</label>
            <textarea name="purpose"><?= htmlspecialchars((string) $assistant['purpose']) ?></textarea>
        </div>

        <div class="card">
            <h2>AI Permissions</h2>
            <div class="note">
                Only enabled categories are exposed as AI tools. Everything else in GETMORE remains unavailable to the assistant.
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

        <button class="save" type="submit">Save AI Settings</button>
    </form>
</div>

<script>
document.querySelectorAll('.color-control input[type=color]').forEach(input => {
    input.addEventListener('input', (e) => {
        const hex = e.target.parentElement.querySelector('.color-hex');
        if (hex) hex.textContent = e.target.value.toUpperCase();
    });
});
</script>
</body>
</html>

<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

AdminAuth::requireLogin();

$pdo = Database::connection();
$instituteRepo = new InstituteRepository($pdo);
$assistantRepo = new AssistantRepository($pdo);
$integrationRepo = new InstituteIntegrationRepository($pdo);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$institute = $instituteRepo->getById($id);

$base = ai_base_path();

if (!$institute) {
    header('Location: ' . $base . '/admin/institutes.php');
    exit;
}

$assistant = $assistantRepo->getByInstituteId($id);
$assistantId = (int) ($assistant['id'] ?? 1);

$integration = $integrationRepo->getByInstituteId($id);
$effectiveConfig = $integrationRepo->getEffectiveConfig($id);
$hasCustomKey = ($integration !== null && !empty($integration['has_api_key']));
$maskedKey = $hasCustomKey ? $integration['masked_api_key'] : null;
$apiBaseUrl = (string) ($integration['api_base_url'] ?? $effectiveConfig['base_url'] ?? 'https://demo.getmore.lk');
$classesEndpoint = (string) ($integration['classes_endpoint'] ?? $effectiveConfig['classes_endpoint'] ?? '/api/v1/classes');
$lecturersEndpoint = (string) ($integration['lecturers_endpoint'] ?? $effectiveConfig['lecturers_endpoint'] ?? '/api/v1/lecturers');
$extraClassesEndpoint = (string) ($integration['extra_classes_endpoint'] ?? $effectiveConfig['extra_classes_endpoint'] ?? '/api/v1/extra-classes');
$attendanceEndpoint = (string) ($integration['attendance_endpoint'] ?? $effectiveConfig['attendance_endpoint'] ?? '/api/v1/student/attendance/today');
$apiEnabled = $integration !== null ? !empty($integration['is_active']) : true;

$saved = isset($_GET['saved']);
$created = isset($_GET['created']);
$keyRegenerated = isset($_GET['key_regenerated']);
$error = $_GET['error'] ?? null;

$widgetScriptUrl = ai_app_url('/widget.js');
$widgetKey = (string) $institute['public_widget_key'];

$embedCode = '<script' . "\n" .
    '    src="' . htmlspecialchars($widgetScriptUrl) . '"' . "\n" .
    '    data-assistant="' . htmlspecialchars($widgetKey) . '">' . "\n" .
    '</script>';

$previewUrl = $base . '/chat.php?assistant=' . urlencode($widgetKey);

// Convert comma-separated allowed domains to newline-separated for cleaner editing
$rawAllowed = (string) ($institute['allowed_domains'] ?? '');
$allowedLines = '';
if ($rawAllowed !== '') {
    $domains = preg_split('/[\s,]+/', $rawAllowed, -1, PREG_SPLIT_NO_EMPTY);
    if (is_array($domains)) {
        $allowedLines = implode("\n", array_unique($domains));
    }
}

$activeNav = 'institutes';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Institute - <?= htmlspecialchars((string) $institute['name']) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/admin.css">
    <style>
        .split-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
        .widget-key-display{display:flex;align-items:center;gap:12px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:10px;padding:8px 14px;font-family:Consolas,monospace;font-size:14px;color:#1e293b}
    </style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>

<div class="wrap">
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit Institute: <?= htmlspecialchars((string) $institute['name']) ?></h1>
            <p class="page-desc">Update institute profile information, contact channels, allowed domains, and integration settings.</p>
        </div>
        <div class="header-actions">
            <a href="<?= htmlspecialchars($base) ?>/admin/index.php?institute_id=<?= $id ?>" class="btn-primary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                Assistant Settings
            </a>
            <a href="<?= htmlspecialchars($base) ?>/admin/institutes.php" class="btn-outline">
                &larr; Institutes List
            </a>
        </div>
    </div>

    <?php if ($created): ?>
        <div class="alert alert-success">
            Institute created successfully! A default AI assistant and public widget key were generated.
        </div>
    <?php endif; ?>

    <?php if ($saved): ?>
        <div class="alert alert-success">Institute settings saved successfully.</div>
    <?php endif; ?>

    <?php if ($keyRegenerated): ?>
        <div class="alert alert-info">
            Public widget key regenerated successfully. Please update the embed code on any websites using this assistant.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Section: Public Widget Key & Embed Code -->
    <div class="card">
        <h2>Website Installation & Widget Key</h2>
        <p class="muted" style="margin-top:-6px;margin-bottom:14px;">
            Each institute has a unique public widget key used by website visitors to connect to this assistant.
        </p>

        <div style="margin-bottom:16px;">
            <label class="field" style="margin-top:0;">Public Widget Key</label>
            <div class="split-actions">
                <div class="widget-key-display">
                    <span id="widget-key-text"><?= htmlspecialchars($widgetKey) ?></span>
                    <button type="button" class="btn-outline btn-sm" onclick="copyWidgetKey()" style="padding:4px 8px;font-size:12px;">
                        <span id="key-copy-btn-text">Copy Key</span>
                    </button>
                </div>

                <form method="post" action="<?= htmlspecialchars($base) ?>/admin/institute-widget-key.php" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <input type="hidden" name="institute_id" value="<?= $id ?>">
                    <button type="submit" class="btn-outline btn-sm btn-danger-outline"
                            onclick="return confirm('WARNING: Regenerating the widget key will invalidate the previous key. Any existing website embed scripts will stop working until updated. Do you want to proceed?');">
                        Regenerate Widget Key
                    </button>
                </form>
            </div>
            <small class="muted" style="display:block;margin-top:4px;">
                Note: This key is for public identification only and is safe to publish in client-side HTML.
            </small>
        </div>

        <div>
            <label class="field">Embed Script Snippet</label>
            <div class="embed-box" id="embed-code-block"><?= htmlspecialchars($embedCode) ?></div>
            <div class="embed-actions">
                <button type="button" class="btn-primary" id="copy-script-btn" onclick="copyEmbedScript()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                    </svg>
                    <span id="copy-btn-text">Copy Script</span>
                </button>

                <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" rel="noopener" class="btn-outline">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                        <polyline points="15 3 21 3 21 9"/>
                        <line x1="10" y1="14" x2="21" y2="3"/>
                    </svg>
                    Preview Assistant
                </a>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <form method="post" action="<?= htmlspecialchars($base) ?>/admin/institute-update.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">

        <!-- Section 1: Basic Information -->
        <div class="card">
            <h2>Basic Information</h2>

            <label class="field">Institute Name <span style="color:#ef4444;">*</span></label>
            <input type="text" name="name" maxlength="255" required value="<?= htmlspecialchars((string) $institute['name']) ?>">

            <label class="field">Short Description</label>
            <input type="text" name="short_description" maxlength="500" value="<?= htmlspecialchars((string) ($institute['short_description'] ?? '')) ?>" placeholder="e.g. Leading higher education and tuition institute">

            <label class="field">About Institute</label>
            <textarea name="about" placeholder="Detailed background, academic mission, and institute achievements"><?= htmlspecialchars((string) ($institute['about'] ?? '')) ?></textarea>
        </div>

        <!-- Section 2: Public Contact Information -->
        <div class="card">
            <h2>Public Contact Information</h2>

            <div class="row">
                <div>
                    <label class="field">Public Address</label>
                    <input type="text" name="public_address" maxlength="500" value="<?= htmlspecialchars((string) ($institute['public_address'] ?? '')) ?>" placeholder="e.g. No. 124, High Level Road, Nugegoda">
                </div>
                <div>
                    <label class="field">Public Phone</label>
                    <input type="text" name="public_phone" maxlength="100" value="<?= htmlspecialchars((string) ($institute['public_phone'] ?? '')) ?>" placeholder="e.g. +94 11 282 9900">
                </div>
            </div>

            <div class="row">
                <div>
                    <label class="field">Public Email</label>
                    <input type="email" name="public_email" maxlength="255" value="<?= htmlspecialchars((string) ($institute['public_email'] ?? '')) ?>" placeholder="e.g. info@institute.lk">
                </div>
                <div>
                    <label class="field">Website</label>
                    <input type="text" name="website" maxlength="255" value="<?= htmlspecialchars((string) ($institute['website'] ?? '')) ?>" placeholder="e.g. https://institute.lk">
                </div>
            </div>

            <label class="field">Opening Hours</label>
            <textarea name="opening_hours" placeholder="e.g. Monday – Saturday: 7:30 AM – 7:00 PM&#10;Sunday: 8:00 AM – 5:00 PM"><?= htmlspecialchars((string) ($institute['opening_hours'] ?? '')) ?></textarea>
        </div>

        <!-- Section 3: Other Public Information -->
        <div class="card">
            <h2>Other Public Information</h2>

            <label class="field">Registration Information</label>
            <textarea name="registration_information" placeholder="How students or parents can register or enroll in classes"><?= htmlspecialchars((string) ($institute['registration_information'] ?? '')) ?></textarea>

            <label class="field">Facilities / Services</label>
            <textarea name="facilities" placeholder="e.g. AC lecture halls, library, study areas, digital attendance, cafeteria"><?= htmlspecialchars((string) ($institute['facilities'] ?? '')) ?></textarea>

            <label class="field">Public Notes (Optional announcements or guidance)</label>
            <textarea name="public_notes" placeholder="Special public notices or helpful visitor instructions"><?= htmlspecialchars((string) ($institute['public_notes'] ?? '')) ?></textarea>
        </div>

        <!-- Section 4: Website Configuration -->
        <div class="card">
            <h2>Website Configuration & Status</h2>

            <label class="field">Allowed Domains</label>
            <textarea name="allowed_domains" rows="3" placeholder="abc.edu.lk&#10;www.abc.edu.lk"><?= htmlspecialchars($allowedLines) ?></textarea>
            <small class="muted" style="display:block;margin-top:6px;">
                One domain per line or comma-separated. Requests from unauthorized domains will be rejected by the API.
            </small>

            <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;">
                <label style="display:flex;align-items:center;gap:10px;font-weight:700;font-size:14px;cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($institute['is_active']) ? 'checked' : '' ?> style="width:18px;height:18px;">
                    Enable Institute & Public AI Assistant
                </label>
            </div>
        </div>

        <!-- Section 5: GETMORE REST API Integration -->
        <div class="card" id="api-integration-section">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                <h2 style="margin:0;">GETMORE REST API Integration</h2>
                <div>
                    <?php if ($hasCustomKey): ?>
                        <span class="badge badge-active"><span class="badge-dot"></span> Configured ✓ (Institute Secret Active)</span>
                    <?php else: ?>
                        <span class="badge" style="background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;"><span class="badge-dot"></span> Global Fallback (.env Active)</span>
                    <?php endif; ?>
                </div>
            </div>
            <p class="muted" style="margin-top:-4px;margin-bottom:18px;">
                Connect this institute's AI assistant directly to the GETMORE REST API for live classes, courses, lecturers, and attendance. Secrets are encrypted and kept strictly server-side.
            </p>

            <div class="row">
                <div>
                    <label class="field">API Base URL</label>
                    <input type="url" name="getmore_api_base_url" id="api-base-url" value="<?= htmlspecialchars($apiBaseUrl) ?>" placeholder="https://demo.getmore.lk">
                    <small class="muted" style="display:block;margin-top:4px;">Base URL for the GETMORE instance (e.g. https://demo.getmore.lk).</small>
                </div>
                <div>
                    <label class="field">Secret API Token</label>
                    <?php if ($hasCustomKey): ?>
                        <div class="api-token-status">
                            <span class="token-preview"><?= htmlspecialchars($maskedKey ?? '') ?></span>
                            <span class="configured-badge">Configured ✓</span>
                        </div>
                        <input type="password" name="getmore_api_key" id="api-secret-key" class="form-control" value="" placeholder="Update API Key (leave empty to keep current key)" autocomplete="new-password">
                        <small class="muted" style="display:block;margin-top:6px;line-height:1.45;">Enter a new token only if you wish to change the currently stored secret key.</small>
                    <?php else: ?>
                        <input type="password" name="getmore_api_key" id="api-secret-key" class="form-control" value="" placeholder="Enter GETMORE secret API key (e.g. gme_...)" autocomplete="new-password">
                        <small class="muted" style="display:block;margin-top:6px;line-height:1.45;">If left blank, the system falls back to GETMORE_API_KEY in .env.</small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div>
                    <label class="field">Classes Endpoint</label>
                    <input type="text" name="getmore_classes_endpoint" id="classes-endpoint" value="<?= htmlspecialchars($classesEndpoint) ?>" placeholder="/api/v1/classes">
                </div>
                <div>
                    <label class="field">Lecturers Endpoint</label>
                    <input type="text" name="getmore_lecturers_endpoint" id="lecturers-endpoint" value="<?= htmlspecialchars($lecturersEndpoint) ?>" placeholder="/api/v1/lecturers">
                </div>
            </div>

            <div class="row">
                <div>
                    <label class="field">Extra Classes Endpoint</label>
                    <input type="text" name="getmore_extra_classes_endpoint" id="extra-classes-endpoint" value="<?= htmlspecialchars($extraClassesEndpoint) ?>" placeholder="/api/v1/extra-classes">
                </div>
                <div>
                    <label class="field">Attendance Endpoint</label>
                    <input type="text" name="getmore_attendance_endpoint" id="attendance-endpoint" value="<?= htmlspecialchars($attendanceEndpoint) ?>" placeholder="/api/v1/student/attendance/today">
                </div>
            </div>

            <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f1f5f9;">
                <label style="display:flex;align-items:center;gap:10px;font-weight:700;font-size:14px;cursor:pointer;">
                    <input type="checkbox" name="getmore_api_enabled" value="1" <?= $apiEnabled ? 'checked' : '' ?> style="width:18px;height:18px;">
                    Enable GETMORE REST API for this Institute
                </label>
                <small class="muted" style="display:block;margin-left:28px;margin-top:2px;">
                    When enabled, the assistant queries the GETMORE REST API for live institute data.
                </small>
            </div>

            <!-- API Test Actions -->
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;">
                <label class="field" style="margin-top:0;margin-bottom:6px;">Live Server-to-Server API Tests</label>
                <p class="muted" style="margin-top:0;margin-bottom:12px;">
                    Verify endpoint connectivity directly from this server. Tests use the secret key securely without leaking it to the browser.
                </p>

                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="btn-outline btn-sm" onclick="runApiTest('test_connection')">
                        Test Connection
                    </button>
                    <button type="button" class="btn-outline btn-sm" onclick="runApiTest('test_classes')">
                        Test Classes
                    </button>
                    <button type="button" class="btn-outline btn-sm" onclick="runApiTest('test_lecturers')">
                        Test Lecturers
                    </button>
                    <button type="button" class="btn-outline btn-sm" onclick="runApiTest('test_extra_classes')">
                        Test Extra Classes
                    </button>
                    <button type="button" class="btn-outline btn-sm" onclick="runApiTest('test_attendance')">
                        Test Attendance
                    </button>
                </div>

                <div id="api-test-spinner" style="display:none;margin-top:12px;align-items:center;gap:8px;font-size:13px;color:#64748b;">
                    <span style="display:inline-block;width:12px;height:12px;border:2px solid #00B957;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;"></span>
                    Testing GETMORE endpoint from server...
                </div>

                <div id="api-test-result" style="display:none;margin-top:12px;padding:12px 14px;border-radius:8px;font-size:13px;line-height:1.5;"></div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">
                Save Changes
            </button>
            <a href="<?= htmlspecialchars($base) ?>/admin/institutes.php" class="btn-outline">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
function copyEmbedScript() {
    const code = document.getElementById('embed-code-block').innerText;
    navigator.clipboard.writeText(code).then(() => {
        const textSpan = document.getElementById('copy-btn-text');
        const oldText = textSpan.textContent;
        textSpan.textContent = 'Copied!';
        setTimeout(() => { textSpan.textContent = oldText; }, 2000);
    }).catch(() => {
        alert('Could not copy automatically. Please copy the code manually.');
    });
}

function copyWidgetKey() {
    const key = document.getElementById('widget-key-text').innerText;
    navigator.clipboard.writeText(key).then(() => {
        const textSpan = document.getElementById('key-copy-btn-text');
        textSpan.textContent = 'Copied!';
        setTimeout(() => { textSpan.textContent = 'Copy Key'; }, 2000);
    }).catch(() => {
        alert('Could not copy automatically. Please copy the key manually.');
    });
}

function runApiTest(action) {
    const resultBox = document.getElementById('api-test-result');
    const spinner = document.getElementById('api-test-spinner');

    spinner.style.display = 'flex';
    resultBox.style.display = 'none';

    const formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars(Csrf::token()) ?>');
    formData.append('institute_id', '<?= (int) $id ?>');
    formData.append('action', action);
    formData.append('api_base_url', document.getElementById('api-base-url').value);
    formData.append('api_key', document.getElementById('api-secret-key').value);
    formData.append('classes_endpoint', document.getElementById('classes-endpoint').value);
    formData.append('lecturers_endpoint', document.getElementById('lecturers-endpoint').value);
    formData.append('extra_classes_endpoint', document.getElementById('extra-classes-endpoint').value);
    formData.append('attendance_endpoint', document.getElementById('attendance-endpoint').value);

    fetch('<?= htmlspecialchars($base) ?>/admin/api-test.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        spinner.style.display = 'none';
        resultBox.style.display = 'block';
        if (data.success) {
            resultBox.style.background = '#ecfdf5';
            resultBox.style.color = '#065f46';
            resultBox.style.border = '1px solid #a7f3d0';
            resultBox.innerHTML = '<strong>' + action.replace(/_/g, ' ').toUpperCase() + ':</strong> ' + data.message;
        } else {
            resultBox.style.background = '#fef2f2';
            resultBox.style.color = '#991b1b';
            resultBox.style.border = '1px solid #fecaca';
            resultBox.innerHTML = '<strong>TEST FAILED:</strong> ' + (data.error || 'Unknown error occurred.');
        }
    })
    .catch(err => {
        spinner.style.display = 'none';
        resultBox.style.display = 'block';
        resultBox.style.background = '#fef2f2';
        resultBox.style.color = '#991b1b';
        resultBox.style.border = '1px solid #fecaca';
        resultBox.innerHTML = '<strong>REQUEST FAILED:</strong> ' + err.message;
    });
}
</script>
</body>
</html>

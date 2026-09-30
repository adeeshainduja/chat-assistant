<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

AdminAuth::requireLogin();

$pdo = Database::connection();
$instituteRepo = new InstituteRepository($pdo);
$institutes = $instituteRepo->getAll();

$base = ai_base_path();
$currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$widgetScriptUrl = $scheme . '://' . $currentHost . $base . '/widget.js';

$statusUpdated = isset($_GET['status_updated']);
$deleted = isset($_GET['deleted']);

$activeNav = 'institutes';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Institutes - GETMORE AI Platform</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/admin.css">
    <style>
        .actions-cell{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
        .modal-backdrop{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(3px);display:none;align-items:center;justify-content:center;z-index:999;padding:20px}
        .modal-backdrop.open{display:flex}
        .modal-box{background:#fff;border-radius:16px;max-width:540px;width:100%;padding:24px;box-shadow:0 20px 40px rgba(0,0,0,0.25);position:relative}
        .modal-title{font-size:18px;font-weight:700;margin:0 0 8px;color:#0f172a}
        .modal-close{position:absolute;top:18px;right:18px;background:none;border:none;font-size:20px;color:#64748b;cursor:pointer}
        .modal-close:hover{color:#0f172a}
    </style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>

<div class="wrap">
    <div class="page-header">
        <div>
            <h1 class="page-title">Institutes</h1>
            <p class="page-desc">Manage multi-institute customer accounts, public profiles, AI assistants, and website embed keys.</p>
        </div>
        <div class="header-actions">
            <a href="<?= htmlspecialchars($base) ?>/admin/institute-create.php" class="btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add Institute
            </a>
        </div>
    </div>

    <?php if ($statusUpdated): ?>
        <div class="alert alert-success">Institute status was updated successfully.</div>
    <?php endif; ?>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap" style="border:0;border-radius:0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Institute Name</th>
                        <th>Status</th>
                        <th>Website</th>
                        <th>Widget Key</th>
                        <th>Created Date</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($institutes)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:36px;color:#64748b;">
                                No institutes found. Click "Add Institute" to create your first institute.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($institutes as $inst):
                            $instId = (int) $inst['id'];
                            $isActive = !empty($inst['is_active']);
                            $widgetKey = (string) $inst['public_widget_key'];
                            $website = trim((string) ($inst['website'] ?? ''));
                            $createdAt = !empty($inst['created_at']) ? date('M j, Y', strtotime((string)$inst['created_at'])) : '—';
                        ?>
                            <tr>
                                <td>
                                    <strong style="color:#0f172a;"><?= htmlspecialchars((string) $inst['name']) ?></strong>
                                    <?php if (!empty($inst['short_description'])): ?>
                                        <div class="muted" style="font-size:12px;margin-top:2px;">
                                            <?= htmlspecialchars(mb_substr((string) $inst['short_description'], 0, 75)) ?><?= mb_strlen((string) $inst['short_description']) > 75 ? '...' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="badge badge-active"><span class="badge-dot"></span> Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-disabled"><span class="badge-dot"></span> Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($website !== ''): ?>
                                        <a href="<?= htmlspecialchars(str_starts_with($website, 'http') ? $website : 'https://' . $website) ?>" target="_blank" rel="noopener" style="color:#00B957;text-decoration:none;font-size:13px;">
                                            <?= htmlspecialchars($website) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="key-code" title="Public Widget Key"><?= htmlspecialchars($widgetKey) ?></span>
                                </td>
                                <td>
                                    <span class="muted" style="font-size:13px;"><?= htmlspecialchars($createdAt) ?></span>
                                </td>
                                <td>
                                    <div class="actions-cell" style="justify-content:flex-end;">
                                        <a href="<?= htmlspecialchars($base) ?>/admin/institute-edit.php?id=<?= $instId ?>" class="btn-outline btn-sm">
                                            Edit
                                        </a>

                                        <a href="<?= htmlspecialchars($base) ?>/admin/index.php?institute_id=<?= $instId ?>" class="btn-outline btn-sm" style="background:#f0fdf4;border-color:#bbf7d0;color:#16a34a;">
                                            Assistant
                                        </a>

                                        <button type="button" class="btn-outline btn-sm" onclick="showEmbedModal('<?= htmlspecialchars((string)$inst['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($widgetKey, ENT_QUOTES) ?>')">
                                            Embed Code
                                        </button>

                                        <form method="post" action="<?= htmlspecialchars($base) ?>/admin/institute-status.php" style="display:inline;margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                            <input type="hidden" name="institute_id" value="<?= $instId ?>">
                                            <input type="hidden" name="status" value="<?= $isActive ? '0' : '1' ?>">
                                            <?php if ($isActive): ?>
                                                <button type="submit" class="btn-outline btn-sm btn-danger-outline" onclick="return confirm('Are you sure you want to disable <?= htmlspecialchars((string)$inst['name'], ENT_QUOTES) ?>? Its public AI assistant will become unavailable.');">
                                                    Disable
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn-outline btn-sm btn-success-outline">
                                                    Enable
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Embed Code Modal -->
<div id="embed-modal" class="modal-backdrop">
    <div class="modal-box">
        <button type="button" class="modal-close" onclick="closeEmbedModal()">&times;</button>
        <h3 class="modal-title" id="modal-inst-name">Website Installation Code</h3>
        <p class="muted" style="margin-top:-4px;margin-bottom:14px;">
            Copy and paste this script tag onto the institute's website before the closing <code>&lt;/body&gt;</code> tag:
        </p>
        <div class="embed-box" id="modal-code-block"></div>
        <div class="embed-actions" style="margin-top:16px;">
            <button type="button" class="btn-primary" id="modal-copy-btn" onclick="copyModalScript()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
                <span id="modal-copy-text">Copy Script</span>
            </button>
            <button type="button" class="btn-outline" onclick="closeEmbedModal()">Close</button>
        </div>
    </div>
</div>

<script>
const widgetScriptUrl = <?= json_encode($widgetScriptUrl) ?>;

function showEmbedModal(instName, widgetKey) {
    document.getElementById('modal-inst-name').textContent = instName + ' - Embed Code';
    const code = `<script\n    src="${widgetScriptUrl}"\n    data-assistant="${widgetKey}">\n<\/script>`;
    document.getElementById('modal-code-block').textContent = code;
    document.getElementById('embed-modal').classList.add('open');
}

function closeEmbedModal() {
    document.getElementById('embed-modal').classList.remove('open');
}

function copyModalScript() {
    const code = document.getElementById('modal-code-block').textContent;
    navigator.clipboard.writeText(code).then(() => {
        const span = document.getElementById('modal-copy-text');
        span.textContent = 'Copied!';
        setTimeout(() => { span.textContent = 'Copy Script'; }, 2000);
    }).catch(() => {
        alert('Could not copy automatically. Please copy the code manually.');
    });
}

document.getElementById('embed-modal').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) closeEmbedModal();
});
</script>
</body>
</html>

<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

AdminAuth::requireLogin();

$base = ai_base_path();
$activeNav = 'institute-create';
$error = $_GET['error'] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Institute - GETMORE AI Platform</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/admin.css">
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>

<div class="wrap">
    <div class="page-header">
        <div>
            <h1 class="page-title">Add New Institute</h1>
            <p class="page-desc">Create a new institute account. A dedicated AI assistant and secure widget embed key will be generated automatically.</p>
        </div>
        <a href="<?= htmlspecialchars($base) ?>/admin/institutes.php" class="btn-outline">
            &larr; Back to Institutes
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($base) ?>/admin/institute-store.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">

        <!-- Section 1: Basic Information -->
        <div class="card">
            <h2>Basic Information</h2>

            <label class="field">Institute Name <span style="color:#ef4444;">*</span></label>
            <input type="text" name="name" maxlength="255" required placeholder="e.g. ABC Education Institute">

            <label class="field">Short Description</label>
            <input type="text" name="short_description" maxlength="500" placeholder="e.g. Leading tuition institute for secondary and A/L students">

            <label class="field">About Institute</label>
            <textarea name="about" placeholder="Detailed background, academic mission, and institute achievements"></textarea>
        </div>

        <!-- Section 2: Public Contact Information -->
        <div class="card">
            <h2>Public Contact Information</h2>

            <div class="row">
                <div>
                    <label class="field">Public Address</label>
                    <input type="text" name="public_address" maxlength="500" placeholder="e.g. No. 124, High Level Road, Nugegoda">
                </div>
                <div>
                    <label class="field">Public Phone</label>
                    <input type="text" name="public_phone" maxlength="100" placeholder="e.g. +94 11 282 9900 / 077 712 3456">
                </div>
            </div>

            <div class="row">
                <div>
                    <label class="field">Public Email</label>
                    <input type="email" name="public_email" maxlength="255" placeholder="e.g. info@institute.lk">
                </div>
                <div>
                    <label class="field">Website</label>
                    <input type="text" name="website" maxlength="255" placeholder="e.g. https://institute.lk">
                </div>
            </div>

            <label class="field">Opening Hours</label>
            <textarea name="opening_hours" placeholder="e.g. Monday – Saturday: 7:30 AM – 7:00 PM&#10;Sunday: 8:00 AM – 5:00 PM"></textarea>
        </div>

        <!-- Section 3: Other Public Information -->
        <div class="card">
            <h2>Other Public Information</h2>

            <label class="field">Registration Information</label>
            <textarea name="registration_information" placeholder="How students or parents can register or enroll in classes"></textarea>

            <label class="field">Facilities / Services</label>
            <textarea name="facilities" placeholder="e.g. AC lecture halls, library, study areas, digital attendance, cafeteria"></textarea>

            <label class="field">Public Notes (Optional announcements or guidance)</label>
            <textarea name="public_notes" placeholder="Special public notices or helpful visitor instructions"></textarea>
        </div>

        <!-- Section 4: Website Configuration -->
        <div class="card">
            <h2>Website Configuration</h2>

            <label class="field">Allowed Domains</label>
            <textarea name="allowed_domains" rows="3" placeholder="abc.edu.lk&#10;www.abc.edu.lk"></textarea>
            <small class="muted" style="display:block;margin-top:6px;">
                Enter allowed website domains (one per line or comma-separated). Requests from other domains will be rejected. In development mode, localhost is automatically permitted.
            </small>

            <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;">
                <label style="display:flex;align-items:center;gap:10px;font-weight:700;font-size:14px;cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" checked style="width:18px;height:18px;">
                    Enable Institute & Public AI Assistant
                </label>
            </div>
        </div>

        <div style="display:flex;gap:12px;margin-top:24px;">
            <button type="submit" class="btn-primary" style="padding:12px 28px;font-size:15px;">
                Create Institute
            </button>
            <a href="<?= htmlspecialchars($base) ?>/admin/institutes.php" class="btn-outline" style="padding:12px 20px;">
                Cancel
            </a>
        </div>
    </form>
</div>
</body>
</html>

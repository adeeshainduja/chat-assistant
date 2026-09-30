<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

AdminAuth::start();

if (AdminAuth::check()) {
    header('Location: ' . ai_base_path() . '/admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (AdminAuth::attempt($username, $password)) {
        header('Location: ' . ai_base_path() . '/admin/index.php');
        exit;
    }

    $error = 'Invalid administrator credentials.';
}

$base = ai_base_path();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Admin Login</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { max-width: 100%; margin: 0; padding: 16px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f3f4f6; display: grid; place-items: center; min-height: 100vh; overflow-x: hidden; }
        .card { width: min(420px, 100%); background: #fff; border-radius: 16px; padding: 28px 24px; box-shadow: 0 20px 55px rgba(0,0,0,.10); box-sizing: border-box; }
        h1 { margin: 0 0 8px; font-size: 24px; color: #0f172a; letter-spacing: -0.02em; }
        .muted { color: #64748b; margin: 0 0 22px; font-size: 14px; line-height: 1.45; }
        label { display: block; font-weight: 700; margin: 14px 0 7px; font-size: 13.5px; color: #334155; }
        input { width: 100%; max-width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 10px; box-sizing: border-box; font: inherit; font-size: 14px; transition: border-color .15s, box-shadow .15s; }
        input:focus { outline: 0; border-color: #00B957; box-shadow: 0 0 0 3px rgba(0, 185, 87, .15); }
        button { margin-top: 20px; width: 100%; padding: 12px; border: 0; border-radius: 10px; background: #0f172a; color: #fff; font-weight: 700; font-size: 14px; cursor: pointer; transition: background .15s; }
        button:hover { background: #1e293b; }
        .error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 10px 14px; border-radius: 10px; margin-bottom: 14px; font-size: 13.5px; line-height: 1.4; }
        @media (max-width: 480px) {
            html, body { padding: 10px; }
            .card { padding: 20px 16px; border-radius: 12px; }
            h1 { font-size: 20px; }
            .muted { font-size: 13px; margin-bottom: 16px; }
        }
    </style>
</head>
<body>
<div class="card">
    <h1>AI Assistant Admin</h1>
    <p class="muted">Sign in to configure the GETMORE student assistant.</p>

    <?php if ($error !== ''): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Username</label>
        <input name="username" required autocomplete="username">

        <label>Password</label>
        <input type="password" name="password" required autocomplete="current-password">

        <button type="submit">Sign In</button>
    </form>
</div>
</body>
</html>

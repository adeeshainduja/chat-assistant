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
        body{font-family:Arial,sans-serif;background:#f3f4f6;margin:0;display:grid;place-items:center;min-height:100vh}
        .card{width:min(420px,calc(100vw - 32px));background:#fff;border-radius:16px;padding:28px;box-shadow:0 20px 55px rgba(0,0,0,.10)}
        h1{margin:0 0 8px;font-size:24px}.muted{color:#707070;margin:0 0 22px}
        label{display:block;font-weight:700;margin:14px 0 7px}input{width:100%;padding:12px;border:1px solid #d7d7d7;border-radius:10px}
        button{margin-top:18px;width:100%;padding:12px;border:0;border-radius:10px;background:#111;color:#fff;font-weight:700;cursor:pointer}
        .error{background:#fff0f0;color:#a52323;padding:10px;border-radius:10px;margin-bottom:12px}
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

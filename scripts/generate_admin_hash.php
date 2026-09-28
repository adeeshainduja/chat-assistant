<?php
declare(strict_types=1);

$password = $argv[1] ?? '';

if ($password === '') {
    fwrite(STDERR, "Usage: php scripts/generate_admin_hash.php \"YourStrongPassword\"\n");
    exit(1);
}

echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;

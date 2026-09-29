<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

try {
    $pdo = Database::connection();
    $sql = file_get_contents(__DIR__ . '/../database/add_multi_institute_support.sql');
    
    // Execute multiple SQL statements
    $pdo->exec($sql);
    echo "Migration completed successfully!\n";

    echo "--- Institutes ---\n";
    print_r($pdo->query("SELECT * FROM institutes")->fetchAll(PDO::FETCH_ASSOC));

    echo "--- Assistants with Institute ---\n";
    print_r($pdo->query("SELECT id, institute_id, name FROM assistants")->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}

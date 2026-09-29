<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

try {
    $aiPdo = Database::connection();
    echo "=== Running AI DB Migration ===\n";
    $sqlAi1 = file_get_contents(__DIR__ . '/../database/add_multi_institute_support.sql');
    $aiPdo->exec($sqlAi1);

    $sqlAi2 = file_get_contents(__DIR__ . '/../database/add_institute_public_details_and_new_courses.sql');
    $aiPdo->exec($sqlAi2);
    echo "AI DB migrations completed successfully.\n";

    echo "--- Institutes Table Columns ---\n";
    print_r($aiPdo->query("DESCRIBE Ai_assistant_institutes")->fetchAll(PDO::FETCH_ASSOC));

    echo "--- Institute 1 Data ---\n";
    print_r($aiPdo->query("SELECT * FROM Ai_assistant_institutes WHERE id = 1")->fetch(PDO::FETCH_ASSOC));

    echo "--- Permissions for Assistant 1 ---\n";
    print_r($aiPdo->query("SELECT * FROM Ai_assistant_permissions WHERE assistant_id = 1")->fetchAll(PDO::FETCH_ASSOC));

    echo "\n=== Running GETMORE DB Classes/Courses Migration ===\n";
    $getmorePdo = GetmoreDatabase::connection();
    $sqlGm = file_get_contents(__DIR__ . '/../database/add_getmore_classes_new_course_fields.sql');
    $getmorePdo->exec($sqlGm);
    echo "GETMORE DB migration completed successfully.\n";

    echo "--- Classes Table Columns (Tail) ---\n";
    print_r($getmorePdo->query("DESCRIBE classes")->fetchAll(PDO::FETCH_ASSOC));

    echo "--- Courses Table Data ---\n";
    print_r($getmorePdo->query("SELECT id, name, start_date, status, is_new, enrollment_open FROM courses")->fetchAll(PDO::FETCH_ASSOC));

} catch (Throwable $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
// CLI seed loader for demo data.
// Usage: php tools/seed.php [--regenerate-demo]
// 
// This script imports centralized SQL files from /database folder:
// 1. schema.sql (tables + reference data)
// 2. demo.sql (demo incidents + user accounts)
//
// Optional: --regenerate-demo to regenerate demo.sql with fresh password hashes
//           (uses environment variables CRIME_SEED_ADMIN_PASSWORD, CRIME_SEED_BARANGAY_PASSWORD)

if (php_sapi_name() !== 'cli') {
    echo "Run this script from the command line only.\n";
    exit(1);
}

require __DIR__ . '/../api/db.php';

$regenerateDemo = in_array('--regenerate-demo', $argv);

if ($regenerateDemo) {
    echo "Regenerating database/demo.sql with fresh password hashes...\n";
    require __DIR__ . '/generate-seed.php';
    exit(0);
}

// Import SQL seed files in order
$seedFiles = [
    dirname(__DIR__, 2) . '/database/schema.sql',
    dirname(__DIR__, 2) . '/database/demo.sql',
];

try {
    $pdo->beginTransaction();

    foreach ($seedFiles as $seedFile) {
        if (!file_exists($seedFile)) {
            throw new Exception("Seed file not found: {$seedFile}");
        }

        $sql = file_get_contents($seedFile);
        if ($sql === false) {
            throw new Exception("Failed to read seed file: {$seedFile}");
        }

        echo "Loading: {$seedFile}\n";
        $pdo->exec($sql);
    }

    $pdo->commit();
    echo "✓ Demo seed data imported successfully.\n";
} catch (Exception $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "✗ Seed failed: " . $exception->getMessage() . "\n";
    exit(1);
}

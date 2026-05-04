<?php
// CLI seed loader for demo data.
// Usage: php tools/seed.php

if (php_sapi_name() !== 'cli') {
    echo "Run this script from the command line only.\n";
    exit(1);
}

require __DIR__ . '/../api/db.php';

function env_seed_value(string $key, string $default): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }

    return ($value === false || $value === '' || $value === null) ? $default : (string) $value;
}

$adminPassword = env_seed_value('CRIME_SEED_ADMIN_PASSWORD', 'ChangeMeAdmin!123');
$barangayPassword = env_seed_value('CRIME_SEED_BARANGAY_PASSWORD', 'ChangeMeBarangay!123');

$adminHash = password_hash($adminPassword, PASSWORD_DEFAULT);
$barangayHash = password_hash($barangayPassword, PASSWORD_DEFAULT);

$demoUsers = [
    ['admin', 'admin@crime.local', null, $adminHash, 'admin', null],
    ['brgy_alapang', 'alapang@crime.local', '+639000000001', $barangayHash, 'barangay', 1],
    ['brgy_alno', 'alno@crime.local', '+639000000002', $barangayHash, 'barangay', 2],
    ['brgy_ambiong', 'ambiong@crime.local', '+639000000003', $barangayHash, 'barangay', 3],
    ['brgy_bahong', 'bahong@crime.local', '+639000000004', $barangayHash, 'barangay', 4],
    ['brgy_balili', 'balili@crime.local', '+639000000005', $barangayHash, 'barangay', 5],
    ['brgy_beckel', 'beckel@crime.local', '+639000000006', $barangayHash, 'barangay', 6],
    ['brgy_betag', 'betag@crime.local', '+639000000007', $barangayHash, 'barangay', 7],
    ['brgy_bineng', 'bineng@crime.local', '+639000000008', $barangayHash, 'barangay', 8],
    ['brgy_cruz', 'cruz@crime.local', '+639000000009', $barangayHash, 'barangay', 9],
    ['brgy_lubas', 'lubas@crime.local', '+639000000010', $barangayHash, 'barangay', 10],
    ['brgy_pico', 'pico@crime.local', '+639000000011', $barangayHash, 'barangay', 11],
    ['brgy_poblacion', 'poblacion@crime.local', '+639000000012', $barangayHash, 'barangay', 12],
    ['brgy_puguis', 'puguis@crime.local', '+639000000013', $barangayHash, 'barangay', 13],
    ['brgy_shilan', 'shilan@crime.local', '+639000000014', $barangayHash, 'barangay', 14],
    ['brgy_tawang', 'tawang@crime.local', '+639000000015', $barangayHash, 'barangay', 15],
    ['brgy_wangal', 'wangal@crime.local', '+639000000016', $barangayHash, 'barangay', 16],
];

$seedFile = __DIR__ . '/../sql/seed-demo.sql';
if (!file_exists($seedFile)) {
    echo "Seed file not found: {$seedFile}\n";
    exit(1);
}

$sql = file_get_contents($seedFile);
if ($sql === false) {
    echo "Failed to read seed file.\n";
    exit(1);
}

try {
    $pdo->beginTransaction();
    $pdo->exec($sql);

    $userStmt = $pdo->prepare('
        INSERT INTO users (username, email, contact, password_hash, role, barangay_id)
        VALUES (:username, :email, :contact, :password_hash, :role, :barangay_id)
        ON DUPLICATE KEY UPDATE
            email = VALUES(email),
            contact = VALUES(contact),
            password_hash = VALUES(password_hash),
            role = VALUES(role),
            barangay_id = VALUES(barangay_id)
    ');

    foreach ($demoUsers as [$username, $email, $contact, $passwordHash, $role, $barangayId]) {
        $userStmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':contact' => $contact,
            ':password_hash' => $passwordHash,
            ':role' => $role,
            ':barangay_id' => $barangayId,
        ]);
    }

    $pdo->commit();
    echo "Demo seed data inserted successfully.\n";
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Seed failed: " . $exception->getMessage() . "\n";
    exit(1);
}

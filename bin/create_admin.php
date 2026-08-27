<?php
declare(strict_types=1);

/**
 * bin/create_admin.php
 *
 * Creates (or updates the password of) an admin user. CLI only -
 * accepts a plaintext password as an argument, so it must never be
 * reachable over HTTP.
 *
 * Usage:
 *   php bin/create_admin.php <username> <password>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../includes/bootstrap.php';

$username = $argv[1] ?? null;
$password = $argv[2] ?? null;

if (!$username || !$password) {
    fwrite(STDERR, "Usage: php bin/create_admin.php <username> <password>\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

try {
    $db = getDbConnection();

    // Upsert: lets you also use this script to reset an existing admin's password.
    $stmt = $db->prepare(
        'INSERT INTO admin_users (username, password_hash) VALUES (:username, :hash)
         ON DUPLICATE KEY UPDATE password_hash = :hash2'
    );
    $stmt->execute([
        ':username' => $username,
        ':hash'     => $passwordHash,
        ':hash2'    => $passwordHash,
    ]);

    echo "Admin user '{$username}' created/updated successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed to create admin user: ' . $e->getMessage() . "\n");
    exit(1);
}

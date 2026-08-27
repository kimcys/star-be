<?php
declare(strict_types=1);

/**
 * Builds an in-memory SQLite database for tests, mirroring the columns
 * ConsentManager/AdminAuth actually query - not the full database/schema.sql
 * (no ENUM/InnoDB/indexes, SQLite doesn't need those). Using SQLite here
 * rather than a real MySQL connection means the test suite runs standalone,
 * with no DB server setup required - a reviewer can just run `vendor/bin/phpunit`.
 */
final class TestDatabase
{
    public static function create(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('
            CREATE TABLE consent_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                guid TEXT NOT NULL,
                consent_status TEXT NOT NULL,
                consent_version INTEGER NOT NULL DEFAULT 1,
                consented_at TEXT NOT NULL,
                ip_address TEXT,
                user_agent TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $pdo->exec('
            CREATE TABLE admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                failed_attempts INTEGER NOT NULL DEFAULT 0,
                locked_until TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        return $pdo;
    }

    public static function seedAdmin(PDO $pdo, string $username, string $password): int
    {
        $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:u, :h)');
        $stmt->execute([
            ':u' => $username,
            ':h' => password_hash($password, PASSWORD_BCRYPT),
        ]);

        return (int) $pdo->lastInsertId();
    }
}

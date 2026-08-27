<?php
declare(strict_types=1);

/**
 * Database connection.
 *
 * Credentials come from environment variables (see .env.example),
 * never hardcoded here. loadEnv() (called from bootstrap.php)
 * populates them from a local .env file for local development; on a
 * real server, real environment variables would already be set and
 * .env simply wouldn't exist there.
 */

require_once __DIR__ . '/env.php'; // safe to include standalone, not just via bootstrap.php

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            env('DB_HOST', 'localhost'),
            env('DB_PORT', '3306'),
            env('DB_NAME', 'star_assessment'),
            env('DB_CHARSET', 'utf8mb4')
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // real server-side prepared statements
        ];

        // Deliberately NOT catching PDOException here - this function is
        // shared by web pages, the JSON endpoint, and CLI scripts, and
        // each wants to present a connection failure differently. That
        // decision belongs to the caller, not to this shared helper.
        $pdo = new PDO(
            $dsn,
            env('DB_USER', 'root'),
            env('DB_PASS', ''),
            $options
        );
    }

    return $pdo;
}

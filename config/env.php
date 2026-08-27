<?php
declare(strict_types=1);

/**
 * Minimal, dependency-free .env loader.
 *
 * A handful of KEY=VALUE lines doesn't justify a Composer dependency.
 * Real environment variables (set by a hosting platform, Docker, etc.)
 * always take priority over .env - .env only exists as a local-dev
 * convenience and should never be committed (see .gitignore).
 */
function loadEnv(string $path): void
{
    static $loaded = false;
    if ($loaded || !is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || substr($line, 0, 1) === '#') {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value, " \t\"'");

        // A real env var set outside this file always wins.
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }

    $loaded = true;
}

/**
 * Reads an environment variable with a default fallback.
 */
function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value !== false ? $value : $default;
}
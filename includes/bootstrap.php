<?php
declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Every entry point requires ONLY this file instead of hand-picking
 * includes itself. Keeps setup in one place so it can't drift between
 * files.
 *
 * Deliberately does NOT start a session here - most of the app is
 * pure cookie-based (no session needed). Only admin pages call
 * bootstrapSession() explicitly, right after requiring this file.
 */

require_once __DIR__ . '/../config/env.php';
loadEnv(__DIR__ . '/../.env');

$appEnv = env('APP_ENV', 'production');

error_reporting(E_ALL);
ini_set('display_errors', $appEnv === 'development' ? '1' : '0');

date_default_timezone_set('Asia/Kuala_Lumpur');

// Autoload our own classes: `ClassName` resolves to includes/ClassName.php
spl_autoload_register(function (string $class): void {
    $path = __DIR__ . '/' . $class . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

// Function libraries aren't classes, so the autoloader above won't catch
// these - require them explicitly.
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/session_bootstrap.php';

require_once __DIR__ . '/../config/db.php';

// Baseline hardening headers on every response.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// --- NEW: CORS ---------------------------------------------------
// Allows the Angular dev server (a different origin - different
// port on localhost still counts) to call this API with cookies
// included. Only echo back the Origin if it's the one we trust -
// reflecting any Origin would defeat the point, since combined with
// credentials that would let any site read authenticated responses.
$allowedOrigin = env('CORS_ALLOWED_ORIGIN', 'http://localhost:4200');
if (($_SERVER['HTTP_ORIGIN'] ?? null) === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-XSRF-TOKEN');
}

// Preflight requests stop here - no need to run the rest of the app.
if (($_SERVER['REQUEST_METHOD'] ?? null) === 'OPTIONS') {
    http_response_code(204);
    exit;
}
// --- END NEW -------------------------------------------------------

/**
 * Last-resort safety net for anything not already caught locally.
 * Fails safe: full details go to the server log, never to the browser
 * unless APP_ENV=development.
 */
set_exception_handler(function (Throwable $e): void {
    error_log(sprintf(
        'Uncaught %s: %s in %s:%d',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (env('APP_ENV', 'production') === 'development') {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES) . '</pre>';
    } else {
        echo 'Something went wrong. Please try again later.';
    }
});

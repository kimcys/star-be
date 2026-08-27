<?php
declare(strict_types=1);

/**
 * api/index.php
 *
 * Root of the JSON API. Not consumed by the frontend - just a
 * human-facing health check / endpoint listing for anyone hitting
 * http://localhost:8000/api/ directly (e.g. from the README).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

echo json_encode([
    'success' => true,
    'name' => 'Star Media Group - Consent API',
    'endpoints' => [
        'GET /api/consent-status.php' => 'Whether the consent banner should be shown',
        'GET /api/csrf-cookie.php' => 'Issue the XSRF-TOKEN cookie',
        'POST /api/admin/login.php' => 'Admin login',
        'POST /api/admin/logout.php' => 'Admin logout',
        'GET /api/admin/me.php' => 'Current admin session',
        'GET /api/admin/consent-logs.php' => 'List submitted consent decisions',
    ],
]);

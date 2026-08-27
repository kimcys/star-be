<?php
declare(strict_types=1);

/**
 * api/admin/logout.php
 *
 * POST - JSON equivalent of admin/logout.php. Also CSRF-protected -
 * every state-changing admin action goes through the same check,
 * for consistency, even though a forced logout is low-severity.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
bootstrapSession();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!csrfVerifyHeader()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token.']);
    exit;
}

AdminAuth::logout();

echo json_encode(['success' => true]);
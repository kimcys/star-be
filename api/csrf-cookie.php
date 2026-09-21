<?php
declare(strict_types=1);

/**
 * api/csrf-cookie.php
 *
 * GET endpoint the Angular app calls once on startup (before any
 * login attempt) purely to receive the XSRF-TOKEN cookie. Angular
 * never sets this cookie itself - it only reads what we give it here.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    bootstrapSession();
    issueXsrfCookie();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    Logger::error('CSRF cookie error', ['exception' => get_class($e), 'message' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to issue CSRF token right now.']);
}

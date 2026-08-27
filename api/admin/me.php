<?php
declare(strict_types=1);

/**
 * api/admin/me.php
 *
 * GET - lets Angular check "am I currently logged in?" on app
 * boot/page refresh - there's no server-rendered page to hook this
 * into anymore. Read-only, so no CSRF needed.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
bootstrapSession();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (AdminAuth::isLoggedIn()) {
    echo json_encode(['loggedIn' => true, 'username' => $_SESSION['admin_username']]);
} else {
    echo json_encode(['loggedIn' => false]);
}
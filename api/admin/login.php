<?php
declare(strict_types=1);

/**
 * api/admin/login.php
 *
 * POST { "username": "...", "password": "..." } - JSON equivalent of
 * the old admin/login.php form. Unlike the public consent endpoints,
 * this creates privileged session state, so it requires a valid
 * X-XSRF-TOKEN header (see api/csrf-cookie.php).
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

if (AdminAuth::isLoggedIn()) {
    echo json_encode(['success' => true, 'username' => $_SESSION['admin_username']]);
    exit;
}

$jsonBody = json_decode((string) file_get_contents('php://input'), true);
$username = trim((string) ($jsonBody['username'] ?? $_POST['username'] ?? ''));
$password = (string) ($jsonBody['password'] ?? $_POST['password'] ?? '');

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Username and password are required.']);
    exit;
}

try {
    if (AdminAuth::attempt(getDbConnection(), $username, $password)) {
        echo json_encode(['success' => true, 'username' => $_SESSION['admin_username']]);
    } else {
        // Intentionally generic - never say "wrong password" vs "unknown user".
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid username or password.']);
    }
} catch (Throwable $e) {
    error_log('Admin login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Something went wrong. Please try again later.']);
}
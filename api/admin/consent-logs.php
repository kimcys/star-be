<?php
declare(strict_types=1);

/**
 * api/admin/consent-logs.php
 *
 * GET - JSON equivalent of admin/dashboard.php's table. Note this
 * does NOT use AdminAuth::requireLogin() - that function redirects,
 * which makes sense for browser navigation but not for a fetch()
 * call. We return 401 JSON instead and let Angular's route
 * guard/interceptor react to that.
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

if (!AdminAuth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

try {
    $db = getDbConnection();
    $stmt = $db->query(
        'SELECT guid, consent_status, consent_version, consented_at, ip_address, created_at
         FROM consent_logs
         ORDER BY created_at DESC
         LIMIT 200'
    );
    echo json_encode(['success' => true, 'logs' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('Admin consent-logs query error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load consent logs right now.']);
}
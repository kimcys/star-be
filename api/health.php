<?php
declare(strict_types=1);

/**
 * api/health.php
 *
 * GET - liveness/readiness probe for Docker's HEALTHCHECK, a load
 * balancer, or an uptime monitor. Deliberately unauthenticated (the
 * response carries no secrets) and cheap - one DB round trip, nothing
 * else.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

try {
    getDbConnection()->query('SELECT 1');
    echo json_encode(['success' => true, 'status' => 'ok']);
} catch (Throwable $e) {
    Logger::error('Health check error', ['exception' => get_class($e), 'message' => $e->getMessage()]);
    http_response_code(503);
    echo json_encode(['success' => false, 'status' => 'unavailable']);
}

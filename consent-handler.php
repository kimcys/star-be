<?php
declare(strict_types=1);

/**
 * consent-handler.php
 *
 * JSON endpoint. POST { "action": "accept" | "decline" }
 */

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Accept either a JSON body or a classic form POST.
$jsonBody = json_decode((string) file_get_contents('php://input'), true);
$action = $jsonBody['action'] ?? $_POST['action'] ?? null;

$allowedActions = ['accept', 'decline'];
if (!is_string($action) || !in_array($action, $allowedActions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing "action". Expected "accept" or "decline".']);
    exit;
}

try {
    $manager = new ConsentManager(getDbConnection());

    if ($action === 'accept') {
        $result = $manager->recordAccept();
        echo json_encode(['success' => true, 'action' => 'accept', 'data' => $result]);
    } else {
        $manager->recordDecline();
        echo json_encode(['success' => true, 'action' => 'decline']);
    }
} catch (Throwable $e) {
    // Cookie may already be queued for sending even if the DB write
    // fails - graceful degradation for the user, while we still
    // surface a 500 so logging/ops know something broke.
    error_log('Consent handler error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to process consent right now.']);
}

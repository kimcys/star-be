<?php
declare(strict_types=1);

/**
 * api/consent-status.php
 *
 * GET endpoint - Angular calls this on every page load to decide
 * whether to show the consent banner. Necessary because the real
 * consent cookies are httponly (deliberately, for XSS protection) -
 * JS can never read them directly, so the frontend has to ask the
 * backend instead.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$manager = new ConsentManager(); // no DB needed - pure cookie inspection

echo json_encode([
    'success' => true,
    'shouldShowBanner' => $manager->shouldShowBanner(),
]);

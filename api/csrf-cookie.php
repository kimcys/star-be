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
bootstrapSession();

issueXsrfCookie();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true]);

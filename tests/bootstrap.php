<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap. Deliberately does NOT require includes/bootstrap.php -
 * that file loads .env, opens a real DB connection, and sets CORS/security
 * headers, which would make these unit tests slow, order-dependent, and
 * unable to run without a live MySQL server. Instead we require just the
 * two class files under test directly, keeping tests fast and self-contained.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/ConsentManager.php';
require_once __DIR__ . '/../includes/AdminAuth.php';
require_once __DIR__ . '/Support/TestDatabase.php';

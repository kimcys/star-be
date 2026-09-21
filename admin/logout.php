<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

try {
    bootstrapSession();
    AdminAuth::logout();
} catch (Throwable $e) {
    // Best-effort: whatever went wrong, the safest outcome for the user
    // is still to land back on the login page rather than see a raw error.
    error_log('Admin logout (page) error: ' . $e->getMessage());
}

header('Location: /admin/login.php');
exit;

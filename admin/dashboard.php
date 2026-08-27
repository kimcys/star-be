<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
bootstrapSession();

AdminAuth::requireLogin(); // redirects to login.php and exits if not authenticated

try {
    $db = getDbConnection();
    $stmt = $db->query(
        'SELECT guid, consent_status, consent_version, consented_at, ip_address, created_at
         FROM consent_logs
         ORDER BY created_at DESC
         LIMIT 200'
    );
    $logs = $stmt->fetchAll();
    $loadError = null;
} catch (Throwable $e) {
    error_log('Admin dashboard query error: ' . $e->getMessage());
    $logs = [];
    $loadError = 'Unable to load consent logs right now.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Consent Log Dashboard</title>
</head>
<body>
    <h1>Consent Log Dashboard</h1>
    <p>
        Logged in as <?= htmlspecialchars($_SESSION['admin_username'], ENT_QUOTES) ?>
        &middot; <a href="/admin/logout.php">Log out</a>
    </p>

    <?php if ($loadError !== null): ?>
        <p style="color:red;"><?= htmlspecialchars($loadError, ENT_QUOTES) ?></p>
    <?php elseif (empty($logs)): ?>
        <p>No consent decisions recorded yet.</p>
    <?php else: ?>
        <table border="1" cellpadding="6">
            <thead>
                <tr><th>GUID</th><th>Status</th><th>Version</th><th>Consented At</th><th>IP Address</th><th>Logged At</th></tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['guid'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($log['consent_status'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars((string) $log['consent_version'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($log['consented_at'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($log['ip_address'] ?? '-', ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($log['created_at'], ENT_QUOTES) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
bootstrapSession();

if (AdminAuth::isLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        try {
            if ($username !== '' && $password !== '' && AdminAuth::attempt(getDbConnection(), $username, $password)) {
                header('Location: /admin/dashboard.php');
                exit;
            }
            // Intentionally generic - never say "wrong password" vs "unknown user".
            $error = 'Invalid username or password.';
        } catch (Throwable $e) {
            error_log('Admin login error: ' . $e->getMessage());
            $error = 'Something went wrong. Please try again later.';
        }
    }
}

$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
</head>
<body>
    <h1>Admin Login</h1>

    <?php if ($error !== null): ?>
        <p style="color:red;"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endif; ?>

    <form method="post" action="/admin/login.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
        <label>Username <input type="text" name="username" required autofocus></label><br>
        <label>Password <input type="password" name="password" required></label><br>
        <button type="submit">Log in</button>
    </form>
</body>
</html>
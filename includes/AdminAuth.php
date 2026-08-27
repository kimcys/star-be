<?php
declare(strict_types=1);

/**
 * AdminAuth
 *
 * Handles admin login/lockout/logout for the bonus backend portal.
 * Deliberately does NOT call session_start() itself - assumes
 * bootstrapSession() has already run. Keeps this class testable
 * without needing a real HTTP session.
 *
 * Lockout state (failed_attempts/locked_until) lives on the
 * admin_users ROW, not in $_SESSION. A session-based lockout can be
 * trivially reset by an attacker simply not sending a session cookie
 * (or clearing it) between attempts - tracking it per-account in the
 * database instead means the lockout actually survives that.
 */
final class AdminAuth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 60;

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['admin_id']);
    }

    /**
     * Redirects to the login page and stops execution if not logged in.
     * Call this at the very top of any protected admin page.
     */
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /admin/login.php');
            exit;
        }
    }

    /**
     * Attempts to log in. Returns true/false rather than throwing,
     * since "wrong password" is an expected outcome, not an error.
     */
    public static function attempt(PDO $db, string $username, string $password): bool
    {
        $stmt = $db->prepare('SELECT id, username, password_hash, failed_attempts, locked_until FROM admin_users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        if ($admin !== false && self::isLockedOut($admin['locked_until'])) {
            return false;
        }

        // Deliberately run password_verify() even when no user was found
        // (against a dummy hash) so a nonexistent username and a wrong
        // password take the same amount of time to reject - avoids
        // leaking "that username doesn't exist" via a timing gap.
        $hashToCheck = $admin['password_hash'] ?? '$2y$10$usesomesillystringfortimingattacksaaaaaaaaaaaaaaaaaaa';
        $isValid = password_verify($password, $hashToCheck) && $admin !== false;

        if ($isValid) {
            self::clearFailedAttempts($db, (int) $admin['id']);
            session_regenerate_id(true); // new session id on privilege change - prevents session fixation
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            return true;
        }

        if ($admin !== false) {
            self::recordFailedAttempt($db, (int) $admin['id'], (int) $admin['failed_attempts']);
        }

        return false;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    private static function isLockedOut(?string $lockedUntil): bool
    {
        if ($lockedUntil === null) {
            return false;
        }

        return strtotime($lockedUntil) > time();
    }

    private static function recordFailedAttempt(PDO $db, int $adminId, int $currentAttempts): void
    {
        $attempts = $currentAttempts + 1;
        $lockedUntil = null;

        if ($attempts >= self::MAX_ATTEMPTS) {
            $lockedUntil = (new DateTimeImmutable('now'))
                ->modify('+' . self::LOCKOUT_SECONDS . ' seconds')
                ->format('Y-m-d H:i:s');
        }

        $stmt = $db->prepare('UPDATE admin_users SET failed_attempts = :attempts, locked_until = :locked WHERE id = :id');
        $stmt->execute([
            ':attempts' => $attempts,
            ':locked'   => $lockedUntil,
            ':id'       => $adminId,
        ]);
    }

    private static function clearFailedAttempts(PDO $db, int $adminId): void
    {
        $stmt = $db->prepare('UPDATE admin_users SET failed_attempts = 0, locked_until = NULL WHERE id = :id');
        $stmt->execute([':id' => $adminId]);
    }
}

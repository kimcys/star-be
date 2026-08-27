<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminAuthTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /**
     * AdminAuth::attempt() calls session_regenerate_id() on a successful
     * login, and logout() calls session_destroy() - both require a
     * genuinely active session, not just a $_SESSION array. Only the
     * handful of tests that actually reach those code paths need this.
     */
    private function startRealSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
    }

    public function testIsLoggedInFalseWhenSessionEmpty(): void
    {
        $this->assertFalse(AdminAuth::isLoggedIn());
    }

    public function testIsLoggedInTrueWhenAdminIdSet(): void
    {
        $_SESSION['admin_id'] = 1;

        $this->assertTrue(AdminAuth::isLoggedIn());
    }

    public function testAttemptFailsForUnknownUsername(): void
    {
        $pdo = TestDatabase::create();

        $this->assertFalse(AdminAuth::attempt($pdo, 'nobody', 'whatever'));
        $this->assertFalse(AdminAuth::isLoggedIn());
    }

    public function testAttemptFailsForWrongPassword(): void
    {
        $pdo = TestDatabase::create();
        TestDatabase::seedAdmin($pdo, 'admin', 'correct-horse-battery');

        $this->assertFalse(AdminAuth::attempt($pdo, 'admin', 'wrong-password'));
        $this->assertFalse(AdminAuth::isLoggedIn());
    }

    /**
     * @runInSeparateProcess
     * Reaches the success branch, which calls session_regenerate_id() -
     * needs a real active session and its own process (see startRealSession()).
     */
    public function testAttemptSucceedsForCorrectPasswordAndSetsSession(): void
    {
        $this->startRealSession();
        $pdo = TestDatabase::create();
        TestDatabase::seedAdmin($pdo, 'admin', 'correct-horse-battery');

        $result = AdminAuth::attempt($pdo, 'admin', 'correct-horse-battery');

        $this->assertTrue($result);
        $this->assertTrue(AdminAuth::isLoggedIn());
        $this->assertSame('admin', $_SESSION['admin_username']);
    }

    /**
     * The important regression test: lockout must be tracked per-account
     * in the database, not in $_SESSION - a session-based lockout can be
     * reset by an attacker simply dropping their session cookie between
     * attempts. This test clears $_SESSION entirely before the final
     * attempt to prove the lockout survives that. No real session needed
     * here since every attempt in this test fails, so
     * session_regenerate_id() is never reached.
     */
    public function testAttemptLocksOutAfterMaxFailedAttemptsEvenAcrossSessions(): void
    {
        $pdo = TestDatabase::create();
        TestDatabase::seedAdmin($pdo, 'admin', 'correct-horse-battery');

        $maxAttempts = (new ReflectionClass(AdminAuth::class))
            ->getConstant('MAX_ATTEMPTS');

        for ($i = 0; $i < $maxAttempts; $i++) {
            AdminAuth::attempt($pdo, 'admin', 'wrong-password');
        }

        // Simulate a brand new browser session - no state carried over.
        $_SESSION = [];

        $result = AdminAuth::attempt($pdo, 'admin', 'correct-horse-battery');

        $this->assertFalse($result, 'Correct password should still be rejected while locked out');
        $this->assertFalse(AdminAuth::isLoggedIn());
    }

    /**
     * @runInSeparateProcess
     */
    public function testAttemptSucceedsAfterLockoutWindowExpires(): void
    {
        $this->startRealSession();
        $pdo = TestDatabase::create();
        $adminId = TestDatabase::seedAdmin($pdo, 'admin', 'correct-horse-battery');

        // Simulate a lockout that already expired a minute ago, rather
        // than sleeping in the test - directly manipulating locked_until
        // is legitimate here since it's just a stored timestamp value,
        // no different from time actually having passed.
        $pastLockout = (new DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare('UPDATE admin_users SET failed_attempts = 5, locked_until = :locked WHERE id = :id');
        $stmt->execute([':locked' => $pastLockout, ':id' => $adminId]);

        $result = AdminAuth::attempt($pdo, 'admin', 'correct-horse-battery');

        $this->assertTrue($result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSuccessfulLoginClearsFailedAttempts(): void
    {
        $this->startRealSession();
        $pdo = TestDatabase::create();
        TestDatabase::seedAdmin($pdo, 'admin', 'correct-horse-battery');

        AdminAuth::attempt($pdo, 'admin', 'wrong-password');
        AdminAuth::attempt($pdo, 'admin', 'correct-horse-battery');

        $row = $pdo->query('SELECT failed_attempts, locked_until FROM admin_users')->fetch();
        $this->assertSame(0, (int) $row['failed_attempts']);
        $this->assertNull($row['locked_until']);
    }

    /**
     * @runInSeparateProcess
     */
    public function testLogoutClearsSessionAndIsLoggedInFalseAfter(): void
    {
        $this->startRealSession();
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_username'] = 'admin';

        AdminAuth::logout();

        $this->assertFalse(AdminAuth::isLoggedIn());
        $this->assertSame([], $_SESSION);
    }
}

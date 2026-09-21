<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ConsentManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
    }

    public function testShouldShowBannerTrueWhenNoCookiesPresent(): void
    {
        $manager = new ConsentManager();

        $this->assertTrue($manager->shouldShowBanner());
    }

    public function testShouldShowBannerFalseWhenValidAcceptCookiePresent(): void
    {
        $_COOKIE['consent_accept'] = json_encode(['guid' => 'x', 'version' => 1, 'consented_at' => 'now']);
        $manager = new ConsentManager();

        $this->assertFalse($manager->shouldShowBanner());
    }

    public function testShouldShowBannerFalseWhenValidDeclineCookiePresent(): void
    {
        $_COOKIE['consent_decline'] = json_encode(['declined_at' => 'now']);
        $manager = new ConsentManager();

        $this->assertFalse($manager->shouldShowBanner());
    }

    /**
     * A cookie that's present but not valid JSON should be treated the
     * same as no cookie at all - shouldShowBanner() should never trust
     * malformed client-controlled data.
     */
    public function testShouldShowBannerTrueWhenCookieIsMalformedJson(): void
    {
        $_COOKIE['consent_accept'] = 'not-json{{{';
        $manager = new ConsentManager();

        $this->assertTrue($manager->shouldShowBanner());
    }

    /**
     * @runInSeparateProcess
     * recordAccept()/recordDecline() call setcookie(), which requires
     * headers not already sent - PHPUnit's own output (even just its
     * startup banner) counts as "sent" in the shared test process, so
     * any test touching these methods needs its own isolated process.
     */
    public function testRecordAcceptWithoutDbThrows(): void
    {
        $manager = new ConsentManager(); // no PDO passed

        $this->expectException(RuntimeException::class);

        $manager->recordAccept();
    }

    /**
     * @runInSeparateProcess
     */
    public function testRecordAcceptReturnsCorrectShapeWithValidGuid(): void
    {
        $pdo = TestDatabase::create();
        $manager = new ConsentManager($pdo);

        $result = $manager->recordAccept();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $result['guid']
        );
        $this->assertSame(1, $result['version']);
        $this->assertArrayHasKey('consented_at', $result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testRecordAcceptPersistsRowToDatabase(): void
    {
        $pdo = TestDatabase::create();
        $manager = new ConsentManager($pdo);

        $result = $manager->recordAccept();

        $row = $pdo->query('SELECT * FROM consent_logs')->fetch();
        $this->assertSame($result['guid'], $row['guid']);
        $this->assertSame('accepted', $row['consent_status']);
        $this->assertSame(1, (int) $row['consent_version']);
    }

    /**
     * @runInSeparateProcess
     */
    public function testRecordAcceptLogsRemoteAddrWhenNoProxyHeaderPresent(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $pdo = TestDatabase::create();
        $manager = new ConsentManager($pdo);

        $manager->recordAccept();

        $row = $pdo->query('SELECT * FROM consent_logs')->fetch();
        $this->assertSame('203.0.113.9', $row['ip_address']);
    }

    /**
     * @runInSeparateProcess
     */
    public function testRecordAcceptPrefersTheLastForwardedForHopOverRemoteAddr(): void
    {
        // REMOTE_ADDR here is Caddy's own container IP (the direct TCP
        // peer); the real visitor IP is the last hop Caddy itself appends
        // to X-Forwarded-For - that's the one that should get logged.
        $_SERVER['REMOTE_ADDR'] = '172.18.0.5';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';
        $pdo = TestDatabase::create();
        $manager = new ConsentManager($pdo);

        $manager->recordAccept();

        $row = $pdo->query('SELECT * FROM consent_logs')->fetch();
        $this->assertSame('203.0.113.9', $row['ip_address']);
    }

    /**
     * @runInSeparateProcess
     */
    public function testRecordAcceptUsesTheLastHopWhenForwardedForHasMultipleProxies(): void
    {
        $_SERVER['REMOTE_ADDR'] = '172.18.0.5';
        // A client could inject a fake first entry themselves - only the
        // hop our own trusted proxy appended (the last one) is safe to trust.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'evil-spoofed-value, 203.0.113.9';
        $pdo = TestDatabase::create();
        $manager = new ConsentManager($pdo);

        $manager->recordAccept();

        $row = $pdo->query('SELECT * FROM consent_logs')->fetch();
        $this->assertSame('203.0.113.9', $row['ip_address']);
    }

    /**
     * @runInSeparateProcess
     */
    public function testRecordDeclineWithoutDbThrows(): void
    {
        $manager = new ConsentManager();

        $this->expectException(RuntimeException::class);

        $manager->recordDecline();
    }

    /**
     * @runInSeparateProcess
     */
    public function testRecordDeclinePersistsRowToDatabase(): void
    {
        $pdo = TestDatabase::create();
        $manager = new ConsentManager($pdo);

        $manager->recordDecline();

        $row = $pdo->query('SELECT * FROM consent_logs')->fetch();
        $this->assertSame('declined', $row['consent_status']);
    }

    /**
     * @runInSeparateProcess
     * Two calls to recordAccept() must produce two different GUIDs -
     * generateGuid() is server-side random, not derived from anything
     * request-specific, so nothing should make them collide.
     */
    public function testConsecutiveAcceptsProduceDifferentGuids(): void
    {
        $pdo = TestDatabase::create();
        $manager = new ConsentManager($pdo);

        $first = $manager->recordAccept();
        $second = $manager->recordAccept();

        $this->assertNotSame($first['guid'], $second['guid']);
    }
}

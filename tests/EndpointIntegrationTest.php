<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration tests against the 5 endpoints that previously had no
 * local try/catch (api/consent-status.php, api/csrf-cookie.php,
 * api/admin/me.php, api/admin/logout.php, admin/logout.php). Unlike
 * ConsentManagerTest/AdminAuthTest, these files are plain entry-point
 * scripts with no class to instantiate - so proving they still behave
 * correctly means actually booting PHP's built-in server and hitting
 * them over real HTTP.
 *
 * These cover the happy path and the JSON/redirect contract each
 * endpoint promises. They deliberately do NOT force the new catch
 * blocks to execute: none of these 5 endpoints touch the database or
 * any other realistically-failable dependency (that's part of why
 * they had no try/catch in the first place - the fix is a defensive
 * consistency improvement, not a fix for an observed crash), so
 * there's no fault to inject here without adding a test-only seam
 * that nothing else in this codebase has. What actually regresses if
 * the fix is done wrong is normal behavior changing - which is what
 * these assert against.
 */
final class EndpointIntegrationTest extends TestCase
{
    private static int $port = 8091;

    /** @var resource */
    private static $serverProcess;

    private static string $baseUrl;

    private string $cookieJar;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = 'http://127.0.0.1:' . self::$port;
        $docroot = dirname(__DIR__);
        $devnull = ['file', '/dev/null', 'w'];

        $process = proc_open(
            ['php', '-S', '127.0.0.1:' . self::$port, '-t', $docroot],
            [1 => $devnull, 2 => $devnull],
            $pipes,
        );

        if ($process === false) {
            self::fail('Could not start the PHP built-in server for integration tests.');
        }

        self::$serverProcess = $process;

        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            if (@file_get_contents(self::$baseUrl . '/api/consent-status.php') !== false) {
                return;
            }
            usleep(100_000);
        }

        self::fail('PHP built-in server did not become reachable in time.');
    }

    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$serverProcess);
        proc_close(self::$serverProcess);
    }

    protected function setUp(): void
    {
        $this->cookieJar = (string) tempnam(sys_get_temp_dir(), 'star-be-test-cookies-');
    }

    protected function tearDown(): void
    {
        @unlink($this->cookieJar);
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function request(string $method, string $path, array $headers = []): array
    {
        $ch = curl_init(self::$baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_HTTPHEADER => array_map(
                static fn (string $k, string $v): string => "$k: $v",
                array_keys($headers),
                array_values($headers),
            ),
        ]);

        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $rawHeaders = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        $parsedHeaders = [];
        foreach (explode("\r\n", trim($rawHeaders)) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $parsedHeaders[strtolower(trim($name))] = trim($value);
            }
        }

        return ['status' => $status, 'headers' => $parsedHeaders, 'body' => $body];
    }

    private function fetchXsrfToken(): string
    {
        $this->request('GET', '/api/csrf-cookie.php');

        foreach (file($this->cookieJar) ?: [] as $line) {
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $fields = preg_split('/\t+/', trim($line));
            if (($fields[5] ?? null) === 'XSRF-TOKEN') {
                return $fields[6];
            }
        }

        self::fail('XSRF-TOKEN cookie was not set by csrf-cookie.php.');
    }

    public function testConsentStatusReturnsWellFormedJson(): void
    {
        $res = $this->request('GET', '/api/consent-status.php');

        self::assertSame(200, $res['status']);
        self::assertStringContainsString('application/json', $res['headers']['content-type'] ?? '');
        $data = json_decode($res['body'], true);
        self::assertTrue($data['success']);
        self::assertIsBool($data['shouldShowBanner']);
    }

    public function testConsentStatusRejectsNonGet(): void
    {
        $res = $this->request('POST', '/api/consent-status.php');

        self::assertSame(405, $res['status']);
        self::assertFalse(json_decode($res['body'], true)['success']);
    }

    public function testCsrfCookieIssuesAReadableXsrfCookie(): void
    {
        $res = $this->request('GET', '/api/csrf-cookie.php');

        self::assertSame(200, $res['status']);
        self::assertTrue(json_decode($res['body'], true)['success']);
        self::assertStringContainsString('XSRF-TOKEN', $res['headers']['set-cookie'] ?? '');
    }

    public function testAdminMeReportsLoggedOutWithoutASession(): void
    {
        $res = $this->request('GET', '/api/admin/me.php');

        self::assertSame(200, $res['status']);
        self::assertFalse(json_decode($res['body'], true)['loggedIn']);
    }

    public function testAdminLogoutJsonRejectsMissingCsrfToken(): void
    {
        $res = $this->request('POST', '/api/admin/logout.php');

        self::assertSame(403, $res['status']);
        self::assertFalse(json_decode($res['body'], true)['success']);
    }

    public function testAdminLogoutJsonSucceedsWithAValidCsrfToken(): void
    {
        $token = $this->fetchXsrfToken();
        $res = $this->request('POST', '/api/admin/logout.php', ['X-XSRF-TOKEN' => $token]);

        self::assertSame(200, $res['status']);
        self::assertTrue(json_decode($res['body'], true)['success']);
    }

    public function testAdminLogoutPageRedirectsToLogin(): void
    {
        $res = $this->request('GET', '/admin/logout.php');

        self::assertSame(302, $res['status']);
        self::assertSame('/admin/login.php', $res['headers']['location'] ?? null);
    }
}

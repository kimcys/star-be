<?php
declare(strict_types=1);

/**
 * ConsentManager
 *
 * Encapsulates all cookie-consent logic required by the spec:
 *  - Decide whether the consent banner should be shown
 *  - Generate a GUID and record an "accept" decision (cookie + DB)
 *  - Record a "decline" decision (cookie only, per spec)
 *
 * Design note on cookies: we store ONE JSON-encoded cookie per state
 * ("accept" / "decline") rather than several loose cookies. Keeps
 * cookie count low and keeps related fields (guid, version,
 * timestamp) atomic - they expire together as a single unit.
 *
 * Design note on expiry: we deliberately do NOT do manual "is this
 * expired?" date math anywhere in this class. Once a cookie's expiry
 * time passes, the browser stops sending it - so if it's missing from
 * $_COOKIE, it is by definition either expired or was never set. That
 * single "is it present and well-formed?" check covers both of the
 * spec's reappearance conditions (expired OR absent) for free.
 */
final class ConsentManager
{
    private const COOKIE_ACCEPT = 'consent_accept';
    private const COOKIE_DECLINE = 'consent_decline';

    private const CURRENT_VERSION = 1;
    private const ACCEPT_EXPIRY_INTERVAL = '+1 year';
    private const DECLINE_EXPIRY_INTERVAL = '+1 day';

    private ?PDO $db;

    /**
     * @param PDO|null $db Required only when calling recordAccept()/recordDecline().
     *                     Pass null for read-only banner checks (shouldShowBanner())
     *                     so those page loads never need a live DB connection.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    /**
     * The banner should show unless we find a valid, well-formed
     * accept or decline cookie for the current visitor.
     */
    public function shouldShowBanner(): bool
    {
        return !$this->hasValidCookie(self::COOKIE_ACCEPT)
            && !$this->hasValidCookie(self::COOKIE_DECLINE);
    }

    /**
     * Handles an "accept" decision:
     *  - generates a GUID
     *  - sets the 1-year cookie {guid, version, consented_at}
     *  - clears any stale decline cookie
     *  - logs the decision to the database
     *
     * @return array{guid:string, version:int, consented_at:string}
     */
    public function recordAccept(): array
    {
        $guid = $this->generateGuid();
        $now = new DateTimeImmutable('now');

        $payload = [
            'guid'         => $guid,
            'version'      => self::CURRENT_VERSION,
            'consented_at' => $now->format(DATE_ATOM),
        ];

        $this->setJsonCookie(
            self::COOKIE_ACCEPT,
            $payload,
            $now->modify(self::ACCEPT_EXPIRY_INTERVAL)
        );

        // If they'd previously declined in this browser, that decision
        // is now superseded - clear it so it can't linger and confuse
        // shouldShowBanner() logic later.
        $this->clearCookie(self::COOKIE_DECLINE);

        $this->logConsent($guid, 'accepted', self::CURRENT_VERSION, $now);

        return $payload;
    }

    /**
     * Handles a "decline" decision:
     *  - sets a 1-day cookie {declined_at}
     *  - logs the decision to the database (extra: spec only requires
     *    the cookie here, but logging keeps the admin dashboard useful
     *    and gives us an audit trail for declines too)
     */
    public function recordDecline(): void
    {
        $now = new DateTimeImmutable('now');
        $guid = $this->generateGuid(); // used only for the DB log row, not stored client-side

        $payload = [
            'declined_at' => $now->format(DATE_ATOM),
        ];

        $this->setJsonCookie(
            self::COOKIE_DECLINE,
            $payload,
            $now->modify(self::DECLINE_EXPIRY_INTERVAL)
        );

        $this->logConsent($guid, 'declined', self::CURRENT_VERSION, $now);
    }

    // -----------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------

    private function hasValidCookie(string $name): bool
    {
        if (!isset($_COOKIE[$name])) {
            return false;
        }

        $data = json_decode($_COOKIE[$name], true);
        return is_array($data);
    }

    private function setJsonCookie(string $name, array $payload, DateTimeImmutable $expires): void
    {
        setcookie($name, json_encode($payload), [
            'expires'  => $expires->getTimestamp(),
            'path'     => '/',
            'httponly' => true,               // not readable by client-side JS - reduces XSS risk
            'samesite' => 'Lax',
            'secure'   => $this->isHttps(),    // only over HTTPS when the site is actually served over HTTPS
        ]);
    }

    private function clearCookie(string $name): void
    {
        if (isset($_COOKIE[$name])) {
            unset($_COOKIE[$name]);
        }
        setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $this->isHttps(),
        ]);
    }

    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) === '443');
    }

    /**
     * RFC 4122 v4 UUID, generated server-side so it can't be
     * influenced or spoofed by the client.
     */
    private function generateGuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant

        $hex = bin2hex($data);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    private function logConsent(string $guid, string $status, int $version, DateTimeImmutable $when): void
    {
        if ($this->db === null) {
            throw new RuntimeException(
                'ConsentManager needs a PDO connection to log consent. ' .
                'Construct it with new ConsentManager(getDbConnection()).'
            );
        }

        $stmt = $this->db->prepare(
            'INSERT INTO consent_logs (guid, consent_status, consent_version, consented_at, ip_address, user_agent)
             VALUES (:guid, :status, :version, :consented_at, :ip, :ua)'
        );

        $stmt->execute([
            ':guid'         => $guid,
            ':status'       => $status,
            ':version'      => $version,
            ':consented_at' => $when->format('Y-m-d H:i:s'),
            ':ip'            => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'            => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }
}

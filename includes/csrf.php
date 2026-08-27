<?php
declare(strict_types=1);

/**
 * Returns the current CSRF token, generating one if none exists yet.
 * Requires an active session (call bootstrapSession() first).
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * hash_equals() instead of === to avoid timing attacks that could let
 * an attacker guess the token byte-by-byte.
 */
function csrfVerify(?string $submittedToken): bool
{
    return is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

/**
 * Sets the CSRF token as a JS-readable cookie, named to match
 * Angular's HttpClientXsrfModule default so it "just works" on the
 * frontend with zero config. NOT httponly - Angular's interceptor
 * needs to read this via document.cookie.
 */
function issueXsrfCookie(): void
{
    $token = csrfToken(); // creates one in $_SESSION if none exists yet

    setcookie('XSRF-TOKEN', $token, [
        'expires'  => 0,       // session cookie - dies when the browser closes
        'path'     => '/',
        'httponly' => false,   // must be JS-readable, unlike our other cookies
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
}

/**
 * Verifies the X-XSRF-TOKEN request header (sent automatically by
 * Angular's HttpClient) against the session-stored token. Use this
 * instead of csrfVerify($_POST[...]) for any JSON API endpoint.
 */
function csrfVerifyHeader(): bool
{
    return csrfVerify($_SERVER['HTTP_X_XSRF_TOKEN'] ?? null);
}


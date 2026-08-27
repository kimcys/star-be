<?php
declare(strict_types=1);

/**
 * Call this at the top of any page needing $_SESSION, before any
 * output. Centralizing this means every session cookie gets the same
 * security flags - nobody can forget them on one page.
 */
function bootstrapSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0, // expires when browser closes - admin sessions shouldn't outlive that
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);

    session_start();
}
<?php
declare(strict_types=1);

/**
 * Docker HEALTHCHECK probe. A plain PHP script (rather than curl/wget)
 * so the runtime image doesn't need to install anything extra just to
 * check itself.
 */

$body = @file_get_contents(
    'http://127.0.0.1:8080/api/health.php',
    false,
    stream_context_create(['http' => ['timeout' => 3]]),
);

exit($body !== false && str_contains($body, '"success":true') ? 0 : 1);

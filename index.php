<?php
declare(strict_types=1);

/**
 * index.php (DocumentRoot)
 *
 * This subdomain is API-only - there's nothing to serve at the bare
 * domain root itself, and Apache has no index file here otherwise
 * (returning a 403, since directory listing is disabled). Redirect
 * anyone who lands on the bare root to the Swagger UI docs page.
 */

http_response_code(302);
header('Location: /docs/api-docs.html');
exit;

# Star Media Group — Practical Test

PHP 7/8 + MySQL backend implementing cookie consent tracking and a bonus
secured admin portal to view submitted consent decisions. Built as an
API-first backend (JSON over HTTP, CORS + CSRF configured for a
cross-origin SPA) so it can be consumed by a separate Angular frontend.

## Status

- ✅ Consent API — accept/decline, cookie handling, DB logging
- ✅ JSON API for an SPA frontend — CORS, CSRF (double-submit cookie),
  consent-status check, admin login/logout/session-check/dashboard data
- ✅ Legacy server-rendered admin portal (`admin/*.php`) — still functional
  as a no-JS fallback, but superseded by the JSON API above
- ✅ Dockerized (`docker compose up`) — PHP + MySQL, schema auto-imported,
  least-privilege DB grants preserved
- ✅ OpenAPI/Swagger docs (`docs/openapi.yaml`, browsable at `/docs/api-docs.html`)
- ✅ Automated test suite (PHPUnit) — `ConsentManager` and `AdminAuth`,
  runs against an in-memory SQLite DB, no live MySQL needed
- ✅ Admin lockout tracked per-account in the database, not per-session —
  survives an attacker dropping/rotating their session cookie
- ⬜ Angular frontend — separate project, not part of this repo
- ⬜ Public frontend pages (Home, About/Contact, Privacy Policy, Terms &
  Conditions) and the consent banner UI — out of scope for this backend repo

## Requirements

- PHP 7.4+ or PHP 8.x, with the `pdo_mysql` extension (`pdo_sqlite` too,
  for running tests)
- MySQL (or MariaDB) server
- [Composer](https://getcomposer.org/) — dev-only, for the test suite;
  the app itself has zero runtime dependencies

## Local setup (Docker — recommended)

Requires Docker Desktop (or another Docker Engine + Compose).

```bash
docker compose up -d --build
docker compose exec app php bin/create_admin.php admin YourPasswordHere123
```

That's it — the app is running at `http://127.0.0.1:8000` and the
database schema is imported automatically on first boot (see
`database/schema.sql`, mounted into MySQL's `docker-entrypoint-initdb.d`).
No local PHP/MySQL install needed.

Notes:
- `db`'s port is intentionally **not** published to the host — only the
  `app` container needs to reach it, over Docker's internal network
  (`DB_HOST=db`). If you want to inspect the database directly, use
  `docker compose exec db mysql -u root -proot_dev_only star_assessment`
  rather than a host-side MySQL client.
- `.env` is ignored entirely when running via Docker — real environment
  variables (set in `docker-compose.yml`) always take priority over
  `.env`, so Compose's `environment:` block is the actual source of
  truth in this mode.
- `docker compose down` stops containers but keeps the database volume;
  add `-v` to also wipe the database and start fresh.
- If you're also running the manual (non-Docker) setup below on the same
  machine, don't run both at once — the local Homebrew MySQL and this
  container both want equivalent roles, and it's needless duplication
  rather than an actual port conflict (the container's DB port isn't
  published to the host).

## Local setup (manual, no Docker)

### 1. Install PHP and MySQL (macOS / Homebrew)

```bash
brew install php mysql
brew services start mysql
```

### 2. Create the database and an application user

```bash
mysql -u root -e "
CREATE DATABASE IF NOT EXISTS star_assessment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'star_app'@'localhost' IDENTIFIED BY 'star_app';
GRANT SELECT, INSERT, UPDATE, DELETE ON star_assessment.* TO 'star_app'@'localhost';
FLUSH PRIVILEGES;
"
```

The app user is intentionally scoped to `SELECT`/`INSERT`/`UPDATE`/`DELETE`
only on this one database — schema changes (below) are run separately as
`root`.

### 3. Configure environment

```bash
cp .env.example .env
```

Edit `.env` and fill in your local credentials:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=star_assessment
DB_USER=star_app
DB_PASS=star_app

APP_ENV=development
APP_DEBUG=true

CORS_ALLOWED_ORIGIN=http://localhost:4200
```

`CORS_ALLOWED_ORIGIN` should match wherever the Angular dev server runs
(`ng serve` defaults to `http://localhost:4200`). `.env` is git-ignored
and must never be committed.

### 4. Create the database schema

```bash
mysql -u root < database/schema.sql
```

Creates two tables: `consent_logs` (one row per accept/decline decision)
and `admin_users` (bonus admin portal credentials, including
`failed_attempts`/`locked_until` for tracking login lockout).

> **Upgrading an existing database** created before these two columns
> existed:
> ```bash
> mysql -u root star_assessment -e "
> ALTER TABLE admin_users
>   ADD COLUMN failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
>   ADD COLUMN locked_until DATETIME DEFAULT NULL;
> "
> ```
> Running via Docker instead: since `database/schema.sql` only auto-imports
> on a *fresh* volume, either run the same `ALTER TABLE` against the
> running container (`docker compose exec db mysql -u root -proot_dev_only star_assessment -e "..."`)
> or just wipe and reinitialize with `docker compose down -v && docker compose up -d --build`.

### 5. Create an admin user

```bash
php bin/create_admin.php admin YourPasswordHere123
```

CLI-only by design — the plaintext password is passed as an argument, so
this must never be reachable over HTTP. Re-running with the same username
updates that admin's password instead of erroring.

### 6. Run the app

```bash
php -S 127.0.0.1:8000
```

- Consent API: `http://127.0.0.1:8000/consent-handler.php`
- JSON API root: `http://127.0.0.1:8000/api/`
- Legacy admin login page: `http://127.0.0.1:8000/admin/login.php`

## API documentation (Swagger)

OpenAPI spec: [docs/openapi.yaml](docs/openapi.yaml). Browsable UI: run
the app (Docker or manual setup above) and open

```
http://127.0.0.1:8000/docs/api-docs.html
```

Same-origin as the API, so the "Try it out" buttons fire real requests
against your running server with no CORS setup needed.

## Running the automated test suite

```bash
composer install
vendor/bin/phpunit --testdox
```

Unit tests for `ConsentManager` and `AdminAuth` — the two classes with
the actual business logic. They run against an **in-memory SQLite
database** (`tests/Support/TestDatabase.php`), not a live MySQL
connection, so `composer install` is the only setup needed; no `.env`,
no running app, no database server. Covers:

- Banner visibility logic (present/absent/malformed cookie)
- Accept/decline persisting the correct row shape to the database
- Login: unknown username, wrong password, correct password
- **Lockout survives a dropped session** — the regression test for the
  fix described in Security notes below: 5 failed attempts followed by
  a brand new (empty) `$_SESSION` still rejects a 6th attempt, proving
  the lockout can't be reset by an attacker just not sending cookies
- Lockout clearing on success, and expiring after the lockout window

## Testing all the endpoints, in order

Some endpoints depend on cookies set by earlier ones (the consent
decision, the CSRF token, the login session), so testing them in this
order matters. Two ways to run through it — pick whichever you prefer.

### Option A — Swagger UI, click-through

**Part 1: public consent flow (no login needed)**

1. `GET /api/consent-status.php` → Execute. Expect `shouldShowBanner: true` (fresh visitor, no cookie yet).
2. `POST /consent-handler.php` → body `{"action": "accept"}` → Execute. Expect `success: true` plus a `guid`.
3. `GET /api/consent-status.php` again → Execute. Now expect `shouldShowBanner: false` — proves the cookie from step 2 is being read.

These three "just work" — same-origin requests send cookies automatically, no extra config.

**Part 2: admin flow (one manual step, for CSRF)**

Swagger UI doesn't auto-copy a cookie's value into a header the way Angular's `HttpClientXsrfModule` will later — that wiring is Angular-specific. So here you paste the token by hand once:

4. `GET /api/csrf-cookie.php` → Execute. Sets the `XSRF-TOKEN` cookie in your browser.
5. Open DevTools → Application (Chrome) / Storage (Firefox) → Cookies → `http://127.0.0.1:8000` → copy the `XSRF-TOKEN` value.
6. `POST /api/admin/login.php` → body `{"username": "admin", "password": "<whatever you set via create_admin.php>"}` → paste the copied value into the `X-XSRF-TOKEN` header field Swagger renders for this endpoint → Execute. Expect `success: true`. (`401` here means wrong credentials — see "Creating/resetting the admin user" below; `403` means a stale/mismatched CSRF value — redo step 4 and re-copy.)
7. `GET /api/admin/me.php` → Execute (no header needed). Expect `loggedIn: true`.
8. `GET /api/admin/consent-logs.php` → Execute. Expect the row from step 2 in the `logs` array.
9. `POST /api/admin/logout.php` → paste the same `X-XSRF-TOKEN` value again → Execute. Expect `success: true`.
10. `GET /api/admin/me.php` again → Execute. Expect `loggedIn: false` — confirms logout killed the session.
11. `GET /api/admin/consent-logs.php` again → Execute. Expect `401` — confirms the protected endpoint rejects the dead session.

### Option B — one shell script

```bash
JAR=/tmp/star-cookies.txt
rm -f "$JAR"
BASE=http://127.0.0.1:8000

echo "1. consent-status (fresh) ->"; curl -s -b "$JAR" -c "$JAR" "$BASE/api/consent-status.php"; echo
echo "2. accept ->"; curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/consent-handler.php" -H "Content-Type: application/json" -d '{"action":"accept"}'; echo
echo "3. consent-status (after accept) ->"; curl -s -b "$JAR" -c "$JAR" "$BASE/api/consent-status.php"; echo

echo "4. csrf-cookie ->"; curl -s -b "$JAR" -c "$JAR" "$BASE/api/csrf-cookie.php"; echo
TOKEN=$(grep XSRF-TOKEN "$JAR" | awk '{print $NF}')

echo "5. login ->"; curl -s -b "$JAR" -c "$JAR" -H "X-XSRF-TOKEN: $TOKEN" -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"CHANGE_ME"}' "$BASE/api/admin/login.php"; echo
echo "6. me (logged in) ->"; curl -s -b "$JAR" "$BASE/api/admin/me.php"; echo
echo "7. consent-logs ->"; curl -s -b "$JAR" "$BASE/api/admin/consent-logs.php"; echo
echo "8. logout ->"; curl -s -b "$JAR" -c "$JAR" -H "X-XSRF-TOKEN: $TOKEN" -X POST "$BASE/api/admin/logout.php"; echo
echo "9. me (logged out) ->"; curl -s -b "$JAR" "$BASE/api/admin/me.php"; echo
echo "10. consent-logs (rejected) ->"; curl -s -o /dev/null -w "HTTP %{http_code}\n" -b "$JAR" "$BASE/api/admin/consent-logs.php"
```

Replace `CHANGE_ME` with whatever password you actually set (see next section) before running.

### Creating/resetting the admin user

`create_admin.php` is an upsert (`ON DUPLICATE KEY UPDATE`) — safe to re-run any time, including just to reset a password you forgot:

```bash
# manual (non-Docker) setup:
php bin/create_admin.php admin YourPasswordHere123

# Docker setup:
docker compose exec app php bin/create_admin.php admin YourPasswordHere123
```

If a login attempt returns `401 Invalid username or password`, that's the app correctly rejecting a mismatch — it doesn't mean anything is broken, it means the password you tried doesn't match what's in the database. Re-run the command above with a password you'll remember, then retry.

## JSON API reference

All endpoints return `application/json`. Requests from a different origin
(e.g. `http://localhost:4200`, once the Angular app exists) must include
`credentials: 'include'` so session/consent cookies are sent — CORS is
configured to allow this only for the origin in `CORS_ALLOWED_ORIGIN`.

### Consent

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| `GET` | `/api/consent-status.php` | — | `{ shouldShowBanner: bool }` — checked because the consent cookies are `httponly` and unreadable from JS |
| `POST` | `/consent-handler.php` | — | Body `{ "action": "accept" \| "decline" }` |

### CSRF

State-changing admin requests (`login`, `logout`) require an
`X-XSRF-TOKEN` header matching the `XSRF-TOKEN` cookie (double-submit
pattern — Angular's `HttpClientXsrfModule` handles this automatically
once configured, since the cookie/header names match its defaults).

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/csrf-cookie.php` | Call once on app boot to receive the `XSRF-TOKEN` cookie before any login attempt |

### Admin

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| `POST` | `/api/admin/login.php` | `X-XSRF-TOKEN` header | Body `{ "username", "password" }`. 5 failed attempts locks out for 60s |
| `POST` | `/api/admin/logout.php` | `X-XSRF-TOKEN` header | Ends the session |
| `GET` | `/api/admin/me.php` | session cookie | `{ loggedIn: bool, username? }` — for a route guard on app boot |
| `GET` | `/api/admin/consent-logs.php` | session cookie | `{ logs: [...] }` — most recent 200 consent decisions; `401` if not logged in |

See "Testing all the endpoints, in order" above for a full worked example
of this table in action.

## Legacy admin portal (no-JS fallback)

`/admin/login.php`, `/admin/dashboard.php`, `/admin/logout.php` — the
original server-rendered pages, kept working alongside the JSON API
above. Same underlying `AdminAuth`/lockout logic, just HTML forms and
redirects instead of JSON responses.

## Database schema

See [database/schema.sql](database/schema.sql).

- `consent_logs` — `guid`, `consent_status` (`accepted`/`declined`),
  `consent_version`, `consented_at`, `ip_address`, `user_agent`, `created_at`
- `admin_users` — `username` (unique), `password_hash` (bcrypt),
  `failed_attempts`, `locked_until` (login lockout, see below)

## Security notes

- Passwords hashed with bcrypt (`password_hash`/`password_verify`)
- Login timing-attack resistant (dummy hash check runs even for unknown usernames)
- **Login lockout is tracked per-account in the database**
  (`admin_users.failed_attempts`/`locked_until`), not in `$_SESSION`. An
  earlier version tracked it in the session, which meant an attacker could
  reset their own attempt counter simply by not sending a session cookie
  between requests — 5 failed attempts, then a fresh cookie jar, repeat
  indefinitely. Tracking it on the account row instead means the lockout
  survives that; see the regression test in "Running the automated test
  suite" above and the `AdminAuth` docblock for the reasoning.
- Session cookies: `httponly`, `samesite=Lax`, regenerated on login
- CSRF: hidden-form-field token on the legacy login form; double-submit
  `XSRF-TOKEN` cookie + `X-XSRF-TOKEN` header for the JSON API
- Consent cookies: `httponly`, `samesite=Lax`
- CORS restricted to a single explicit origin (`CORS_ALLOWED_ORIGIN`) —
  never a wildcard, since that's incompatible with credentialed requests
- All HTML-rendered output passed through `htmlspecialchars()`
- All database queries use PDO prepared statements

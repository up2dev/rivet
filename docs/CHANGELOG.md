# Changelog

## v1.3.0

Password tokens and two-factor hardening, keeping the best of
LumePack Foundation 2.3 (whose `tokens` table Rivet already ships).
No new table: two upgrade migrations only.

### Upgrade notes

- Run `php artisan migrate`. Two migrations are added:
  `drop_legacy_pwd_token_columns_from_users` (drops `users.pwd_token*`
  when still present, e.g. a database coming from LumePack <= 2.2) and
  `encrypt_two_factor_secrets` (widens `user_two_factor_methods.secret`
  to `text` and encrypts existing TOTP secrets with `APP_KEY`).
  On Laravel 10, the column change requires `doctrine/dbal`.
- Keep `APP_KEY` stable: TOTP secrets are now unreadable without it.
- Schedule `php artisan model:prune --model="Rivet\Data\Models\Token"`
  to purge expired password tokens.
- `POST /auth/pwd/forgot` now always answers `200`, and
  `POST /auth/user/login` no longer returns a validation error for an
  unknown email (no account enumeration). Adjust any frontend relying
  on those errors.

### Added

- `auth.pwd_reset_revokes_sessions` (`PWD_RESET_REVOKES_SESSIONS`,
  default `true`): setting a password through an emailed link deletes
  the user's Sanctum tokens.
- `two_factor.max_attempts` (`TWO_FACTOR_MAX_ATTEMPTS`, default `5`):
  wrong codes allowed on a pending token before it is destroyed.
- `two_factor.bypass_roles` (`TWO_FACTOR_BYPASS_ROLES`): role uids
  exempted from 2FA, alongside `bypass_permission`.
- `Token` model: `PURPOSE_*` constants, `purpose()`/`valid()` scopes,
  `findValid()`, `isExpired()`, `MassPrunable`.
- `User::issuePasswordToken()` / `User::revokePasswordTokens()`.
- `Rivet\Services\AccessTokenService`: single place issuing Sanctum
  tokens (login, 2FA verify, refresh).

### Fixed

- **Expired password links were still accepted**: the expiry check only
  read the minutes component of the interval, so a token expired for
  N whole hours (+ < 1 min) passed. Replaced by a real date comparison.
- Any token of the `tokens` table (whatever its purpose) could set a
  password; only `pwd_create`/`pwd_forgot` are accepted now.
- A successful reset only deleted the token used: other pending links
  stayed valid. All of them are revoked now.
- The password creation email was re-sent (with a new token) on every
  save of a user without password; sent once while a link is valid.
- The tokens migration used `removeColumn()`, which never touches the
  database: `users.pwd_token*` were never dropped.
- `POST /auth/user/login` (login reminder) mailed the logins to
  `MAIL_FROM_ADDRESS` instead of the user, then crashed (no response set).
- `refresh` ignored `sanctum.expiration_override`, and the 2FA login
  path never pruned expired access tokens.
- `DELETE /auth/2fa/{method}` could remove the last method while
  enrollment is forced (now `422`).
- Force-deleting a user left its password tokens behind.

### Security

- TOTP secrets encrypted at rest (`encrypted` cast).
- Email OTP codes stored hashed in cache.
- TOTP anti-replay: a code already accepted for a user is refused.
- `POST /auth/pwd/forgot` no longer reveals whether a login exists, and
  a new request revokes the previous link.

### Deprecated

- `AuthController::setTokenBody()`: use `AccessTokenService::issue()`.

## v1.2.1

### Fixed

- Register `config/frontend.php` in the service provider.

## v1.2.0

### Added

- Localized frontend links in auth emails (`config/frontend.php`,
  `Rivet\Support\FrontendUrl`): the locale is resolved from
  `Accept-Language` before the mail is queued.

## v1.1.2

### Fixed

- Self-service 2FA routes (`totp/setup`, `totp/confirm`, `email/enable`,
  `email/confirm`, `email/request-code`) rejected a valid Sanctum bearer
  token with a `401`. These routes deliberately skip `lpfauth:sanctum`
  middleware so the same routes also serve the pending-token/forced-
  enrollment case — but that middleware is also what makes
  `$request->user()` resolve against the `sanctum` guard rather than
  the app's default guard (`web`, never populated for a stateless API
  request). `_targetUser()` now resolves `$request->user('sanctum')`
  explicitly.

## v1.1.1

### Fixed

- `verify()` and `ResolvePendingTwoFactorToken` now resolve the user
  through `config('crud.user_model')` instead of hardcoding Rivet's own
  base `User` class. A host application's own `User` subclass (default
  eager-loaded relations, accessors, etc.) was silently dropped on this
  path only — including the forced-enrollment completion path, which
  shares the same code.
- `TwoFactorService::issueToken()` now eager-loads
  `config('query.relations')` before issuing the token, so `?with=` is
  honored the same way a direct login already honors it. Concretely: a
  client requesting `?with=roles.permissions` on `POST /auth/2fa/verify`
  got back a `user` object with no `roles` key at all.

## v1.1.0

### Added

- **`config('two_factor.available_methods')`** (`TWO_FACTOR_AVAILABLE_METHODS`,
  default `totp,email`) — which methods a project actually offers.
  `TwoFactorLoginChallenger` now lists it in `methods` on a pending
  `intent: "enroll"` response (previously always empty, since nothing
  is confirmed yet for a brand-new user); `setup()`/`enableEmail()`
  reject any method left out of it with a `404`, even called directly.
- **`GET /auth/2fa/methods`** — `{ available, enabled }` for a "manage
  my two-factor methods" settings screen. Requires full Sanctum
  authentication, never a pending token (same as `DELETE
  /auth/2fa/{method}`).

## v1.0.0

Initial release.

### Added

- **CRUD engine** — convention-based resolution (Controller → Repository
  → Model), generic `list`/`show`/`add`/`edit`/`remove` actions plus
  mass variants, all through `BaseController`/`CRUD`.
- **Filter and sort DSL** — `?filters=`/`?sort=`/`?with=` query string
  syntax, including comparison/set/null/boolean operators, bitwise
  AND/OR combination, and filtering or sorting on a related model's
  field. Supports both `GET` and the HTTP `QUERY` method (RFC 10008).
- **Mass assignment protection** — enforced via `$fillable` across
  every model, consistently.
- **Authentication** — Sanctum-based tokens, login/refresh/logout,
  per-route permission enforcement, password reset flow.
- **Native two-factor authentication** — TOTP and email one-time
  codes, optional forced enrollment, an exemption permission, and a
  `LoginChallenger` extension point for applications that need
  different login-time behaviour entirely. See `docs/USAGE.md`.
- **RBAC** — roles and permissions, with a `rightsmanagement` Artisan
  command for role/user/permission management from the CLI.
- **File storage** — chunked upload, `File`/`Media` models, automatic
  cleanup of the physical file on delete.
- **Mailing** — a base `Mailable` reading its configuration from
  `config()` (never `env()` directly, so it survives `config:cache`),
  with an async queue (`SendmailService` dispatcher, `ProcessSendmail`
  worker) alongside the existing synchronous transactional-email path.
  A white-label MJML-generated layout (`resources/views/emails/layout.blade.php`,
  one accent color via `config('mail.brand_color')`) backs all 7 of
  the package's transactional emails.
- **Additional modules** — an event log (`Log`, on a dedicated MongoDB
  connection), `Dictionaries` (`Taxonomy`/`TaxonomyValue`), `CRONTask`
  (declarative — no built-in scheduler execution), and `DBVersion`
  (runs and logs a SQL script on row creation).
- **`rivet:make:crud`** — scaffolds a full CRUD entity (Model,
  Repository, Controller, Validator, optional migration) from an
  existing database table: `$fillable`/`$casts`/`belongsTo` relations
  (from foreign keys)/`$filters`/validation rules (including `unique:`,
  `max:`, and an `email` heuristic) are all inferred from the schema.

### Known limitations

- CORS is handled by Laravel's own `HandleCors` middleware
  (`config/cors.php`), not by this package — configure it there.
- `CRONTask` is a declaration table only; nothing in the package reads
  and executes it. An application wanting it to actually do something
  needs to wire its own scheduler against it.
- Sanctum's `sanctum` guard can't always be injected automatically:
  `mergeConfigFrom()` does a shallow merge, so a host application that
  already declares its own `guards` key (which stock Laravel scaffolding
  does for `web`) doesn't inherit this package's default — the guard
  must be added by hand in that case, per Sanctum's own install docs.

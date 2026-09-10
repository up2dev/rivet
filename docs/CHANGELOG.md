# Changelog

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

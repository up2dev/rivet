# Testing

## Running the suite

Via Docker (no local PHP required):

```bash
docker compose build && docker compose run --rm tests
```

See `DOCKER.md` for details, including what to do when `docker compose
build` itself fails.

Locally, with PHP and Composer dependencies installed:

```bash
composer install
vendor/bin/phpunit
```

To run a single test class or method:

```bash
vendor/bin/phpunit --filter CrudQueryDslTest
vendor/bin/phpunit --filter testGreaterThanOperator
```

## Test environment

`tests/TestCase.php` boots a real Laravel application via Orchestra
Testbench, with Foundation's own service providers registered, against
an in-memory SQLite database. Tests exercise actual routes, middleware,
and migrations shipped by the package rather than a hand-rolled fake.

The `logs` table (on a dedicated MongoDB connection) is excluded from
the migrations loaded in tests; no test currently exercises the Log
module.

## Test organization

- **`tests/Unit/`** — tests with no HTTP/database dependency.
- **`tests/Feature/`** — tests that exercise real routes over HTTP,
  organized by module (`Auth/`, `Repositories/`, `Storage/`,
  `Mailing/`, `DBVersion/`, `Middleware/`).
- **`tests/Fixtures/`** — models, repositories, controllers, and
  migrations used only by tests (e.g. `Widget`/`WidgetCategory`, used
  by `CrudGenericActionsTest` and `CrudQueryDslTest` to exercise
  `CRUD`'s generic actions and filter DSL without depending on any of
  the package's real domain models).

## Coverage notes

- **`CrudGenericActionsTest`** covers `BaseController`'s generic
  actions (list/show/add/massAdd/edit/massEdit/remove/massRemove) end
  to end over HTTP, including the `QUERY` HTTP method and the
  request-scoped `QueryContext`.
- **`CrudQueryDslTest`** characterizes `CRUD`'s filter/sort DSL:
  comparison operators, `in`/`btw`/`n`/`ist`/`isf`, bitwise `|u|`/`|n|`
  combination, and filtering/sorting by a field on a related model
  (the reflection-based join resolution in `ResolvesRelationJoins`).
- **`MassAssignmentProtectionTest`**, **`RbacMassAssignmentTest`**,
  **`RemainingModulesMassAssignmentTest`**, and
  **`FileMediaMassAssignmentTest`** cover `$fillable` enforcement
  across the package's models.
- **`SendmailQueueTest`** covers the async Sendmail queue
  (`SendmailService` as dispatcher, `ProcessSendmail` as worker).
- Some tests register their own routes via `defineRoutes()` inside the
  test class, scoped to that class, rather than relying on
  `routes/api.php` — used where a test needs a route shape the
  package's own routes don't provide (e.g. the `Widget` fixture
  controller).

## A note on test-scoped Route/controller caching

Laravel caches a `Route`'s resolved controller instance on the `Route`
object itself. Because `defineRoutes()` registers routes once per test
method, several HTTP requests to the same route *within one test
method* can hit the same repository instance — this is why some tests
(e.g. `CrudQueryDslTest`) make only one filtered request per test
method rather than several in sequence. `CrudGenericActionsTest`'s
`testFilteringOneRequestDoesNotLeakIntoTheNextUnfilteredOne` exercises
this scenario deliberately, to confirm request-scoped state doesn't
leak between such calls.

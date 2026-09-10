# Running tests via Docker

No local PHP installation required - the suite runs entirely inside the
container, against an in-memory SQLite database (no external database
service to run).

## Build and run

```bash
docker compose build
docker compose run --rm tests
```

Without Docker Compose:

```bash
docker build -t rivet .
docker run --rm -v "$(pwd)/test-results:/app/test-results" rivet
```

## Test results

Written to `./test-results/` on the host (created automatically,
bind-mounted from the container):

- **`output.txt`** - human-readable output (`--testdox`), one line per
  test with a pass/fail indicator.
- **`junit.xml`** - the same results in JUnit format, for CI
  integration.

`test-results/` is not tracked in version control.

## If `docker compose build` fails before running any tests

This is usually `composer install` failing (a missing or incompatible
dependency), not a test failure. Check the build output for the
specific error.

## Rebuilding after a source change

The image is built from a `COPY` of the source tree, not a live mount.
After changing any file under `src/`, `tests/`, `database/`, or
`config/`, rebuild before running the suite again:

```bash
docker compose build && docker compose run --rm tests
```

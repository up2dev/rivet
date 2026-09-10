# syntax=docker/dockerfile:1
FROM composer:2 AS composer

FROM php:8.2-cli-bookworm

# Extensions needed by Laravel/Testbench + an in-memory SQLite test DB.
# mbstring/pdo_sqlite/bcmath are NOT compiled in by default on this base
# image, unlike most 'core' PHP extensions.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libonig-dev \
        libsqlite3-dev \
        libzip-dev \
    && docker-php-ext-install pdo pdo_sqlite mbstring bcmath zip \
    && rm -rf /var/lib/apt/lists/*

# The base image's default memory_limit (128M) isn't enough to boot a
# full Laravel app (Testbench + Sanctum + this package's RBAC/CRUD
# providers) - it was exhausting memory inside Str::plural() just
# during application bootstrap, well before any test logic ran.
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/zz-memory-limit.ini

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy only the dependency manifest first so `composer install` is
# cached by Docker as long as composer.json doesn't change - avoids a
# full re-download on every source-code edit.
COPY composer.json ./
RUN composer install --no-interaction --prefer-dist --no-scripts || true

COPY . .
RUN composer install --no-interaction --prefer-dist
RUN chmod +x scripts/run-tests.sh

CMD ["sh", "scripts/run-tests.sh"]

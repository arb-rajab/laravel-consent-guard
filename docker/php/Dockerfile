# Local development/test image only — this package ships no application
# image of its own (see README.md: "installed with composer require, not
# cloned and run"). Exists because the audit-log feature genuinely needs
# Postgres and real forked OS processes (pcntl) to test, which many dev
# machines (this one included) don't have natively.
FROM php:8.5-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        unzip \
        git \
    && docker-php-ext-install pdo_pgsql pgsql pcntl posix \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

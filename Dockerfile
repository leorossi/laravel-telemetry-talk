# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1: install Composer dependencies into an isolated layer.
# Only the manifests are copied here, so this layer is rebuilt only when
# composer.json / composer.lock change.
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /build

COPY composer.json composer.lock ./

RUN composer install \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

# ---------------------------------------------------------------------------
# Stage 2: the application image.
# ---------------------------------------------------------------------------
FROM php:8.4-cli-alpine

RUN apk add --no-cache \
        bash \
        curl \
        git \
        libpq \
        libzip \
        unzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# The application. .dockerignore keeps secrets, vendor/ and host-only files out.
COPY . .

# vendor/ comes from the vendor stage, so the host copy is never shipped.
COPY --from=vendor /build/vendor ./vendor

# Runs the package:discover hook in composer.json and creates the writable dirs.
# OTel is disabled here so discovery doesn't try to export metrics during build.
RUN OTEL_SDK_DISABLED=true composer dump-autoload --optimize --no-interaction \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]

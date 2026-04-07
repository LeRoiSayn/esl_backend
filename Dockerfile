# syntax=docker/dockerfile:1
#
# Render (https://render.com): create a Web Service with runtime "Docker".
# - Build: uses this Dockerfile; set Root Directory empty and dockerfilePath to backend/Dockerfile,
#   or set dockerContext to "backend" from repo root (see render.yaml in repo root).
# - Render injects PORT; CMD below binds artisan serve to 0.0.0.0:$PORT.
# - Set env: APP_KEY, APP_URL (https://your-service.onrender.com), DB_* to your PostgreSQL host.
# - Run migrations once: Dashboard → Shell, or a one-off job: php artisan migrate --force
#

# --- Install Composer dependencies (cached layer) ---
FROM composer:2 AS composer_deps
WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --no-interaction

# --- Runtime ---
FROM php:8.2-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev \
    libonig-dev \
    libpng-dev \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
    intl \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=composer_deps /app/vendor ./vendor
COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi --no-interaction || true

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data

# Railway (and similar) provide PORT; default for local `docker run`
ENV PORT=8000
EXPOSE 8000

CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=\"${PORT}\""]

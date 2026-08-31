# Koyeb runtime — staging (main → Staging)
# Misma aplicación, adapter de runtime distinto a development.
# Development: nginx + php-fpm (docker/php/Dockerfile + docker-compose.yml)
# Staging Koyeb: php-cli + artisan serve $PORT (este Dockerfile)
# No supervisord, no nginx, no migrate --force en arranque (ver docs/deployment/staging.md §11).

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci 2>/dev/null || npm install
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

FROM php:8.4-cli-alpine

RUN apk add --no-cache \
        libpq-dev \
        libzip-dev \
        icu-dev \
        zip \
        unzip \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql bcmath intl zip opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/90-ceramica.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/91-opcache.ini

WORKDIR /var/www/html

# Copiar fuentes (sin vendor/node_modules — se generan en build)
COPY . .

# Copiar assets compilados
COPY --from=assets /app/public/build ./public/build

# Instalar dependencias PHP sin dev (Telescope es require-dev → no va a staging)
RUN composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader

# Permisos de escritura para Laravel
RUN mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# Koyeb inyecta $PORT; local default 8000. Health check liveness es GET /up (bootstrap/app.php: health: '/up')
# Migraciones NO se ejecutan aquí — son un step controlado del deployment (docs/deployment/staging.md §11).
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]

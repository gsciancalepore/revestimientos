# Koyeb/Render runtime — staging (main → Staging)
# Misma aplicación, adapter de runtime distinto a development.
# Development: nginx + php-fpm (docker/php/Dockerfile + docker-compose.yml)
# Staging: FrankenPHP + Octane 2 workers (multi-proceso, evita bloqueo single-thread)
# No supervisord, no nginx separado, no migrate --force en arranque (ver docs/deployment/staging.md §11).

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci 2>/dev/null || npm install
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

FROM dunglas/frankenphp:1-php8.4-alpine

RUN install-php-extensions pdo_pgsql bcmath intl zip opcache redis pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/90-ceramica.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/91-opcache.ini

WORKDIR /var/www/html

COPY . .

COPY --from=assets /app/public/build ./public/build

RUN composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader

RUN mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# Render/Koyeb inyecta $PORT; health check es GET /up (bootstrap/app.php: health: '/up')
# Migraciones NO se ejecutan aquí — son un step controlado del deployment (docs/deployment/staging.md §11).
# Octane FrankenPHP con 2 workers evita que / (Neon) bloquee /up en Render Free (512MB/0.1 vCPU).
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=${PORT:-8000}", "--workers=2", "--max-requests=500"]

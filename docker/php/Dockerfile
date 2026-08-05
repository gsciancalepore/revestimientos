FROM php:8.4-fpm-alpine

RUN addgroup -g 1000 app \
    && adduser -D -u 1000 -G app app

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
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]

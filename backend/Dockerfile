FROM php:8.3-fpm-alpine

RUN apk add --no-cache bash git icu-dev libpq-dev oniguruma-dev zip unzip \
    && docker-php-ext-install intl mbstring opcache pdo pdo_pgsql \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]

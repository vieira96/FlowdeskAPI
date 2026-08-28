FROM php:8.4-cli-alpine

# docker-php-ext-install removes PHP's temporary build tools; PECL needs them
# again to compile the Redis extension.
RUN apk add --no-cache \
        git \
        libzip-dev \
        mysql-client \
        oniguruma-dev \
        unzip \
    && docker-php-ext-install \
        mbstring \
        pdo_mysql \
        zip \
    && apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

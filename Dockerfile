FROM php:8.4-apache AS app

RUN docker-php-ext-install -j"$(nproc)" pdo_mysql

WORKDIR /var/www/html

COPY . .

FROM app AS tests

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN composer install --no-interaction --prefer-dist --no-progress

CMD ["vendor/bin/phpunit", "--colors=never"]

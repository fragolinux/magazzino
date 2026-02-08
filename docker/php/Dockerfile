FROM php:8.0-fpm

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV TZ=Europe/Rome

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends git unzip libzip-dev tzdata \
    && ln -snf "/usr/share/zoneinfo/$TZ" /etc/localtime \
    && echo "$TZ" > /etc/timezone \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY db_migrate.php /usr/local/bin/db_migrate.php
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

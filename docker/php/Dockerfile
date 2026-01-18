FROM php:8.0-fpm

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html

COPY db_migrate.php /usr/local/bin/db_migrate.php
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

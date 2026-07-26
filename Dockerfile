FROM php:8.3-fpm-alpine

RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /tmp/*

WORKDIR /var/www/html
COPY . /var/www/html
RUN mkdir -p storage/database storage/exports storage/logs \
    && chown -R www-data:www-data storage \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && chmod -R 775 storage

USER www-data
CMD ["php-fpm", "-F"]

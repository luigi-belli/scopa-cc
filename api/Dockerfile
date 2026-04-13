FROM php:8.5-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libicu-dev \
    unzip \
    cron \
    && docker-php-ext-install pdo_pgsql intl \
    && rm -rf /var/lib/apt/lists/*

# PHP-FPM production tuning (static pool, status page)
COPY php-fpm-prod.conf /usr/local/etc/php-fpm.d/zzz-prod.conf

# OPcache production settings (no timestamp validation, JIT enabled)
COPY opcache-prod.ini /usr/local/etc/php/conf.d/opcache-prod.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --optimize --no-dev

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]

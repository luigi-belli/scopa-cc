# Stage 1: Build frontend
FROM node:24-alpine AS frontend-build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm install
COPY frontend/ ./
RUN npm run build

# Stage 2: Build custom FrankenPHP binary with rate_limit module
FROM dunglas/frankenphp:1.12-builder-php8.5-bookworm AS builder

COPY --from=caddy:builder /usr/bin/xcaddy /usr/bin/xcaddy

# CGO must be enabled to build FrankenPHP
# Build includes: Mercure, Vulcain, Brotli compression, and rate limiting
RUN CGO_ENABLED=1 \
    XCADDY_SETCAP=1 \
    XCADDY_GO_BUILD_FLAGS="-ldflags='-w -s' -tags=nobadger,nomysql,nopgx" \
    CGO_CFLAGS=$(php-config --includes) \
    CGO_LDFLAGS="$(php-config --ldflags) $(php-config --libs)" \
    xcaddy build \
        --output /usr/local/bin/frankenphp \
        --with github.com/dunglas/frankenphp=./ \
        --with github.com/dunglas/frankenphp/caddy=./caddy/ \
        --with github.com/dunglas/caddy-cbrotli \
        --with github.com/dunglas/mercure/caddy \
        --with github.com/dunglas/vulcain/caddy \
        --with github.com/mholt/caddy-ratelimit

# Stage 3: Final image
FROM dunglas/frankenphp:1.12-php8.5-bookworm

# Install PHP extensions
RUN install-php-extensions pdo_pgsql intl opcache

# Replace FrankenPHP binary with custom build (includes rate limiting module)
COPY --from=builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp

# Production PHP settings
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY api/opcache-prod.ini /usr/local/etc/php/conf.d/opcache-prod.ini

# curl for healthcheck, cron for the cron service (shares this image)
RUN apt-get update && apt-get install -y --no-install-recommends curl cron \
    && rm -rf /var/lib/apt/lists/*

# Install Composer and app dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY api/composer.json api/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-scripts
COPY api/ .
RUN composer dump-autoload --optimize --no-dev

# Copy frontend build output into the Symfony public directory
# (index.html, assets/, favicon.svg, etc. alongside Symfony's index.php)
COPY --from=frontend-build /app/dist/ /app/public/

# Caddyfile
COPY frankenphp/Caddyfile /etc/caddy/Caddyfile

# Entrypoint (TLS cert handling + PHP initialization)
COPY frankenphp/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 443/tcp 443/udp 8080/tcp

ENTRYPOINT ["/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

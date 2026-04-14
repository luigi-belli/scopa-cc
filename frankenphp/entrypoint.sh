#!/bin/bash
set -e

# ─── TLS Certificate Configuration ───

SSL_DIR="/ssl"

certs_match() {
    cert_pub=$(openssl x509 -noout -pubkey -in "$SSL_DIR/cert.pem" 2>/dev/null)
    key_pub=$(openssl pkey -pubout -in "$SSL_DIR/key.pem" 2>/dev/null)
    [ -n "$cert_pub" ] && [ "$cert_pub" = "$key_pub" ]
}

if [ "$TLS_MODE" = "letsencrypt" ]; then
    # Wait for acme container to provide a valid cert+key pair
    echo "frankenphp: Waiting for Let's Encrypt certificate..."
    while ! certs_match; do
        sleep 5
    done
    echo "frankenphp: Let's Encrypt certificate ready"
    export TLS_CONFIG="tls /ssl/cert.pem /ssl/key.pem"
    # Periodically reload to pick up renewed certificates
    (while true; do
        sleep 300
        frankenphp reload --config /etc/caddy/Caddyfile 2>/dev/null || true
    done) &
elif certs_match; then
    echo "frankenphp: Using provided certificate"
    export TLS_CONFIG="tls /ssl/cert.pem /ssl/key.pem"
else
    echo "frankenphp: Using Caddy auto-TLS (internal CA)"
    export TLS_CONFIG="tls internal"
fi

# ─── PHP Application Initialization ───

echo "Clearing cache..."
php bin/console cache:clear --env=prod --no-debug 2>/dev/null || true

echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod 2>/dev/null || true

echo "Setting up messenger transports..."
php bin/console messenger:setup-transports --env=prod 2>/dev/null || true

echo "Resetting stuck messenger messages..."
php bin/console doctrine:query:sql "UPDATE messenger_messages SET delivered_at = NULL WHERE delivered_at IS NOT NULL" --env=prod 2>/dev/null || true

exec "$@"

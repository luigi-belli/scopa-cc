#!/bin/sh
set -e

CERT_DIR="/etc/nginx/ssl"
LE_CERT="/etc/letsencrypt/live/${EXTERNAL_HOSTNAME}/fullchain.pem"
LE_KEY="/etc/letsencrypt/live/${EXTERNAL_HOSTNAME}/privkey.pem"

mkdir -p "$CERT_DIR"

if [ "$TLS_MODE" = "letsencrypt" ] && [ -f "$LE_CERT" ] && [ -f "$LE_KEY" ]; then
    # Symlink Let's Encrypt certificates
    ln -sf "$LE_CERT" "$CERT_DIR/cert.pem"
    ln -sf "$LE_KEY" "$CERT_DIR/key.pem"
    echo "nginx: Using Let's Encrypt certificate for $EXTERNAL_HOSTNAME"
else
    # Generate self-signed certificate if missing or hostname changed
    NEEDS_CERT=false
    if [ ! -f "$CERT_DIR/cert.pem" ] || [ ! -f "$CERT_DIR/key.pem" ]; then
        NEEDS_CERT=true
    elif ! openssl x509 -in "$CERT_DIR/cert.pem" -noout -subject 2>/dev/null | grep -q "CN = ${EXTERNAL_HOSTNAME}"; then
        echo "nginx: Certificate CN does not match $EXTERNAL_HOSTNAME, regenerating"
        NEEDS_CERT=true
    fi
    if [ "$NEEDS_CERT" = true ]; then
        openssl req -x509 -newkey ec -pkeyopt ec_paramgen_curve:prime256v1 \
          -keyout "$CERT_DIR/key.pem" -out "$CERT_DIR/cert.pem" \
          -days 3650 -nodes \
          -subj "/CN=${EXTERNAL_HOSTNAME}" \
          -addext "subjectAltName=DNS:${EXTERNAL_HOSTNAME},IP:127.0.0.1" 2>/dev/null
        echo "nginx: Generated self-signed certificate for $EXTERNAL_HOSTNAME"
    fi
fi

# When using Let's Encrypt, periodically pick up renewed certs and reload nginx
if [ "$TLS_MODE" = "letsencrypt" ]; then
    (while true; do
        sleep 6h
        if [ -f "$LE_CERT" ] && [ -f "$LE_KEY" ]; then
            ln -sf "$LE_CERT" "$CERT_DIR/cert.pem"
            ln -sf "$LE_KEY" "$CERT_DIR/key.pem"
        fi
        nginx -s reload 2>/dev/null || true
    done) &
fi

# Delegate to the official nginx entrypoint (runs envsubst on *.template files)
exec /docker-entrypoint.sh "$@"

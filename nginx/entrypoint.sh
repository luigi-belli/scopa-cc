#!/bin/sh
set -e

CERT_DIR="/etc/nginx/ssl"

mkdir -p "$CERT_DIR"

certs_match() {
    cert_pub=$(openssl x509 -noout -pubkey -in "$CERT_DIR/cert.pem" 2>/dev/null)
    key_pub=$(openssl pkey -pubout -in "$CERT_DIR/key.pem" 2>/dev/null)
    [ -n "$cert_pub" ] && [ "$cert_pub" = "$key_pub" ]
}

if [ "$TLS_MODE" = "letsencrypt" ]; then
    # Wait for acme to provide a valid cert+key pair
    echo "nginx: Waiting for Let's Encrypt certificate..."
    while ! certs_match; do
        sleep 5
    done
    echo "nginx: Let's Encrypt certificate ready"
    # Periodically reload to pick up renewed certificates
    (while true; do sleep 300; nginx -s reload 2>/dev/null || true; done) &
elif certs_match; then
    echo "nginx: Using provided certificate"
else
    openssl req -x509 -newkey ec -pkeyopt ec_paramgen_curve:prime256v1 \
      -keyout "$CERT_DIR/key.pem" -out "$CERT_DIR/cert.pem" \
      -days 3650 -nodes \
      -subj "/CN=${EXTERNAL_HOSTNAME}" \
      -addext "subjectAltName=DNS:${EXTERNAL_HOSTNAME},IP:127.0.0.1" 2>/dev/null
    echo "nginx: Generated self-signed certificate for $EXTERNAL_HOSTNAME"
fi

# Delegate to the official nginx entrypoint (runs envsubst on *.template files)
exec /docker-entrypoint.sh "$@"

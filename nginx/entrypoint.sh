#!/bin/sh
set -e

CERT_DIR="/etc/nginx/ssl"

mkdir -p "$CERT_DIR"

# Use provided certificate if both files exist, otherwise generate self-signed
if [ -f "$CERT_DIR/cert.pem" ] && [ -f "$CERT_DIR/key.pem" ]; then
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

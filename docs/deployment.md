# Deployment Guide

## Overview

The application runs as a Docker Compose stack behind HTTPS with HTTP/3 (QUIC) + HTTP/2 support. Deployment configuration is driven by a `.env` file on the deploy machine. TLS certificates are provided by the operator in the `ssl/` directory; if absent, a self-signed certificate is generated automatically for development.

## Configuration

### Parameter File

Copy the template and fill in your values:

```bash
cp .env.dist .env
```

### Parameters

| Parameter | Description | Example |
|---|---|---|
| `EXTERNAL_HOSTNAME` | Public hostname (used for TLS cert CN and nginx `server_name`) | `scopa.example.com` |
| `EXTERNAL_PORT` | Public port users see (after NAT forwarding) | `59820` |
| `INTERNAL_PORT` | Port Docker binds on the host machine | `5982` |
| `TLS_MODE` | `selfsigned` for dev, `letsencrypt` for production | `letsencrypt` |
| `DYNU_CLIENT_ID` | Dynu DNS OAuth2 client ID | `abc123...` |
| `DYNU_SECRET` | Dynu DNS API secret | `xyz789...` |

The `.env` file is gitignored. The `.env.dist` template is committed to the repo.

Dynu DNS credentials are found at: https://www.dynu.com/en-US/ControlPanel/APICredentials

### Port Chain

```
Browser → https://EXTERNAL_HOSTNAME:EXTERNAL_PORT
       → NAT forwards to server:INTERNAL_PORT
       → Docker maps INTERNAL_PORT → container:443 (TCP + UDP)
       → nginx serves HTTP/2 (TCP) + HTTP/3 (QUIC/UDP)
```

The `Alt-Svc` response header advertises `h3=":EXTERNAL_PORT"` so browsers discover HTTP/3 and attempt QUIC on the same external port.

## NAT / Firewall Requirements

| External Port | Internal Port | Protocol | Purpose |
|---|---|---|---|
| `EXTERNAL_PORT` | `INTERNAL_PORT` | **TCP** | HTTPS + HTTP/2 |
| `EXTERNAL_PORT` | `INTERNAL_PORT` | **UDP** | HTTP/3 (QUIC) |

Only two port forwards are needed.

UDP forwarding is required for HTTP/3. Without it, browsers fall back to HTTP/2 over TCP (still works, but without QUIC benefits like 0-RTT and no head-of-line blocking).

## TLS Certificates

Certificates are loaded from the `ssl/` directory in the project root (bind-mounted into the nginx container at `/etc/nginx/ssl`). This directory is gitignored.

### Using Your Own Certificate

Place two PEM files in the `ssl/` directory:

```
ssl/
  cert.pem    # Full certificate chain (server cert + intermediates)
  key.pem     # Private key (unencrypted)
```

| File | Contents | Format |
|---|---|---|
| `cert.pem` | Server certificate followed by any intermediate CA certificates | PEM (base64-encoded, `-----BEGIN CERTIFICATE-----`) |
| `key.pem` | Private key corresponding to the certificate | PEM (base64-encoded, `-----BEGIN PRIVATE KEY-----` or `-----BEGIN EC PRIVATE KEY-----`) |

The filenames must be exactly `cert.pem` and `key.pem`.

**Example with a CA-signed certificate:**

```bash
mkdir -p ssl

# If your CA provided separate files, concatenate them (server cert first):
cat server.crt intermediate.crt > ssl/cert.pem
cp server.key ssl/key.pem

# Restrict permissions on the private key
chmod 600 ssl/key.pem
```

**Example with Let's Encrypt (certbot on the host):**

```bash
# After running certbot on the host machine:
mkdir -p ssl
cp /etc/letsencrypt/live/scopa.example.com/fullchain.pem ssl/cert.pem
cp /etc/letsencrypt/live/scopa.example.com/privkey.pem ssl/key.pem
```

After placing new certificates, reload nginx:

```bash
docker compose exec nginx nginx -s reload
```

### Self-Signed Certificate (Automatic)

If `ssl/cert.pem` and `ssl/key.pem` do not exist when the container starts, the entrypoint automatically generates a self-signed ECDSA P-256 certificate using `EXTERNAL_HOSTNAME` as the CN and SAN. This is suitable for local development — browsers will show a certificate warning.

The generated files are written to `ssl/` and persist across restarts. To regenerate (e.g. after changing `EXTERNAL_HOSTNAME`), delete the existing files and restart:

```bash
rm ssl/cert.pem ssl/key.pem
docker compose restart nginx
```

## Local Development

```bash
# 1. Create config
cp .env.dist .env
# Edit: EXTERNAL_HOSTNAME=localhost, EXTERNAL_PORT=5982

# 2. Start (self-signed cert auto-generated)
docker compose up --build -d
```

Access `https://localhost:5982`. Accept the browser's certificate warning.

## Production with Let's Encrypt + Dynu DNS (Automated)

No manual intervention required. The `acme` service automatically issues and renews certificates via DNS-01 challenge using the Dynu DNS API.

```bash
# 1. Create config
cp .env.dist .env
# Edit:
#   EXTERNAL_HOSTNAME=scopa.example.com
#   EXTERNAL_PORT=59820
#   INTERNAL_PORT=5982
#   TLS_MODE=letsencrypt
#   DYNU_CLIENT_ID=your-client-id
#   DYNU_SECRET=your-api-key

# 2. Start (acme service issues cert automatically)
docker compose --profile letsencrypt up --build -d
```

What happens on first start:
1. nginx starts with a temporary self-signed cert
2. The `acme` container issues a Let's Encrypt cert via Dynu DNS-01 challenge (~1-2 min)
3. The cert is written to `ssl/cert.pem` and `ssl/key.pem`
4. nginx reloads every 5 minutes and picks up the real cert automatically

Certificate renewal is fully automated:
- The `acme` container checks daily and renews when within 30 days of expiry
- After renewal, the new cert is written to `ssl/`
- nginx picks it up on the next 5-minute reload cycle

## Production with Custom Certificate (Manual)

```bash
# 1. Create config
cp .env.dist .env
# Edit:
#   EXTERNAL_HOSTNAME=scopa.example.com
#   EXTERNAL_PORT=59820
#   INTERNAL_PORT=5982

# 2. Place your TLS certificate
mkdir -p ssl
cp /path/to/fullchain.pem ssl/cert.pem
cp /path/to/privkey.pem ssl/key.pem

# 3. Start
docker compose up --build -d
```

## How the Certificate Flow Works

```
nginx container starts → nginx/entrypoint.sh
  │
  ├─ ssl/cert.pem AND ssl/key.pem exist?
  │    → use them as-is
  │
  ├─ Either file missing?
  │    → generate self-signed cert with CN=EXTERNAL_HOSTNAME
  │
  ├─ TLS_MODE=letsencrypt?
  │    → start 5-minute reload loop (picks up renewed certs)
  │
  └─ Delegates to nginx's docker-entrypoint.sh
       → envsubst on default.conf.template (only EXTERNAL_* vars)
       → starts nginx

acme container (profile: letsencrypt)
  │
  ├─ Registers Let's Encrypt account
  ├─ Issues cert via acme.sh + Dynu DNS-01 (if not already present)
  ├─ Installs cert to ssl/cert.pem + ssl/key.pem
  └─ Renewal loop: checks daily, reinstalls after renewal
```

## Nginx Configuration (envsubst Template)

The nginx config is a template at `nginx/default.conf.template`. At container startup, nginx's built-in entrypoint processes it with `envsubst`, replacing `${EXTERNAL_HOSTNAME}` and `${EXTERNAL_PORT}` with values from the environment. The `NGINX_ENVSUBST_FILTER=^EXTERNAL_` setting ensures only these two variables are substituted — nginx-native variables like `$host`, `$uri`, `$request_uri` are left untouched.

The processed config is written to `/etc/nginx/conf.d/default.conf` inside the container.

## HTTP/3 and Performance Features

| Feature | Description |
|---|---|
| HTTP/3 (QUIC) | UDP-based transport, 0-RTT connections, no head-of-line blocking |
| HTTP/2 | TCP fallback with multiplexed streams |
| TLS 1.3 only | Fastest handshake, required for QUIC |
| 0-RTT early data | Repeat visitors get assets in the first flight |
| 0-RTT replay protection | POST endpoints return HTTP 425 on early data |
| Gzip compression | JS, CSS, JSON, SVG compressed (level 5) |
| QUIC retry | Anti-amplification via address validation tokens |
| QUIC GSO | Generic Segmentation Offload for better UDP throughput |
| HSTS | Strict-Transport-Security with 2-year max-age |
| TLS session cache | 10MB shared cache, 24h timeout, tickets off (forward secrecy) |
| ECDSA P-256 cert | Faster TLS handshakes than RSA (self-signed only; bring your own for production) |

## Updating Configuration

### Changing Hostname or Port

```bash
vim .env   # Update EXTERNAL_HOSTNAME and/or EXTERNAL_PORT
docker compose up --build -d
```

If you changed `EXTERNAL_HOSTNAME` and are using a self-signed cert, delete the old one so a new one is generated:

```bash
rm ssl/cert.pem ssl/key.pem
docker compose restart nginx
```

### Replacing Certificates

```bash
cp /path/to/new/fullchain.pem ssl/cert.pem
cp /path/to/new/privkey.pem ssl/key.pem
docker compose exec nginx nginx -s reload
```

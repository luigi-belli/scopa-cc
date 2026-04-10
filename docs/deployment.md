# Deployment Guide

## Overview

The application runs as a Docker Compose stack behind HTTPS with HTTP/3 (QUIC) + HTTP/2 support. Deployment configuration is driven by a `.env` file on the deploy machine. TLS certificates are either self-signed (local dev) or obtained from Let's Encrypt (production).

## Configuration

### Parameter File

Copy the template and fill in your values:

```bash
cp .env.dist .env
```

### Parameters

| Parameter | Description | Example |
|---|---|---|
| `EXTERNAL_HOSTNAME` | Public hostname users see (domain for LE, `localhost` for dev) | `scopa.example.com` |
| `EXTERNAL_PORT` | Public port users see (after NAT forwarding) | `59820` |
| `INTERNAL_PORT` | Port Docker binds on the host machine | `5982` |
| `TLS_MODE` | `selfsigned` for dev, `letsencrypt` for production | `letsencrypt` |
| `LETSENCRYPT_EMAIL` | Contact email for Let's Encrypt notifications | `admin@example.com` |

The `.env` file is gitignored. The `.env.dist` template is committed to the repo.

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
| 80 | 80 | **TCP** | Let's Encrypt ACME challenges |

UDP forwarding is required for HTTP/3. Without it, browsers fall back to HTTP/2 over TCP (still works, but without QUIC benefits like 0-RTT and no head-of-line blocking).

Port 80 is only needed when `TLS_MODE=letsencrypt` — for initial certificate issuance and renewals. It serves ACME HTTP-01 challenge responses and redirects all other traffic to HTTPS.

## Local Development (Self-Signed)

```bash
# 1. Create config
cp .env.dist .env
# Edit: EXTERNAL_HOSTNAME=localhost, EXTERNAL_PORT=5982, TLS_MODE=selfsigned

# 2. Start
docker compose up --build -d
```

Access `https://localhost:5982`. The browser will show a certificate warning — this is expected with self-signed certs. Accept it to proceed.

The self-signed certificate is generated at container startup using `EXTERNAL_HOSTNAME` as the CN and SAN. It persists in the `ssl-certs` Docker volume across restarts. If you change `EXTERNAL_HOSTNAME`, the entrypoint detects the CN mismatch and regenerates automatically.

## Production (Let's Encrypt)

### Prerequisites

- `EXTERNAL_HOSTNAME` must be a real domain with DNS A/AAAA record pointing to the server
- Port 80 must be reachable from the internet (for ACME HTTP-01 challenges)
- Port `EXTERNAL_PORT` must be forwarded (both TCP and UDP) through NAT to `INTERNAL_PORT`

### Initial Setup

```bash
# 1. Create config
cp .env.dist .env
# Edit with your production values:
#   EXTERNAL_HOSTNAME=scopa.example.com
#   EXTERNAL_PORT=59820
#   INTERNAL_PORT=5982
#   TLS_MODE=letsencrypt
#   LETSENCRYPT_EMAIL=admin@example.com

# 2. Start the stack (boots with a temporary self-signed cert)
docker compose up --build -d

# 3. Request the initial Let's Encrypt certificate
docker compose --profile letsencrypt run --rm certbot \
  certonly --webroot -w /var/www/certbot \
  -d "scopa.example.com" --email "admin@example.com" \
  --agree-tos --no-eff-email

# 4. Reload nginx to use the real certificate
docker compose exec nginx nginx -s reload

# 5. Start the certbot renewal container
docker compose --profile letsencrypt up -d certbot
```

### Certificate Renewal

Renewal is automated:

- The **certbot** container checks for renewal every 12 hours
- The **nginx** entrypoint runs a background loop that re-symlinks certs and reloads nginx every 6 hours

Within 6 hours of a certbot renewal, nginx picks up the new certificate automatically. No manual intervention needed.

To force a manual reload:

```bash
docker compose exec nginx nginx -s reload
```

## How the Certificate Flow Works

```
Container starts → nginx/entrypoint.sh
  │
  ├─ TLS_MODE=letsencrypt AND LE certs exist?
  │    → symlink /etc/letsencrypt/live/<domain>/{fullchain,privkey}.pem
  │      to /etc/nginx/ssl/{cert,key}.pem
  │
  ├─ No certs yet (or hostname changed)?
  │    → generate self-signed cert with CN=EXTERNAL_HOSTNAME
  │
  └─ TLS_MODE=letsencrypt?
       → start background loop: every 6h re-symlink + nginx -s reload
  │
  └─ Delegates to nginx's docker-entrypoint.sh
       → envsubst on default.conf.template (only EXTERNAL_* vars)
       → starts nginx
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
| ECDSA P-256 cert | Faster TLS handshakes than RSA |

## Docker Volumes

| Volume | Purpose | Persists |
|---|---|---|
| `pgdata` | PostgreSQL game data | Yes |
| `ssl-certs` | Active TLS certificates (self-signed or LE symlinks) | Yes |
| `certbot-certs` | Let's Encrypt certificates and renewal state | Yes |
| `certbot-webroot` | ACME challenge response files | Yes |

## Updating Configuration

### Changing Hostname or Port

```bash
vim .env   # Update EXTERNAL_HOSTNAME and/or EXTERNAL_PORT
docker compose up --build -d   # Entrypoint detects changes, regenerates cert if self-signed
```

If using Let's Encrypt with a **new domain**, re-run certbot:

```bash
docker compose --profile letsencrypt run --rm certbot \
  certonly --webroot -w /var/www/certbot \
  -d "new-domain.com" --email "admin@example.com" \
  --agree-tos --no-eff-email
docker compose exec nginx nginx -s reload
```

### Docker Compose Profiles

| Command | What starts |
|---|---|
| `docker compose up -d` | Core stack only (postgres, php, messenger, cron, mercure, nginx) |
| `docker compose --profile letsencrypt up -d` | Core stack + certbot renewal container |

The certbot container is behind the `letsencrypt` profile — it does not start by default.

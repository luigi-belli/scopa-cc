# Deployment Guide

## Overview

The application runs as a Docker Compose stack behind HTTPS with HTTP/3 (QUIC) + HTTP/2 support. Deployment configuration is driven by a `.env` file on the deploy machine. TLS certificates are either self-signed (local dev) or obtained from Let's Encrypt via DNS-01 challenge (production — no port 80 needed).

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

Only two port forwards are needed. No port 80 — Let's Encrypt uses DNS-01 validation.

UDP forwarding is required for HTTP/3. Without it, browsers fall back to HTTP/2 over TCP (still works, but without QUIC benefits like 0-RTT and no head-of-line blocking).

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

## Production (Let's Encrypt via DNS-01)

Let's Encrypt validates domain ownership via a DNS TXT record. No inbound port 80 is needed — only the HTTPS/QUIC ports you already forward.

### Prerequisites

- `EXTERNAL_HOSTNAME` must be a real domain with DNS A/AAAA record pointing to the server
- You must have access to your DNS provider's control panel to add TXT records
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

# 3. Request the initial Let's Encrypt certificate (interactive — see below)
docker compose --profile letsencrypt run --rm certbot certonly \
  --manual --preferred-challenges dns \
  -d "scopa.example.com" --email "admin@example.com" \
  --agree-tos --no-eff-email

# 4. Reload nginx to use the real certificate
docker compose exec nginx nginx -s reload
```

### What Happens During Step 3 (DNS Challenge)

Certbot will pause and display a prompt like this:

```
Please deploy a DNS TXT record under the name:

    _acme-challenge.scopa.example.com

with the following value:

    XyZ1aBcDeFgHiJkLmNoPqRsTuVwXyZ1aBcDeF

Before continuing, verify the TXT record has been deployed.
Press Enter to continue...
```

**What you need to do:**

1. Log in to your DNS provider (Cloudflare, Route53, GoDaddy, Namecheap, etc.)
2. Add a **TXT record** with these values:

   | Field | Value |
   |---|---|
   | **Type** | `TXT` |
   | **Name** / **Host** | `_acme-challenge` (some providers want the full `_acme-challenge.scopa.example.com`) |
   | **Value** / **Content** | The token string certbot displayed (e.g. `XyZ1aBcDeFgHiJkLmNoPqRsTuVwXyZ1aBcDeF`) |
   | **TTL** | `300` (5 minutes) or the lowest available |

3. Wait for DNS propagation (usually 1-5 minutes). You can verify with:
   ```bash
   dig TXT _acme-challenge.scopa.example.com +short
   ```
   The output should show the token value in quotes.

4. Press **Enter** in the certbot prompt. Certbot verifies the TXT record and issues the certificate.

5. **Remove the TXT record** from your DNS provider after the certificate is issued. It is no longer needed.

### Certificate Renewal

Let's Encrypt certificates are valid for 90 days. To renew, re-run the certbot command:

```bash
docker compose --profile letsencrypt run --rm certbot certonly \
  --manual --preferred-challenges dns \
  -d "scopa.example.com" --email "admin@example.com" \
  --agree-tos --no-eff-email
docker compose exec nginx nginx -s reload
```

This will prompt for a new DNS TXT record (same process as initial issuance). Set a calendar reminder for every ~60 days.

The nginx entrypoint also runs a background loop that re-symlinks Let's Encrypt certs and reloads nginx every 6 hours, so if the cert volume is updated (e.g. by an external renewal tool), nginx picks it up automatically.

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
  ├─ TLS_MODE=letsencrypt?
  │    → start background loop: every 6h re-symlink + nginx -s reload
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

## Updating Configuration

### Changing Hostname or Port

```bash
vim .env   # Update EXTERNAL_HOSTNAME and/or EXTERNAL_PORT
docker compose up --build -d   # Entrypoint detects changes, regenerates cert if self-signed
```

If using Let's Encrypt with a **new domain**, re-run certbot:

```bash
docker compose --profile letsencrypt run --rm certbot certonly \
  --manual --preferred-challenges dns \
  -d "new-domain.com" --email "admin@example.com" \
  --agree-tos --no-eff-email
docker compose exec nginx nginx -s reload
```

### Docker Compose Profiles

| Command | What starts |
|---|---|
| `docker compose up -d` | Core stack (postgres, php, messenger, cron, mercure, nginx) |
| `docker compose --profile letsencrypt run --rm certbot ...` | One-shot certbot for cert issuance |

The certbot container is behind the `letsencrypt` profile and runs as a one-shot command — it does not stay running.

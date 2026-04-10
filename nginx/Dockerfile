FROM node:22-alpine AS frontend-build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm install
COPY frontend/ ./
RUN npm run build

FROM nginx:1.27-alpine

# OpenSSL for runtime self-signed cert generation (kept in image, ~1MB on Alpine)
RUN apk add --no-cache openssl && mkdir -p /etc/nginx/ssl

# Config template — processed by nginx's built-in envsubst on container start
# (replaces only defined env vars, leaves nginx $variables untouched)
COPY nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY nginx/fastcgi_api.conf /etc/nginx/fastcgi_api.conf

# Custom entrypoint: manages TLS certs then delegates to nginx's entrypoint
COPY nginx/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY --from=frontend-build /app/dist /usr/share/nginx/html

EXPOSE 443/tcp 443/udp

ENTRYPOINT ["/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]

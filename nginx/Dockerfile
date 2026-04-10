FROM node:22-alpine AS frontend-build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm install
COPY frontend/ ./
RUN npm run build

FROM nginx:1.27-alpine

# Generate self-signed TLS certificate for HTTP/3 (QUIC) + HTTP/2
RUN apk add --no-cache openssl && \
    mkdir -p /etc/nginx/ssl && \
    openssl req -x509 -newkey ec -pkeyopt ec_paramgen_curve:prime256v1 \
      -keyout /etc/nginx/ssl/key.pem \
      -out /etc/nginx/ssl/cert.pem \
      -days 3650 -nodes \
      -subj "/CN=localhost" \
      -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" && \
    apk del openssl

COPY nginx/default.conf /etc/nginx/conf.d/default.conf
COPY nginx/fastcgi_api.conf /etc/nginx/fastcgi_api.conf
COPY --from=frontend-build /app/dist /usr/share/nginx/html

EXPOSE 443/tcp 443/udp

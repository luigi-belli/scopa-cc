.PHONY: up down build restart logs acme-up acme-down test test-back test-front build-front shell db

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose up -d --build

restart:
	docker compose restart

logs:
	docker compose logs -f

acme-up:
	docker compose --profile letsencrypt up -d

acme-down:
	docker compose --profile letsencrypt down

test: test-back test-front

test-back:
	docker compose exec php bin/phpunit

test-front:
	docker run --rm -v "$$(pwd)/frontend:/app" -w /app node:22-alpine sh -c "npm install && npx vitest run"

build-front:
	docker run --rm -v "$$(pwd)/frontend:/app" -w /app node:22-alpine sh -c "npm install && npm run build"

shell:
	docker compose exec php sh

db:
	docker compose exec postgres psql -U scopa scopa

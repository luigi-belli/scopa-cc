.PHONY: up down build restart logs acme-up acme-down test test-back test-front build-front shell db node-up node-install

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

# Ensure the persistent node container is running and has dependencies installed
node-up:
	@docker compose --profile dev up -d node
	@docker compose --profile dev exec node sh -c "test -d node_modules/.package-lock.json 2>/dev/null && npm ls --depth=0 >/dev/null 2>&1 || npm ci"

node-install:
	docker compose --profile dev up -d node
	docker compose --profile dev exec node npm ci

test: test-back test-front

test-back:
	docker compose exec php bin/phpunit

test-front: node-up
	docker compose --profile dev exec node npx vitest run

# Run only tests related to changed files
test-front-changed: node-up
	docker compose --profile dev exec node npx vitest run --changed

build-front: node-up
	docker compose --profile dev exec node npm run build

shell:
	docker compose exec php sh

db:
	docker compose exec postgres psql -U scopa scopa

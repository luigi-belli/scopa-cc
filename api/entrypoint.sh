#!/bin/bash
set -e

echo "Clearing cache..."
php bin/console cache:clear --env=prod --no-debug 2>/dev/null || true

echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod 2>/dev/null || true

echo "Setting up messenger transports..."
php bin/console messenger:setup-transports --env=prod 2>/dev/null || true

exec "$@"

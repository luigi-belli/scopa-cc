#!/bin/bash
set -e

echo "Clearing cache..."
php bin/console cache:clear --env=prod --no-debug 2>/dev/null || true

echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod 2>/dev/null || true

echo "Setting up messenger transports..."
php bin/console messenger:setup-transports --env=prod 2>/dev/null || true

echo "Resetting stuck messenger messages..."
php -r "
\$dsn = getenv('DATABASE_URL');
if (\$dsn) {
    \$pdo = new PDO(\$dsn);
    \$count = \$pdo->exec('UPDATE messenger_messages SET delivered_at = NULL WHERE delivered_at IS NOT NULL');
    echo \"Reset \$count stuck message(s).\n\";
}
" 2>/dev/null || true

exec "$@"

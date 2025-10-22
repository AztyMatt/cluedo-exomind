#!/usr/bin/env bash
set -euo pipefail

echo "➡️  Running DB init (docker-init-db.php)…"
# Optionnel: attendre la DB (ou gérer les retries dans ton PHP)
# until nc -z "$MYSQLHOST" "$MYSQLPORT"; do echo "waiting DB…"; sleep 1; done

php /var/www/html/docker-init-db.php || echo "ℹ️  init script finished (or non-fatal)."

echo "🚀 Starting Apache on PORT=${PORT}"
exec apache2-foreground

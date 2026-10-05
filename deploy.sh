#!/usr/bin/env bash
# Kunstbegleiter Auto-Deploy (Vorlage: Tourtool deploy.sh)
# Laeuft jede Minute per Cron. Deployt nur, wenn der Remote-Branch neuer ist als der Server-Stand.
# Manuell erzwingen: $APP_DIR/deploy.sh --force
#
# Live:     APP_DIR=/var/www/vhosts/tourtool.app/art.tourtool.app BRANCH=main ./deploy.sh
# Staging:  APP_DIR=/var/www/vhosts/tourtool.app/art-staging.tourtool.app BRANCH=staging SEED_AFTER_MIGRATE=1 ./deploy.sh
# Der Queue-Worker (systemd) startet sich ueber kunst-queue-reload.path selbst neu, sobald "optimize" die
# Datei bootstrap/cache/config.php schreibt (deploy/kunst-queue-reload.path). Kein sudo noetig.
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")" && pwd)}"
BRANCH="${BRANCH:-main}"
PHP="${PHP:-/opt/plesk/php/8.5/bin/php}"
COMPOSER="${COMPOSER:-/opt/psa/var/modules/composer/composer.phar}"
LOCK_FILE="${DEPLOY_LOCK:-$HOME/.deploy-$(basename "$APP_DIR").lock}"
SEED_AFTER_MIGRATE="${SEED_AFTER_MIGRATE:-0}"

cd "$APP_DIR"
exec 9>"$LOCK_FILE"
flock -n 9 || exit 0

remote=$(git ls-remote origin "refs/heads/$BRANCH" | cut -f1)
[ -n "$remote" ] || exit 0
if [ "$remote" = "$(git rev-parse HEAD)" ] && [ "${1:-}" != "--force" ]; then
  exit 0
fi

echo "=== $(date '+%F %T') Deploy $BRANCH $remote nach $APP_DIR ==="
$PHP artisan down --retry=15 || true
trap '$PHP artisan up || true' EXIT

git fetch -q origin "$BRANCH"
git reset -q --hard "origin/$BRANCH"

$PHP $COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress
$PHP artisan migrate --force
# Stammdaten (Epochen) bei jedem Deploy nachziehen, mehrfach aufrufbar
$PHP artisan db:seed --force
$PHP artisan optimize:clear
$PHP artisan optimize

echo "=== $(date '+%F %T') fertig ==="

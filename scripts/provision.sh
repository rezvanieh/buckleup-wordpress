#!/usr/bin/env bash
# BuckleUp WordPress — idempotent provisioning.
# Brings the site from "empty DB" to "fully configured, themed, seeded" using WP-CLI.
# Safe to re-run. Run from the repo root:  ./scripts/provision.sh
set -euo pipefail

cd "$(dirname "$0")/.."

# Load .env so we have admin creds / titles available to this script.
if [ -f .env ]; then set -a; . ./.env; set +a; fi

WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-admin123}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@buckleupdriving.ca}"
WP_TITLE="${WP_TITLE:-BuckleUp Driving School}"
WP_HOME="${WP_HOME:-http://localhost:8080}"

# Helper: run WP-CLI inside the one-shot wpcli service (auto-removed).
wp() { docker compose run --rm -T wpcli wp "$@"; }

echo "==> Waiting for the database to be healthy..."
docker compose up -d db redis wordpress
until [ "$(docker inspect -f '{{.State.Health.Status}}' buckleup-db 2>/dev/null)" = "healthy" ]; do
  sleep 2; echo "    ...db not ready yet"
done

echo "==> Ensuring WordPress core is installed..."
if ! wp core is-installed 2>/dev/null; then
  wp core install \
    --url="$WP_HOME" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
  echo "    WordPress installed."
else
  echo "    WordPress already installed — skipping."
fi

echo "==> Core options (timezone, permalinks, blog name/desc)..."
wp option update blogname "$WP_TITLE"
wp option update blogdescription "ICBC-certified driving school in Port Moody & Vancouver"
wp option update timezone_string "America/Vancouver"
wp option update date_format "F j, Y"
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

echo "==> Installing required plugins (from wp.org)..."
# Object cache, SEO, forms, redirects. Pinned-ish to current stable lines.
wp plugin install redis-cache seo-by-rank-math wpforms-lite redirection safe-svg --activate

echo "==> Enabling Redis object cache drop-in..."
wp redis enable || echo "    (redis enable will retry after Redis Object Cache is active)"

echo "==> Activating the BuckleUp theme..."
wp theme activate buckleup || echo "    WARNING: theme 'buckleup' not found — build it first."

echo "==> Roles & capabilities (instructor + student)..."
wp eval-file /scripts/wp/roles.php

echo "==> Demo users (admin already exists; add instructors + demo student)..."
wp eval-file /scripts/wp/users.php

echo "==> Seeding catalog (services, packages) + content (graduates, FAQ, testimonials)..."
wp eval-file /scripts/wp/seed-catalog.php
wp eval-file /scripts/wp/seed-content.php

echo "==> Importing media from the source public/ assets (logos, hero, icons)..."
wp eval-file /scripts/wp/import-media.php || echo "    (media import skipped — see scripts/wp/import-media.php)"

echo "==> Flushing rewrite + object cache..."
wp rewrite flush --hard
wp cache flush || true

echo ""
echo "============================================================"
echo " BuckleUp WordPress is ready."
echo "   Site   : $WP_HOME"
echo "   Admin  : $WP_HOME/wp-admin  ($WP_ADMIN_USER / $WP_ADMIN_PASSWORD)"
echo "   Adminer: http://localhost:${ADMINER_PORT:-8081}"
echo "   Mailpit: http://localhost:${MAILPIT_UI_PORT:-8025}"
echo "============================================================"

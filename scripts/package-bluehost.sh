#!/usr/bin/env bash
# =============================================================================
# package-bluehost.sh — assemble a Bluehost-ready upload bundle from the running
# local Docker build. Output: dist/bluehost/
#
# Produces:
#   theme-buckleup.zip          -> wp-admin: Appearance > Add Theme > Upload
#   buckleup-core.zip           -> wp-admin: Plugins > Add New > Upload (activate 1st)
#   buckleup-app.zip            -> wp-admin: Plugins > Add New > Upload (activate 2nd)
#   uploads.tar.gz              -> extract into wp-content/uploads (incl. .webp)
#   database/buckleup-prod.sql  -> import (URLs already rewritten to the prod host)
#   mu-plugins/{10,11}.php       -> SFTP into wp-content/mu-plugins (NOT the mailpit one)
#   htaccess/{root,uploads,wp-includes}.htaccess
#   config/wp-config-snippet.php
#   *.md                        -> the runbook + checklists
#
# Requires the local stack to be UP (make up) so we can build assets, generate
# WebP, export the DB, and copy uploads out of the wp-core volume.
#
# Usage:
#   ./scripts/package-bluehost.sh                         # defaults to prod domain
#   SITE_URL=https://staging.example.com ./scripts/package-bluehost.sh
#   SKIP_WEBP=1 ./scripts/package-bluehost.sh             # skip the slow EWWW pass
#   SRC_URL=http://localhost:8080 ./scripts/package-bluehost.sh
# =============================================================================
set -euo pipefail

# --- Config ------------------------------------------------------------------
SRC_URL="${SRC_URL:-http://localhost:8080}"          # the local dev origin in the DB
SITE_URL="${SITE_URL:-https://www.buckleupdriving.ca}" # the production origin
SKIP_WEBP="${SKIP_WEBP:-0}"

cd "$(dirname "$0")/.."                                # repo root
ROOT="$(pwd)"
OUT="$ROOT/dist/bluehost"
DC="docker compose"

say()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m    %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31mERROR: %s\033[0m\n' "$*" >&2; exit 1; }

# --- Preflight ---------------------------------------------------------------
command -v docker >/dev/null 2>&1 || die "docker not found."
command -v zip    >/dev/null 2>&1 || die "zip not found (brew install zip)."
$DC ps --status running 2>/dev/null | grep -q buckleup-wp || \
  die "The stack isn't running. Start it first:  make up"

say "Packaging BuckleUp for Bluehost"
echo "    SRC_URL  = $SRC_URL  (rewritten ->)  SITE_URL = $SITE_URL"
echo "    Output   = $OUT"

rm -rf "$OUT"
mkdir -p "$OUT/database" "$OUT/mu-plugins" "$OUT/htaccess" "$OUT/config"

# --- 1. Build theme assets (Vite) -------------------------------------------
say "Building theme assets (Vite production build)..."
$DC --profile assets run --rm assets sh -c "npm install --no-audit --no-fund && npm run build"
[ -f "$ROOT/wp-content/themes/buckleup/build/.vite/manifest.json" ] || \
  die "Vite manifest missing after build — theme would render unstyled. Aborting."

# --- 2. Generate WebP siblings (EWWW local binaries, in the web container) ----
if [ "$SKIP_WEBP" = "1" ]; then
  warn "SKIP_WEBP=1 — skipping WebP generation (using whatever siblings exist)."
elif $DC exec -T -u www-data wordpress sh -c 'command -v cwebp >/dev/null'; then
  say "Generating WebP siblings (EWWW bulk; this can take a few minutes)..."
  $DC exec -T -u www-data wordpress wp ewwwio optimize media --force --noprompt --path=/var/www/html \
    || warn "EWWW bulk pass reported an issue — continuing (re-run to retry)."
else
  warn "cwebp not in the web container — skipping WebP. Rebuild: docker compose build wordpress"
fi

# --- 3. Export the DB with URLs rewritten to the prod host -------------------
# search-replace --export handles serialized data and writes a transformed dump
# WITHOUT touching the live DB. The dump uses the dev table prefix (wp_) — set
# $table_prefix = 'wp_' in the prod wp-config.php to match (see wp-config-snippet).
say "Exporting database (search-replace $SRC_URL -> $SITE_URL)..."
$DC run --rm -T wpcli wp search-replace "$SRC_URL" "$SITE_URL" \
  --all-tables --export=/wp-data/buckleup-prod.sql --report-changed-only --quiet
cp "$ROOT/wp-data/buckleup-prod.sql" "$OUT/database/buckleup-prod.sql"
rm -f "$ROOT/wp-data/buckleup-prod.sql"

# --- 4. Copy uploads (with .webp siblings) out of the wp-core volume ---------
say "Archiving wp-content/uploads (with .webp siblings)..."
$DC exec -T wordpress tar czf - -C /var/www/html/wp-content uploads > "$OUT/uploads.tar.gz"

# --- 5. Zip the theme + first-party plugins ---------------------------------
say "Zipping theme + plugins..."
( cd "$ROOT/wp-content/themes"  && zip -rqX "$OUT/theme-buckleup.zip" buckleup \
    -x '*/node_modules/*' '*/src/*' '*/.DS_Store' '*/package-lock.json' )
( cd "$ROOT/wp-content/plugins" && zip -rqX "$OUT/buckleup-core.zip" buckleup-core -x '*/.DS_Store' )
( cd "$ROOT/wp-content/plugins" && zip -rqX "$OUT/buckleup-app.zip"  buckleup-app  -x '*/.DS_Store' )

# --- 6. mu-plugins (the prod-safe two only — NOT the dev Mailpit one) --------
say "Copying production mu-plugins (excluding the dev Mailpit transport)..."
cp "$ROOT/docker/wordpress/mu-plugins/10-buckleup-seo.php" "$OUT/mu-plugins/"
cp "$ROOT/docker/wordpress/mu-plugins/11-buckleup-pwa.php" "$OUT/mu-plugins/"

# --- 7. .htaccess + wp-config snippet + docs --------------------------------
say "Copying .htaccess, wp-config snippet, and docs..."
cp "$ROOT/deploy/bluehost/htaccess-root.txt"        "$OUT/htaccess/root.htaccess"
cp "$ROOT/deploy/bluehost/htaccess-uploads.txt"     "$OUT/htaccess/uploads.htaccess"
cp "$ROOT/deploy/bluehost/htaccess-wp-includes.txt" "$OUT/htaccess/wp-includes.htaccess"
cp "$ROOT/deploy/bluehost/wp-config-snippet.php"    "$OUT/config/"
cp "$ROOT/deploy/bluehost/README.md" \
   "$ROOT/deploy/bluehost/plugins.md" \
   "$ROOT/deploy/bluehost/smtp-and-dns.md" \
   "$ROOT/deploy/bluehost/preflight-checklist.md" "$OUT/"

# --- 8. Manifest -------------------------------------------------------------
say "Bundle contents:"
( cd "$OUT" && find . -type f | sort | sed 's/^\./    /' )
echo
du -sh "$OUT" | sed 's/^/    total: /'
say "Done. Upload per deploy/bluehost/README.md."
warn "Reminder: change the dev admin creds (admin/admin123) after import, and"
warn "set up SMTP (deploy/bluehost/smtp-and-dns.md) before relying on email."

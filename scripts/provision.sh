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

# Helper: run a wp eval-file step without letting a not-yet-registered CPT/helper
# abort the whole provision run. The seed scripts are individually idempotent and
# self-guard (they no-op + warn if their CPT isn't registered yet); this is a
# second belt-and-suspenders layer so `set -e` can't kill provisioning either.
wp_eval() {
  local file="$1"
  if wp eval-file "$file"; then
    return 0
  fi
  echo "    WARNING: eval-file ${file} reported an error — continuing (re-run after the plugin/theme that owns it is active)."
  return 0
}

echo "==> Waiting for the database to be healthy..."
docker compose up -d db wordpress
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
# Posts live under /blog/{slug} for parity with the live site. This prefixes
# only posts; pages and the /locations/ CPT keep their own bases. The static
# front page + posts page are wired in seed-content.php (Reading settings).
wp rewrite structure '/blog/%postname%/' --hard
wp rewrite flush --hard

echo "==> Installing required plugins (from wp.org)..."
# v1 marketing-site stack — lean, no object cache (low traffic; do not over-engineer):
#   seo-by-rank-math       SEO + schema + sitemap
#   wpforms-lite           contact form
#   redirection            URL-parity 301s + 404 logging
#   safe-svg               sanitized SVG uploads (brand icons)
#   cache-enabler          static-HTML page cache (KeyCDN; pairs with EWWW WebP)
#   ewww-image-optimizer   local image optimization + WebP (free, no API key)
# Install per-slug with a retry so one flaky wp.org download (transient network)
# can't abort the whole provision under `set -e`. Already-installed = success.
for plugin in seo-by-rank-math wpforms-lite redirection safe-svg cache-enabler ewww-image-optimizer; do
  if wp plugin is-installed "$plugin" 2>/dev/null; then
    wp plugin activate "$plugin" >/dev/null 2>&1 || true
    echo "    $plugin: already installed (ensured active)."
    continue
  fi
  if wp plugin install "$plugin" --activate; then
    echo "    $plugin: installed + activated."
  elif wp plugin install "$plugin" --activate; then
    echo "    $plugin: installed + activated (retry)."
  else
    echo "    WARNING: $plugin failed to install (transient?) — continuing; re-run provision to retry."
  fi
done

echo "==> Configuring page cache (Cache Enabler) for a low-traffic marketing site..."
# Cache logged-out HTML to disk; WebP variants are served when EWWW has made them.
# WP_CACHE is defined in docker-compose so Cache Enabler can load advanced-cache.php.
wp eval '
  if ( class_exists( "Cache_Enabler" ) ) {
    update_option( "cache_enabler", array(
      "cache_expires"       => 1,    // hours
      "clear_site_cache_on_saved_post" => 1,
      "convert_image_urls_to_webp"     => 1,
      "minify_html"         => 1,
    ) );
  }
' || echo "    (Cache Enabler not active yet — settings will apply on next run.)"

echo "==> Configuring image optimization (EWWW) for local WebP generation..."
# Local-only optimization: no cloud API, auto-generate WebP, strip metadata.
# Also create EWWW's own DB table: activating EWWW via WP-CLI skips the table
# install, and without it every media upload floods the log with
# "Table 'wp_ewwwio_images' doesn't exist" errors during the media import.
wp eval '
  if ( function_exists( "ewww_image_optimizer_install_table" ) ) {
    ewww_image_optimizer_install_table();
  }
  if ( function_exists( "ewww_image_optimizer_set_option" ) ) {
    ewww_image_optimizer_set_option( "ewww_image_optimizer_webp", true );
    ewww_image_optimizer_set_option( "ewww_image_optimizer_metadata_remove", true );
    ewww_image_optimizer_set_option( "ewww_image_optimizer_jpg_level", 10 );
    ewww_image_optimizer_set_option( "ewww_image_optimizer_png_level", 10 );
    // CWV: resize oversized uploads on import + tune WebP/JPEG quality so the
    // hero LCP payload stays small. 1920px max comfortably covers full-bleed.
    ewww_image_optimizer_set_option( "ewww_image_optimizer_maxmediawidth", 1920 );
    ewww_image_optimizer_set_option( "ewww_image_optimizer_maxmediaheight", 1920 );
    ewww_image_optimizer_set_option( "ewww_image_optimizer_jpg_quality", 82 );
    ewww_image_optimizer_set_option( "ewww_image_optimizer_webp_quality", 75 );
    // NOTE on front-end WebP DELIVERY: EWWWs HTML rewrite (webp_for_cdn /
    // picture_webp) does NOT engage on this nginx + PHP-FPM + Cache-Enabler stack
    // (its output-buffer parser never fires here), so we do NOT enable it — it
    // would be misleading dead config. WebP GENERATION still runs (the .webp
    // siblings exist for the theme hero picture/preload and any future CDN layer).
    // The hero LCP is served as WebP by the theme (home-hero.php <picture> +
    // preload). Content/featured-image WebP delivery, if wanted, is a theme-side
    // the_post_thumbnail/wp_get_attachment_image <picture> filter (theme lane).
  }
' || echo "    (EWWW not active yet — settings will apply on next run.)"

echo "==> Activating the buckleup-core plugin (registers CPTs/meta the seeds write to)..."
# CRITICAL: without this, a clean provision registers no CPTs, so every CPT seed
# below no-ops and the site comes up empty. Must run BEFORE the seed steps.
wp plugin activate buckleup-core || echo "    WARNING: plugin 'buckleup-core' not found — CPT seeds will be skipped."

echo "==> Activating the BuckleUp theme..."
wp theme activate buckleup || echo "    WARNING: theme 'buckleup' not found — build it first."

echo "==> Roles & capabilities (instructor + student)..."
wp_eval /scripts/wp/roles.php

echo "==> Demo users (admin already exists; add instructors + demo student)..."
wp_eval /scripts/wp/users.php

echo "==> Seeding catalog (services + home pricing packages)..."
wp_eval /scripts/wp/seed-catalog.php

echo "==> Seeding content (testimonials, FAQ, instructors, locations, pages)..."
wp_eval /scripts/wp/seed-content.php

echo "==> Importing the 5 blog posts (slugs/categories/tags/HTML preserved)..."
wp_eval /scripts/wp/import-posts.php

echo "==> Staging + publishing the 10 SEO articles (content/blog-seo/)..."
# Stage the writer's articles + manifest into the bind-mounted wp-data dir so
# seed-blog-seo.php (running in the wpcli container) can read them.
SEO_SRC="content/blog-seo"
SEO_STAGE="wp-data/blog-seo"
if [ -f "$SEO_SRC/manifest.json" ]; then
  mkdir -p "$SEO_STAGE"
  cp -f "$SEO_SRC/manifest.json" "$SEO_STAGE/manifest.json"
  cp -f "$SEO_SRC"/*.html "$SEO_STAGE"/ 2>/dev/null || true
  echo "    staged $(ls -1 "$SEO_STAGE"/*.html 2>/dev/null | wc -l | tr -d ' ') article(s) + manifest into $SEO_STAGE"
  wp_eval /scripts/wp/seed-blog-seo.php
else
  echo "    no $SEO_SRC/manifest.json — skipping SEO blog import (writer hasn't delivered yet)."
fi

echo "==> Migrating live Hall-of-Fame graduate photos (client-approved)..."
# Fetch the live public graduates feed + download each image host-side (the wpcli
# container has no reliable outbound internet), into the bind-mounted wp-data dir;
# seed-graduates.php then side-loads them + creates the graduate CPT entries.
# Override the feed with GRADUATES_API_URL if needed; safe to skip if offline.
GRADUATES_API_URL="${GRADUATES_API_URL:-https://www.buckleupdriving.ca/api/graduates}"
GRAD_STAGE="wp-data/graduates"
mkdir -p "$GRAD_STAGE"
if curl -fsS -m 30 "$GRADUATES_API_URL" -o "$GRAD_STAGE/manifest.json" 2>/dev/null \
   && [ -s "$GRAD_STAGE/manifest.json" ]; then
  # Download each active image by its URL into the staging dir (basename = key).
  python3 - "$GRAD_STAGE" <<'PY' || echo "    (image download step had issues — continuing)"
import json, os, sys, urllib.request
stage = sys.argv[1]
rows = json.load(open(os.path.join(stage, "manifest.json")))
ok = 0
for r in rows:
    if not r.get("isActive", True):
        continue
    url = r.get("url") or ""
    if not url:
        continue
    bn = os.path.basename(urllib.parse.urlparse(url).path)
    dest = os.path.join(stage, bn)
    if os.path.exists(dest) and os.path.getsize(dest) > 0:
        ok += 1; continue
    try:
        urllib.request.urlretrieve(url, dest)
        ok += 1
    except Exception as e:
        print(f"    download failed {bn}: {e}")
print(f"    staged {ok} graduate image(s) into {stage}")
PY
  wp_eval /scripts/wp/seed-graduates.php
elif [ -s "$GRAD_STAGE/manifest.json" ] && ls "$GRAD_STAGE"/*.jpeg >/dev/null 2>&1; then
  # Live feed unreachable, but a previous run already staged the manifest + images
  # into wp-data/graduates (a host bind mount that survives `down -v`). Re-import
  # from that so a clean make reset still yields the full graduates while offline.
  echo "    live feed unreachable — re-importing from the persisted staging dir ($(ls -1 "$GRAD_STAGE"/*.jpeg 2>/dev/null | wc -l | tr -d ' ') image(s))."
  wp_eval /scripts/wp/seed-graduates.php
else
  echo "    live graduates feed unreachable ($GRADUATES_API_URL) and no staged copy — skipping graduate migration."
  echo "    (Drop a manifest.json + images into $GRAD_STAGE manually to seed offline.)"
fi

echo "==> Staging source brand media into wp-data/media-import/ ..."
# Copy the brand assets from the source Next.js public/ into the bind-mounted
# import dir so import-media.php (which runs in the wpcli container) can read them.
# Override the source path with SOURCE_PUBLIC_DIR if your checkout lives elsewhere.
SOURCE_PUBLIC_DIR="${SOURCE_PUBLIC_DIR:-/Users/esfandiyar/Projects/Buckleup/public}"
STAGE_DIR="wp-data/media-import"
if [ -d "$SOURCE_PUBLIC_DIR" ]; then
  mkdir -p "$STAGE_DIR"
  for f in logo.png logo-dark.png image2.png hero_card_image.png farhad-instructor.jpg \
           owner_withcar.png icon-16x16.png icon-32x32.png icon-192x192.png \
           icon-512x512.png apple-touch-icon.png; do
    # owner_withcar.png lives under public/images/ in the source layout.
    if [ -f "$SOURCE_PUBLIC_DIR/$f" ]; then
      cp -f "$SOURCE_PUBLIC_DIR/$f" "$STAGE_DIR/$f"
    elif [ -f "$SOURCE_PUBLIC_DIR/images/$f" ]; then
      cp -f "$SOURCE_PUBLIC_DIR/images/$f" "$STAGE_DIR/$f"
    fi
  done
  echo "    staged $(ls -1 "$STAGE_DIR" 2>/dev/null | wc -l | tr -d ' ') file(s) into $STAGE_DIR"
else
  echo "    SOURCE_PUBLIC_DIR not found ($SOURCE_PUBLIC_DIR) — skipping media staging."
  echo "    (Drop brand assets into $STAGE_DIR manually, or set SOURCE_PUBLIC_DIR.)"
fi

echo "==> Generating + staging the 5 per-category blog cards..."
# Generate the on-brand per-category featured-image cards (idempotent — overwrites
# the PNGs) if rsvg-convert is available, then stage them into the media-import
# dir so import-media.php picks them up. If the cards already exist (committed) or
# rsvg-convert is missing, we still stage whatever is in content/blog-cards/.
mkdir -p "$STAGE_DIR"
if command -v rsvg-convert >/dev/null 2>&1; then
  bash scripts/gen-blog-cards.sh || echo "    (card generation had issues — staging any existing cards)"
fi
if ls content/blog-cards/blog-card-*.png >/dev/null 2>&1; then
  cp -f content/blog-cards/blog-card-*.png "$STAGE_DIR"/
  echo "    staged $(ls -1 content/blog-cards/blog-card-*.png | wc -l | tr -d ' ') blog card(s) into $STAGE_DIR"
else
  echo "    no blog cards in content/blog-cards/ — posts will fall back to a brand photo."
fi

echo "==> Importing media into the Library (logos, hero, icons, blog cards) + Site Icon..."
wp_eval /scripts/wp/import-media.php

echo "==> Assigning on-brand default featured images to posts (per category)..."
# Runs AFTER media import (needs the brand images in the Library) + after the post
# imports. Non-destructive: only fills posts with an empty _thumbnail_id.
wp_eval /scripts/wp/assign-post-images.php

echo "==> Optimizing images + generating WebP (EWWW bulk, via the web container)..."
# CWV/LCP: generate WebP siblings + compress every Media Library image so the
# theme's <picture>/preload serves next-gen WebP. EWWW's local binaries (cwebp,
# optipng, jpegtran, …) live in the WEB image, NOT the wpcli runner — so this MUST
# run via `docker compose exec wordpress`, not the wp() wpcli wrapper. Media is
# imported through wpcli (no binaries), so auto-on-upload doesn't fire; this is the
# explicit pass that makes the optimized WebP part of the reproducible build.
if docker compose exec -T -u www-data wordpress sh -c 'command -v cwebp >/dev/null'; then
  # --force so PNG cards + every size variant get a .webp sibling even if EWWW's
  # table already marks them "done" (a plain run can skip PNG->WebP and leave the
  # blog cards without webp). Safe + idempotent: re-encodes to the same output.
  docker compose exec -T -u www-data wordpress wp ewwwio optimize media --force --noprompt --path=/var/www/html \
    || echo "    (EWWW bulk-optimize reported an issue — continuing; re-run to retry)"
else
  echo "    cwebp not in the web container — skipping WebP generation."
  echo "    (Rebuild the image: 'docker compose build wordpress' adds the EWWW binaries.)"
fi

echo "==> Configuring SEO (Rank Math titles/sitemap/robots, per-page meta, Redirection parity)..."
# Runs AFTER content + media so per-page meta lands on the real pages/CPT posts
# (resolved by their canonical URLs) and the PWA manifest/logo resolve to the
# imported attachments. Idempotent + re-runnable. The JSON-LD + geo-meta + PWA
# manifest layer itself lives in the 10-buckleup-seo.php / 11-buckleup-pwa.php
# mu-plugins (always-on); this step only writes the Rank Math + Redirection options.
#
# seo-config.php self-verifies and exits non-zero if Rank Math didn't engage
# (wizard flag / titles missing). A fresh-activation race can leave Rank Math not
# fully loaded on the first pass, so run it and RETRY ONCE on failure — this is
# the reproducibility fix for the "Rank Math dormant after make reset" regression.
# The end-of-provision verify gate is the final backstop either way.
if ! wp eval-file /scripts/wp/seo-config.php; then
  echo "    SEO config first pass failed (Rank Math may not have been ready) — retrying once..."
  wp eval-file /scripts/wp/seo-config.php || echo "    WARNING: SEO config still failing — the verify gate below will catch it."
fi

echo "==> Flushing rewrite + page cache + Rank Math sitemap cache..."
wp rewrite flush --hard
wp cache flush || true
wp cache-enabler clear 2>/dev/null || true
# Rank Math caches its XML sitemap; after a scripted bulk import the cache is stale
# and omits the new posts. Invalidate it so the sitemap reflects all content.
wp eval '
  if ( class_exists( "RankMath\\Sitemap\\Cache" ) && method_exists( "RankMath\\Sitemap\\Cache", "invalidate_storage" ) ) {
    RankMath\Sitemap\Cache::invalidate_storage();
  } elseif ( function_exists( "rank_math" ) ) {
    do_action( "rank_math/sitemap/invalidate_object_type", "post" );
  }
' 2>/dev/null || true

echo "==> Verifying seeded content counts (fails the run on mismatch)..."
# The wp_eval helper deliberately swallows eval-file errors so a transient issue
# can't abort provisioning — but that also masks a silently-empty seed (e.g. if
# buckleup-core wasn't active). This gate is the backstop: it asserts the exact
# expected counts and exits non-zero so a broken provision can't pass as "ready".
# Exact-count types (fixed seeds): service 3, package 4, faq 14, testimonial 5,
# instructor 2, location 5, post 15. Graduates are migrated from the LIVE feed
# (count can drift), so they're asserted as non-empty (>=1) only when the live
# feed was staged this run — an offline provision won't false-fail on them.
grad_min=0
if [ -s "wp-data/graduates/manifest.json" ]; then grad_min=1; fi
# $grad_min is inlined into the PHP below (closing+reopening the single-quote so
# the shell substitutes it) — host env vars don't cross into the wpcli container.
verify_out="$(wp eval '
  $expect = array(
    "service" => 3, "package" => 4, "faq" => 14, "testimonial" => 5,
    "instructor" => 2, "location" => 5, "post" => 15,
  );
  $fail = 0;
  foreach ( $expect as $type => $want ) {
    $counts = wp_count_posts( $type );
    $got = $counts ? (int) $counts->publish : 0;
    if ( $got !== $want ) {
      echo "MISMATCH {$type}: got {$got}, want {$want}\n";
      $fail = 1;
    } else {
      echo "ok {$type}: {$got}\n";
    }
  }
  $grad_min = (int) '"$grad_min"';
  $grad = (int) wp_count_posts( "graduate" )->publish;
  if ( $grad_min > 0 && $grad < $grad_min ) {
    echo "MISMATCH graduate: got {$grad}, want >= {$grad_min}\n";
    $fail = 1;
  } else {
    echo "ok graduate: {$grad}" . ( $grad_min ? " (>= {$grad_min})" : " (live feed not staged this run)" ) . "\n";
  }

  // --- MEDIA backstop: a reset must re-import ALL media, not land media-light. ---
  // (Concurrent resets / a missing SOURCE_PUBLIC_DIR / an unreachable graduates
  // feed could otherwise leave the instance with empty brand URLs + no thumbnails
  // yet still pass the content gate. Assert the media is fully populated.)
  $att = (int) wp_count_posts( "attachment" )->inherit;
  if ( $att < 16 ) { echo "MISMATCH attachments: got {$att}, want >= 16 (brand media + blog cards + graduates)\n"; $fail = 1; }
  else { echo "ok attachments: {$att}\n"; }
  // Every published post must have a featured image (15/15).
  $no_thumb = 0;
  foreach ( get_posts( array( "post_type" => "post", "posts_per_page" => -1, "post_status" => "publish", "fields" => "ids" ) ) as $pid ) {
    if ( ! get_post_thumbnail_id( $pid ) ) { $no_thumb++; }
  }
  if ( $no_thumb > 0 ) { echo "MISMATCH post thumbnails: {$no_thumb} post(s) missing a featured image\n"; $fail = 1; }
  else { echo "ok post thumbnails: 15/15\n"; }
  // The hero brand image must be in the Library AND resolve to an uploads URL.
  $hero = get_posts( array( "post_type" => "attachment", "posts_per_page" => 1, "fields" => "ids", "meta_key" => "_bu_source_file", "meta_value" => "image2.png", "no_found_rows" => true ) );
  $hero_url = $hero ? (string) wp_get_attachment_url( $hero[0] ) : "";
  if ( "" === $hero_url || false === strpos( $hero_url, "/uploads/" ) ) { echo "MISMATCH brand media: image2 hero not imported / URL empty\n"; $fail = 1; }
  else { echo "ok brand media: image2 hero resolves\n"; }

  // --- SEO backstop: Rank Math per-page meta must be ACTIVE + reproducible. ---
  // seo-config.php runs above with a retry-once, but its own WP_CLI::error only
  // aborts that sub-shell — so this gate is the authoritative check that fails a
  // make reset if Rank Math still went dormant (empty titles / wizard flag lost /
  // per-page meta missing). Mirrors the HIGH regression where every page fell
  // back to the WP-default <title>.
  $rm_titles = (array) get_option( "rank_math_titles", array() );
  $rm_wizard = (bool) get_option( "rank_math_wizard_completed" );
  if ( ! $rm_wizard ) { echo "MISMATCH rank_math: wizard_completed not set (frontend head dormant)\n"; $fail = 1; }
  elseif ( empty( $rm_titles["homepage_title"] ) || empty( $rm_titles["pt_page_title"] ) ) {
    echo "MISMATCH rank_math: titles option empty (run seo-config.php)\n"; $fail = 1;
  } else { echo "ok rank_math: wizard complete, titles populated (" . count( $rm_titles ) . " keys)\n"; }
  // A representative inner page must carry its configured per-page title meta.
  $about = get_page_by_path( "about" );
  $about_title = $about ? (string) get_post_meta( $about->ID, "rank_math_title", true ) : "";
  if ( "" === $about_title ) { echo "MISMATCH rank_math: /about has no rank_math_title meta (per-page SEO dead)\n"; $fail = 1; }
  else { echo "ok rank_math: /about per-page title present\n"; }

  echo $fail ? "VERIFY_FAIL\n" : "VERIFY_OK\n";
' 2>&1)"
echo "$verify_out" | sed 's/^/    /'
if ! printf '%s' "$verify_out" | grep -q "VERIFY_OK"; then
  echo ""
  echo "############################################################"
  echo " PROVISION FAILED: seeded content counts did not match."
  echo " The site is NOT fully seeded — see the MISMATCH lines above."
  echo " (Most common cause: buckleup-core plugin not active before seeds.)"
  echo "############################################################"
  exit 1
fi

echo ""
echo "============================================================"
echo " BuckleUp WordPress is ready."
echo "   Site   : $WP_HOME"
echo "   Admin  : $WP_HOME/wp-admin  ($WP_ADMIN_USER / $WP_ADMIN_PASSWORD)"
echo "   Adminer: http://localhost:${ADMINER_PORT:-8081}"
echo "   Mailpit: http://localhost:${MAILPIT_UI_PORT:-8025}"
echo "============================================================"

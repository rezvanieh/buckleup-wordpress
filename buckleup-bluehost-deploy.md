---
name: buckleup-bluehost-deploy
description: Host = Bluehost (Business plan); deployment package + the 5 Docker→shared-hosting deltas
metadata: 
  node_type: memory
  type: project
  originSessionId: b4add7ce-e13d-471c-ba04-69c20105756a
---

Client is hosting the BuckleUp WP site on **Bluehost shared hosting** (recommended
**Business** plan ~$6.99→$13.99/mo; Starter also works but tighter). Confirmed
compatible (2026-06-04, adversarially verified workflow): open cPanel hosting
(arbitrary custom themes/plugins OK), PHP 8.3 available, DB user gets CREATE/ALTER
on its own DB (so the `bu_*` dbDelta tables work), `.htaccess` honored, SSH+WP-CLI
+ real cron available. Target domain stays `https://www.buckleupdriving.ca` (baked into the SEO layer).

**STATUS: LIVE IN PRODUCTION at https://www.buckleupdriving.ca (2026-06-04).** Cutover done:
DNS A records changed at Namespro (apex A→50.6.245.84, www CNAME→yuw.aey.mybluehost.me; nameservers
kept at Namespro so Zoho email untouched), prod DB re-imported (www URLs), WP_HOME=https://www,
AutoSSL cert issued (shared SAN cert covers apex+www), prod .htaccess live (force-https + apex→www +
HSTS max-age=31536000 + the security denies), demo `admin` password reset to a strong value (NOT stored
here), importer deleted. Verified: all pages 200/HTTPS, redirects 301, headers on 200+404, xmlrpc/
wp-config/uploads-php 403, user-enum 404, console gating 302, WebP served. Staging URL was
https://yuw.aey.mybluehost.me (now redirects to the live domain). **Zoho SMTP DONE + confirmed
end-to-end** (real contact-form email received): creds in wp-config `BUCKLEUP_SMTP_*` (smtp.zoho.com:465
SSL, user info@buckleupdriving.ca), read by committable mu-plugin `20-buckleup-smtp.php` (no-op without
the constants so dev keeps Mailpit; forces From=info@ per Zoho, keeps Reply-To). Post-launch fixes
applied to prod + repo source: (1) `buckleup_admin` role granted blog caps (edit_posts/upload_files/etc,
NOT edit_pages) so the console Blogs→wp-admin/edit.php works — roles.php + a BUCKLEUP_APP_ROLES_VERSION
gate; (2) favicon — migration imported the mislabeled WIDE public/icon-512x512.png (512×100 logo strip);
fixed by setting the square src/app/icon.png (512×512) as WP Site Icon + designed favicon.ico at docroot.
Bluehost's own mu-plugins present: endurance-page-cache.php (Newfold page cache — why no Cache Enabler)
+ sso.php. REMAINING (optional): commit the repo fixes (roles.php, 20-buckleup-smtp.php, square-favicon
provisioning, package-bluehost.sh mu list), final cleanup (server /home2/yuwaeymy/bu-deploy archives +
orphan g40_ tables + local /tmp secrets), remove `*@buckleup.test` demo users. SSH key
`~/.ssh/buckleup_bluehost` kept for future maintenance (revocable in cPanel).
Server: SSH host `50.6.245.84`, user `yuwaeymy`, home `/home2/yuwaeymy`, docroot `public_html`,
DB `yuwaeymy_WPTF7`. **Shell access is DISABLED** (Bluehost gate, "contact support") → no WP-CLI;
the whole deploy was done over **SFTP** (key `~/.ssh/buckleup_bluehost`, public key added in cPanel)
+ a token-protected `bu-deploy.php` (steps extract/import/finalize/cleanup) triggered over HTTPS.
Bluehost runs **WordPress 7.0** (newer than our 6.9 export → ran routine forward DB upgrade via
`/wp-admin/upgrade.php?step=1`). Original random table prefix `g40_` → switched wp-config to `wp_`
to match our dump (orphan g40_ tables remain, drop later). Bluehost ships **Redis object cache** +
**Newfold/EPC page cache** (so NO Cache Enabler; bumped WP_REDIS_PREFIX to flush stale keys). Import
gotcha: Bluehost MySQL strict mode rejected Action Scheduler's `'0000-00-00'` default → importer runs
`SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'` + `mysqli_report(MYSQLI_REPORT_OFF)`. Smoke test green:
all pages 200, console gating 302→/login, WebP delivered (image/webp 200), xmlrpc/wp-config/users
locked, theme CSS + JSON-LD + PWA manifest OK. Web-exposed importer deleted after staging.

**Cutover (pending client go + domain added to Bluehost + DNS A→50.6.245.84):** re-upload `bu-deploy.php`,
import FINAL dump `bu-deploy/buckleup-prod.sql` (www URLs, already on server), upload final wp-config
(`/tmp/bh-wp-config.php`: WP_HOME=www.buckleupdriving.ca), enable https/www canonical redirect + HSTS in
root .htaccess, run finalize, AutoSSL the domain, set up SMTP, change dev admin creds, delete importer.
Staging review is VISUAL ONLY (cutover re-imports → staging edits don't persist). Deploy artifacts staged
at `/home2/yuwaeymy/bu-deploy/` (outside webroot) + local `/tmp/bu-deploy/`.

**Deployment package** lives in the repo: `deploy/bluehost/` (README runbook,
plugins.md, smtp-and-dns.md, preflight-checklist.md, htaccess-root/uploads/
wp-includes, wp-config-snippet.php) + `scripts/package-bluehost.sh` (builds
`dist/bluehost/` from the running local stack: theme/plugin zips, uploads.tar.gz
with .webp, DB export already search-replaced localhost→prod, prod-safe mu-plugins).

**The 5 Docker→Bluehost deltas (the non-obvious ones):**
1. Apache, NOT nginx → `docker/nginx/default.conf` hardening hand-ported to
   `.htaccess` (hardening OUTSIDE `# BEGIN WordPress`; `Header always set` inside
   `<IfModule mod_headers.c>`; `Require all denied` FilesMatch — NEVER
   `php_flag engine off`; keep the `.well-known` exception).
2. `exec()`/shell_exec DISABLED on ALL shared tiers → EWWW local-binary WebP can't
   run on the host. We PRE-GENERATE `.webp` locally and upload them; the theme
   serves them via PHP `<picture>` ([[ewww-webp-needs-binaries-in-web-image]]).
   **Do NOT add an Accept-header .htaccess WebP rewrite** — it double-serves and
   breaks the non-WebP fallback (sibling naming is `<file>.webp` appended).
3. No `WORDPRESS_CONFIG_EXTRA` off-Docker → wp-config constants added by hand; and
   **omit** `WP_CACHE`/`WPMS_ON` in prod.
4. Bluehost has its own page cache → use it; do NOT install Cache Enabler
   (double-cache). Bluehost Redis object cache is fine to leave on.
5. Dev Mailpit mu-plugin (`00-mailpit-smtp.php`) is NOT deployed → install a real
   SMTP plugin (FluentSMTP + Brevo/SES) + SPF/DKIM/DMARC.

Other gotchas: pin `$table_prefix='wp_'` to match the dump (Bluehost installer can
randomize it → empty duplicate bu_ tables); delete bundled **Yoast** (conflicts
with Rank Math) + Jetpack/CreativeMail; phpMyAdmin import cap ~50MB (our DB is
tiny); change dev admin creds (admin/admin123) post-import.

See also [[buckleup-wp-project]].

# BuckleUp WordPress — Go‑Live Checklist (production deployment)

> v1 is a complete, QA‑validated **marketing site** running in Docker locally. This file is the bridge
> from that working local build to publishing on **https://www.buckleupdriving.ca**. None of the items
> below are needed for the local build to work; they are the production‑hardening + cutover steps that
> were deliberately deferred out of v1 scope (and that the independent review flagged for go‑live).
>
> Booking, payments, and the Student/Instructor/Admin app portals remain **Phase 2** (not in this build).

## 0. Pre‑req: source is committed ✅ (gate #1)
- The full build is version‑controlled in `agenticsoultions-madeira/buckleup-wordpress` (theme, `buckleup-core` plugin, mu‑plugins, seed/provision scripts, generated blog cards, docs). Build artifacts (`build/`, `node_modules`, `.env`, `wp-data/` staging) stay git‑ignored.
- A fresh clone + `cp .env.example .env && make up && make provision` reproduces the full seeded site (verification gate prints `VERIFY_OK`). Confirm this on a clean checkout before deploying.

## 1. Production environment / Docker
- [ ] Add `docker-compose.prod.yml` overlay (or a managed‑WP equivalent) that: **drops Adminer + Mailpit + the assets watcher**, sets `WP_ENVIRONMENT_TYPE=production`, `WP_DEBUG=false`, `WP_DEBUG_LOG=false`, `SCRIPT_DEBUG=false`, `opcache.validate_timestamps=0` (+ an opcache reset on deploy), and **digest‑pins** all images.
- [ ] Build theme assets into the release image (`make build-assets`) — `functions.php` enqueues from the Vite manifest, which is git‑ignored, so the prod image MUST contain a fresh build.
- [ ] Run the WP container as non‑root where the host allows; keep the least‑privilege DB user (already in place).

## 2. Domain, TLS, canonical host
- [ ] Point `https://www.buckleupdriving.ca` at the host; issue TLS (Let's Encrypt / managed cert).
- [ ] **301 apex → www** and **http → https** at the edge/server (the app currently has no TLS/redirect; dev is HTTP‑only).
- [ ] Set `WP_HOME`/`WP_SITEURL` to `https://www.buckleupdriving.ca`, then **regenerate the Rank Math sitemap** (it currently emits localhost because siteurl is local) and submit `sitemap_index.xml` to Google Search Console. (Canonicals/schema already hardcode the www origin.)
- [ ] Ship security response headers at the edge: **HSTS** (`max-age>=31536000; includeSubDomains; preload`), plus the X‑Frame‑Options / X‑Content‑Type‑Options / Referrer‑Policy already set in the dev nginx (carry them into the prod vhost). Consider a report‑only CSP allow‑listing Google Maps + fonts.

## 3. Email deliverability (contact form)
- [ ] Replace the dev Mailpit transport with a real provider (Resend / FluentSMTP / SES, etc.) so the contact form (`info@buckleupdriving.ca`) actually delivers in prod.
- [ ] Publish **SPF + DKIM + DMARC** for `buckleupdriving.ca` and send from a verified domain (NOT a sandbox sender).
- [ ] Verify a real submission lands in the business inbox; confirm the honeypot + rate‑limit (3/10min) behave.

## 4. Security hardening (carry the dev hardening into prod + add the deploy‑only pieces)
Already done in the build: DISALLOW_FILE_EDIT, Safe SVG, least‑priv DB user, no committed secrets, **XML‑RPC blocked (403)**, **user/author enumeration locked (404)**, generator removed, nginx deny on `*.log` / `readme.html` / `uploads/*.php` + the deny rule ordered above the PHP handler.
- [ ] **Replace the dev admin credentials**: a strong unique password + a **non‑`admin` username** (provisioning generates a random dev password and prints it once — never carry a dev login to prod).
- [ ] Add a firewall + login throttling (Wordfence free or equivalent) and consider 2FA for the admin.
- [ ] Belt‑and‑suspenders: also `deny` `/xmlrpc.php` at the prod nginx (app already 403s it).
- [ ] Confirm `wp-config.php` salts are fresh/unique in prod and file permissions are tight.

## 5. Backups & updates
- [ ] Scheduled **backups** (DB + uploads) — UpdraftPlus free or host‑level `wp db export`/`mysqldump` to offsite storage; **run a restore drill**.
- [ ] Define an **update cadence**: enable minor/security core auto‑updates (or a scheduled manual review) and apply the pending plugin updates (akismet/wpforms/etc.); auto‑updates are currently off.
- [ ] Set `DISABLE_WP_CRON=true` + a real system cron hitting `wp cron event run --due-now` (pseudo‑cron is unreliable on a low‑traffic site).

## 6. Deploy pipeline / data
- [ ] Document + automate: build assets → deploy image → run `provision.sh` against the prod DB **or** export the seeded DB (`wp db export`) + `wp-content/uploads` and import to prod → flush rewrite + sitemap + page cache.
- [ ] CI is verification‑only today (php‑lint, theme‑build, compose‑validate) — add a deploy stage gated on those + the QA suite.

## 7. Content follow‑ups (optional polish, not blockers)
- [ ] Dedicated **1200×630 social/OG image** for the homepage (currently reuses the wide logo — parity with the live site, but a proper card renders better on social).
- [ ] Replace the generated per‑category blog **featured cards** with real photos (lessons/vehicles/instructors) when available — the assignment is non‑destructive, so client‑chosen images won't be overwritten.
- [ ] Curate the migrated **graduate photos** (11 pulled from the live feed) if any should be added/removed.
- [ ] Optional: the FAQ "book online" answer + nav reflect the live copy; revisit when the Phase‑2 booking engine ships.

## 8. Pre‑DNS smoke test (run on the prod host BEFORE cutover)
- [ ] apex → www 301 + https enforced; all key URLs 200 (home, /about, /services, /instructors, /contact, 5 /locations, /blog + a post, /resources + ICBC article).
- [ ] Contact form delivers a real email from the verified domain (SPF/DKIM pass).
- [ ] `sitemap_index.xml` reachable + submitted to GSC; canonicals resolve to the www host; no localhost leaks.
- [ ] Security probes: `/xmlrpc.php` blocked, `/wp-json/wp/v2/users` 404, no generator/version leak, sensitive paths 403.
- [ ] Lighthouse CWV within budget on the prod host.
- [ ] Backup taken + restore verified.

Then cut DNS over to the new host.

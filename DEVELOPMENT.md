# BuckleUp WordPress — Development Guide

## Stack (docker-compose services)

| Service     | Image / build                  | Port (host) | Purpose                                            |
| ----------- | ------------------------------ | ----------- | -------------------------------------------------- |
| `db`        | `mariadb:11.4`                 | —           | Database (MySQL-compatible LTS)                    |
| `wordpress` | custom (`wordpress:6.9-php8.3-fpm` + intl/zip) | — | PHP-FPM running WordPress |
| `nginx`     | `nginx:1.27-alpine`            | **8080**    | Web entrypoint -> PHP-FPM                          |
| `adminer`   | `adminer:5`                    | **8081**    | DB GUI                                             |
| `mailpit`   | `axllent/mailpit`              | **8025**    | Catches all outgoing mail (SMTP :1025 internal)    |
| `wpcli`     | `wordpress:cli-php8.3`         | —           | One-shot WP-CLI runner (profile `cli`)             |
| `assets`    | `node:22-alpine`               | —           | Theme asset builder (profile `assets`)             |

Only `nginx`, `adminer`, `mailpit` publish ports. `wpcli` and `assets` are profiled
so `docker compose up` doesn't start them; they're invoked on demand.

## Why these choices

- **MariaDB 11.4 LTS** over MySQL 8 — lighter, fully WP-compatible, has a `healthcheck.sh`
  so the provisioner can wait on real DB readiness.
- **nginx + PHP-FPM** over the apache `wordpress` image — closer to the production target
  and lets us harden config (block `wp-config.php`, PHP in uploads, etc.).
- **Mailpit** over MailHog — MailHog is unmaintained since 2020; Mailpit is a drop-in
  replacement (same ports), actively maintained, faster, with search.
- **Adminer 5** over phpMyAdmin — single-file, far lighter, enough for dev DB work.
- **Lean caching (no Redis)** — v1 is a low-traffic marketing site, so we don't run an
  object-cache tier. Cache Enabler provides a static-HTML page cache and EWWW Image
  Optimizer does local WebP/optimization; both are free and need no API key.
- **Vite + Tailwind v4 (CSS-first)** — matches the source app exactly (no `tailwind.config.js`;
  tokens live in `src/css/app.css` via `@theme inline`). Guarantees the same arbitrary-value
  class output the design system depends on.

## Asset pipeline

The theme builds with Vite into `wp-content/themes/buckleup/build/` and writes a
`build/.vite/manifest.json`. `functions.php` reads the manifest and enqueues the hashed
CSS/JS. Source lives in `src/css/app.css` and `src/js/main.js`.

```bash
make assets        # watch mode (rebuild on save)
make build-assets  # one-off production build
```

**Geist fonts:** download the variable `Geist-Variable.woff2` and
`GeistMono-Variable.woff2` from the official Geist release and drop them in
`wp-content/themes/buckleup/assets/fonts/`. They are referenced by `@font-face` in
`src/css/app.css`. (Do not rely on Google Fonts CDN — subsetting differs.)

## Provisioning (idempotent)

`make provision` runs `scripts/provision.sh`, which:
1. waits for the DB healthcheck,
2. `wp core install` (skips if already installed),
3. sets options + `/blog/%postname%/` permalinks (posts under `/blog/`; pages and the
   `/locations/` CPT keep their own bases),
4. installs/activates plugins (per-slug, with retry): Rank Math SEO, WPForms Lite,
   Redirection, Safe SVG, Cache Enabler (page cache), EWWW Image Optimizer (local WebP),
5. activates the **`buckleup-core` plugin** (registers the CPTs/meta the seeds write to —
   required before seeding) and the `buckleup` theme,
6. runs the seed eval-files in `scripts/wp/`:
   - `roles.php` — `student` + `instructor` roles + admin caps,
   - `users.php` — Sarah, Farhad, demo student (from `prisma/seed.ts`),
   - `seed-catalog.php` — 3 services + 4 home pricing packages,
   - `seed-content.php` — front/Blog/static pages, 5 testimonials, 14 FAQ, 2 instructors,
     5 location CPT posts, the ICBC resources article,
   - `import-posts.php` — the 5 original blog posts,
   - `seed-blog-seo.php` — the 10 SEO articles from `content/blog-seo/`,
   - `import-media.php` — brand assets into the Media Library + Site Icon,
   - `seo-config.php` — Rank Math titles/sitemap/per-page meta,
7. **verifies seeded counts** (services 3, packages 4, FAQ 14, testimonials 5,
   instructors 2, locations 5, posts 15) and exits non-zero on any mismatch.

Re-run any time; everything upserts by slug/email/filename. A clean `make reset`
reproduces the full seeded site from empty volumes.

### Importing brand media

The source images live in the Next.js repo at `…/Buckleup/public/`. Copy the needed
files into `wp-data/media-import/` before provisioning:

```bash
mkdir -p wp-data/media-import
cp /Users/esfandiyar/Projects/Buckleup/public/{logo.png,logo-dark.png,image2.png,\
hero_card_image.png,farhad-instructor.jpg,icon-192x192.png,icon-512x512.png,\
apple-touch-icon.png} wp-data/media-import/
```

## Plugin scaffold (`buckleup-core`)

`buckleup-core.php` lazy-includes files under `includes/` if they exist, so panel teams
add `cpt.php`, `tables.php`, `rest-booking.php`, `rest-portal.php`, `notifications.php`,
`schema.php` incrementally without breaking activation. Custom DB tables (availability,
bookings, transactions, notification queue/log) are created in `tables.php` on activation.

## Common commands

```bash
make wp CMD="plugin list"          # any WP-CLI command
make wp CMD="user list"
make shell                         # bash in the PHP container
make logs SVC=wordpress            # tail one service
make reset                         # nuke volumes + re-provision (DESTRUCTIVE)
```

## Secrets / env

`.env` (git-ignored) drives compose + provisioning. `.env.example` is the template.
Third-party keys (Stripe, Twilio, Resend) stay blank in dev — Mailpit catches mail and
Stripe runs in test mode when the booking team wires it. Real values go into GitHub
Actions secrets / the production host, never the repo.

## Branching

`main` is protected; work on feature branches and open PRs. CI runs PHP `-l` lint on all
theme/plugin/script PHP, builds theme assets, and validates the compose file.

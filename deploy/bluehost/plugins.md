# BuckleUp on Bluehost — Plugins: install, upload, remove

Three groups: (A) third-party plugins to install from wp.org, (B) our own
theme + plugins + mu-plugins to upload, (C) Bluehost-bundled plugins to remove.

---

## A. Third-party plugins to INSTALL (Plugins → Add New → search → Install)

| Plugin | Slug | Required? | Notes |
|---|---|---|---|
| **Rank Math SEO** | `seo-by-rank-math` | **Required** | Titles/meta/sitemap/robots. The seed wrote `rank_math_titles` + per-page meta into the DB import, so config rides along. The SEO mu-plugin suppresses Rank Math's JSON-LD and owns schema. |
| **Redirection** | `redirection` | **Required** | URL-parity 301s + 404 logging. Creates `wp_redirection_*` tables on activation (the DB import already contains the redirect rules). |
| **Safe SVG** | `safe-svg` | **Required** | Sanitizes the brand SVG uploads. |
| **An SMTP plugin** | `fluent-smtp` (recommended) or `wp-mail-smtp` | **Required** | wp_mail() on shared hosting falls back to PHP mail() and gets spam-filtered. See `smtp-and-dns.md`. |
| WPForms Lite | `wpforms-lite` | Optional | Installed in dev for parity, but the live **/contact** form uses buckleup-core's own admin-post → wp_mail handler, **not** WPForms. Skip unless you want WPForms for something else. |
| EWWW Image Optimizer | `ewww-image-optimizer` | **Optional / skip** | We ship pre-generated `.webp` siblings; the theme serves them via `<picture>`. EWWW's **local** optimizer CANNOT run on Bluehost (no cwebp/optipng binaries, exec() disabled). Only install it if you later want its cloud (Easy IO / Compress API) mode for *new* uploads. Not needed for launch. |
| Cache Enabler | `cache-enabler` | **DO NOT install** | Use Bluehost's built-in server cache instead. Two full-page caches collide. |

---

## B. Our code to UPLOAD

### Theme (Appearance → Themes → Add New → Upload Theme)
- `theme-buckleup.zip` → upload → **Activate**.
- Must contain the built `build/` dir (the packaging script runs the Vite build first). Without `build/.vite/manifest.json` the site falls back to unstyled CSS.

### Plugins (Plugins → Add New → Upload Plugin) — **activate in this order**
1. `buckleup-core.zip` → Install → **Activate first** (CPTs, settings, contact form, base hardening).
2. `buckleup-app.zip` → Install → **Activate second** (roles, `bu_*` tables via dbDelta on activation, REST routes, consoles).

> Activation order matters: core registers CPTs/roles scaffolding that the app build assumes. After activating both, the 5 `bu_*` tables exist (either from the DB import or rebuilt by the app's `plugins_loaded` safety net).

### Must-use plugins (SFTP/File Manager into `wp-content/mu-plugins/`)
- `10-buckleup-seo.php` — **deploy** (JSON-LD schema, canonicals, OG image, sitemap host).
- `11-buckleup-pwa.php` — **deploy** (manifest + PWA meta/icons).
- `00-mailpit-smtp.php` — **DO NOT deploy** (dev-only; would route all mail to a non-existent Mailpit and break outbound email).

---

## C. Bluehost-bundled plugins to REMOVE / disable (before launch)

| Bundled item | Action | Why |
|---|---|---|
| **Yoast SEO** | **Delete** | Hard conflict with Rank Math (two SEO plugins fight over titles/meta/schema). |
| **Jetpack** | Deactivate/Delete | Heavy; not needed. Keep only if you specifically want its stats. |
| **CreativeMail** | Delete | Email-marketing bloat. |
| **MOJO / Bluehost Marketplace** | Optional remove | Store upsell. |
| **Bluehost plugin / WonderBlocks** | Keep (optional) | Provides the cache + performance controls. If you keep it, **turn OFF its "Coming Soon"/under-construction page** before go-live. |
| **OptinMonster** (if present) | Delete | Growth upsell. |

After changes: **Settings → Permalinks → Save** (flushes rewrite rules) and purge the Bluehost cache.

# BuckleUp → Bluehost deployment

Step-by-step runbook for publishing the BuckleUp WordPress build on **Bluehost
shared hosting**. This is the Bluehost-specific companion to the host-agnostic
`GO-LIVE.md` at the repo root.

> **Target domain:** `https://www.buckleupdriving.ca` (baked into the SEO/schema
> layer). If the final domain differs, change it in three places only:
> `wp-config-snippet.php`, `htaccess-root.txt`, and the `SITE_URL` you pass to
> the packaging script — plus `BUCKLEUP_SEO_BASE_URL` in
> `docker/wordpress/mu-plugins/10-buckleup-seo.php`.

---

## 0. Compatibility verdict (why Bluehost works)

Bluehost runs the **full** build — custom block theme, both custom plugins with
their `bu_*` MySQL tables, the consoles, Rank Math, the mu-plugins. It's open
cPanel hosting (not a locked marketplace), PHP 8.3 is available, the DB user gets
`CREATE/ALTER` on its own database, `.htaccess` is honored, and SSH + WP-CLI +
real cron are available. The five things that differ from the Docker build — and
which this package handles — are:

1. **Server is Apache, not nginx** → nginx hardening ported to `.htaccess`.
2. **`exec()` is disabled** → EWWW can't generate WebP on the host; we **pre-generate
   `.webp` locally and upload** them (the theme serves them via `<picture>`).
3. **No `WORDPRESS_CONFIG_EXTRA`** → wp-config constants added by hand.
4. **Bluehost has its own page cache** → use it; **don't** install Cache Enabler.
5. **No dev Mailpit** → install a real SMTP plugin.

**Recommended plan:** **Business** (~$6.99/mo intro → $13.99 renewal, 50 GB, more
CPU/RAM headroom). The cheaper **Starter** also runs everything but is tighter on
memory/CPU once Rank Math + caching are active. (A VPS is only needed if you ever
want on-server image optimization — not required for this site.)

---

## 1. Buy + initial Bluehost setup

1. Buy the **Business** plan; let Bluehost install a fresh WordPress (or use an
   existing one). You can attach the real domain now or later.
2. cPanel / Bluehost Portal:
   - **PHP version → 8.3** (Portal PHP tile or cPanel MultiPHP Manager).
   - **Enable SSH** (Portal → *Files & Access* → Shell Access). Optional but makes
     DB import + search-replace far easier.
   - **Free SSL** (AutoSSL / Let's Encrypt) for the domain.
   - **Raise PHP limits** if needed (cPanel MultiPHP INI Editor): `memory_limit`
     256M is fine for serving; bump to 512M only if a large editor save complains.
3. **Remove the bundled plugins** that conflict — see `plugins.md` §C
   (especially **delete Yoast**, it fights Rank Math). Disable any
   "Coming Soon" page when you're ready to go public.

---

## 2. Build the upload bundle (locally)

With the local Docker stack running:

```bash
make up                       # if not already running
./scripts/package-bluehost.sh # -> dist/bluehost/
```

This builds the theme assets, generates the `.webp` siblings, exports the DB with
URLs rewritten to the production host, and packages everything. Override the
domain with `SITE_URL=… ./scripts/package-bluehost.sh`; skip the slow WebP pass
with `SKIP_WEBP=1`.

Output lands in `dist/bluehost/` (theme/plugin zips, `uploads.tar.gz`,
`database/buckleup-prod.sql`, the mu-plugins, `.htaccess` files, the wp-config
snippet, and these docs).

---

## 3. Upload the code

1. **Theme:** wp-admin → *Appearance → Themes → Add New → Upload* →
   `theme-buckleup.zip` → **Activate**.
2. **Plugins (order matters):** *Plugins → Add New → Upload* →
   `buckleup-core.zip` → Activate, then `buckleup-app.zip` → Activate.
   *(If a zip exceeds the wp-admin upload cap, SFTP the unzipped folders into
   `wp-content/plugins/` instead, then activate from the Plugins screen.)*
3. **mu-plugins:** SFTP/File Manager → put `10-buckleup-seo.php` and
   `11-buckleup-pwa.php` into `wp-content/mu-plugins/` (create the folder if
   absent). **Do not** upload `00-mailpit-smtp.php`.
4. **Third-party plugins:** install per `plugins.md` §A (Rank Math, Redirection,
   Safe SVG, an SMTP plugin). **Do not** install Cache Enabler.
5. **Uploads:** extract `uploads.tar.gz` into `wp-content/` so files land at
   `wp-content/uploads/...` **with their `.webp` siblings** (e.g.
   `image.png` **and** `image.png.webp`). Via SSH:
   `tar xzf uploads.tar.gz -C wp-content/`.

---

## 4. Import the database

The dump (`database/buckleup-prod.sql`) already has URLs rewritten to the prod
host and uses the **`wp_`** table prefix.

**With SSH/WP-CLI (preferred):**
```bash
wp db import database/buckleup-prod.sql
wp cache flush
```

**Without SSH (phpMyAdmin):**
- cPanel → *MySQL Databases* → note the DB name + user (full privileges).
- cPanel → *phpMyAdmin* → select that DB → **Import** → `buckleup-prod.sql`.
  *(The dump has no `CREATE DATABASE`/`USE` lines, so it imports into the selected
  DB. It's well under the ~50 MB phpMyAdmin cap.)*

> **Prefix must match.** Set `$table_prefix = 'wp_';` in wp-config.php (Bluehost's
> 1-click installer sometimes randomizes it). If config and dump disagree, the
> app rebuilds empty duplicate `bu_*` tables and the site looks blank.

---

## 5. Configure wp-config.php

Edit the site's `wp-config.php` (cPanel File Manager or SFTP) and add the
constants from `config/wp-config-snippet.php` (above `/* That's all, stop
editing! */`). Keep Bluehost's DB creds + salts. Key points: set the prod
`WP_HOME`/`WP_SITEURL`, `WP_ENVIRONMENT_TYPE=production`, `DISALLOW_FILE_EDIT`,
`FS_METHOD=direct`, `DISABLE_WP_CRON`, **no** `WP_CACHE`, **no** `WPMS_ON`.

---

## 6. Install the .htaccess files

- **Root** (`public_html/.htaccess` or the domain docroot): merge
  `htaccess/root.htaccess`. The BuckleUp hardening sits **above** the
  `# BEGIN WordPress` block on purpose — keep it there.
- **`wp-content/uploads/.htaccess`** ← `htaccess/uploads.htaccess`.
- **`wp-includes/.htaccess`** ← `htaccess/wp-includes.htaccess`.

Then **Settings → Permalinks → Save** to (re)generate WP's rewrite block.

---

## 7. Email, caching, cron

- **SMTP:** follow `smtp-and-dns.md` (FluentSMTP + Brevo/Resend/SES + SPF/DKIM/
  DMARC). The contact form and console emails depend on this.
- **Caching:** turn ON Bluehost's built-in page cache (Bluehost plugin /
  Performance). Cache Enabler is **not** installed. Bluehost's Redis object cache
  is fine to leave on.
- **Cron:** cPanel → *Cron Jobs* → every 5 min:
  ```
  cd /home/USER/public_html; /usr/local/bin/php -q wp-cron.php >/dev/null 2>&1
  ```
  (Pairs with `DISABLE_WP_CRON` in wp-config.)

---

## 8. WebP — confirm, don't re-add

WebP is already handled: the theme wraps content/featured images in
`<picture><source type="image/webp">` pointing at the uploaded `.webp` siblings.
**Do not** add an Accept-header WebP rewrite to `.htaccess` — it would double-serve
and break the fallback. Just confirm in DevTools that images load as `webp`, and
that a spot-checked `wp-content/uploads/.../<file>.png.webp` returns 200.

---

## 9. Security + go-live hardening

- **Change the dev admin login** (`admin/admin123` is dev-only): create a new
  admin with a non-`admin` username + strong password, then delete the old one.
- Consider **Wordfence (free)** for login throttling + firewall, and 2FA.
- Confirm `/xmlrpc.php` → 403 and `wp-json/wp/v2/users` → 404 (the plugin + the
  `.htaccess` both cover these).
- Take a **backup** (UpdraftPlus or cPanel) and verify a restore.

---

## 10. Pre-DNS smoke test → cut over

Run **every** item in `preflight-checklist.md` against the temporary Bluehost URL
(or a `hosts`-file override) **before** changing DNS. When all green:

- Point `buckleupdriving.ca` DNS to Bluehost; ensure apex → www + http → https
  301s fire and SSL is valid on the www host.
- In wp-admin → Rank Math, **regenerate the sitemap**, then submit
  `sitemap_index.xml` to Google Search Console.

---

## File map

| File | What it is |
|---|---|
| `README.md` | This runbook |
| `plugins.md` | Install / upload / remove plugin list |
| `smtp-and-dns.md` | Email transport + SPF/DKIM/DMARC |
| `preflight-checklist.md` | Pre-DNS smoke test |
| `htaccess-root.txt` | Production root `.htaccess` (hardening + redirects) |
| `htaccess-uploads.txt` | `wp-content/uploads/.htaccess` (deny PHP) |
| `htaccess-wp-includes.txt` | `wp-includes/.htaccess` (deny PHP) |
| `wp-config-snippet.php` | Constants to add to `wp-config.php` |
| `../../scripts/package-bluehost.sh` | Builds `dist/bluehost/` from the local stack |

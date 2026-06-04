# BuckleUp WordPress — Project Guide for Agents

> Read this first. It's the operational memory for this project: what it is, how
> the **local dev env** and the **live production site** work, the exact
> **hotfix → deploy-to-prod** workflow (no WP-CLI on the host!), the **WP-expert
> team** and how to re-spawn it, and the **hard-won gotchas** from the build +
> launch. Future agents should be able to fix a bug and ship it from this doc
> alone.

Companion docs: `PLAN.md` (full build plan), `docs/CONSOLES-*.md` (consoles),
`deploy/bluehost/` (the deploy runbook + configs), `GO-LIVE.md` (host-agnostic
checklist). Durable session learnings also live in the auto-memory (see §9).

---

## 1. What this is

BuckleUp Driving School's website — a **custom WordPress block theme + two custom
plugins**, rebuilt pixel-faithfully from a Next.js app (source at
`/Users/esfandiyar/Projects/Buckleup`). It is **LIVE in production** at
**https://www.buckleupdriving.ca**, hosted on **Bluehost** (migrated off Vercel
on 2026-06-04). Repo: `agenticsoultions-madeira/buckleup-wordpress` (private).

It includes both the marketing site **and** the Phase-2 app: Student / Instructor
/ Admin **consoles** with custom roles, custom DB tables, and ~28 REST routes.

---

## 2. Architecture at a glance

- **Theme** — `wp-content/themes/buckleup/` — block theme (FSE) + **compiled
  Tailwind v4** (Vite). Hashed assets + `build/.vite/manifest.json` are
  **git-ignored** → you MUST `make build-assets` and ship `build/` for any deploy.
  Logo/brand helpers + the vanilla-JS interaction layer live in `inc/` + `src/js/`.
- **Plugins**
  - `wp-content/plugins/buckleup-core/` — 7 marketing CPTs, `buckleup_settings`
    options, the **contact form** handler (`includes/contact.php`, admin-post →
    `wp_mail`, branded HTML email), lean security hardening.
  - `wp-content/plugins/buckleup-app/` — 3 roles, the **`bu_*` MySQL tables**
    (dbDelta), REST routes, slot engine, console auth/guards (`includes/auth.php`,
    `includes/roles.php`).
- **mu-plugins** (`docker/wordpress/mu-plugins/` in repo; deploy to
  `wp-content/mu-plugins/` on the server):
  - `10-buckleup-seo.php` — JSON-LD schema, canonicals (hardcoded
    `https://www.buckleupdriving.ca`), OG image, host normalization. **PROD.**
  - `11-buckleup-pwa.php` — `/manifest.webmanifest` + PWA meta. **PROD.**
  - `20-buckleup-smtp.php` — routes `wp_mail()` through SMTP when the
    `BUCKLEUP_SMTP_*` constants exist; no-op otherwise. **PROD** (committable, no
    secrets — creds live in `wp-config.php`).
  - `00-mailpit-smtp.php` — **DEV ONLY. NEVER deploy** (forces SMTP to Mailpit).
- **Roles / consoles** — `buckleup_student` / `buckleup_instructor` /
  `buckleup_admin`; front-end consoles at `/student`, `/instructor`, `/admin`;
  login at `/login/`. WP administrators can access all consoles.

---

## 3. Local dev environment (keep it working — the client uses it for bug fixing)

Docker stack (OrbStack). From the repo root:

```bash
cp .env.example .env        # first time
make up                     # start db + wordpress(8.3) + nginx + adminer + mailpit
make provision              # idempotent: install WP, plugins, theme, roles, seed data
make build-assets           # compile theme CSS/JS (Vite) — REQUIRED after theme edits
make reset                  # DESTROY volumes + re-provision from scratch (VERIFY gate)
make wp CMD="plugin list"   # run any WP-CLI command in the dev stack
```

- Site: **http://localhost:8080** (admin/admin123) · Mailpit: **:8025** · Adminer: **:8081**
- **Demo console logins** (kept for testing — do not delete): log in at `/login/`
  - Student: `student@buckleup.test` / `Student123!`
  - Instructor: `instructor@buckleup.test` / `Instruct123!`
  - Admin (console): `appadmin@buckleup.test` / `Admin12345!`
- **Golden rule:** reproduce + fix a bug **here first**, verify locally, *then*
  deploy to prod. The dev env mirrors prod (same theme/plugins/mu-plugins code).

Dev vs prod differences to remember: dev uses Mailpit (not Zoho), nginx (not
Apache), `WP_DEBUG` on, table prefix `wp_` (same), and the full WP-CLI toolchain.

---

## 4. Production environment (Bluehost) — READ BEFORE TOUCHING PROD

| Fact | Value |
|---|---|
| URL | https://www.buckleupdriving.ca |
| SSH/SFTP host | `50.6.245.84`, user `yuwaeymy` |
| Home / docroot | `/home2/yuwaeymy` / `/home2/yuwaeymy/public_html` |
| Database | `yuwaeymy_WPTF7` (creds in the server's `wp-config.php`; **never** commit them) |
| Table prefix | **`wp_`** (set in `wp-config.php`; Bluehost's installer randomized it to `g40_` originally) |
| WordPress / PHP | **WP 7.0** · **PHP 8.3** (`ea-php83` / `lsphp`) |
| **Shell access** | **DISABLED** by Bluehost ("contact support") → **NO WP-CLI, no SSH shell.** SFTP works. |
| SFTP key | `~/.ssh/buckleup_bluehost` (private key, local only; public key is authorized in cPanel → SSH Access. Revocable there.) |
| Caching | Newfold **`endurance-page-cache.php`** (page cache) + **Redis** object cache (`WP_REDIS_*` in wp-config). **DO NOT install Cache Enabler / a 2nd page cache.** |
| Email | **Zoho** (MX `mx.zoho.com`). Outbound via `smtp.zoho.com:465` SSL, user `info@buckleupdriving.ca`, app password — all in `wp-config.php` `BUCKLEUP_SMTP_*`. |
| DNS | Registered at **Namespro**; nameservers `htns{1,2,3}.namespro.ca` (kept there). Apex **A** → `50.6.245.84`; `www` **CNAME** → `yuw.aey.mybluehost.me`. **Zoho MX/SPF/TXT untouched.** |
| SSL | Bluehost **AutoSSL** (shared Let's Encrypt SAN cert covering apex + www). |
| Temp/staging URL | `https://yuw.aey.mybluehost.me` (the Bluehost account domain; valid cert — handy for staging before DNS) |

**The single most important constraint: there is no WP-CLI on the host.** Every
operation that normally uses `wp ...` must be done another way (see §5C).

---

## 5. THE HOTFIX → PROD WORKFLOW (this is the core of this doc)

The repeatable loop for "fix a bug and deploy it to production" on this
shell-less host. This is exactly how the favicon, Blogs-permissions, SMTP, and
HTML-email fixes were shipped in one session.

### A. Reproduce + fix locally
1. Reproduce the bug on the Docker dev env (`make up`).
2. Fix the **source in the repo** (theme/plugin/mu-plugin PHP).
3. If you changed theme CSS/JS: `make build-assets`.
4. Verify the fix locally at `localhost:8080`.

### B. Deploy the changed file(s) over SFTP
```bash
# one file
printf 'put wp-content/plugins/buckleup-core/includes/contact.php public_html/wp-content/plugins/buckleup-core/includes/contact.php\n' \
| sftp -b - -i ~/.ssh/buckleup_bluehost -o StrictHostKeyChecking=accept-new -o BatchMode=yes yuwaeymy@50.6.245.84
```
- Mirror the repo path under `public_html/`.
- **OPcache revalidates** on this host (validate_timestamps on) → the new file is
  picked up within seconds. No restart needed. (Confirmed repeatedly this session.)
- Run all `sftp`/`curl`-to-prod `Bash` calls with `dangerouslyDisableSandbox: true`
  (outbound network), and `-o BatchMode=yes -o StrictHostKeyChecking=accept-new`
  so they never hang on a prompt.

### C. For anything that needs WordPress loaded (DB, options, roles, cache, media, site-icon): the **token-protected one-off PHP helper** pattern
There's no WP-CLI, so drop a tiny PHP script into `public_html`, hit it over HTTPS
with a secret token, read the output, then **delete it**. Template:

```php
<?php
/** One-off. DELETE after use. */
define('BU_TOKEN', '__PUT_A_FRESH_RANDOM_TOKEN__'); // openssl rand -hex 16
if (!hash_equals(BU_TOKEN, (string)($_GET['token'] ?? ''))) { http_response_code(403); exit("forbidden\n"); }
header('Content-Type: text/plain; charset=utf-8');
require '/home2/yuwaeymy/public_html/wp-load.php';   // full WP, plugins loaded
// ... do the thing: update_option(), wp_insert_attachment(), a role re-register,
//     a raw mysqli import, get_site_icon_url() checks, etc. ECHO results to verify.
```
Deploy + run + clean up:
```bash
TOKEN=$(openssl rand -hex 16)        # generate fresh EVERY time; never reuse
# 1) write the script with the token, sftp it to public_html/bu-fix.php
# 2) trigger + capture:
curl -sS "https://www.buckleupdriving.ca/bu-fix.php?token=$TOKEN"
# 3) ALWAYS delete it + verify 404:
printf 'rm public_html/bu-fix.php\n' | sftp -b - -i ~/.ssh/buckleup_bluehost yuwaeymy@50.6.245.84
curl -sS -o /dev/null -w '%{http_code}\n' "https://www.buckleupdriving.ca/bu-fix.php?token=$TOKEN"  # expect 404
```
Notes:
- Keep big staging files (SQL dumps, zips) **outside** the webroot
  (`/home2/yuwaeymy/bu-deploy/`) so they're never web-served; only the small
  trigger script goes in `public_html`, token-protected, deleted right after.
- To force a specific host/cert during testing:
  `curl --resolve www.buckleupdriving.ca:443:50.6.245.84 [-k] https://www.buckleupdriving.ca/...`
- A bulk importer/extractor used for the migration lived at the same path
  (`bu-deploy.php`, steps extract/import/finalize/cleanup) — re-create from this
  template if you ever need a full re-import; it's deleted from the server now.

### D. Verify on prod (always)
```bash
curl -sS -o /dev/null -w '%{http_code}' https://www.buckleupdriving.ca/<page>
curl -sI https://www.buckleupdriving.ca/ | grep -iE 'strict-transport|x-frame|x-content-type'   # headers on 200 AND a 404
# security probes: xmlrpc.php→403, wp-config.php→403, wp-json/wp/v2/users→404, uploads/x.php→403
```
If the change is in HTML (favicon links, nonces) and you see stale output, the
**Newfold page cache** is serving cached HTML → append `?cb=$(date +%s)` to bypass.

### E. Cleanup + commit
- Delete every helper script from the webroot (never leave web-exposed PHP).
- The fix is on prod **and** in the repo source — commit when the user asks
  ("Commit or push only when the user asks"). Keep repo == production.

### Gotchas baked into this workflow (the expensive lessons)
- **mysqli throws on PHP 8.1+** → in any raw-SQL helper: `mysqli_report(MYSQLI_REPORT_OFF);`
  before connecting, and check `mysqli_errno()` yourself.
- **DB import:** run `SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'` first — Bluehost's
  strict mode rejects legacy `'0000-00-00 00:00:00'` defaults (e.g. Action Scheduler).
- **Table prefix is `wp_`.** Any imported dump must match; `$table_prefix` in
  `wp-config.php` must be `wp_` (Bluehost's 1-click installer randomizes it).
- **Redis object cache:** after a large DB change, bump `WP_REDIS_PREFIX` in
  `wp-config.php` to instantly orphan stale keys (a cheap "flush" with no CLI).
- **Role capability changes** don't apply to existing roles unless re-registered.
  `buckleup-app/includes/roles.php` has a `BUCKLEUP_APP_ROLES_VERSION` gate — **bump
  it** when you change `buckleup_app_role_caps()` and it auto-applies on next load.
- **Favicon / site icon:** set via `WP_Site_Icon` to generate square sizes
  (`get_site_icon_url(32/180/192)`); a plain media attachment yields a distorted
  non-square crop. ⚠️ The source `public/icon-512x512.png` is a **mislabeled wide
  logo** (512×100); the real square icon is **`src/app/icon.png`** (512×512).
- **Secrets** (DB, SMTP, Redis) live ONLY in the server's `wp-config.php`. Code
  reads constants and **no-ops without them**, so it's safe to commit. Never put a
  password in the repo or in `CLAUDE.md`.
- **Zoho SMTP:** the From / envelope sender MUST be the authenticated mailbox
  (`info@buckleupdriving.ca`) or Zoho rejects it; keep Reply-To as the submitter.
- **Forms use nonces** + the page cache can stale them. When scripting a contact
  submission: `GET /contact/?cb=<ts>` (cache-bust) to grab a fresh
  `buckleup_contact_nonce`, then POST to `wp-admin/admin-post.php` with a cookie
  jar. Honeypot field is `website` (leave empty); there's no `bu_ts` timing field.
- **TLS cutover:** AutoSSL only issues **after** DNS points at Bluehost; the old
  site shipped HSTS, so the cert must be ready fast. Validate `.well-known/` stays
  reachable (our `.htaccess` keeps it open) so HTTP-01 succeeds.

---

## 6. The WP-expert team (how to re-spawn it)

A 6-member team built the site. **Team name: `buckleup-wp`.** The agent
definitions persist in `.claude/agents/wp-*.md` (TeamDelete removed the team dir,
not these files), so they're available as `subagent_type`s any time:

| Agent (`subagent_type`) | Owns / does |
|---|---|
| `wp-theme-engineer` | `wp-content/themes/buckleup` — block theme, Tailwind v4 build, JS interactions |
| `wp-plugin-engineer` | `buckleup-core` (+ `buckleup-app`) — CPTs, meta, REST, secure server logic |
| `wp-content-engineer` | `scripts/` — idempotent WP-CLI provisioning + content/media migration |
| `wp-seo-specialist` | Rank Math + the SEO mu-plugin — JSON-LD, canonicals, sitemaps, redirects |
| `wp-qa-engineer` | `tests/` — Playwright visual/functional/SEO parity + a11y + CWV harness |
| `wp-seo-content-writer` | blog articles (writes; the content engineer publishes) |

**Each agent owns a directory** — that boundary is what keeps parallel work
conflict-free. Spawn the team only for **large, multi-area work** (a sizable
feature, a re-build, a broad audit). For a single-file bug fix, **do it solo** —
the client is usage-sensitive; keep fan-out small (≈3 parallel agents max,
synthesize in the main loop). See memory `keep-agent-fanout-small`.

To re-spawn:
1. `TeamCreate({ team_name: "buckleup-wp", description: "..." })` — creates the
   team + its shared task list.
2. Create tasks with `TaskCreate` (they attach to the team's list).
3. Spawn each member with the **Agent** tool, passing `team_name: "buckleup-wp"`,
   a `name`, and the matching `subagent_type` (e.g. `wp-theme-engineer`). Assign
   tasks via `TaskUpdate({ owner })`.
4. Shut down gracefully when done: SendMessage `{type:"shutdown_request"}` to each,
   then `TeamDelete` once all have terminated (it fails while members are active —
   shut them down one by one to avoid rate limits).

For an alternative deterministic fan-out (no chat coordination), the **Workflow**
tool also works well for review/migration sweeps — but only when the user opts in.

---

## 7. Deploying / packaging (reference)

- **`scripts/package-bluehost.sh`** — builds `dist/bluehost/` from the running dev
  stack (theme/plugin zips, `uploads.tar.gz` with WebP, a DB export with URLs
  search-replaced to the target host, prod-safe mu-plugins). Run after `make up`.
  Add `20-buckleup-smtp.php` to its mu-plugin copy list if you regenerate the bundle.
- **`deploy/bluehost/`** — the full runbook (`README.md`), `plugins.md`,
  `smtp-and-dns.md`, `preflight-checklist.md`, the three `.htaccess` files, and
  the `wp-config-snippet.php`. Start here for a from-scratch redeploy or a new host.
- Production `.htaccess` lives at `public_html/.htaccess`: our hardening
  (force-https, apex→www, HSTS, FilesMatch denies, dotfile deny incl. `.well-known`
  exception) sits **above** the WordPress + Newfold-managed blocks. Per-folder PHP
  denies in `wp-content/uploads/.htaccess` + `wp-includes/.htaccess`.

---

## 8. Image / WebP delivery (don't break it)

WebP is delivered by the **theme's PHP `<picture>` markup** (`inc/webp.php`),
serving pre-generated `<file>.webp` siblings in `wp-content/uploads`. EWWW's
local optimizer **cannot** run on Bluehost (no binaries, `exec()` disabled), so
WebP is **pre-generated locally** and uploaded. **Do NOT add an `.htaccess`
Accept-header WebP rewrite** — it double-serves and breaks the non-WebP fallback.

---

## 9. Persistent memory (auto-loaded each session)

Key learnings live in the auto-memory at
`~/.claude/projects/-Users-esfandiyar-Projects-Buckleup-wordpress/memory/`:
- `buckleup-bluehost-deploy` — the live prod facts + the 5 Docker→shared deltas + cutover steps
- `buckleup-wp-project` — v1 scope, repo, key decisions
- `buckleup-consoles-ui-complete`, `buckleup-seo-architecture`,
  `buckleup-theme-data-contract`, the Rank Math / EWWW / provisioning landmines, etc.

When you learn something new and non-obvious during a fix, **write it to memory**
(and, if it's an operational pattern, add it here).

---

## 10. House rules (from this client)

- **Commit only when asked.** Keep prod and repo in sync, but don't auto-push.
- **Keep secrets out of the repo** (wp-config constants on the server only).
- **Keep fan-out small** — solo for small fixes; team/workflow only for big work.
- **Demo accounts (`*@buckleup.test`) are kept** for ongoing console testing.
- **Always reproduce + verify locally, then deploy, then verify on prod, then
  clean up** any helper scripts.

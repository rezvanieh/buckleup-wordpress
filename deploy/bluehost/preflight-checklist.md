# BuckleUp on Bluehost — Pre-DNS smoke test

Run all of this on the Bluehost host **before** pointing `buckleupdriving.ca` DNS
at it. Test via the temporary Bluehost URL or a `hosts`-file override. Replace the
domain below if the final one differs.

## Content / pages — all 200, styled, no localhost leaks
- [ ] Home `/`, `/about`, `/services`, `/instructors`, `/contact`
- [ ] 5 locations: `/locations/{slug}` (each renders an H1 + LocalBusiness schema)
- [ ] `/blog` + at least one post; `/resources` + the ICBC article
- [ ] Pages are **styled** (Tailwind built CSS loaded — confirms the theme `build/`
      uploaded; if unstyled, `build/.vite/manifest.json` is missing)
- [ ] View source: no `localhost:8080` anywhere (canonical, OG, links, srcset)

## SEO
- [ ] `https://www.buckleupdriving.ca/sitemap_index.xml` loads and lists the real
      URLs (regenerate via Rank Math after setting WP_HOME if it shows localhost)
- [ ] Canonicals resolve to the **www** host; JSON-LD present (LocalBusiness /
      DrivingSchool, FAQPage, BlogPosting) — validate one URL in Google Rich Results
- [ ] `robots.txt` sane; submit the sitemap to Google Search Console

## Images / WebP
- [ ] A content/featured image is wrapped in `<picture><source type="image/webp">`
      and the browser loads the `.webp` (DevTools → Network → Img → Type=webp)
- [ ] The `.webp` siblings actually uploaded: spot-check
      `wp-content/uploads/.../<file>.png.webp` returns 200
- [ ] Confirm you did **not** add an Accept-header WebP rewrite to `.htaccess`

## Security probes
- [ ] `curl -I https://www.buckleupdriving.ca/` → `X-Frame-Options`,
      `X-Content-Type-Options`, `Referrer-Policy` (and `Strict-Transport-Security`)
- [ ] **Same headers on a 404:** `curl -I https://www.buckleupdriving.ca/nope`
      (this is the silent gap — `Header always set` is what makes it work)
- [ ] `curl -I https://www.buckleupdriving.ca/xmlrpc.php` → 403
- [ ] `curl -s https://www.buckleupdriving.ca/wp-json/wp/v2/users` → 404/empty
      (author enumeration locked)
- [ ] `wp-config.php`, `readme.html`, `license.txt`, `*.log`, `/.git/` → 403/404
- [ ] Upload-a-shell defense: `wp-content/uploads/test.php` is **not** executed
- [ ] `/.well-known/` still reachable (SSL renewal depends on it)

## Redirects / TLS
- [ ] `http://...` → `https://...` (301); `buckleupdriving.ca` (apex) → `www` (301)
- [ ] Valid SSL cert (Bluehost AutoSSL / Let's Encrypt) on the www host

## App / consoles (the buckleup-app portals are live in this build)
- [ ] `/login` works; logging in as each role lands on its dashboard
      (`/student`, `/instructor`, `/admin`) — **not** wp-admin
- [ ] Logged-out `/student` (etc.) → redirected to `/login`
- [ ] The 5 `bu_*` tables exist with data (Adminer/phpMyAdmin or
      `wp db query "SHOW TABLES LIKE '%bu_%'"`)
- [ ] **Change the dev admin login** (the seed no longer hard-codes a password — replace the
      username + password; do NOT carry dev creds to prod)
- [ ] Demo console users (`student@buckleup.test` etc.) — keep for a demo, or
      delete before public launch

## Email
- [ ] SMTP test email arrives in an external inbox (Inbox, not Spam)
- [ ] Live `/contact` submission delivers; SPF/DKIM/DMARC pass (see `smtp-and-dns.md`)

## Performance / caching
- [ ] Bluehost built-in page cache ON; Cache Enabler NOT installed
- [ ] Lighthouse: LCP/CLS within budget on a real page
- [ ] cPanel cron hitting `wp-cron.php` is scheduled (DISABLE_WP_CRON is set)

## Backup
- [ ] Take a full backup (DB + `wp-content`) and confirm a restore works

**Only after every box is checked → cut DNS over to Bluehost.**

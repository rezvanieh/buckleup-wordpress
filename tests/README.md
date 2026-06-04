# BuckleUp WordPress — Parity QA Harness

Validates the Dockerized WordPress rebuild (**candidate**, `http://localhost:8080`) against
the live Next.js site (**golden master**, `https://www.buckleupdriving.ca`) for visual,
SEO/structured-data, functional, and accessibility parity. Owned by the QA engineer; per
PLAN.md Phase 7. v1 is a marketing site — there is **no booking/checkout/portal** to test.

## Layout

```
tests/
├─ package.json              # deps + npm scripts (Playwright, pixelmatch, pngjs, axe-core)
├─ playwright.config.js       # one project per viewport (×browser); reduced-motion; pinned DPR
├─ parity-matrix.csv          # THE matrix: every URL × state, pass criteria, owner, divergences
├─ config/
│  ├─ urls.json               # canonical inventory of all public URLs (+ dynamic-region keys)
│  ├─ viewports.json          # 375 / 768 / 1099 / 1100 / 1440 (1099↔1100 = nav breakpoint)
│  ├─ masks.json              # dynamic-region mask selectors + per-page visual thresholds
│  └─ seo-expectations.json   # business facts + live-observed values + 3 intended divergences
├─ lib/
│  ├─ sites.js                # live vs candidate base URLs (env-overridable)
│  ├─ freeze.js               # freeze all motion for stable screenshots
│  ├─ theme.js                # force light/dark deterministically + assert it resolved
│  ├─ load.js                 # navigate → settle (capped waits) → assert theme → autoscroll
│  ├─ seo-extract.js          # DOM extractor: title/meta/canonical/OG/JSON-LD (+ helpers)
│  ├─ mask.js                 # overpaint dynamic regions; resolve per-page thresholds
│  └─ diff.js                 # pixelmatch wrapper (size-mismatch aware)
├─ bin/
│  ├─ capture-baseline.js     # pin a dated baseline of a target (screens+SEO+headers+sitemap)
│  ├─ seo-diff.js             # SEMANTIC SEO assertions (+ --selfcheck against live)
│  └─ probe-theme.js          # throwaway: confirm theme forcing works on a target
├─ specs/
│  ├─ visual.spec.js          # 14 pages × 2 themes × 5 viewports = 140 pixel-diff tests
│  ├─ functional.spec.js      # nav, theme no-flash, accent-hue, links, FAQ, FAB, breakpoint
│  └─ a11y.spec.js            # axe-core: no NEW serious/critical vs live baseline
└─ baseline/
   └─ live/<YYYY-MM-DD>/       # PINNED golden master (immutable per date); `latest` → newest
```

## Quick start

```bash
cd tests
npm install
npm run install:browsers          # Chromium (+ WebKit/Firefox for cross-browser)

# 1. Pin the live golden master (run once; re-run only to intentionally re-pin)
npm run baseline                  # → baseline/live/<today>/  + latest pointer
node bin/seo-diff.js --selfcheck  # sanity: live facts self-consistent (expect 36/36)
TARGET=live A11Y_VIEWPORT=1440 npx playwright test specs/a11y.spec.js  # write a11y baseline

# 2. Once the WP build serves pages on :8080 (Task #9), validate the candidate:
npm run visual                    # pixel diff vs pinned baseline
npm run seo                       # semantic SEO + the 3 intended divergences (EXPECTED)
npm run functional                # interactions + URL parity
npx playwright test specs/a11y.spec.js
```

Override targets via env: `LIVE_BASE_URL`, `CANDIDATE_BASE_URL`. Most runners take
`--target live|candidate` (CLI) or `TARGET=` (specs).

## How parity is judged

- **Visual** — full-page screenshots are masked over their dynamic regions (graduates,
  testimonials, recent-blogs, dates) on BOTH sites, then pixelmatch'd. A page fails when the
  diff ratio exceeds its `masks.json` threshold. Diffs land in `results/visual-diffs/` and as
  report attachments. Motion is frozen (reduced-motion + injected CSS + `window.__BUCKLEUP_TEST__`).
- **SEO** — `seo-diff.js` compares **semantics, not markup**: LocalBusiness 4.98/500, NAP,
  hours 09:00–18:00, founding 2014, $100–$620 OfferCatalog band, the 3 schema @types, a
  14-item FAQPage, geo meta. It also asserts the **3 intended divergences** (see below) hold
  on the candidate and reports them as `EXPECTED`.
- **Functional** — theme toggle with no flash-of-wrong-theme, the intentional light(emerald)
  vs dark(lime) `--accent` difference, WhatsApp `wa.me/16044413677` / `tel:` / `mailto:`,
  Locations dropdown, FAQ accordion, mobile bottom tab bar + WhatsApp FAB, and the 1099↔1100
  nav breakpoint. Contact-form→Mailpit is candidate-only.
- **a11y** — axe-core (WCAG 2.0/2.1 A/AA) at 1440 in light+dark; the candidate must introduce
  **no new** serious/critical violations vs the live fingerprint baseline.

## The 3 intended SEO divergences (NOT failures)

The WP build deliberately **fixes** live SEO bugs; these are PASS conditions, flagged
`EXPECTED` in the matrix and by `seo-diff.js`:

1. **Self-referential canonicals on every page.** Live BUG: `/locations/*` and `/blog/*`
   inherit the homepage canonical (`https://www.buckleupdriving.ca`); `/about` & `/services`
   self-canonical but on the **apex** host. Confirmed against live on 2026-06-03.
2. **www standardization + apex→www 301.** Live mixes www (canonical/OG) with apex
   (JSON-LD `url`/`logo`). WP uses `www` everywhere.
3. **Complete sitemap.** Live `sitemap.xml` lists only 3 URLs (`/`, `/about`, `/contact`);
   WP lists all pages/CPTs/posts (≥14).

## Selector contract (`data-*`)

Functional/mask selectors target the theme's **existing** `data-*` interaction contract
first (see the project memory `buckleup-theme-data-contract`), with role/text/href fallbacks
so the harness also runs against the live Next site today:

- Theme toggle: `[data-theme-toggle]` (2-state) / `[data-theme-set="light|dark|system"]`
  (3-option); storage key `buckleup-theme` (localStorage + cookie); no-flash inline script at
  `wp_head` priority 1.
- Navbar: `[data-navbar]` (sets `data-scrolled` at scrollY>20), `[data-nav-toggle]` (mobile
  hamburger), `[data-nav-mobile]` (mobile drawer).
- Locations menu: `[data-dropdown]` > `[data-dropdown-trigger]` / `[data-dropdown-content]`.
- FAQ: native `<details>` with `[data-faq-item]` (toggle flips the `open` attribute).

Two hooks the harness needs that are **not yet in the theme contract** (reported to the team
lead): dynamic-region mask markers — `[data-graduates]` / `[data-testimonials]` /
`[data-recent-blogs]` / `[data-mask='date']` — and the mobile `[data-mobile-fab]` (WhatsApp
FAB) + `[data-mobile-tabbar]`. The harness falls back to id/class/href heuristics meanwhile;
where a fallback is brittle the finding is filed, not silently skipped.

## Baseline immutability

`capture-baseline.js` writes to `baseline/<target>/<YYYY-MM-DD>/` and never overwrites a prior
date, so a live-content change can't silently move the goalposts. `latest` points at the run
the diff tools read. Screenshot PNGs are git-ignored (regenerate from live); the small SEO
JSON / headers / sitemap / robots / manifest are kept for provenance.

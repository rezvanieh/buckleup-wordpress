# BuckleUp WordPress — Implementation Plan

> Rebuild of the BuckleUp Driving School site (live Next.js app at https://www.buckleupdriving.ca)
> as a **custom WordPress block theme + a small support plugin**, running in Docker locally first.
> Source of truth for the original app: `/Users/esfandiyar/Projects/Buckleup` (Next.js 16 + Prisma/Postgres).

This plan was produced after a 19‑agent discovery + architecture pass (full notes archived in the
session). It is intentionally **right‑sized to the agreed v1 scope**: a faithful **marketing website**,
not the full application. The application layer (live booking, payments, role portals, notifications)
is captured as a **Phase 2 roadmap** so nothing is lost, but it is **out of scope for v1**.

---

## 1. Scope (agreed with client)

**v1 is a simple, pixel‑faithful static *business* website.** It reproduces everything the live site
shows a visitor, the brand design system 1:1 (light + dark), the blog as a real CMS, and best‑practice
SEO — but it does **not** build the booking engine, Stripe payments, or the student/instructor/admin
application portals. Primary conversions stay exactly as they are on the live site: **WhatsApp deep
links, phone, and the contact form.**

| Decision | Choice |
|---|---|
| v1 product shape | **Simple marketing website** (not the full app) — "adjust accordingly" |
| Services & Instructors pages | **Included** (live in source but feature‑flagged off) — kept simple |
| Packages purchase / refunds / SMS‑WhatsApp notifications | **Deferred to Phase 2** |
| Theme architecture | **Custom block theme + compiled Tailwind v4** (thin `theme.json`) |
| Performance stack | **Lean** — simple page cache + image optimization + self‑hosted fonts (low traffic; do not over‑engineer) |
| Security | **Lean baseline** — free hardening (no payments → no PCI scope in v1) |
| Data migration | **Content only** — blog posts (+images), graduate photos, services/instructors; users re‑register later |
| Repo | `agenticsoultions-madeira/buckleup-wordpress` (private) — **already created**, Phase‑0 scaffold pushed |

### In scope for v1
- Pixel‑faithful **design system** (HSL tokens light+dark, Geist fonts, glass/gradient‑text/glow utilities, 0.75rem radius, animations).
- **Public pages:** Home (Hero → Graduates → Pricing → Testimonials → FAQ), About, Contact (form + map), Services, Instructors, 5 Location pages, Resources index + ICBC road‑test article.
- **Blog** as native WordPress posts (Gutenberg), with categories/tags and the 5 migrated posts.
- **Editable content** via CPTs/ACF: Graduates gallery, Testimonials, FAQ, Pricing/Packages, Services, Instructors, Locations, site settings.
- **SEO:** Rank Math — per‑page meta, LocalBusiness/DrivingSchool + FAQPage + BlogPosting + Breadcrumb JSON‑LD, geo meta, complete sitemap, self‑referential canonicals, apex→www 301 (fixing the source's known SEO bugs).
- **Dark/light theme toggle** with no flash‑of‑wrong‑theme.
- **WhatsApp / tel / mailto** CTAs and Google Maps embed, byte‑faithful.
- **Lean performance & security**, content migration, and a **QA parity pass** against the live site.

### Explicitly deferred to Phase 2 (the "application")
Booking wizard + server‑computed availability/slot engine, Stripe Checkout + webhook confirmation,
Student / Instructor / Admin custom portals, the notification queue (email/SMS/WhatsApp), prepaid
package hours + redemption, refunds, per‑user theme sync. See **§12 Roadmap**.

---

## 2. Target architecture (v1)

```
agenticsoultions-madeira/buckleup-wordpress
├─ docker-compose.yml            # nginx + PHP-FPM (WP 6.9/PHP 8.3) + MariaDB 11.4 + Adminer + Mailpit
├─ wp-content/
│  ├─ themes/buckleup/           # custom BLOCK THEME (Vite + Tailwind v4, CSS-first tokens)
│  │  ├─ theme.json              # thin: disable WP presets, register Geist, base radius
│  │  ├─ src/css/app.css         # globals.css ported verbatim (@theme inline, :root/.dark, utilities)
│  │  ├─ src/js/                 # Motion One reveals, FLIP magic-move, hero tilt, lightbox, nav, theme toggle
│  │  ├─ templates/ + parts/     # FSE header/footer + page templates
│  │  └─ patterns/               # Hero, Pricing, Testimonials, FAQ, Graduates section patterns
│  └─ plugins/buckleup-core/     # CPTs, ACF field groups, JSON-LD schema, contact handling, shortcodes/blocks
├─ scripts/                      # idempotent WP-CLI provisioning + content migration
└─ PLAN.md (this file)
```

**Principle:** the **block theme owns presentation**; the **`buckleup-core` plugin owns content types
and site logic** (so they survive theme changes). No WooCommerce, no Amelia/Bookly, no membership/LMS
plugin in v1 — none are needed for a marketing site. The compiled Tailwind bundle is the single source
of truth for styling; `theme.json` stays thin (it cannot express `hsl(var(--x)/<alpha>)` modifiers).

### Content model (v1)
| Source concept | WordPress mapping |
|---|---|
| Blog posts | **Native `post`** + category taxonomy + `post_tag`; featured image in Media Library |
| Graduate gallery images | **`graduate` CPT** (title, description, image, order, active) → Hall‑of‑Fame rail + lightbox |
| Testimonials | **`testimonial` CPT** (name, role, rating, photo, quote) → grid/carousel (replaces hardcoded fallbacks) |
| FAQ (14 Q&A) | **`faq` CPT** (or ACF repeater) → single source that renders the accordion **and** FAQPage schema |
| Services (3) | **`service` CPT** + ACF (type, duration, price, sort) — drives Services page + schema OfferCatalog |
| Packages / home pricing plans | **`package` CPT** + ACF (price, hours, popular, +car fee, WhatsApp text) — drives Pricing section |
| Instructors | **`instructor` CPT** + ACF (bio, certs[], languages[], photo, rating) — Instructors page |
| Locations (5) | **`location` CPT**, rewrite base `/locations/` (hero title/highlight/subtitle, meta) |
| Site settings (NAP, hours, social, schema claims) | **ACF Options page** |
| Users/auth, bookings, transactions, availability, notifications | **Out of v1** (Phase 2) |

### URL parity (must not change)
`/`, `/about`, `/contact`, `/services`, `/instructors`, `/blog`, `/blog/{slug}`,
`/locations/{coquitlam,north-vancouver,port-coquitlam,port-moody,tri-cities}`,
`/resources`, `/resources/icbc-road-test-failures`. Permalinks set to `/%postname%/`; `location` CPT
rewrites to `/locations/`. Server‑level **apex→www 301**. Preserve the 5 existing blog slugs exactly.

---

## 3. Design‑system fidelity spec (the non‑negotiables)

Port `src/app/globals.css` **verbatim** through the theme's Tailwind v4 build:

- **Tokens:** all `:root` (light) and `.dark` HSL channel triplets, consumed as `hsl(var(--x))` so `/90 /10 /50` opacity modifiers keep working. **Do not collapse the intentionally different accent hues** (light `--accent 160 84% 39%` emerald vs dark `142 71% 45%` lime). `--radius: 0.75rem` with sm/md/lg/xl `calc()` derivations.
- **Fonts:** self‑host **Geist** + **Geist Mono** variable woff2 (OFL), exposed as `--font-geist-sans` / `--font-geist-mono`; `font-feature-settings: "rlig" 1, "calt" 1`; `antialiased`. (Self‑host, not Google CDN — metrics drift.)
- **Utilities (verbatim):** `.glass` (separate light/dark bg‑alpha + inset highlight), `.gradient-text` (3‑stop 135° primary→`280 65% 60/65%`→accent, bg‑clip‑text, + dark variant), `.glow-primary/.glow-accent`, `.shine`, `.hover-lift`, `.animate-float/pulse-glow/gradient`, `.skeleton`, `.card-highlight`, custom scrollbar, `::selection`, `:focus-visible`, the **150ms global color/bg/border transition** + `html.no-transitions` flash‑guard + `prefers-reduced-motion` overrides.
- **Components** (rebuilt as Tailwind class‑string partials, same `cva` matrices): Button (all variants/sizes), Card, Input, Textarea, Label, Select, Dialog, DropdownMenu, Switch, Avatar, Table, pill **CustomTabs**, eyebrow/badge pills, **FAQ accordion**. Behavioral ones get a small vanilla‑JS layer toggling the same `data-[state]` attributes `tw-animate-css` targets.
- **Animations (framer‑motion → vanilla):** Motion One `inView` for the ~48 fade‑in‑up scroll reveals (stagger `index*0.05`, once, amount ~0.2); a small **FLIP** module for the 4 `layoutId` "magic‑move" indicators (nav pill, sidebar item, tabs bubble); scroll‑aware Navbar (`scrollY>20` glass/shadow + `h-32→h-16` at the custom `min-[1100px]` breakpoint); hero **3D mouse‑tilt** card; shared‑element **lightbox**. All gated behind `prefers-reduced-motion`.
- **Responsive parity:** custom `min-[1100px]` desktop‑nav breakpoint; signed‑out **mobile bottom tab bar** + WhatsApp FAB; testimonials grid→carousel; pricing 1/2/3‑col; graduates horizontal snap‑scroll 2‑row rail; lg‑only hero tilt card with stacked mobile variant.

---

## 4. Exact content to reproduce (highlights)

- **Home Hero:** H1 "Master the Road **with Confidence**" (gradient span); the exact subtitle; trust badges ICBC Certified / Fully Insured / 100% Pass Guarantee; CTA "Start Learning Today" → `#most-popular`; floating "4.98 / Based on 200+ reviews"; "Farhad Sanaeifar / Senior Instructor • ICBC Certified"; Toyota badge.
- **Pricing (home):** Single Session **$100**, 4 Sessions **$360**, 6 Sessions **$480** (Most Popular, `#most-popular`), 8 Sessions **$620** — with the exact feature bullets, +car‑on‑road‑test fees, and the WhatsApp template `Hi! I'm interested in booking the *<name>* ($<price>).` → `wa.me/16044413677`.
- **FAQ:** 14 Q&A verbatim (English+Farsi, 24h/$35 cancellation, Toyota, cash+e‑transfer, ~6 lessons, ~90 min, service areas) — single source feeding accordion **and** FAQPage schema.
- **Graduates:** "Milestones of Success" / "The Hall of Fame" + intro; empty state "NO GRADUATES YET".
- **Testimonials:** "Loved by Thousands" + the 5 named fallback quotes (seeded as Testimonial CPT entries) + trust row.
- **About / Contact / Services / Instructors / Locations / Resources:** copy, headings, cards, and per‑city local‑landmark hero subtitles (Lougheed Hwy, Lynn Valley, bridge traffic, Port Moody routes) reproduced verbatim.
- **NAP everywhere:** 136 Maple Dr, Port Moody, BC V3H 0A8 · (604) 441‑3677 · info@buckleupdriving.ca · Mon–Sun 9am–6pm · geo 49.2838/‑122.8556 · founded 2014 · rating 4.98/500.
- **Footer:** "Ready to Start Driving?" CTA band, brand blurb, Quick Links, Service Areas, Recent Blogs (real), Contact, social (Instagram budrivingschool, Facebook DriveMasterca), copyright.

**Notes / clean‑ups (don't clone source defects):** ignore the dead `*PageContent.tsx` twins; for Instructors use the **real** instructor data (Farhad + Sarah) rather than the Unsplash placeholder personas; give every page its **own** canonical (source wrongly inherits the homepage canonical); standardize all URLs on **www**; ship a **complete** sitemap (source lists only 3 URLs).

---

## 5. Phased implementation plan (the todo list)

### Phase 0 — Foundation ✅ *scaffolded (by discovery agent); needs boot‑verification*
- [x] Private repo `agenticsoultions-madeira/buckleup-wordpress` created + Phase‑0 scaffold pushed to `main`.
- [x] Docker stack (nginx + PHP‑FPM WP 6.9 + MariaDB 11.4 + Adminer + Mailpit), Makefile, CI, block‑theme + plugin skeletons, Vite+Tailwind v4 pipeline, idempotent WP‑CLI provisioning.
- [ ] **Verify the stack actually boots** end‑to‑end on the Mac (`make up && make provision`), WP serves on :8080, Adminer :8081, Mailpit :8025; fix anything that doesn't. *(First execution step.)*
- [ ] Drop in Geist + Geist Mono woff2; run first `make build-assets`; confirm hashed assets enqueue.
- [ ] Right‑size the stack to "low traffic / don't over‑engineer": keep nginx+MariaDB; **drop Redis/object‑cache** from v1; add a simple page‑cache + image‑optimization plugin instead. Protect `main` (PR + CI).

### Phase 1 — Design system & theme shell
- [ ] Port `globals.css` 1:1 into `src/css/app.css`; build and diff emitted token CSS vs the Next output.
- [ ] Port every bespoke utility (`.glass` light+dark, `.gradient-text` 3‑stop+dark, glow/shine/float/pulse‑glow, skeleton, card‑highlight, scrollbar, focus‑visible, 150ms transition, no‑transitions guard, reduced‑motion).
- [ ] Self‑host Geist fonts + wire `--font-geist-*`; verify hero type scale (`text-5xl…lg:text-[5.5rem] xl:text-[6.5rem]`, `tracking-tighter`, `leading-[0.95]`).
- [ ] Build component partials (Button cva matrix, Card, Input, Textarea, Label, Select, Dialog, Dropdown, Switch, Avatar, Table, CustomTabs, badges/pills, FAQ accordion) + their vanilla‑JS behavior layer.
- [ ] Dark/light toggle: inline pre‑paint blocking script (cookie/localStorage, resolve System), 3‑option ThemeToggle UI, logo theme‑swap. *(No per‑user DB sync in v1.)*
- [ ] FSE header/footer parts: Navbar (Locations dropdown, scroll‑shrink at `min-[1100px]`, mobile hamburger + signed‑out bottom tab bar + WhatsApp FAB) and Footer (5‑col, Service Areas, Recent Blogs, CTA band).
- [ ] Motion layer: Motion One scroll reveals, FLIP magic‑move indicators, hero 3D tilt, graduates lightbox — all `prefers-reduced-motion`‑aware.

### Phase 2 — Content types & editability (`buckleup-core` plugin)
- [ ] Register CPTs + ACF field groups: `graduate`, `testimonial`, `faq`, `service`, `package`, `instructor`, `location` (rewrite `/locations/`); ACF Options page for NAP/hours/social/schema claims.
- [ ] Section block patterns wired to the CPTs (Hero, Pricing from `package`, Testimonials from `testimonial`, FAQ from `faq`, Graduates rail from `graduate`).
- [ ] WhatsApp/tel/mailto deep links rendered server‑side with exact templates; Google Maps embed + quick‑question chips.

### Phase 3 — Pages
- [ ] **Home** front page: Hero → Graduates → Pricing → Testimonials → FAQ (fixed order).
- [ ] **About** (mission, values), **Contact** (form → email to info@, map, response cards), **Services** (4 license‑class tabbed pricing — simplified), **Instructors** (real instructors), **Resources** index + **ICBC road‑test** article.
- [ ] **Location** CPT template (per‑city H1/highlight/subtitle/meta) × 5 entries with exact slugs + copy.
- [ ] **Blog** index (archive) + single (native posts, `prose` styling, BlogPosting schema).

### Phase 4 — SEO & structured data (Rank Math)
- [ ] Install/configure Rank Math; title template `%title% | BuckleUp Driving School Vancouver`; homepage title/description verbatim; OG/Twitter defaults; robots.
- [ ] Server‑level **apex→www 301**; set WP site URL to `https://www.buckleupdriving.ca`.
- [ ] Per‑page titles/descriptions for all pages + 5 locations + resources (verbatim from source); **self‑referential canonicals on every page** (fix the source bug).
- [ ] mu‑plugin: verbatim **LocalBusiness/EducationalOrganization/DrivingSchool** JSON‑LD (NAP, geo, hours, OfferCatalog $100–$620, AggregateRating 4.98/500, payments, areaServed) + **geo meta** tags.
- [ ] **FAQPage** schema on home + 5 locations from the single FAQ source; **BlogPosting** on posts; **BreadcrumbList** sitewide; (bonus) Article schema on the ICBC guide.
- [ ] Complete sitemap (all pages/CPTs/posts); robots disallow wp‑admin; PWA manifest + full icon set; Redirection plugin loaded with the URL‑parity map + 404 logging.

### Phase 5 — Content migration (content‑only)
- [ ] WP‑CLI migration script: import the 5 blog posts (preserve slug/date/category/tags; side‑load featured + inline images into Media Library), graduate photos (from Supabase/seed), services/packages, instructor profiles (Farhad + Sarah), testimonials, FAQ.
- [ ] Copy brand media from source `public/` (logo, logo‑dark, image2, hero_card_image, farhad‑instructor, icons) into Media Library; set Site Icon + theme‑swap logos.

### Phase 6 — Performance & security (lean)
- [ ] Performance: simple page cache (e.g. WP Super Cache / Cache Enabler), WebP image optimization, lazy‑load below‑fold, **eager + preload the hero LCP image**, minified single CSS bundle, deferred JS. Self‑hosted fonts preloaded. Modest CWV check vs live (no LiteSpeed/QUIC over‑engineering).
- [ ] Security baseline (no payments in v1): keep WP/plugins updated, disable file editing, disable XML‑RPC, limit‑login + basic firewall (Wordfence **free** or equivalent), lock REST user enumeration, security headers, least‑privilege DB user + secrets out of git, scheduled backups (UpdraftPlus free). Contact form spam protection.

### Phase 7 — QA parity & sign‑off
- [ ] Capture a pinned visual/SEO/DOM baseline of the **live** site; build a Parity Matrix (every public URL + content + SEO + responsive states), masking dynamic regions (graduates/testimonials/recent‑blogs/dates).
- [ ] Visual‑regression (Playwright + pixelmatch) at 375/768/1099/1100/1440 in **light and dark**; functional checks (nav, theme toggle no‑flash, contact form → Mailpit, WhatsApp/tel links, lightbox, accordion); SEO/meta/JSON‑LD diff (assert LocalBusiness 4.98/500, 14‑item FAQPage, NAP, hours, prices) with the 3 intended SEO fixes annotated.
- [ ] a11y (axe — no new serious violations vs live) + Lighthouse CWV within tolerance; cross‑browser (Chromium/WebKit/Firefox).
- [ ] Written launch sign‑off checklist; pre‑DNS production smoke test (apex→www 301, email deliverability from a verified `buckleupdriving.ca` domain — not the `resend.dev` sandbox, sitemap submitted to GSC).

---

## 6. Roadmap (Phase 2 — the "application", out of v1)

Documented so it's not lost; built later as a separate, scoped effort. Would add a `buckleup-app`
plugin with **custom InnoDB tables** (bookings, availability, availability_exceptions, transactions,
lesson_progress, student_packages, reviews, notification_*), `buckleup/v1` REST endpoints mirroring the
Next `/api/*` shapes, a ported **slot/availability engine** (30‑min, duration‑aware, weekly + per‑date
exceptions, overlap exclusion), **Stripe Checkout + raw‑body webhook** (with the source bugs *fixed*:
honor exceptions at checkout; expire stale PENDING bookings), three **role‑gated React‑island portals**
(Student/Instructor/Admin), the **notification engine** (42 templates, email + Twilio SMS/WhatsApp,
real cron, the missing opt‑in UI), **packages/hours redemption**, and **refunds**. Plus full
auth/roles (native WP + social login + bcrypt rehash‑on‑login if migrating real users) and per‑user
theme sync. Each ships behind its own QA parity pass.

---

## 7. Key risks & how we handle them (v1)

- **Pixel parity of framer‑motion** → Motion One + a small FLIP module; QA freezes animations for stable diffs and checks motion manually. Highest‑effort visual item: the 4 magic‑move indicators + hero tilt.
- **Tailwind v4 arbitrary classes** (`lg:text-[5.5rem]`, `blur-[120px]`, `bg-card/98`, `ring-[3px]`) → real Tailwind v4 build with content scanning so nothing is purged; ship one compiled, hashed CSS file.
- **Theme flash** → inline pre‑paint script; QA explicitly checks no flash and the light‑vs‑dark accent hue difference.
- **FAQ schema drift** → single FAQ source renders both the accordion and FAQPage JSON‑LD.
- **SEO regressions in source** → we *fix* (self‑canonicals, www, full sitemap), recorded as intended divergences in the Parity Matrix.
- **Dead/mock/hidden source surfaces** → reconciled per §1/§4; we don't clone dead twins or ship broken links.
- **Scaffold not yet verified to boot** → Phase 0 first task; treat the agent‑generated scaffold as a reviewable starting point, not as "done".

---

## 8. Status

- Discovery + architecture: **complete** (19‑agent pass).
- Repo + Phase‑0 scaffold: **exists on `main`** (boot‑verification pending).
- Awaiting: **client go‑ahead to begin Phase 1**. No production/DNS changes happen without explicit sign‑off.

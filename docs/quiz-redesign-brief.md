# Practice-Center Redesign — Theme Implementation Brief

The `buckleup-quiz` backend is DONE and verified (grouped/batched serving, name
capture, certificate page + verify, aggregate stats, prestige email). This brief
covers the THEME work (`wp-content/themes/buckleup/`). Build to this contract.

## Goals (client feedback)
1. A compact **marketing band** atop the landing pages + a **differentiated nav CTA**.
2. A **Category Rail** UX: left sidebar of categories with per-category progress +
   active-category color that changes as you move through topics.
3. **One question at a time, forward-only** — the next question appears after the
   current is answered; never show future questions; no "jump" grid.
4. Fix the **missing navigation on category pages** (rail + breadcrumbs).
5. **On-page stats** (aggregate band + personal donut + per-topic bars).
6. **Certificate of completion** CTA on a pass (page is already built by the plugin).
7. (Email already redesigned in the plugin — theme just links to `certificateUrl`.)

## NEW REST contract (the runner must use this — it changed)
Base `window.buckleupAuth.restUrl` + `X-WP-Nonce`, `credentials:'same-origin'`.
- `GET  quiz/status` → `{attempts:{unlimited,max,used,remaining}, loggedIn}` (unchanged).
- `POST quiz/start {mode}` → `{sessionId, mode, total, batchCount, categories:[{slug,index,label,short,total}], batch:{categoryIndex,slug,position:0,questions:[{qid,category,categoryIndex,question,options[4]}]}}`.
  **Only the FIRST category's questions are returned.** Build the rail from `categories`.
- `POST quiz/batch {sessionId}` → `{batch:{categoryIndex,slug,position,questions[]}, done:bool}` — call this to fetch the NEXT category when the user finishes the current one. Future categories are served only on demand (anti-peek). `{done:true}` when no categories remain.
- `POST quiz/submit {sessionId, answers:{qid:displayIndex}, email, name, website}` → `{resultToken, name, score, total, pct, passed, breakdown:{cat:{correct,total}}, categories:[manifest], review:[…], attempts, certificateUrl}`. `certificateUrl` is non-empty only on a pass. `name` optional (logged-in uses account name).
- `POST quiz/claim/{resultToken} {name}` → `{name, certificateUrl}` — the post-results "add your name to claim your certificate" step (only works while name is empty).

Runner flow: start → render rail from `categories` + show batch[0] questions one at a
time (forward-only, "Next" hidden until an option is chosen) → when the current
category's questions are exhausted, `quiz/batch` for the next → after the last
question, show the email/name **gate** (anon) → `quiz/submit` → results in-place.
Track answers `{qid:displayIndex}` across batches; submit them all. (Logged-in skips
the gate.) Server enforces a 10s minimum + attempt caps + honeypot — keep the hidden
`website` field.

## Engine helpers available to PHP (the pattern)
- `buckleup_quiz_js_config($mode)` → includes `catIndex` (slug→0..11 map for `data-cat`).
- `buckleup_quiz_categories()`, `buckleup_quiz_category_index_map()`, `buckleup_quiz_category_label($slug)`.
- `buckleup_quiz_page_context()`, `buckleup_quiz_hub_url()`, `buckleup_quiz_category_url($slug)`.
- `buckleup_quiz_sample_questions($cat='',6)`, `buckleup_quiz_hub_faqs()` (existing).
- `buckleup_quiz_aggregate_stats()` → `{tests_taken,avg_pct,pass_rate,most_missed,most_missed_label}` (cached). Render the aggregate band only when it has meaningful data (e.g. `tests_taken >= 25`); otherwise omit (graceful absence).
- `buckleup_quiz_certificate_url($token)` (the cert page is plugin-rendered).

## Category color palette — PURGE-SAFE (add to src/css/app.css, literal)
Key the 12-color palette off a stable integer via `data-cat="N"` (N = category
index from `catIndex`); never build `bg-cat-${slug}` strings.
```css
[data-cat="0"]{--cat:217 91% 50%} [data-cat="1"]{--cat:12 83% 52%}
[data-cat="2"]{--cat:160 84% 36%} [data-cat="3"]{--cat:84 64% 40%}
[data-cat="4"]{--cat:35 92% 47%}  [data-cat="5"]{--cat:265 70% 56%}
[data-cat="6"]{--cat:190 80% 40%} [data-cat="7"]{--cat:234 70% 56%}
[data-cat="8"]{--cat:178 70% 36%} [data-cat="9"]{--cat:340 75% 52%}
[data-cat="10"]{--cat:25 90% 50%} [data-cat="11"]{--cat:205 30% 45%}
.cat-dot{background:hsl(var(--cat))}
.cat-bar-fill{background:hsl(var(--cat))}
.cat-rail-active{border-color:hsl(var(--cat));background:hsl(var(--cat)/0.08)}
.cat-rail-active .cat-label{color:hsl(var(--cat));font-weight:600}
.cat-accent-text{color:hsl(var(--cat))}
.cat-accent-border{border-color:hsl(var(--cat)/0.4)}
.cat-accent-soft{background:hsl(var(--cat)/0.1)}
```
PHP/JS only ever set `data-cat="<N>"` + these literal `.cat-*` classes. Bar widths
= inline `style="width:N%"` (numbers, allowed). QA each hue for AA on `--card`.

## Category Rail (one primitive, 3 faces)
- **Hub landing:** a list/grid of all 12 categories (each `data-cat="N"` + `cat-dot`,
  label + blurb + "N questions"), each links to its category page. Pure nav, none active.
- **Category landing:** the rail as a **sticky left sidebar**, current category
  `data-cat="N"` + `cat-rail-active`; add **breadcrumbs** (Home › Practice Tests ›
  {Category}); a "Take full mock →" link pinned at the rail foot. Remove the old
  weak "Other topics" pill row.
- **Runner:** the rail shows only the categories in THIS test (from `categories`),
  each with `answered/total` + a per-category mini bar in its color; active row
  `data-state="active"`; rows `done|active|todo`. An overall progress bar too.
  On mobile, the rail collapses into a drawer (clone `src/js/modules/console.js`'s
  `[data-*-drawer]`/`data-state=open|closed`/overlay/Esc/scroll-lock pattern) with a
  sticky mini-header showing the current category color + name + overall bar.

## Runner main area (single question, forward-only)
One question card; its wrapper `data-cat="N"` (active category) so the tag + selected
option recolor per topic. Options are `role="radio"` buttons with `data-quiz-option="i"`
+ `data-state="selected|unset"` (selected uses `cat-accent-border`/`cat-accent-soft`).
Selecting reveals the forward button (`data-quiz-next`, hidden until answered) +
updates the rail; last question → "See my results". NO Back, NO jump grid. Add
arrow-key roving radiogroup + move focus to the question heading on change +
`aria-live` announce category transitions. (Answers are never client-side, so this is
purely UX integrity.)

## Results screen
- Overall **score donut** (inline SVG, stroke-dashoffset; `data-state="passed|failed"`
  → `data-[state=passed]:stroke-accent` / `data-[state=failed]:stroke-destructive`;
  offset = C*(1-pct/100), C≈263.9 for r=42; set via inline style). Numeric label as
  real text beside it.
- **Score by topic** as a `<table>` with category-colored bars (`data-cat`+`cat-bar-fill`),
  rows below pass mark get `data-state="weak"`.
- **Focus your study** — weakest categories (sort breakdown `correct/total` asc, lowest
  2–3) as category links (`data-cat`+`cat-dot`) to their pages.
- **Certificate card (pass only):** "🎓 You passed! Claim your Certificate" → if
  `name` empty, an inline "add your name" mini-form that POSTs `quiz/claim/{token}`,
  then reveal the button linking to `certificateUrl`.
- CTAs: Take another test / Practise a topic / Book a lesson.

## Email gate (add name field)
The existing gate gets an OPTIONAL **Full name** field (autocomplete="name", "for your
certificate") next to email. Submit sends `name`. Name optional for results; needed for
the cert (or the claim step above).

## Stats — aggregate band (landing)
A compact `.glass` 3-figure strip inside the marketing band: tests taken · average
score · most-missed topic (links to that category, dot-colored). Optional reduced-motion-
safe count-up. Render only if `buckleup_quiz_aggregate_stats()` has meaningful volume.

## Nav CTA (differentiated)
Add a header CTA → `buckleup_quiz_hub_url()`, label **"Free Practice Test"**, using the
**accent (emerald)** so it doesn't clash with the red "Book a Lesson" button. Literal
classes:
`inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold text-accent bg-accent/10 border border-accent/30 hover:bg-accent/15 transition-colors data-[active=true]:bg-accent data-[active=true]:text-accent-foreground`
`data-active` when `buckleup_quiz_page_context()['type'] !== ''`. Mobile menu: same item,
full-width, at top. (Add to `buckleup_nav_items()` / `patterns/site-header.php`.)

## Marketing copy (drop-in; pick the (rec) variants)
- **HUB HEADLINE:** "Pass Your ICBC Class 4 Knowledge Test — Free Practice, Built for BC"
- **SUBHEAD:** "Sharpen up before the real exam with a free practice test that mirrors ICBC's Class 4 — same length, same 12 commercial-driving topics, instant scoring. No signup needed to start."
- **BULLETS (chips):** Know before you go (~50 Qs) · Find your weak topics · Real 80% pass mark · 100% free, instant + emailed results
- **PRIMARY_CTA:** "Start the Free Practice Test"  ·  **TRUST_LINE:** "Built by BuckleUp's ICBC-certified instructors — a Tri-Cities driving school with a ~98% first-time pass rate since 2014."
- **CATEGORY_INTRO ({Category}):** "Master {Category} before test day — practise the exact kind of questions ICBC asks, score instantly, and see where to focus."  ·  **CATEGORY CTA:** "Practise {Category} Now"
- **NAV_CTA:** "Free Practice Test"
- **LIMIT_UPSELL:** "You've used all 3 free attempts — nice work putting in the prep. Want unlimited practice and a real instructor in your corner? Create a free account to keep going, or book a lesson with a BuckleUp instructor and walk into your ICBC test ready."
- **RESULTS_INTRO:** "Here's how you did — your score, plus a topic-by-topic breakdown so you know exactly what to brush up before the real ICBC exam."
- **STATS_HEADLINE:** "Join Thousands of BC Drivers Getting Test-Ready" · **STATS_ONELINER:** "See how the BuckleUp community is preparing — and where most learners trip up — so you can walk in sharper than the average." · tile labels: Practice tests taken · Average score · Most-missed topic · Pass-mark hit rate · **PERSONAL_CHART_INTRO:** "Find and fix your mistakes before the real exam — here's exactly which topics cost you points, ranked weakest-first."
- **CERT card heading:** "Congratulations — You Passed the Practice Test!" (the cert PAGE itself is plugin-rendered; this is just the results-screen CTA copy).

## Constraints
Tailwind v4 + vanilla JS; `data-state`/`data-cat` only (no dynamic class strings);
`make build-assets` (node container) after CSS/JS; respect `prefers-reduced-motion`;
match the existing console/card aesthetic + brand red `#dc2626`. Verify on
`localhost:8080`; the result email lands in Mailpit (`:8025`).

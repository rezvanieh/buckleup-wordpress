# Practice Exam Center — Build Spec (authoritative)

Dedicated, distraction-free exam experience. Backend is DONE: `/quiz/start` REQUIRES
`consent:true` and RETURNS `timeLimit` (seconds; 0 = untimed). Route:
`/icbc-class-4-knowledge-test/exam/?mode={full|<category-slug>}`. The exam page
(`#214`, slug `exam`, child of hub) is already provisioned with the `page-exam`
template + `noindex`. Reuses the existing `data-quiz` engine, the `.cat-*` palette,
and the `data-state`/`data-cat` purge discipline. See also `docs/quiz-redesign-brief.md`.

## Chrome shell (wraps all 3 screens)
Fixed top, `h-14` (mobile `h-12`), `glass` (`bg-background/95 backdrop-blur-xl border-b
border-border`), z-50, with a spacer below. Left: `buckleup_logo()` h-7 — **unlinked
while `data-quiz-step="running"`** (a click-away is an exit), linked-home on intro/results.
Center: "ICBC Class 4 Knowledge Test — Practice Exam" + (running only) the live timer chip.
Right: ghost "Exit exam". Body: plain `bg-background`, NO gradient/glow; `max-w-3xl`
(briefing/results) / `max-w-4xl` (running).

## Screen 1 — Briefing & Consent (`data-quiz-step="intro"`, re-skinned)
Centered, official instruction-panel feel, NO marketing red except the Begin button.
- Eyebrow "Practice Examination" · H1 "Before you begin" · subtitle (full: "ICBC Class 4
  Knowledge — Full Practice Exam"; category: "ICBC Class 4 — {Category} Practice").
- Lede: "Read the conditions below. The timer starts the moment you press Begin, so make
  sure you're ready and won't be interrupted."
- **Test details** card (`.glass rounded-3xl border border-border p-6 md:p-8`, grid
  `sm:grid-cols-2 gap-x-8 gap-y-4`, values `font-bold tabular-nums`, labels `text-sm
  text-muted-foreground`):
  - Questions: **50** (category: 10) · Pass mark: **80%** · Time limit: **45:00**
    (category: 10:00) · Format: One question at a time · Going back: Not allowed ·
    Attempts left: {n} of {max} (signed-in: Unlimited, from a `GET /quiz/status` probe).
  - **Time-limit row elevated**: `text-2xl md:text-3xl font-bold`, `bg-primary/5 rounded-xl
    px-3 py-2`, clock icon — the eye's anchor.
  - Footer line: "When you finish you'll get an instant score, a topic-by-topic breakdown,
    an emailed study report, and a certificate of completion if you pass."
- **Exam conditions** (4 `check-circle` `text-accent` ticks, verbatim):
  1. One question at a time — once you move on, you can't return to a previous question.
  2. The 45-minute timer starts when you press Begin and can't be paused. (category: The 10-minute timer …)
  3. When the time runs out, your exam is submitted automatically and scored.
  4. Leaving, refreshing, or closing this page ends the attempt — your progress will be lost.
- **Integrity pledge** (quoted block `rounded-2xl border border-border bg-muted/40 p-5
  text-sm leading-relaxed`, verbatim):
  > I will complete this test on my own — with no AI tools, no search engines, no notes or
  > apps, and no help from anyone else — and I'll answer as I would in the real ICBC exam.
  > I understand this is a practice test to help me prepare, not the official ICBC knowledge test.
- **Consent checkbox** `[data-quiz-consent]`: "I have read and agree to the exam conditions
  and the integrity pledge above."
- **Begin button** `[data-quiz-begin]` (primary, with → icon, `disabled` until checked).
  Helper `[data-quiz-begin-help]` swaps: unchecked "Tick the box above to begin." ⇄ checked
  "The 45-minute timer starts as soon as you press Begin." (category: 10-minute).
- Fine print: "This is a free practice exam from BuckleUp Driving School. It is not
  affiliated with ICBC."
- Mobile: details stack 1-col; Begin sticky at the bottom.

## Screen 2 — Timed exam (`data-quiz-step="running"`)
Reuse the existing 2-col runner (category rail + one-question-forward-only card + mobile
drawer) VERBATIM — just calmer/roomier: card `p-6 md:p-10`, question `text-xl md:text-2xl`,
options `py-3.5`, no gradient. Per-category accent on active card + selected option stays.
ADD a persistent timer chip in the chrome (mobile: chrome center).
- **Timer** `[data-quiz-timer]`: `data-deadline="<epoch-ms>"` set on `/start` success =
  `Date.now() + timeLimit*1000`; one tick loop (wall-clock — backgrounding does NOT pause;
  on `visibilitychange→visible` re-render immediately, may already be expired). States via a
  single `data-timer` attr toggling **bespoke literal `.timer-*` classes** (NOT Tailwind
  variants):
  - calm `>5:00` → `.timer-calm` · warn `≤5:00` → `.timer-warn` (amber) · urgent `≤1:00` →
    `.timer-urgent` (destructive + gentle pulse, reduced-motion-safe) · expired `0:00` → overlay.
  - `[data-quiz-timer-label]` aria-hidden MM:SS (updates each second).
  - `[data-quiz-timer-live]` SEPARATE sr-only `aria-live="polite"`; announces ONLY at
    10/5/2/1/0.5 min (throttled, never per-second).
  - `[data-quiz-timer-toasts]` fixed top-right under chrome, `aria-live="assertive"`,
    fire-once toasts: at 5:00 "5 minutes remaining."; at 1:00 "1 minute remaining — finish up."
- **Auto-submit overlay** `[data-quiz-expired-overlay]` (hidden until `data-timer="expired"`):
  heading "Time's up.", body "Submitting your exam…", a 10-second visible countdown; inputs
  disabled the instant 0:00 hits, then calls the existing submit path with current answers.
  (Anon time-out skips the gate; the results page offers an inline email field for the report.)

## Screen 3 — Results (`data-quiz-step="results"`)
Reuse the existing results renderer (donut, per-topic colored table, focus-areas, cert card
on pass, CTAs) VERBATIM, inside the calm shell. Add eyebrow "Exam complete". Chrome right
action flips to "Done — back to site" (links hub, no confirm). Retake CTA label →
"Take another exam" → returns to **Screen 1** (re-consent + re-probe `/quiz/status`).

## Exit flow + guards
- `[data-quiz-exit]` chrome ghost: running → opens `[data-quiz-exit-dialog]`
  (`data-state="open|closed"`, clone `console.js` drawer: overlay + Esc + scroll-lock);
  intro/results → navigate directly.
  - Dialog: title "Leave the exam?", body "Your progress won't be saved and this attempt will
    be lost. You can start a new exam afterward, but the timer will reset.",
    `[data-quiz-exit-confirm]` "Leave exam" → hubUrl, `[data-quiz-exit-cancel]` "Keep going".
- `beforeunload` "Leave site?" guard ONLY while running (remove on submit/results).

## CSS to add to `src/css/app.css` (bespoke literals — DO NOT add amber-* to the palette)
```css
.timer-calm    { background:hsl(var(--muted)); color:hsl(var(--foreground)); }
.timer-warn    { background:#fef3c7; color:#92400e; border-color:#fcd34d; }
.timer-urgent  { background:hsl(var(--destructive)/0.10); color:hsl(var(--destructive)); border-color:hsl(var(--destructive)/0.30); }
.timer-expired { background:hsl(var(--destructive)/0.10); color:hsl(var(--destructive)); }
.exam-pulse    { animation: pulse-glow 1.4s ease-in-out infinite; } /* gate behind prefers-reduced-motion */
```
JS class constants (verbatim, like the existing OPT_BTN_ON/OFF):
`TIMER_BASE='inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-sm font-semibold tabular-nums transition-colors border border-transparent';`
then toggle one of `timer-calm|timer-warn|timer-urgent|timer-expired` keyed off `data-timer`.

## data-* hooks (new)
- `[data-exam-shell]` on the page-exam body wrapper — presence flips `initQuiz` into exam mode
  (timer + briefing + exit). Absent = legacy inline behavior untouched.
- Screen 1: `[data-quiz-consent]`, `[data-quiz-begin]`, `[data-quiz-begin-help]`,
  `[data-quiz-attempts]` (status probe).
- Timer: `[data-quiz-timer]` (+ `data-timer`, `data-deadline`), `[data-quiz-timer-label]`,
  `[data-quiz-timer-live]`, `[data-quiz-timer-toasts]`.
- Auto-submit: `[data-quiz-expired-overlay]`.
- Exit: `[data-quiz-exit]`, `[data-quiz-exit-dialog]`, `[data-quiz-exit-confirm]`, `[data-quiz-exit-cancel]`.
- Reused as-is: all `[data-quiz-panel|rail|card|option|next|drawer*|gate-*|cert*|retake|railhead*]`, `[data-stat-*]`.

## Build tasks (theme)
1. `templates/page-exam.html` — model on `page-login.html` but NO header/footer template
   parts; `<main>` + post-content only. Register in `theme.json` customTemplates as `page-exam`.
2. `patterns/exam.php` (sibling of `practice-test.php`) — read `?mode` (`sanitize_key` +
   `buckleup_quiz_is_category()` else `'full'`); compute `buckleup_quiz_js_config($mode)`,
   `buckleup_quiz_time_limit($mode)`, total, pass_pct; server-render the chrome + Screen-1
   briefing inside the `[data-quiz]` mount (`data-quiz-step="intro"`), keeping the existing
   empty running/gate/results panels. Wrap the body in `[data-exam-shell]`. Add `'exam'` to
   `buckleup_sections()` allowlist in `inc/site.php`.
3. `quiz.js`: `startTest()` POSTs `{mode, consent:true}`; on 200 read `data.timeLimit` → if >0
   set deadline + start tick loop + show timer, if 0 hide; add briefing/consent gating, timer
   states + toasts + auto-submit, exit flow + dialog + `beforeunload`. Gate ALL new code behind
   `[data-exam-shell]` so inline-landing behavior is untouched. REST contract otherwise unchanged.
4. `patterns/practice-test.php`: "Start the Free Practice Test" / "Practise {Category} Now"
   become `<a>` links to `…/exam/?mode=full` / `…/exam/?mode={slug}`; landings STOP
   instantiating the runner. Out-of-attempts handling moves to Screen 1's status probe.
5. Nav CTA: KEEP the current single-line **solid** emerald pill (already fixed in
   `site-header.php`) — it's prominent + single-line. (UX offered a soft variant; we keep solid.)
6. `make build-assets`; verify on localhost (full + a category, timer→0 auto-submit,
   reduced-motion, mobile drawer, anon gate vs logged-in skip, Mailpit email).

## Already handled (engine/SEO — don't redo)
- The exam WP page + `page-exam` template assignment + `noindex` (seed-pages.php).
- `/quiz/start` consent gate + `timeLimit`; `buckleup_quiz_time_limit($mode)`.
- No Quiz/FAQ schema emits on `/exam/` (the SEO detectors are path-prefix on the hub/category
  slugs; `exam` is neither) — plus the page is `noindex`.

## A11y (binding)
Consent gates Begin (visibly disabled + server 400) · forward-only already built · timer
wall-clock (deadline) + visibility re-render · `beforeunload` only while running · timer
announce throttled (never per-second), toasts assertive, state never color-only (icon + bold
+ textual toasts) · focus: H1 on intro, question heading on running, "Keep going" on dialog
open / return to exit on close, overlay heading on expired · reduced-motion gates the pulse,
donut anim, toast slide, smooth-scroll.

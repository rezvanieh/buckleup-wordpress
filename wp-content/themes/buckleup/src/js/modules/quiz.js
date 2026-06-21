// ICBC Class 4 practice-center runner. Enhances the server-rendered
// patterns/practice-test.php mount ([data-quiz]) into an interactive,
// single-question, forward-only test driven by the buckleup-quiz REST API. The
// static SEO body (sample questions, category grid, FAQ) stays in the DOM for
// crawlers / no-JS; only the [data-quiz] block's panels are swapped.
//
// Flow (states via data-quiz-step on the mount): intro → running → gate (anon
// only) → results. The server is authoritative and anti-peek: /quiz/start
// returns the rail MANIFEST (all categories) but only the FIRST category's
// questions; /quiz/batch fetches the NEXT category on demand once the user
// reaches it, so future categories never reach the client. Grading happens on
// /quiz/submit. We track answers { qid: displayIndex } across batches and never
// expose a correct answer until the result payload comes back.
//
// CRITICAL: never build Tailwind class strings dynamically — Tailwind purges
// classes it can't find as literal text in @source-scanned files. Every class
// used here is written out in full below, and visual state changes ride on
// data-state / data-cat attributes (styled by data-[state=…] variants + the
// .cat-* palette in src/css/app.css). Category COLOUR is ALWAYS keyed off a
// literal data-cat="<N>" integer (N from cfg.catIndex); widths / the donut
// offset are inline style NUMBERS (allowed) — never colour class strings.

import { prefersReducedMotion } from '../lib/motion-prefs.js';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const DONUT_C = 263.9; // 2πr for r=42, the results donut circumference.

// Exam timer chip — bespoke LITERAL .timer-* classes (NOT Tailwind variants, which
// would compile to nothing against bespoke CSS). The base layout class is always on
// the chip; we swap exactly one state class keyed off data-timer on each tick.
const TIMER_BASE = 'inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-sm font-semibold tabular-nums transition-colors border border-transparent';
const TIMER_STATE_CLASS = {
  calm: 'timer-calm',
  warn: 'timer-warn',
  urgent: 'timer-urgent',
  expired: 'timer-expired',
};
const WARN_AT = 5 * 60; // seconds remaining at which the chip turns amber
const URGENT_AT = 60; // …and destructive + pulse
// Live-region announce marks (seconds remaining). Throttled — never per-second.
const ANNOUNCE_MARKS = [10 * 60, 5 * 60, 2 * 60, 60, 30];

function fmtMMSS(totalSeconds) {
  const s = Math.max(0, Math.floor(totalSeconds));
  const m = Math.floor(s / 60);
  const r = s % 60;
  return `${m}:${String(r).padStart(2, '0')}`;
}

// Small escape helper — all question/option/explanation text is server-stored
// but we still escape before injecting into innerHTML (defense in depth).
function esc(s) {
  const d = document.createElement('div');
  d.textContent = s == null ? '' : String(s);
  return d.innerHTML;
}

export function initQuiz(root = document) {
  initStatsCountUp(root);

  const mount = root.querySelector('[data-quiz]');
  if (!mount) return;

  const cfgRaw = mount.getAttribute('data-quiz-config') || '{}';
  let cfg = {};
  try {
    cfg = JSON.parse(cfgRaw);
  } catch (_e) {
    cfg = {};
  }
  const mode = mount.getAttribute('data-quiz-mode') || cfg.mode || 'full';
  const passPct = Number(cfg.passPct) || 80;
  const hubUrl = cfg.hubUrl || '/';
  // slug → 0..11 index, for data-cat colour mapping (purge-safe).
  const catIndex = cfg.catIndex && typeof cfg.catIndex === 'object' ? cfg.catIndex : {};
  const idxFor = (slug) => (slug in catIndex ? Number(catIndex[slug]) : 0);

  const auth = window.buckleupAuth || {};
  const apiBase = auth.restUrl || '/wp-json/buckleup/v1/';
  const headers = { 'Content-Type': 'application/json', 'X-WP-Nonce': auth.nonce || '' };

  // Exam mode: the page-exam template wraps the mount in [data-exam-shell]. Its
  // presence flips on the briefing→consent gate, the wall-clock timer (+ states,
  // toasts, auto-submit), and the exit dialog + beforeunload guard. Absent =
  // legacy inline-landing behaviour, untouched.
  const examShell = mount.closest('[data-exam-shell]');
  const isExam = !!examShell;

  const panels = {
    intro: mount.querySelector('[data-quiz-panel="intro"]'),
    running: mount.querySelector('[data-quiz-panel="running"]'),
    gate: mount.querySelector('[data-quiz-panel="gate"]'),
    results: mount.querySelector('[data-quiz-panel="results"]'),
  };

  // Runtime state.
  let loggedIn = false;
  // session: { sessionId, total, categories:[{slug,index,label,short,total}] }
  let session = null;
  let order = []; // flat, in-test order: [{ qid, category, categoryIndex, question, options }]
  let loadedCats = 0; // how many categories' question batches have been appended to `order`
  let answers = {}; // { qid: displayIndex }
  let cursor = 0; // current question index over `order`
  let fetchingBatch = false;

  // Exam-mode runtime: the wall-clock timer + guards. `deadline` is an absolute
  // epoch-ms set on a timed /start success; backgrounding does NOT pause it.
  let deadline = 0; // epoch ms, 0 = no timer armed
  let timeLimit = 0; // seconds for this attempt (0 = untimed)
  let tickHandle = 0; // setInterval id for the tick loop
  let timerExpired = false; // latched once 0:00 fires (idempotent auto-submit)
  let beforeUnloadBound = false;
  const firedMarks = new Set(); // announce marks already spoken (throttle)
  const firedToasts = new Set(); // toast thresholds already shown (fire-once)
  const examEls = isExam
    ? {
        chrome: examShell.querySelector('[data-exam-chrome]'),
        logoLink: examShell.querySelector('[data-exam-logo-link]'),
        timer: examShell.querySelector('[data-quiz-timer]'),
        timerLabel: examShell.querySelector('[data-quiz-timer-label]'),
        timerLive: examShell.querySelector('[data-quiz-timer-live]'),
        toasts: examShell.querySelector('[data-quiz-timer-toasts]'),
        exitBtn: examShell.querySelector('[data-quiz-exit]'),
        exitDialog: examShell.querySelector('[data-quiz-exit-dialog]'),
        expiredOverlay: examShell.querySelector('[data-quiz-expired-overlay]'),
        expiredCount: examShell.querySelector('[data-quiz-expired-count]'),
        consent: mount.querySelector('[data-quiz-consent]'),
        begin: mount.querySelector('[data-quiz-begin]'),
        beginHelp: mount.querySelector('[data-quiz-begin-help]'),
      }
    : {};

  const showStep = (step) => {
    mount.setAttribute('data-quiz-step', step);
    Object.keys(panels).forEach((key) => {
      if (panels[key]) panels[key].hidden = key !== step;
    });
    if (isExam) syncExamChrome(step);
  };

  // Keep the chrome in step with the screen: the logo is UNLINKED while running
  // (a click-away is an exit); the Exit action reads "Exit exam" while running and
  // "Done — back to site" on results.
  const syncExamChrome = (step) => {
    const running = step === 'running';
    const link = examEls.logoLink;
    if (link) {
      if (running) {
        link.removeAttribute('href');
        link.setAttribute('aria-disabled', 'true');
        link.classList.add('pointer-events-none');
      } else {
        link.setAttribute('href', hubUrl);
        link.removeAttribute('aria-disabled');
        link.classList.remove('pointer-events-none');
      }
    }
    const exitLabel = examEls.exitBtn ? examEls.exitBtn.querySelector('[data-quiz-exit-label]') : null;
    if (exitLabel) {
      exitLabel.textContent = step === 'results' ? 'Done — back to site' : 'Exit exam';
    }
  };

  // Move focus to a panel heading without scrolling jankily under reduced motion.
  const focusPanel = (step) => {
    const panel = panels[step];
    if (!panel) return;
    const h = panel.querySelector('[data-quiz-focus], h2');
    if (h) {
      h.setAttribute('tabindex', '-1');
      h.focus({ preventScroll: true });
    }
    mount.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
  };

  /* ------------------------------ intro ---------------------------------- */

  const startBtn = mount.querySelector('[data-quiz-start]');
  const startWrap = mount.querySelector('[data-quiz-start-wrap]');
  const lockedWrap = mount.querySelector('[data-quiz-locked]');
  const attemptsEl = mount.querySelector('[data-quiz-attempts]');

  const renderAttempts = (status) => {
    if (!attemptsEl) return;
    // Exam Screen 1 renders attempts as a value-only "details" row ("{n} of {max}"
    // / "Unlimited") that is always visible — never hidden like the landing notice.
    if (isExam) {
      const a = status.attempts || {};
      if (status.loggedIn || a.unlimited) {
        attemptsEl.textContent = 'Unlimited';
      } else {
        const remaining = Number(a.remaining);
        const max = Number(a.max);
        attemptsEl.textContent = Number.isFinite(remaining) && max ? `${remaining} of ${max}` : '—';
      }
      return;
    }
    if (status.loggedIn) {
      attemptsEl.textContent = 'You have unlimited practice attempts.';
      attemptsEl.hidden = false;
      return;
    }
    const a = status.attempts || {};
    if (a.unlimited) {
      attemptsEl.hidden = true;
      return;
    }
    const remaining = Number(a.remaining);
    if (Number.isFinite(remaining) && remaining > 0 && Number(a.max)) {
      attemptsEl.textContent = `${remaining} of ${a.max} free attempts remaining.`;
      attemptsEl.hidden = false;
    } else {
      attemptsEl.hidden = true;
    }
  };

  const showLocked = () => {
    if (startWrap) startWrap.hidden = true;
    if (lockedWrap) lockedWrap.hidden = false;
    if (attemptsEl && !isExam) attemptsEl.hidden = true;
    // Exam Screen 1: also disable Begin + the consent box so the locked CTA reads
    // as the only path forward.
    if (isExam) {
      if (examEls.begin) examEls.begin.disabled = true;
      if (examEls.consent) examEls.consent.disabled = true;
      const consentWrap = examEls.consent ? examEls.consent.closest('.glass') : null;
      if (consentWrap) consentWrap.hidden = true;
    }
  };

  const loadStatus = () => {
    fetch(`${apiBase}quiz/status`, { headers, credentials: 'same-origin' })
      .then((res) => res.json())
      .then((data) => {
        loggedIn = !!data.loggedIn;
        renderAttempts(data);
        const a = data.attempts || {};
        if (!loggedIn && !a.unlimited && Number(a.remaining) === 0) {
          showLocked();
        }
      })
      .catch(() => {
        /* status is best-effort; leave the default Start button */
      });
  };

  /* ------------------------------ start ---------------------------------- */

  // The trigger button differs by flow: exam Screen 1 uses [data-quiz-begin]
  // (consent-gated); the landing intro uses [data-quiz-start].
  const triggerBtn = () => (isExam ? examEls.begin : startBtn);

  const startTest = () => {
    // Exam: never start without an explicit consent tick (server also enforces).
    if (isExam && examEls.consent && !examEls.consent.checked) {
      return;
    }
    const btn = triggerBtn();
    if (btn) {
      btn.disabled = true;
      btn.dataset.loading = 'true';
    }
    // Exam sends consent:true; landings omit it (the engine treats absence as the
    // legacy untimed flow). The REST contract is otherwise identical.
    const body = isExam ? { mode, consent: true } : { mode };
    fetch(`${apiBase}quiz/start`, {
      method: 'POST',
      headers,
      credentials: 'same-origin',
      body: JSON.stringify(body),
    })
      .then((res) => res.json().then((data) => ({ ok: res.ok, status: res.status, data })))
      .then(({ ok, status: code, data }) => {
        if (!ok) {
          if (code === 403) {
            showLocked();
            showStep('intro');
          } else {
            window.alert((data && (data.message || data.error)) || 'Could not start the test. Please try again.');
          }
          return;
        }
        session = {
          sessionId: data.sessionId,
          total: Number(data.total) || 0,
          categories: Array.isArray(data.categories) ? data.categories : [],
        };
        answers = {};
        cursor = 0;
        order = [];
        loadedCats = 0;
        appendBatch(data.batch); // the first category's questions ship with /start
        // Exam: arm the wall-clock timer from the server's timeLimit (seconds).
        if (isExam) {
          armTimer(Number(data.timeLimit) || 0);
          armBeforeUnload();
        }
        renderRunning();
        showStep('running');
        focusPanel('running');
      })
      .catch(() => window.alert('Something went wrong. Please try again.'))
      .finally(() => {
        if (btn) {
          btn.disabled = false;
          delete btn.dataset.loading;
        }
      });
  };

  // Append a fetched batch's questions to the flat `order`, stamping each with its
  // colour index so the running card + rail recolour per topic.
  const appendBatch = (batch) => {
    if (!batch || !Array.isArray(batch.questions)) return;
    const slug = batch.slug || '';
    const ci = Number.isFinite(Number(batch.categoryIndex)) ? Number(batch.categoryIndex) : idxFor(slug);
    batch.questions.forEach((q) => {
      order.push({
        qid: Number(q.qid),
        category: q.category || slug,
        categoryIndex: Number.isFinite(Number(q.categoryIndex)) ? Number(q.categoryIndex) : ci,
        question: q.question,
        options: Array.isArray(q.options) ? q.options : [],
      });
    });
    loadedCats += 1;
  };

  // Fetch the NEXT category on demand. Returns a promise that resolves once the
  // batch is appended (or rejects/handled on error). `done:true` means no more.
  const fetchNextBatch = () => {
    if (fetchingBatch || !session) return Promise.resolve(false);
    fetchingBatch = true;
    return fetch(`${apiBase}quiz/batch`, {
      method: 'POST',
      headers,
      credentials: 'same-origin',
      body: JSON.stringify({ sessionId: session.sessionId }),
    })
      .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
      .then(({ ok, data }) => {
        if (!ok) {
          window.alert((data && (data.message || data.error)) || 'Could not load the next questions. Please try again.');
          return false;
        }
        if (data.done && !data.batch) return false;
        if (data.batch) appendBatch(data.batch);
        return true;
      })
      .catch(() => {
        window.alert('Something went wrong loading the next questions. Please try again.');
        return false;
      })
      .finally(() => {
        fetchingBatch = false;
      });
  };

  /* ----------------------------- the rail -------------------------------- */
  // Per-category answered/total + a mini colour bar; the active row is data-
  // state="active". Categories that have all their questions answered are "done".
  // An overall progress bar caps it. The same markup powers the desktop sidebar
  // and the mobile drawer (built once, injected into both).

  const optionLetters = ['A', 'B', 'C', 'D'];

  // Option-button class lists. The selected look uses the LITERAL `.cat-*` classes
  // (which read --cat from the card's data-cat) — NOT Tailwind `data-[state]:cat-*`
  // variants, because the .cat-* classes are bespoke CSS, not Tailwind utilities,
  // so a variant on them compiles to nothing. We toggle the literal classes in JS.
  const OPT_BTN_CLASS = 'flex w-full items-start gap-3 rounded-xl border px-4 py-3 text-left text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background';
  const OPT_BTN_OFF = 'border-border bg-background hover:bg-secondary';
  const OPT_BTN_ON = 'cat-accent-border cat-accent-soft';
  const OPT_LETTER_CLASS = 'shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-full border text-xs font-semibold';
  const OPT_LETTER_OFF = 'border-border text-muted-foreground';
  const OPT_LETTER_ON = 'cat-accent-border cat-accent-text';

  // Count answered questions for a category index using `order` (loaded) — future
  // categories show 0/total from the manifest (anti-peek: we never have their qids).
  const catAnswered = (categoryIndex) =>
    order.reduce((n, q) => (q.categoryIndex === categoryIndex && answers[q.qid] !== undefined ? n + 1 : n), 0);

  const railRowsHtml = (activeIndex) => {
    const cats = (session && session.categories) || [];
    return cats
      .map((c) => {
        const ci = Number(c.index);
        const tot = Number(c.total) || 0;
        const ans = catAnswered(ci);
        const isActive = ci === activeIndex;
        const isDone = tot > 0 && ans >= tot;
        const state = isActive ? 'active' : isDone ? 'done' : 'todo';
        const w = tot ? Math.round((ans / tot) * 100) : 0;
        // `cat-rail-active` is a LITERAL class (its `.cat-rail-active .cat-label`
        // descendant rule needs the class name on the row, not a data-variant).
        const rowCls = isActive ? 'cat-rail-active' : isDone ? 'bg-muted/40 border-transparent' : 'border-transparent opacity-80';
        return (
          `<li data-cat="${ci}" data-state="${state}" class="group rounded-xl border px-3 py-2 transition-colors ${rowCls}">` +
          `<div class="flex items-center gap-2.5">` +
          `<span class="cat-dot w-2 h-2 rounded-full shrink-0 group-data-[state=todo]:opacity-40"></span>` +
          `<span class="cat-label flex-1 text-sm leading-tight text-foreground">${esc(c.short || c.label || '')}</span>` +
          `<span class="text-xs font-medium text-muted-foreground tabular-nums">${ans}/${tot}</span>` +
          `</div>` +
          `<div class="h-1.5 mt-1.5 w-full rounded-full bg-muted overflow-hidden"><div class="cat-bar-fill h-full rounded-full transition-all duration-300" style="width:${w}%"></div></div>` +
          `</li>`
        );
      })
      .join('');
  };

  const overallHtml = () => {
    const answered = Object.keys(answers).length;
    const total = session ? session.total : 0;
    const pct = total ? Math.round((answered / total) * 100) : 0;
    return (
      `<div class="flex items-center justify-between text-xs font-medium text-muted-foreground mb-1.5">` +
      `<span>Overall progress</span><span class="tabular-nums">${answered}/${total}</span>` +
      `</div>` +
      `<div class="h-2 w-full rounded-full bg-muted overflow-hidden"><div class="h-full bg-primary rounded-full transition-all duration-300" style="width:${pct}%"></div></div>`
    );
  };

  const railInnerHtml = (activeIndex) =>
    `<div class="px-2 py-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Your test</div>` +
    `<ul class="space-y-0.5 mb-3">${railRowsHtml(activeIndex)}</ul>` +
    `<div class="px-1">${overallHtml()}</div>`;

  // Update the desktop rail + drawer rail + the mobile mini-header in place (cheap;
  // no re-render of the question card).
  const refreshRail = () => {
    if (!session) return;
    const active = order[cursor] ? order[cursor].categoryIndex : -1;
    const inner = railInnerHtml(active);
    mount.querySelectorAll('[data-quiz-rail]').forEach((el) => {
      el.innerHTML = inner;
    });
    // Mobile mini-header: current category colour + name + overall.
    const cat = (session.categories || []).find((c) => Number(c.index) === active);
    const answered = Object.keys(answers).length;
    const total = session.total;
    const pct = total ? Math.round((answered / total) * 100) : 0;
    const head = mount.querySelector('[data-quiz-railhead]');
    if (head) {
      head.setAttribute('data-cat', String(active < 0 ? 0 : active));
      const nameEl = head.querySelector('[data-quiz-railhead-name]');
      if (nameEl) nameEl.textContent = cat ? cat.short || cat.label || '' : '';
      const bar = head.querySelector('[data-quiz-railhead-bar]');
      if (bar) bar.style.width = `${pct}%`;
      const count = head.querySelector('[data-quiz-railhead-count]');
      if (count) count.textContent = `${answered}/${total}`;
    }
  };

  /* ----------------------------- running --------------------------------- */
  // The running panel is the two-column shell (desktop rail + question card) and,
  // on mobile, a drawer rail cloned from console.js. Built ONCE; the question card
  // is the only part re-rendered per question; the rail refreshes in place.

  const buildRunningShell = () => {
    panels.running.innerHTML =
      // Mobile drawer trigger + sticky mini-header (current category + overall bar).
      `<div class="lg:hidden mb-4">` +
      `<div data-quiz-railhead data-cat="0" class="glass rounded-2xl border border-border p-3 flex items-center gap-3">` +
      `<span class="cat-dot w-3 h-3 rounded-full shrink-0"></span>` +
      `<div class="flex-1 min-w-0">` +
      `<div class="flex items-center justify-between gap-2 mb-1"><span data-quiz-railhead-name class="text-sm font-semibold text-foreground truncate"></span><span data-quiz-railhead-count class="text-xs text-muted-foreground tabular-nums"></span></div>` +
      `<div class="h-1.5 w-full rounded-full bg-muted overflow-hidden"><div data-quiz-railhead-bar class="h-full bg-primary rounded-full transition-all duration-300" style="width:0%"></div></div>` +
      `</div>` +
      `<button type="button" data-quiz-drawer-open aria-label="Show all topics" class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-xl border border-border text-muted-foreground hover:bg-muted transition-colors"><svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg></button>` +
      `</div>` +
      `</div>` +
      // Two-column shell.
      `<div class="lg:grid lg:grid-cols-[18rem_1fr] lg:gap-8 lg:items-start">` +
      `<aside class="hidden lg:block lg:sticky lg:top-28 self-start"><div data-quiz-rail class="glass rounded-2xl border border-border p-3"></div></aside>` +
      `<div data-quiz-card class="min-w-0"></div>` +
      `</div>` +
      // Mobile drawer (cloned console.js pattern: data-state=open|closed, overlay, Esc).
      `<div data-quiz-drawer data-state="closed" hidden class="lg:hidden fixed inset-0 z-50">` +
      `<div data-quiz-drawer-overlay class="absolute inset-0 bg-black/50 backdrop-blur-sm data-[state=closed]:opacity-0 transition-opacity duration-200"></div>` +
      `<div class="absolute left-0 top-0 bottom-0 w-[20rem] max-w-[85vw] bg-card border-r border-border shadow-2xl overflow-y-auto p-4 transition-transform duration-200 data-[state=closed]:-translate-x-full">` +
      `<div class="flex items-center justify-between mb-3"><span class="text-sm font-semibold text-foreground">Your test</span><button type="button" data-quiz-drawer-close aria-label="Close" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-muted-foreground hover:bg-muted transition-colors"><svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button></div>` +
      `<div data-quiz-rail></div>` +
      `</div>` +
      `</div>`;

    // Sync the drawer's translate-x state to its data-state (the overlay element
    // child needs the parent state too).
    const drawer = panels.running.querySelector('[data-quiz-drawer]');
    if (drawer) {
      panels.running.querySelectorAll('[data-quiz-drawer-overlay], [data-quiz-drawer] > div').forEach((el) => {
        el.setAttribute('data-state', 'closed');
      });
    }
  };

  const renderCard = () => {
    const card = panels.running.querySelector('[data-quiz-card]');
    if (!card || !session) return;
    const q = order[cursor];
    if (!q) return;
    const total = session.total;
    const num = cursor + 1;
    const picked = answers[q.qid];
    const answered = picked !== undefined;
    const isLast = num >= total;

    const opts = (q.options || [])
      .map((opt, i) => {
        const on = picked === i;
        return (
          `<button type="button" role="radio" aria-checked="${on ? 'true' : 'false'}" tabindex="${on || (picked === undefined && i === 0) ? '0' : '-1'}" data-quiz-option="${i}" data-state="${on ? 'selected' : 'unset'}" ` +
          `class="${OPT_BTN_CLASS} ${on ? OPT_BTN_ON : OPT_BTN_OFF}">` +
          `<span class="${OPT_LETTER_CLASS} ${on ? OPT_LETTER_ON : OPT_LETTER_OFF}">${optionLetters[i] || ''}</span>` +
          `<span class="pt-0.5 text-foreground">${esc(opt)}</span>` +
          `</button>`
        );
      })
      .join('');

    const cat = (session.categories || []).find((c) => Number(c.index) === q.categoryIndex);
    const catName = cat ? cat.short || cat.label || '' : '';

    card.innerHTML =
      `<div data-cat="${q.categoryIndex}" class="glass rounded-3xl border border-border p-6 md:p-8">` +
      // header: category tag + question counter
      `<div class="flex items-center justify-between gap-3 mb-5">` +
      `<span class="inline-flex items-center gap-2 px-3 py-1 rounded-full cat-accent-soft text-xs font-semibold"><span class="cat-dot w-2 h-2 rounded-full"></span><span class="cat-accent-text">${esc(catName)}</span></span>` +
      `<span class="text-sm font-medium text-muted-foreground tabular-nums">Question ${num} of ${total}</span>` +
      `</div>` +
      // aria-live category announce (visually hidden)
      `<p data-quiz-announce aria-live="polite" class="sr-only">${esc(catName)} — question ${num} of ${total}</p>` +
      // question
      `<h2 data-quiz-focus tabindex="-1" class="text-lg md:text-xl font-semibold text-foreground leading-snug mb-5 outline-none">${esc(q.question)}</h2>` +
      `<div role="radiogroup" aria-label="Answer options" class="space-y-3 mb-8">${opts}</div>` +
      // nav: forward-only — the button is hidden until an option is chosen.
      `<div class="flex items-center justify-end gap-3">` +
      (isLast
        ? `<button type="button" data-quiz-next data-final="true" ${answered ? '' : 'hidden'} class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold transition-all duration-200 h-11 px-8 bg-primary text-primary-foreground shadow-md hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50">See my results</button>`
        : `<button type="button" data-quiz-next ${answered ? '' : 'hidden'} class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold transition-all duration-200 h-11 px-8 bg-primary text-primary-foreground shadow-md hover:bg-primary/90">Next<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></button>`) +
      `</div>` +
      `</div>`;
  };

  const renderRunning = () => {
    if (!panels.running || !session) return;
    buildRunningShell();
    renderCard();
    refreshRail();
    wireDrawer();
  };

  // Re-render only the question card + rail when moving between questions (the
  // shell/drawer persist).
  const goToCard = () => {
    renderCard();
    refreshRail();
    const h = panels.running.querySelector('[data-quiz-focus]');
    if (h) h.focus({ preventScroll: true });
    if (!prefersReducedMotion()) {
      const card = panels.running.querySelector('[data-quiz-card]');
      if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  const selectOption = (idx) => {
    const q = order[cursor];
    if (!q) return;
    answers[q.qid] = idx;
    const card = panels.running.querySelector('[data-quiz-card]');
    if (!card) return;
    card.querySelectorAll('[data-quiz-option]').forEach((b) => {
      const on = Number(b.getAttribute('data-quiz-option')) === idx;
      b.setAttribute('data-state', on ? 'selected' : 'unset');
      b.setAttribute('aria-checked', on ? 'true' : 'false');
      b.setAttribute('tabindex', on ? '0' : '-1');
      // Swap the literal .cat-* / default classes (variants can't target .cat-*).
      b.className = `${OPT_BTN_CLASS} ${on ? OPT_BTN_ON : OPT_BTN_OFF}`;
      const letter = b.querySelector('span');
      if (letter) letter.className = `${OPT_LETTER_CLASS} ${on ? OPT_LETTER_ON : OPT_LETTER_OFF}`;
    });
    // Reveal the forward button.
    const next = card.querySelector('[data-quiz-next]');
    if (next) next.hidden = false;
    refreshRail();
  };

  // Advance forward only. If we've run out of loaded questions but more
  // categories remain, fetch the next batch first; if truly last, grade.
  const advance = () => {
    const total = session.total;
    if (cursor + 1 < order.length) {
      cursor += 1;
      goToCard();
      return;
    }
    // Past the loaded set: more categories to fetch?
    if (loadedCats < (session.categories || []).length) {
      const card = panels.running.querySelector('[data-quiz-card]');
      const next = card && card.querySelector('[data-quiz-next]');
      if (next) {
        next.disabled = true;
        next.dataset.loading = 'true';
      }
      fetchNextBatch().then((more) => {
        if (next) {
          next.disabled = false;
          delete next.dataset.loading;
        }
        if (more && cursor + 1 < order.length) {
          cursor += 1;
          goToCard();
        } else if (order.length >= total || loadedCats >= (session.categories || []).length) {
          onReachEnd();
        }
      });
      return;
    }
    // No more categories — this was the last question.
    onReachEnd();
  };

  const onReachEnd = () => {
    // Exam: the candidate has answered the last question — stop the clock. The gate
    // (anon) / submit shouldn't be timed, and we don't want a 0:00 auto-submit to
    // race a normal finish. The beforeunload guard stays until results.
    if (isExam) disarmTimer();
    if (loggedIn) {
      submitToServer('', '', '');
    } else {
      showStep('gate');
      focusPanel('gate');
      const nameEl = gateForm && gateForm.querySelector('input[name="name"]');
      if (nameEl) nameEl.focus({ preventScroll: true });
    }
  };

  // Arrow-key roving radiogroup over the option buttons.
  const onCardKeydown = (e) => {
    const optsEls = Array.from(panels.running.querySelectorAll('[data-quiz-option]'));
    if (!optsEls.length) return;
    const active = document.activeElement;
    let i = optsEls.indexOf(active);
    if (i === -1) return;
    let handled = true;
    if (e.key === 'ArrowDown' || e.key === 'ArrowRight') i = (i + 1) % optsEls.length;
    else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') i = (i - 1 + optsEls.length) % optsEls.length;
    else if (e.key === ' ' || e.key === 'Enter') {
      selectOption(Number(active.getAttribute('data-quiz-option')));
    } else handled = false;
    if (!handled) return;
    e.preventDefault();
    if (e.key.startsWith('Arrow')) {
      optsEls.forEach((b, j) => b.setAttribute('tabindex', j === i ? '0' : '-1'));
      optsEls[i].focus();
    }
  };

  const onRunningClick = (e) => {
    const opt = e.target.closest('[data-quiz-option]');
    if (opt) {
      selectOption(Number(opt.getAttribute('data-quiz-option')));
      return;
    }
    if (e.target.closest('[data-quiz-next]')) {
      advance();
    }
  };

  /* ------------------------- mobile rail drawer --------------------------- */
  // Clone of console.js: open via the trigger, close on X / overlay / Esc, with
  // a body scroll lock. The drawer + overlay carry data-state for the CSS slide.

  let drawerEscBound = false;
  const wireDrawer = () => {
    const drawer = panels.running.querySelector('[data-quiz-drawer]');
    if (!drawer) return;
    const setState = (state) => {
      drawer.setAttribute('data-state', state);
      drawer.querySelectorAll('[data-quiz-drawer-overlay], [data-quiz-drawer] > div').forEach((el) => el.setAttribute('data-state', state));
    };
    const open = () => {
      drawer.hidden = false;
      // next frame so the transition runs from the closed transform
      requestAnimationFrame(() => setState('open'));
      document.body.style.overflow = 'hidden';
    };
    const close = () => {
      setState('closed');
      document.body.style.overflow = '';
      window.setTimeout(() => {
        if (drawer.getAttribute('data-state') === 'closed') drawer.hidden = true;
      }, 220);
    };
    panels.running.querySelectorAll('[data-quiz-drawer-open]').forEach((b) => b.addEventListener('click', open));
    drawer.querySelectorAll('[data-quiz-drawer-close], [data-quiz-drawer-overlay]').forEach((b) => b.addEventListener('click', close));
    if (!drawerEscBound) {
      document.addEventListener('keydown', (e) => {
        const d = panels.running && panels.running.querySelector('[data-quiz-drawer]');
        if (e.key === 'Escape' && d && d.getAttribute('data-state') === 'open') {
          d.setAttribute('data-state', 'closed');
          d.querySelectorAll('[data-quiz-drawer-overlay], [data-quiz-drawer] > div').forEach((el) => el.setAttribute('data-state', 'closed'));
          document.body.style.overflow = '';
          window.setTimeout(() => {
            if (d.getAttribute('data-state') === 'closed') d.hidden = true;
          }, 220);
        }
      });
      drawerEscBound = true;
    }
  };

  /* ------------------------------- gate ---------------------------------- */
  // Logged-in users submit straight through; anon users pass the email (+ optional
  // name) gate first. We grade (POST /quiz/submit) only once we have an email.

  const gateForm = mount.querySelector('[data-quiz-gate-form]');
  const gateError = mount.querySelector('[data-quiz-gate-error]');
  const gateEmail = gateForm ? gateForm.querySelector('input[name="email"]') : null;
  const gateName = gateForm ? gateForm.querySelector('input[name="name"]') : null;
  const gateWebsite = gateForm ? gateForm.querySelector('input[name="website"]') : null;
  const gateSubmit = mount.querySelector('[data-quiz-gate-submit]');

  const showGateError = (msg) => {
    if (!gateError) return;
    gateError.textContent = msg;
    gateError.hidden = false;
  };
  const clearGateError = () => {
    if (!gateError) return;
    gateError.textContent = '';
    gateError.hidden = true;
  };

  if (gateForm) {
    gateForm.addEventListener('submit', (e) => {
      e.preventDefault();
      clearGateError();
      const email = gateEmail ? gateEmail.value.trim() : '';
      const name = gateName ? gateName.value.trim() : '';
      const website = gateWebsite ? gateWebsite.value : '';
      if (!EMAIL_RE.test(email)) {
        showGateError('Please enter a valid email address.');
        return;
      }
      submitToServer(email, name, website);
    });
  }

  const submitToServer = (email, name, website) => {
    if (!session) return;
    const btns = [gateSubmit, panels.running && panels.running.querySelector('[data-quiz-next]')].filter(Boolean);
    btns.forEach((b) => {
      b.disabled = true;
      b.dataset.loading = 'true';
    });
    fetch(`${apiBase}quiz/submit`, {
      method: 'POST',
      headers,
      credentials: 'same-origin',
      body: JSON.stringify({ sessionId: session.sessionId, answers, email, name, website }),
    })
      .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
      .then(({ ok, data }) => {
        if (!ok) {
          showGateError((data && (data.message || data.error)) || 'Could not submit your test. Please try again.');
          // A failed submit (e.g. while the auto-submit overlay is up) shouldn't
          // strand the candidate behind the overlay — drop it back to the gate.
          if (isExam) hideExpiredOverlay();
          return;
        }
        // Exam: the attempt is over — disarm the timer + remove the unload guard.
        if (isExam) {
          disarmTimer();
          disarmBeforeUnload();
          hideExpiredOverlay();
        }
        renderResults(data, email);
        showStep('results');
        focusPanel('results');
      })
      .catch(() => showGateError('Something went wrong. Please try again.'))
      .finally(() => {
        btns.forEach((b) => {
          b.disabled = false;
          delete b.dataset.loading;
        });
      });
  };

  /* ------------------------------ results -------------------------------- */

  // slug → {label, short, index} from the result manifest (preferred) or session.
  const catMetaMap = (result) => {
    const map = {};
    const list = (result && Array.isArray(result.categories) && result.categories) || (session && session.categories) || [];
    list.forEach((c) => {
      map[c.slug] = { label: c.label || c.slug, short: c.short || c.label || c.slug, index: Number(c.index) };
    });
    return map;
  };

  const renderResults = (result, email) => {
    if (!panels.results) return;
    const passed = !!result.passed;
    const pct = Number(result.pct) || 0;
    const cats = catMetaMap(result);
    const labelFor = (slug) => (cats[slug] ? cats[slug].label : slug);
    const idxForSlug = (slug) => (cats[slug] ? cats[slug].index : idxFor(slug));

    // --- Score donut (inline SVG; offset = C*(1 - pct/100), set inline). ---
    const offset = (DONUT_C * (1 - pct / 100)).toFixed(1);
    const donut =
      `<div class="relative w-36 h-36 shrink-0">` +
      `<svg viewBox="0 0 100 100" class="w-full h-full -rotate-90" aria-hidden="true">` +
      `<circle cx="50" cy="50" r="42" fill="none" stroke="hsl(var(--muted))" stroke-width="9"></circle>` +
      `<circle data-state="${passed ? 'passed' : 'failed'}" cx="50" cy="50" r="42" fill="none" stroke-width="9" stroke-linecap="round" ` +
      `class="data-[state=passed]:stroke-accent data-[state=failed]:stroke-destructive transition-all duration-700" ` +
      `stroke-dasharray="${DONUT_C}" stroke-dashoffset="${prefersReducedMotion() ? offset : DONUT_C}" style="--final-offset:${offset}"></circle>` +
      `</svg>` +
      `<div class="absolute inset-0 flex flex-col items-center justify-center">` +
      `<span class="text-3xl font-bold text-foreground tabular-nums">${pct}%</span>` +
      `<span class="text-xs text-muted-foreground">${result.score}/${result.total}</span>` +
      `</div>` +
      `</div>`;

    const badge = passed
      ? `<span data-state="passed" class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-sm font-semibold bg-accent/15 text-accent border border-accent/30"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 12 2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>Passed</span>`
      : `<span data-state="failed" class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-sm font-semibold bg-destructive/15 text-destructive border border-destructive/30"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>Keep practising</span>`;

    const emailNote =
      email && !loggedIn
        ? `<p class="text-sm text-muted-foreground mt-3">We emailed your results to <span class="font-medium text-foreground">${esc(email)}</span>.</p>`
        : loggedIn
          ? `<p class="text-sm text-muted-foreground mt-3">We emailed your results to your account email.</p>`
          : '';

    // --- Score-by-topic table (category-coloured bars; weak rows flagged). ---
    const breakdown = result.breakdown || {};
    const bdSlugs = Object.keys(breakdown);
    const bdRows = bdSlugs
      .map((slug) => {
        const b = breakdown[slug] || {};
        const corr = Number(b.correct) || 0;
        const tot = Number(b.total) || 0;
        const w = tot ? Math.round((corr / tot) * 100) : 0;
        const weak = w < passPct;
        return (
          `<tr data-cat="${idxForSlug(slug)}" data-state="${weak ? 'weak' : 'ok'}" class="data-[state=weak]:bg-destructive/5">` +
          `<td class="py-2.5 pr-3 align-middle"><span class="inline-flex items-center gap-2"><span class="cat-dot w-2 h-2 rounded-full shrink-0"></span><span class="text-sm font-medium text-foreground">${esc(labelFor(slug))}</span></span></td>` +
          `<td class="py-2.5 px-3 align-middle w-1/2"><div class="h-2 w-full rounded-full bg-muted overflow-hidden"><div class="cat-bar-fill h-full rounded-full" style="width:${w}%"></div></div></td>` +
          `<td class="py-2.5 pl-3 align-middle text-right text-sm text-muted-foreground tabular-nums">${corr}/${tot}</td>` +
          `</tr>`
        );
      })
      .join('');
    const bdTable = bdRows
      ? `<div class="glass rounded-3xl border border-border p-6 md:p-8 mb-8"><h3 class="text-lg font-semibold text-foreground mb-1">Score by topic</h3>` +
        `<p class="text-sm text-muted-foreground mb-5">Find and fix your mistakes before the real exam — here's exactly which topics cost you points, ranked weakest-first.</p>` +
        `<table class="w-full border-collapse"><tbody>${bdRows}</tbody></table></div>`
      : '';

    // --- Focus your study: weakest 2–3 categories (correct/total asc). ---
    const ranked = bdSlugs
      .map((slug) => {
        const b = breakdown[slug] || {};
        const tot = Number(b.total) || 0;
        return { slug, ratio: tot ? (Number(b.correct) || 0) / tot : 1, tot };
      })
      .filter((r) => r.tot > 0 && r.ratio < 1)
      .sort((a, b) => a.ratio - b.ratio)
      .slice(0, 3);
    const focusLinks = ranked
      .map(
        (r) =>
          `<a href="${esc(hubUrl + r.slug + '/')}" data-cat="${idxForSlug(r.slug)}" class="group flex items-center gap-2.5 rounded-xl border border-border bg-background px-4 py-3 hover:bg-secondary transition-colors">` +
          `<span class="cat-dot w-2.5 h-2.5 rounded-full shrink-0"></span>` +
          `<span class="flex-1 text-sm font-medium text-foreground">${esc(labelFor(r.slug))}</span>` +
          `<span class="cat-accent-text text-xs font-semibold">${Math.round(r.ratio * 100)}%</span>` +
          `<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>` +
          `</a>`
      )
      .join('');
    const focusBlock = focusLinks
      ? `<div class="glass rounded-3xl border border-border p-6 md:p-8 mb-8"><h3 class="text-lg font-semibold text-foreground mb-1">Focus your study</h3>` +
        `<p class="text-sm text-muted-foreground mb-5">Brush up on these topics first — they cost you the most points.</p>` +
        `<div class="grid gap-2.5 sm:grid-cols-2">${focusLinks}</div></div>`
      : '';

    // --- Certificate card (pass only). If no name yet → inline claim form. ---
    const hasName = result.name && String(result.name).trim() !== '';
    let certCard = '';
    if (passed) {
      const claimedBtn = result.certificateUrl
        ? `<a href="${esc(result.certificateUrl)}" data-quiz-cert-link class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold transition-all duration-200 h-12 px-7 bg-accent text-accent-foreground shadow-md hover:bg-accent/90"><svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>View your certificate</a>`
        : '';
      const claimForm = !hasName
        ? `<form data-quiz-claim-form novalidate class="mt-5 flex flex-col sm:flex-row items-stretch gap-2.5 max-w-md mx-auto">` +
          `<input type="text" name="name" required autocomplete="name" placeholder="Your full name" aria-label="Your full name for the certificate" class="flex h-12 w-full min-w-0 rounded-full border border-input bg-background px-4 py-2 text-base text-foreground shadow-sm transition-all duration-200 placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:border-accent md:text-sm">` +
          `<button type="submit" data-quiz-claim-submit class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold transition-all duration-200 h-12 px-6 bg-accent text-accent-foreground shadow-md hover:bg-accent/90 disabled:pointer-events-none disabled:opacity-50 shrink-0">Claim certificate</button>` +
          `</form><p data-quiz-claim-error hidden role="alert" aria-live="polite" class="text-sm text-destructive mt-2">${''}</p>`
        : '';
      certCard =
        `<div data-quiz-cert class="glass rounded-3xl border border-accent/30 bg-accent/5 p-7 md:p-9 text-center mb-8">` +
        `<span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-accent/15 text-accent mb-4"><svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg></span>` +
        `<h3 class="text-xl md:text-2xl font-bold text-foreground mb-2">Congratulations — You Passed the Practice Test!</h3>` +
        `<p class="text-muted-foreground max-w-md mx-auto">${hasName ? `Your certificate of completion is ready, ${esc(result.name)}.` : 'Add your name to claim your printable certificate of completion.'}</p>` +
        `<div data-quiz-cert-action class="mt-5 flex justify-center">${claimedBtn}</div>` +
        claimForm +
        `</div>`;
    }

    panels.results.innerHTML =
      // headline
      `<div class="glass rounded-3xl border border-border p-8 md:p-10 mb-8">` +
      `<div class="flex flex-col sm:flex-row items-center gap-6 sm:gap-8">` +
      donut +
      `<div class="text-center sm:text-left flex-1">` +
      `<div class="mb-3">${badge}</div>` +
      `<h2 data-quiz-focus tabindex="-1" class="text-2xl md:text-3xl font-bold text-foreground mb-2 outline-none">${passed ? "You're test-ready!" : 'Keep practising'}</h2>` +
      `<p class="text-muted-foreground">Here's how you did — your score, plus a topic-by-topic breakdown so you know exactly what to brush up before the real ICBC exam.</p>` +
      emailNote +
      `</div>` +
      `</div>` +
      `</div>` +
      certCard +
      bdTable +
      focusBlock +
      // CTAs
      `<div class="flex flex-col sm:flex-row items-center justify-center gap-3">` +
      `<button type="button" data-quiz-retake class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold transition-all duration-200 h-12 px-6 bg-primary text-primary-foreground shadow-md hover:bg-primary/90"><svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>Take another test</button>` +
      `<a href="${esc(hubUrl)}" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold transition-all duration-200 h-12 px-6 border-2 border-border bg-background text-foreground shadow-sm hover:bg-secondary">Practise a topic</a>` +
      `<a href="/contact/" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold transition-all duration-200 h-12 px-6 border-2 border-border bg-background text-foreground shadow-sm hover:bg-secondary">Book a lesson</a>` +
      `</div>`;

    // Animate the donut from full offset to its final value (skip under reduced motion).
    if (!prefersReducedMotion()) {
      const ring = panels.results.querySelector('circle[data-state]');
      if (ring) requestAnimationFrame(() => requestAnimationFrame(() => { ring.style.strokeDashoffset = offset; }));
    }

    // Wire the claim form (pass + no-name path).
    wireClaim(result);
  };

  // Post-results "add your name to claim your certificate" — POST /quiz/claim/{token}.
  const wireClaim = (result) => {
    const form = panels.results.querySelector('[data-quiz-claim-form]');
    if (!form || !result.resultToken) return;
    const err = panels.results.querySelector('[data-quiz-claim-error]');
    const btn = form.querySelector('[data-quiz-claim-submit]');
    const input = form.querySelector('input[name="name"]');
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      if (err) err.hidden = true;
      const name = input ? input.value.trim() : '';
      if (!name) {
        if (err) {
          err.textContent = 'Please enter a name for your certificate.';
          err.hidden = false;
        }
        return;
      }
      if (btn) {
        btn.disabled = true;
        btn.dataset.loading = 'true';
      }
      fetch(`${apiBase}quiz/claim/${result.resultToken}`, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify({ name }),
      })
        .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
          if (!ok) {
            if (err) {
              err.textContent = (data && (data.message || data.error)) || 'Could not save your name. Please try again.';
              err.hidden = false;
            }
            return;
          }
          // Swap the claim form for the certificate button.
          form.remove();
          if (err) err.remove();
          const action = panels.results.querySelector('[data-quiz-cert-action]');
          if (action && data.certificateUrl) {
            action.innerHTML = `<a href="${esc(data.certificateUrl)}" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold transition-all duration-200 h-12 px-7 bg-accent text-accent-foreground shadow-md hover:bg-accent/90"><svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>View your certificate</a>`;
          }
        })
        .catch(() => {
          if (err) {
            err.textContent = 'Something went wrong. Please try again.';
            err.hidden = false;
          }
        })
        .finally(() => {
          if (btn) {
            btn.disabled = false;
            delete btn.dataset.loading;
          }
        });
    });
  };

  /* ===================== EXAM MODE — timer + guards ====================== */
  // Everything below runs ONLY when the mount is inside [data-exam-shell]. It adds
  // the consent→Begin gate, the wall-clock timer (states/toasts/auto-submit), and
  // the exit dialog + beforeunload guard. The REST contract is unchanged; this is
  // purely the distraction-free timed shell over the same engine.

  /* ---- consent → Begin gating (Screen 1) ---- */
  const syncBegin = () => {
    if (!examEls.begin || !examEls.consent) return;
    const ok = !!examEls.consent.checked;
    examEls.begin.disabled = !ok;
    if (examEls.beginHelp) {
      examEls.beginHelp.textContent = ok ? beginHelpReady() : 'Tick the box above to begin.';
    }
  };
  // "The 45-minute timer starts as soon as you press Begin." — minutes derived from
  // buckleup_quiz_time_limit, which the server also returns on /start. At intro we
  // don't have it yet, so read the server-rendered chip label (MM:SS → minutes).
  const beginHelpReady = () => {
    let mins = 0;
    if (examEls.timerLabel) {
      const m = String(examEls.timerLabel.textContent || '').split(':')[0];
      mins = parseInt(m, 10) || 0;
    }
    return mins > 0
      ? `The ${mins}-minute timer starts as soon as you press Begin.`
      : 'The timer starts as soon as you press Begin.';
  };

  /* ---- timer: arm / disarm / tick ---- */
  const armTimer = (seconds) => {
    timeLimit = Number(seconds) || 0;
    timerExpired = false;
    firedMarks.clear();
    firedToasts.clear();
    if (timeLimit <= 0) {
      // Untimed mode — keep the chip hidden, no loop.
      if (examEls.timer) examEls.timer.hidden = true;
      return;
    }
    deadline = Date.now() + timeLimit * 1000;
    if (examEls.timer) examEls.timer.hidden = false;
    renderTimer();
    if (tickHandle) window.clearInterval(tickHandle);
    tickHandle = window.setInterval(renderTimer, 1000);
  };

  const disarmTimer = () => {
    if (tickHandle) {
      window.clearInterval(tickHandle);
      tickHandle = 0;
    }
    deadline = 0;
    if (examEls.timer) examEls.timer.hidden = true;
  };

  const remainingSeconds = () => (deadline ? Math.max(0, Math.round((deadline - Date.now()) / 1000)) : 0);

  const setTimerState = (state) => {
    if (!examEls.timer) return;
    examEls.timer.setAttribute('data-timer', state);
    examEls.timer.className = `${TIMER_BASE} ${TIMER_STATE_CLASS[state] || TIMER_STATE_CLASS.calm}`;
    // Gentle pulse only in the urgent final minute (reduced-motion neutralises it
    // via the global animation override, but gate explicitly too).
    if (state === 'urgent' && !prefersReducedMotion()) {
      examEls.timer.classList.add('exam-pulse');
    } else {
      examEls.timer.classList.remove('exam-pulse');
    }
  };

  const renderTimer = () => {
    if (!deadline) return;
    const left = remainingSeconds();
    if (examEls.timerLabel) examEls.timerLabel.textContent = fmtMMSS(left);

    if (left <= 0) {
      setTimerState('expired');
      onTimeUp();
      return;
    }
    setTimerState(left <= URGENT_AT ? 'urgent' : left <= WARN_AT ? 'warn' : 'calm');
    announceTime(left);
    toastTime(left);
  };

  // sr-only polite announce ONLY at the marks (throttled — never per-second).
  const announceTime = (left) => {
    if (!examEls.timerLive) return;
    for (const mark of ANNOUNCE_MARKS) {
      if (left <= mark && !firedMarks.has(mark)) {
        firedMarks.add(mark);
        const m = mark / 60;
        const label = m >= 1 ? `${m} minute${m === 1 ? '' : 's'} remaining.` : '30 seconds remaining.';
        examEls.timerLive.textContent = label;
        break;
      }
    }
  };

  // Assertive fire-once toasts at 5:00 and 1:00.
  const toastTime = (left) => {
    if (!examEls.toasts) return;
    const push = (key, text) => {
      if (firedToasts.has(key)) return;
      firedToasts.add(key);
      const t = document.createElement('div');
      t.className =
        'pointer-events-auto glass border border-border rounded-xl shadow-lg px-4 py-2.5 text-sm font-medium text-foreground' +
        (prefersReducedMotion() ? '' : ' transition-all duration-300');
      t.textContent = text;
      examEls.toasts.appendChild(t);
      window.setTimeout(() => {
        t.style.opacity = '0';
        window.setTimeout(() => t.remove(), 320);
      }, 6000);
    };
    if (left <= WARN_AT && left > URGENT_AT) push('5', '5 minutes remaining.');
    if (left <= URGENT_AT) push('1', '1 minute remaining — finish up.');
  };

  /* ---- auto-submit overlay at 0:00 ---- */
  const onTimeUp = () => {
    if (timerExpired) return; // idempotent
    timerExpired = true;
    if (tickHandle) {
      window.clearInterval(tickHandle);
      tickHandle = 0;
    }
    // The answers so far are locked in — disable every input the instant 0:00 hits.
    if (panels.running) {
      panels.running.querySelectorAll('[data-quiz-option], [data-quiz-next]').forEach((el) => {
        el.setAttribute('disabled', 'true');
      });
    }

    // Logged-in: the server grades on the account email, so auto-submit straight
    // through under the overlay. Anonymous: the server requires an email to grade,
    // so the overlay confirms "time's up", then hands off to the gate — the locked
    // answers submit as soon as the candidate enters their email.
    if (loggedIn) {
      showExpiredOverlay();
      // Visible ~10s reassurance countdown; the submit fires in parallel.
      let n = 10;
      if (examEls.expiredCount) examEls.expiredCount.textContent = `(${n})`;
      const cd = window.setInterval(() => {
        n -= 1;
        if (examEls.expiredCount) examEls.expiredCount.textContent = n > 0 ? `(${n})` : '';
        if (n <= 0) window.clearInterval(cd);
      }, 1000);
      window.setTimeout(() => submitToServer('', '', ''), prefersReducedMotion() ? 0 : 600);
    } else {
      // Brief "time's up" flash, then the email gate (answers are already frozen).
      showExpiredOverlay();
      window.setTimeout(() => {
        hideExpiredOverlay();
        showStep('gate');
        focusPanel('gate');
        const emailEl = gateForm && gateForm.querySelector('input[name="email"]');
        if (emailEl) emailEl.focus({ preventScroll: true });
      }, prefersReducedMotion() ? 300 : 1400);
    }
  };

  const showExpiredOverlay = () => {
    if (!examEls.expiredOverlay) return;
    examEls.expiredOverlay.hidden = false;
    document.body.style.overflow = 'hidden';
    const h = examEls.expiredOverlay.querySelector('#bu-exam-expired-title');
    if (h) h.focus({ preventScroll: true });
  };
  const hideExpiredOverlay = () => {
    if (!examEls.expiredOverlay) return;
    examEls.expiredOverlay.hidden = true;
    document.body.style.overflow = '';
  };

  /* ---- beforeunload guard (only while running) ---- */
  const onBeforeUnload = (e) => {
    e.preventDefault();
    e.returnValue = '';
    return '';
  };
  const armBeforeUnload = () => {
    if (beforeUnloadBound) return;
    window.addEventListener('beforeunload', onBeforeUnload);
    beforeUnloadBound = true;
  };
  const disarmBeforeUnload = () => {
    if (!beforeUnloadBound) return;
    window.removeEventListener('beforeunload', onBeforeUnload);
    beforeUnloadBound = false;
  };

  // Re-render the timer immediately when the tab becomes visible again (it may have
  // already expired while backgrounded — the wall-clock deadline doesn't pause).
  if (isExam) {
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden && deadline && !timerExpired) renderTimer();
    });
  }

  /* ---- exit dialog (clone of the console.js drawer) ---- */
  let exitReturnFocus = null;
  const openExitDialog = () => {
    const dlg = examEls.exitDialog;
    if (!dlg) return;
    exitReturnFocus = document.activeElement;
    dlg.hidden = false;
    requestAnimationFrame(() => {
      dlg.querySelectorAll('[data-quiz-exit-overlay], [role="dialog"]').forEach((el) => el.setAttribute('data-state', 'open'));
      dlg.setAttribute('data-state', 'open');
    });
    document.body.style.overflow = 'hidden';
    const cancel = dlg.querySelector('[data-quiz-exit-cancel]');
    if (cancel) cancel.focus({ preventScroll: true });
  };
  const closeExitDialog = () => {
    const dlg = examEls.exitDialog;
    if (!dlg) return;
    dlg.setAttribute('data-state', 'closed');
    dlg.querySelectorAll('[data-quiz-exit-overlay], [role="dialog"]').forEach((el) => el.setAttribute('data-state', 'closed'));
    document.body.style.overflow = '';
    window.setTimeout(() => {
      if (dlg.getAttribute('data-state') === 'closed') dlg.hidden = true;
    }, 220);
    if (exitReturnFocus && typeof exitReturnFocus.focus === 'function') exitReturnFocus.focus({ preventScroll: true });
  };
  // Exit chrome action: while running → confirm; intro/results → leave straight.
  const onExitClick = () => {
    const step = mount.getAttribute('data-quiz-step');
    if (step === 'running') {
      openExitDialog();
    } else {
      disarmBeforeUnload();
      window.location.href = hubUrl;
    }
  };
  const confirmExit = () => {
    disarmTimer();
    disarmBeforeUnload();
    window.location.href = hubUrl;
  };

  /* ------------------------------ wiring --------------------------------- */

  if (startBtn) startBtn.addEventListener('click', startTest);
  if (isExam) {
    if (examEls.begin) examEls.begin.addEventListener('click', startTest);
    if (examEls.consent) {
      examEls.consent.addEventListener('change', syncBegin);
      syncBegin(); // initial (unchecked → disabled)
    }
    if (examEls.exitBtn) examEls.exitBtn.addEventListener('click', onExitClick);
    if (examEls.exitDialog) {
      examEls.exitDialog.querySelectorAll('[data-quiz-exit-cancel], [data-quiz-exit-overlay]').forEach((b) => b.addEventListener('click', closeExitDialog));
      const confirmBtn = examEls.exitDialog.querySelector('[data-quiz-exit-confirm]');
      if (confirmBtn) confirmBtn.addEventListener('click', confirmExit);
    }
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && examEls.exitDialog && examEls.exitDialog.getAttribute('data-state') === 'open') {
        closeExitDialog();
      }
    });
  }
  if (panels.running) {
    panels.running.addEventListener('click', onRunningClick);
    panels.running.addEventListener('keydown', onCardKeydown);
  }
  if (panels.results) {
    panels.results.addEventListener('click', (e) => {
      if (e.target.closest('[data-quiz-retake]')) {
        session = null;
        answers = {};
        cursor = 0;
        order = [];
        loadedCats = 0;
        clearGateError();
        if (gateForm) gateForm.reset();
        document.body.style.overflow = '';
        // Exam retake → back to Screen 1: re-consent + re-probe /quiz/status.
        if (isExam) {
          disarmTimer();
          disarmBeforeUnload();
          timerExpired = false;
          if (examEls.consent) examEls.consent.checked = false;
          syncBegin();
        }
        showStep('intro');
        loadStatus();
        focusPanel('intro');
      }
    });
  }

  // Initial: probe attempt status, leave intro visible.
  showStep('intro');
  loadStatus();
}

/* --------------------------- aggregate stats --------------------------- */
// Reduced-motion-safe count-up for the social-proof strip ([data-quiz-stats] with
// [data-stat-count][data-stat-value]). Animates once on first scroll into view;
// under reduced motion the final values (already server-rendered) just stay.

function initStatsCountUp(root) {
  const strip = root.querySelector('[data-quiz-stats]');
  if (!strip) return;
  const counters = Array.from(strip.querySelectorAll('[data-stat-count]'));
  if (!counters.length || prefersReducedMotion() || typeof IntersectionObserver === 'undefined') return;

  const run = () => {
    counters.forEach((el) => {
      const target = Number(el.getAttribute('data-stat-value')) || 0;
      if (target <= 0) return;
      const start = performance.now();
      const dur = 900;
      const step = (now) => {
        const t = Math.min(1, (now - start) / dur);
        const eased = 1 - Math.pow(1 - t, 3);
        el.textContent = Math.round(target * eased).toLocaleString();
        if (t < 1) requestAnimationFrame(step);
        else el.textContent = target.toLocaleString();
      };
      requestAnimationFrame(step);
    });
  };

  const io = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          run();
          io.disconnect();
        }
      });
    },
    { threshold: 0.4 }
  );
  io.observe(strip);
}

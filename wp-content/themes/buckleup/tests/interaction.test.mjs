// Smoke tests for the vanilla-JS interaction layer, run in jsdom (node).
//
// These validate the *logic* of the modules — the data-state/attribute toggles
// the components and CSS depend on — by importing the source modules directly and
// driving synthetic DOM events. The animation libraries (Motion One inView/animate,
// WAAPI FLIP) need a real layout/raf pipeline and are exercised in the browser by
// the QA Playwright harness (Tasks #8/#9); here we assert the modules initialize
// without throwing and that the stateful components flip correctly.
//
// Run: node tests/interaction.test.mjs   (exits non-zero on failure)

import { JSDOM } from 'jsdom';

let pass = 0;
let fail = 0;
function ok(label, cond) {
  if (cond) {
    pass++;
    console.log(`  ok   ${label}`);
  } else {
    fail++;
    console.error(`  FAIL ${label}`);
  }
}

function setupDom(html) {
  const dom = new JSDOM(`<!doctype html><html><head></head><body>${html}</body></html>`, {
    pretendToBeVisual: true,
    url: 'http://localhost/',
  });
  const { window } = dom;
  // Wire globals the modules read. jsdom already provides localStorage,
  // matchMedia, CustomEvent, etc. on its window; only stub what's missing.
  global.window = window;
  global.document = window.document;
  if (!window.matchMedia) {
    window.matchMedia = () => ({
      matches: false,
      addEventListener() {},
      removeEventListener() {},
      addListener() {},
      removeListener() {},
    });
  }
  global.matchMedia = window.matchMedia;
  global.CustomEvent = window.CustomEvent;
  global.Event = window.Event;
  // Motion One's inView resolves elements against the global Element ctor; jsdom
  // exposes it on window, not node's global. Browsers have it globally.
  global.Element = window.Element;
  global.HTMLElement = window.HTMLElement;
  global.Node = window.Node;
  // jsdom doesn't implement IntersectionObserver, which Motion One's inView uses.
  // Stub a no-op so initReveals wires up without throwing (real reveal behavior is
  // covered by the browser QA harness).
  if (!window.IntersectionObserver) {
    window.IntersectionObserver = class {
      constructor(cb) { this.cb = cb; }
      observe() {}
      unobserve() {}
      disconnect() {}
      takeRecords() { return []; }
    };
  }
  global.IntersectionObserver = window.IntersectionObserver;
  window.requestAnimationFrame = (cb) => setTimeout(() => cb(Date.now()), 0);
  global.requestAnimationFrame = window.requestAnimationFrame;
  global.localStorage = window.localStorage;
  // jsdom lacks element.animate by default in older versions; stub it as a no-op
  // returning a finished-like object so FLIP modules don't throw.
  window.Element.prototype.animate = window.Element.prototype.animate || function () {
    return { finished: Promise.resolve(), onfinish: null, cancel() {} };
  };
  return window;
}

function click(el) {
  el.dispatchEvent(new global.window.Event('click', { bubbles: true, cancelable: true }));
}

// --- Switch + FAQ (forms.js) ---------------------------------------------
{
  const win = setupDom(`
    <button data-switch data-slot="switch" data-state="unchecked" aria-checked="false">
      <span data-slot="switch-thumb" data-state="unchecked"></span>
    </button>
    <div data-faq>
      <details data-faq-item data-state="closed"><summary>Q</summary><div data-faq-panel>A</div></details>
    </div>`);
  const { initForms } = await import('../src/js/modules/forms.js');
  initForms(win.document);

  const sw = win.document.querySelector('[data-switch]');
  click(sw);
  ok('Switch flips to checked on click', sw.getAttribute('data-state') === 'checked' && sw.getAttribute('aria-checked') === 'true');
  ok('Switch thumb mirrors state', sw.querySelector('[data-slot="switch-thumb"]').getAttribute('data-state') === 'checked');
  click(sw);
  ok('Switch flips back to unchecked', sw.getAttribute('data-state') === 'unchecked');
}

// --- Dialog (overlays.js) -------------------------------------------------
{
  const win = setupDom(`
    <button data-dialog-open="demo">open</button>
    <div data-dialog="demo" data-state="closed" hidden>
      <div data-dialog-overlay data-state="closed"></div>
      <div data-slot="dialog-content" data-state="closed"><button data-dialog-close>x</button></div>
    </div>`);
  const { initOverlays } = await import('../src/js/modules/overlays.js');
  initOverlays(win.document);

  const dialog = win.document.querySelector('[data-dialog="demo"]');
  click(win.document.querySelector('[data-dialog-open]'));
  ok('Dialog opens (data-state=open, not hidden)', dialog.getAttribute('data-state') === 'open' && dialog.hidden === false);
  click(dialog.querySelector('[data-dialog-close]'));
  ok('Dialog closes on close button', dialog.getAttribute('data-state') === 'closed');
}

// --- Dropdown (overlays.js) ----------------------------------------------
{
  const win = setupDom(`
    <div data-dropdown>
      <button data-dropdown-trigger aria-expanded="false">menu</button>
      <div data-dropdown-content data-state="closed" hidden><a data-dropdown-item href="#">item</a></div>
    </div>`);
  const { initOverlays } = await import('../src/js/modules/overlays.js');
  initOverlays(win.document);

  const trigger = win.document.querySelector('[data-dropdown-trigger]');
  const content = win.document.querySelector('[data-dropdown-content]');
  click(trigger);
  ok('Dropdown opens on trigger click', content.getAttribute('data-state') === 'open' && trigger.getAttribute('aria-expanded') === 'true');
  ok('Dropdown content gets a data-side default', content.getAttribute('data-side') === 'bottom');
}

// --- Select (overlays.js) -------------------------------------------------
{
  const win = setupDom(`
    <div data-select>
      <button data-select-trigger aria-expanded="false"><span data-select-value data-placeholder>Pick</span></button>
      <select hidden><option value=""></option><option value="a">A</option></select>
      <div data-select-content data-state="closed" hidden>
        <div data-select-item="a">A</div>
      </div>
    </div>`);
  const { initOverlays } = await import('../src/js/modules/overlays.js');
  initOverlays(win.document);

  const trigger = win.document.querySelector('[data-select-trigger]');
  click(trigger);
  const content = win.document.querySelector('[data-select-content]');
  ok('Select opens on trigger', content.getAttribute('data-state') === 'open');
  click(win.document.querySelector('[data-select-item="a"]'));
  ok('Select item sets value text', win.document.querySelector('[data-select-value]').textContent === 'A');
  ok('Select syncs native <select>', win.document.querySelector('select').value === 'a');
  ok('Select closes after pick', content.getAttribute('data-state') === 'closed');
}

// --- Theme (theme.js) -----------------------------------------------------
{
  const win = setupDom(`
    <img data-logo data-logo-light="/logo.png" data-logo-dark="/logo-dark.png" src="/logo.png">
    <button data-theme-toggle></button>
    <button data-theme-set="light"></button>
    <button data-theme-set="dark"></button>
    <button data-theme-set="system"></button>`);
  const { initTheme } = await import('../src/js/modules/theme.js');
  initTheme();

  click(win.document.querySelector('[data-theme-set="dark"]'));
  ok('Theme set=dark adds .dark', win.document.documentElement.classList.contains('dark'));
  ok('Theme swaps logo to dark src', win.document.querySelector('[data-logo]').getAttribute('src') === '/logo-dark.png');
  ok('Theme set=dark marked selected', win.document.querySelector('[data-theme-set="dark"]').getAttribute('aria-pressed') === 'true');
  click(win.document.querySelector('[data-theme-toggle]'));
  ok('Theme toggle flips dark->light', !win.document.documentElement.classList.contains('dark'));
}

// --- Navbar (navbar.js) ---------------------------------------------------
{
  const win = setupDom(`
    <header data-navbar data-scrolled="false">
      <button data-nav-toggle aria-expanded="false"></button>
    </header>
    <div data-nav-mobile data-state="closed" hidden></div>`);
  const { initNavbar } = await import('../src/js/modules/navbar.js');
  initNavbar(win.document);
  const nav = win.document.querySelector('[data-navbar]');
  ok('Navbar initializes data-scrolled', nav.getAttribute('data-scrolled') === 'false');
  click(win.document.querySelector('[data-nav-toggle]'));
  ok('Mobile menu opens on hamburger', win.document.querySelector('[data-nav-mobile]').getAttribute('data-state') === 'open');
}

// --- Tabs (magic-move.js) -------------------------------------------------
{
  const win = setupDom(`
    <div data-tabs="t">
      <button data-tab="one" data-state="active" aria-selected="true" class="text-primary-foreground">
        <span data-bubble></span><span data-bubble-bg></span><span>One</span>
      </button>
      <button data-tab="two" data-state="inactive" aria-selected="false" class="text-muted-foreground hover:text-foreground"><span>Two</span></button>
    </div>
    <div data-tab-panels="t">
      <div data-tab-panel="one"></div>
      <div data-tab-panel="two" hidden></div>
    </div>`);
  // getBoundingClientRect returns zeros in jsdom; the module must not throw.
  const { initMagicTabs } = await import('../src/js/modules/magic-move.js');
  initMagicTabs(win.document);
  click(win.document.querySelector('[data-tab="two"]'));
  const two = win.document.querySelector('[data-tab="two"]');
  const one = win.document.querySelector('[data-tab="one"]');
  ok('Tab click activates target tab', two.getAttribute('data-state') === 'active' && two.getAttribute('aria-selected') === 'true');
  ok('Tab click moves bubble into active tab', two.querySelector('[data-bubble]') !== null);
  ok('Active tab label uses AA-safe primary-foreground', two.classList.contains('text-primary-foreground') && !one.classList.contains('text-primary-foreground') && one.classList.contains('text-muted-foreground'));
  ok('Tab panels toggle', win.document.querySelector('[data-tab-panel="two"]').hidden === false && win.document.querySelector('[data-tab-panel="one"]').hidden === true);
}

// --- init-without-throw for animation modules ----------------------------
{
  const win = setupDom(`
    <div data-reveal-stagger="0.05"><div data-reveal></div><div data-reveal></div></div>
    <div data-tilt><div data-tilt-card></div></div>
    <div data-lightbox><button data-lightbox-item data-full="/x.jpg"><img src="/t.jpg"></button></div>`);
  let threw = false;
  try {
    const { initReveals } = await import('../src/js/modules/reveal.js');
    const { initHeroTilt } = await import('../src/js/modules/tilt.js');
    const { initLightbox } = await import('../src/js/modules/lightbox.js');
    initReveals(win.document);
    initHeroTilt(win.document);
    initLightbox(win.document);
  } catch (e) {
    threw = true;
    console.error(e);
  }
  ok('reveal/tilt/lightbox init without throwing in jsdom', !threw);
}

// --- instructor Availability: tabs + weekly toggle reveal -----------------
{
  const win = setupDom(`
    <div data-avail-tabs>
      <button data-avail-tab="weekly" aria-selected="true" class="bg-background text-foreground shadow-sm"></button>
      <button data-avail-tab="calendar" aria-selected="false" class="text-muted-foreground"></button>
    </div>
    <div data-avail-panel="weekly">
      <div data-weekly>
        <div data-weekly-day="1" class="bg-muted/50 border-transparent">
          <button data-switch data-state="unchecked" data-weekly-toggle="1"><span data-slot="switch-thumb" data-state="unchecked"></span></button>
          <span data-weekly-label class="text-muted-foreground"></span>
          <div data-weekly-times class="hidden"><input data-weekly-start value="09:00"><input data-weekly-end value="17:00"></div>
          <span data-weekly-off></span>
        </div>
      </div>
      <button data-weekly-save></button>
    </div>
    <div data-avail-panel="calendar" class="hidden">
      <h2 data-cal-title></h2>
      <button data-cal-prev></button><button data-cal-next></button>
      <div data-cal-grid></div>
      <div data-exc-card class="hidden"><div data-exc-list></div></div>
    </div>
    <div data-avail-status hidden></div>`);
  // Stub fetch — the module loads exceptions on init; return an empty set so the
  // calendar renders without a network. (Mutations aren't exercised here.)
  win.fetch = global.fetch = () => Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({ exceptions: [] }) });
  let threw = false;
  try {
    const { initForms } = await import('../src/js/modules/forms.js');
    const { initConsoleAvailability } = await import('../src/js/modules/console-availability.js');
    initForms(win.document);
    initConsoleAvailability(win.document);
  } catch (e) {
    threw = true;
    console.error(e);
  }
  ok('Availability inits without throwing in jsdom', !threw);

  // Tab switch → calendar panel shows, weekly hides.
  win.document.querySelector('[data-avail-tab="calendar"]').dispatchEvent(new win.Event('click', { bubbles: true }));
  ok('Availability calendar tab reveals calendar panel',
    win.document.querySelector('[data-avail-panel="calendar"]').classList.contains('hidden') === false &&
    win.document.querySelector('[data-avail-panel="weekly"]').classList.contains('hidden') === true);

  // Flip a weekly switch → its time inputs reveal, "off" label hides.
  win.document.querySelector('[data-weekly-toggle="1"]').dispatchEvent(new win.Event('click', { bubbles: true }));
  const day1 = win.document.querySelector('[data-weekly-day="1"]');
  ok('Availability weekly toggle reveals time inputs',
    day1.querySelector('[data-weekly-times]').classList.contains('hidden') === false &&
    day1.querySelector('[data-weekly-off]').classList.contains('hidden') === true);

  // Calendar grid renders from the stubbed (empty) exceptions — loadExceptions()
  // is async, so let its promise chain settle before asserting.
  await new Promise((r) => setTimeout(r, 0));
  ok('Availability calendar grid renders day cells',
    win.document.querySelectorAll('[data-cal-grid] [data-cal-day]').length > 0);
}

// Contact form: intentionally NO JS test — the v1 form is a plain server-rendered
// admin-post POST (no AJAX module). Its data path (validation, nonce, ?contact=
// success|error redirect, wp_mail → Mailpit) is owned + tested by the plugin
// (buckleup-core) and verified end-to-end by QA. The theme side is pure markup
// (fields + nonce + honeypot) covered by the parity/functional pass.

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);

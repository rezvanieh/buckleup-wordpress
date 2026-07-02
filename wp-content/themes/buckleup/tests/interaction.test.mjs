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

// --- Theme (theme.js) — site is LIGHT-ONLY; dark mode was removed ----------
{
  const win = setupDom(`
    <img data-logo data-logo-light="/logo.png" data-logo-dark="/logo-dark.png" src="/logo-dark.png">
    <button data-theme-toggle></button>
    <button data-theme-set="light"></button>
    <button data-theme-set="dark"></button>
    <button data-theme-set="system"></button>`);
  // Simulate a returning visitor who was stranded in (now-removed) dark mode.
  win.document.documentElement.classList.add('dark');
  try { win.localStorage.setItem('buckleup-theme', 'dark'); } catch (e) { /* noop */ }
  const { initTheme } = await import('../src/js/modules/theme.js');
  initTheme();

  ok('Theme forces light on init (no .dark)', !win.document.documentElement.classList.contains('dark'));
  ok('Theme clears the stored dark preference', !win.localStorage.getItem('buckleup-theme'));
  ok('Theme resolves to the light logo', win.document.querySelector('[data-logo]').getAttribute('src') === '/logo.png');
  ok('Theme marks light chooser selected', win.document.querySelector('[data-theme-set="light"]').getAttribute('aria-pressed') === 'true');
  // A stray "dark" chooser or old toggle click can NEVER re-apply dark.
  click(win.document.querySelector('[data-theme-set="dark"]'));
  ok('Clicking dark chooser never applies .dark', !win.document.documentElement.classList.contains('dark'));
  click(win.document.querySelector('[data-theme-toggle]'));
  ok('Clicking old toggle never applies .dark', !win.document.documentElement.classList.contains('dark'));
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

// --- instructor My Students: search + filter ------------------------------
{
  const win = setupDom(`
    <div data-students-toolbar>
      <input data-students-search>
      <button data-students-filter="all" aria-pressed="true"></button>
      <button data-students-filter="upcoming" aria-pressed="false"></button>
      <button data-students-filter="active" aria-pressed="false"></button>
    </div>
    <div data-students-list>
      <div data-student data-student-search="alice alice@ex.com" data-student-upcoming="1" data-student-active="1"></div>
      <div data-student data-student-search="bob bob@ex.com" data-student-upcoming="0" data-student-active="0"></div>
      <div data-students-noresults class="hidden"></div>
    </div>`);
  let threw = false;
  try {
    const { initConsoleStudents } = await import('../src/js/modules/console-students.js');
    initConsoleStudents(win.document);
  } catch (e) {
    threw = true;
    console.error(e);
  }
  ok('Students roster inits without throwing in jsdom', !threw);

  const cards = () => Array.from(win.document.querySelectorAll('[data-student]'));
  const visible = () => cards().filter((c) => !c.classList.contains('hidden'));

  // Search narrows to matching name/email.
  const search = win.document.querySelector('[data-students-search]');
  search.value = 'bob';
  search.dispatchEvent(new win.Event('input', { bubbles: true }));
  ok('Students search filters by name/email', visible().length === 1 && visible()[0].getAttribute('data-student-search').includes('bob'));

  // Clear search, then "Has Upcoming" keeps only upcoming.
  search.value = '';
  search.dispatchEvent(new win.Event('input', { bubbles: true }));
  win.document.querySelector('[data-students-filter="upcoming"]').dispatchEvent(new win.Event('click', { bubbles: true }));
  ok('Students "Has Upcoming" filter shows only upcoming', visible().length === 1 && visible()[0].getAttribute('data-student-upcoming') === '1');

  // A search with no matches reveals the no-results card.
  search.value = 'zzz';
  search.dispatchEvent(new win.Event('input', { bubbles: true }));
  ok('Students no-results card shows when empty',
    visible().length === 0 && win.document.querySelector('[data-students-noresults]').classList.contains('hidden') === false);
}

// --- admin Students: init + delete-confirm dialog -------------------------
{
  const win = setupDom(`
    <div data-admin-students-toolbar>
      <input data-admin-students-search>
      <select data-admin-students-status-filter><option value=""></option><option value="ACTIVE"></option></select>
      <select data-admin-students-license><option value=""></option></select>
    </div>
    <table><tbody data-admin-students-rows>
      <tr data-admin-student="7"><td><button data-admin-student-delete="7" data-admin-student-name="Alice">Delete</button></td></tr>
    </tbody></table>
    <div data-admin-students-pager class="hidden">
      <span data-admin-students-count></span>
      <button data-admin-students-prev></button>
      <span data-admin-students-pageinfo></span>
      <button data-admin-students-next></button>
    </div>
    <div data-stat-total></div><div data-stat-active></div><span data-stat-active-pct></span>
    <div data-stat-license></div><div data-stat-status></div>
    <div data-admin-students-status hidden></div>
    <div data-admin-del-dialog data-state="closed" class="hidden" hidden>
      <div data-admin-del-overlay></div>
      <button data-admin-del-cancel></button>
      <button data-admin-del-confirm></button>
    </div>`);
  // No initial load fetch needed (page 1 is server-rendered); stub fetch anyway
  // so a stray call can't throw.
  win.fetch = global.fetch = () => Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({ students: [], stats: {}, pagination: { page: 1, pages: 1, total: 0 } }) });
  let threw = false;
  try {
    const { initConsoleAdminStudents } = await import('../src/js/modules/console-admin-students.js');
    initConsoleAdminStudents(win.document);
  } catch (e) {
    threw = true;
    console.error(e);
  }
  ok('Admin students inits without throwing in jsdom', !threw);

  const dialog = win.document.querySelector('[data-admin-del-dialog]');
  // Clicking a row delete button opens the confirm dialog.
  win.document.querySelector('[data-admin-student-delete="7"]').dispatchEvent(new win.Event('click', { bubbles: true }));
  ok('Admin students delete opens confirm dialog', dialog.getAttribute('data-state') === 'open' && dialog.classList.contains('hidden') === false);

  // Cancel closes it.
  win.document.querySelector('[data-admin-del-cancel]').dispatchEvent(new win.Event('click', { bubbles: true }));
  ok('Admin students delete dialog cancels', dialog.getAttribute('data-state') === 'closed' && dialog.classList.contains('hidden') === true);
}

// --- admin Graduates: file preview + delete dialog ------------------------
{
  const win = setupDom(`
    <form data-graduate-form>
      <input type="file" data-graduate-file>
      <input type="text" data-graduate-title>
      <div data-graduate-placeholder></div>
      <div data-graduate-preview class="hidden"><img data-graduate-preview-img><button data-graduate-clear></button></div>
      <button type="submit" data-graduate-submit disabled></button>
      <span data-graduate-status hidden></span>
    </form>
    <span data-graduate-count>2</span>
    <div data-graduate-grid>
      <div data-graduate="3"><button data-graduate-delete="3">×</button></div>
      <div data-graduate="4"><button data-graduate-delete="4">×</button></div>
    </div>
    <div data-graduate-empty class="hidden"></div>
    <div data-graduate-del-dialog data-state="closed" class="hidden" hidden>
      <div data-graduate-del-overlay></div>
      <button data-graduate-del-confirm></button>
      <button data-graduate-del-cancel></button>
    </div>`);
  // jsdom lacks URL.createObjectURL — stub it for the preview path.
  win.URL.createObjectURL = global.URL.createObjectURL = () => 'blob:preview';
  win.URL.revokeObjectURL = global.URL.revokeObjectURL = () => {};
  if (!global.CSS) global.CSS = win.CSS = { escape: (s) => String(s) };
  let threw = false;
  try {
    const { initConsoleGraduates } = await import('../src/js/modules/console-graduates.js');
    initConsoleGraduates(win.document);
  } catch (e) {
    threw = true;
    console.error(e);
  }
  ok('Graduates inits without throwing in jsdom', !threw);

  // Selecting a file shows the preview + enables submit.
  const fileInput = win.document.querySelector('[data-graduate-file]');
  Object.defineProperty(fileInput, 'files', { value: [{ name: 'g.jpg', type: 'image/jpeg' }], configurable: true });
  fileInput.dispatchEvent(new win.Event('change', { bubbles: true }));
  ok('Graduates file select shows preview + enables submit',
    win.document.querySelector('[data-graduate-preview]').classList.contains('hidden') === false &&
    win.document.querySelector('[data-graduate-submit]').disabled === false);

  // Hover-delete opens the confirm dialog.
  const dialog = win.document.querySelector('[data-graduate-del-dialog]');
  win.document.querySelector('[data-graduate-delete="3"]').dispatchEvent(new win.Event('click', { bubbles: true }));
  ok('Graduates delete opens confirm dialog', dialog.getAttribute('data-state') === 'open');

  // Cancel closes it without removing the tile.
  win.document.querySelector('[data-graduate-del-cancel]').dispatchEvent(new win.Event('click', { bubbles: true }));
  ok('Graduates delete cancel keeps tile',
    dialog.getAttribute('data-state') === 'closed' && win.document.querySelector('[data-graduate="3"]') !== null);
}

// --- admin Reviews: search + filter + approve toggle ----------------------
{
  const win = setupDom(`
    <span data-reviews-pending-badge><span data-reviews-pending>1</span> Pending</span>
    <div data-reviews-toolbar>
      <input data-reviews-search>
      <button data-reviews-filter="all" aria-pressed="true"></button>
      <button data-reviews-filter="pending" aria-pressed="false"></button>
      <button data-reviews-filter="approved" aria-pressed="false"></button>
    </div>
    <div data-reviews-list>
      <div data-review="1" data-review-search="alice great lesson" data-review-approved="1">
        <button data-review-approve></button><button data-review-pending hidden></button><button data-review-delete></button>
      </div>
      <div data-review="2" data-review-search="bob needs work" data-review-approved="0">
        <button data-review-approve hidden></button><button data-review-pending></button><button data-review-delete></button>
      </div>
      <div data-reviews-noresults class="hidden"></div>
    </div>
    <div data-reviews-status hidden></div>`);
  win.fetch = global.fetch = () => Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({}) });
  let threw = false;
  try {
    const { initConsoleAdminReviews } = await import('../src/js/modules/console-admin-reviews.js');
    initConsoleAdminReviews(win.document);
  } catch (e) {
    threw = true;
    console.error(e);
  }
  ok('Admin reviews inits without throwing in jsdom', !threw);

  const visible = () => Array.from(win.document.querySelectorAll('[data-review]')).filter((c) => !c.classList.contains('hidden'));

  // Pending filter shows only the unapproved one.
  win.document.querySelector('[data-reviews-filter="pending"]').dispatchEvent(new win.Event('click', { bubbles: true }));
  ok('Admin reviews pending filter shows only unapproved', visible().length === 1 && visible()[0].getAttribute('data-review') === '2');

  // Back to All; search narrows by content.
  win.document.querySelector('[data-reviews-filter="all"]').dispatchEvent(new win.Event('click', { bubbles: true }));
  const search = win.document.querySelector('[data-reviews-search]');
  search.value = 'great';
  search.dispatchEvent(new win.Event('input', { bubbles: true }));
  ok('Admin reviews search filters by content', visible().length === 1 && visible()[0].getAttribute('data-review') === '1');

  // Approve the pending review → its state flips, pending count drops to 0.
  search.value = '';
  search.dispatchEvent(new win.Event('input', { bubbles: true }));
  win.document.querySelector('[data-review="2"] [data-review-pending]').dispatchEvent(new win.Event('click', { bubbles: true }));
  await new Promise((r) => setTimeout(r, 0));
  ok('Admin reviews approve flips state + clears pending badge',
    win.document.querySelector('[data-review="2"]').getAttribute('data-review-approved') === '1' &&
    win.document.querySelector('[data-reviews-pending]').textContent === '0' &&
    win.document.querySelector('[data-reviews-pending-badge]').classList.contains('hidden') === true);

  // The card now shows ONLY the approved state — the "Approve" button is hidden via
  // the `hidden` attribute, the "Approved" pill is shown (the both-buttons bug).
  const card2 = win.document.querySelector('[data-review="2"]');
  ok('Admin reviews approve shows ONLY the approved state (no double button)',
    card2.querySelector('[data-review-pending]').hidden === true &&
    card2.querySelector('[data-review-approve]').hidden === false);
}

// --- admin Settings avatar card: upload swaps preview ---------------------
{
  const win = setupDom(`
    <div data-avatar-card>
      <div data-avatar-preview>AB</div>
      <input type="file" data-avatar-input>
      <button data-avatar-remove></button>
      <div data-avatar-status hidden></div>
    </div>`);
  win.fetch = global.fetch = () => Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({ avatar: 'https://x/test.jpg' }) });
  let threw = false;
  try {
    const { initConsoleAvatar } = await import('../src/js/modules/console-avatar.js');
    initConsoleAvatar(win.document);
  } catch (e) {
    threw = true;
    console.error(e);
  }
  ok('Admin avatar inits without throwing in jsdom', !threw);

  const fileInput = win.document.querySelector('[data-avatar-input]');
  Object.defineProperty(fileInput, 'files', { value: [{ name: 'a.jpg', type: 'image/jpeg' }], configurable: true });
  fileInput.dispatchEvent(new win.Event('change', { bubbles: true }));
  await new Promise((r) => setTimeout(r, 0));
  ok('Admin avatar upload swaps the preview to the returned image',
    win.document.querySelector('[data-avatar-preview] img')?.getAttribute('src') === 'https://x/test.jpg');

  // Remove clears the preview.
  win.document.querySelector('[data-avatar-remove]').dispatchEvent(new win.Event('click', { bubbles: true }));
  await new Promise((r) => setTimeout(r, 0));
  ok('Admin avatar remove clears the preview', win.document.querySelector('[data-avatar-preview]').innerHTML === '');
}

// Contact form: intentionally NO JS test — the v1 form is a plain server-rendered
// admin-post POST (no AJAX module). Its data path (validation, nonce, ?contact=
// success|error redirect, wp_mail → Mailpit) is owned + tested by the plugin
// (buckleup-core) and verified end-to-end by QA. The theme side is pure markup
// (fields + nonce + honeypot) covered by the parity/functional pass.

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);

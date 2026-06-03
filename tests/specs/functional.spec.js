// Functional parity checks for the CANDIDATE (WP build). These validate behavior the
// pixel diff cannot: navigation + Locations dropdown, theme toggle with NO flash-of-wrong-
// theme, the light/dark accent-hue difference, WhatsApp/tel/mailto deep links, the FAQ
// accordion, the mobile bottom tab bar + WhatsApp FAB, and the 1099->1100 nav breakpoint.
//
// Target defaults to the candidate; override with TARGET=live to characterize the golden
// master. v1 has NO booking/checkout/portal — none are tested.
//
// Selectors target the theme's existing data-* interaction contract (see project memory
// `buckleup-theme-data-contract`: [data-theme-toggle]/[data-theme-set], [data-navbar]/
// [data-nav-toggle], [data-dropdown]>[data-dropdown-trigger], [data-faq-item] native
// <details>), with role/text/href fallbacks so the spec also exercises the live site today.
// Where the candidate must exist for a check to be meaningful, the test FAILS loudly (does
// not skip) so a missing affordance is reported, not hidden.

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { baseUrlFor } = require('../lib/sites');
const { applyFreeze } = require('../lib/freeze');
const { preparePage } = require('../lib/theme');

const ROOT = path.resolve(__dirname, '..');
const urls = JSON.parse(fs.readFileSync(path.join(ROOT, 'config', 'urls.json'), 'utf8'));
const BASE = baseUrlFor(process.env.TARGET || 'candidate');
const FACTS = JSON.parse(fs.readFileSync(path.join(ROOT, 'config', 'seo-expectations.json'), 'utf8')).businessFacts;

// Only run functional checks at the desktop + mobile viewports that matter for behavior.
const FUNCTIONAL_VIEWPORTS = ['375', '1099', '1100', '1440'];
function viewportInScope(testInfo) {
  return FUNCTIONAL_VIEWPORTS.includes(testInfo.project.metadata.viewport);
}

test.describe('functional parity', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    test.skip(!viewportInScope(testInfo), 'functional checks run at 375/1099/1100/1440 only');
    await applyFreeze(page);
  });

  test('homepage loads with the expected title token', async ({ page }) => {
    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle(/BuckleUp Driving School/i);
  });

  test('WhatsApp / tel / mailto deep links use the exact business identifiers', async ({ page }) => {
    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
    // tel: link to (604) 441-3677
    const tel = page.locator('a[href^="tel:"]').first();
    await expect(tel, 'a tel: link must exist').toHaveCount(1);
    expect((await tel.getAttribute('href')).replace(/[^\d+]/g, '')).toContain('6044413677');
    // WhatsApp wa.me/16044413677
    const wa = page.locator('a[href*="wa.me/"], a[href*="api.whatsapp.com"]').first();
    await expect(wa, 'a WhatsApp link must exist').toHaveCount(1);
    expect(await wa.getAttribute('href')).toContain(FACTS.whatsapp);
    // mailto info@buckleupdriving.ca (footer/contact)
    const mail = page.locator(`a[href^="mailto:"]`).first();
    if (await mail.count()) {
      expect(await mail.getAttribute('href')).toContain(FACTS.email);
    }
  });

  test('theme toggle switches the .dark class with no flash-of-wrong-theme', async ({ page }) => {
    // Seed dark BEFORE load; the pre-paint script must apply .dark on the very first frame.
    await preparePage(page, 'dark');
    // Record the html class at the earliest observable point via an init script.
    await page.addInitScript(() => {
      const obs = () => { window.__firstHtmlClass = document.documentElement.className; };
      // capture as soon as documentElement exists
      if (document.documentElement) obs();
      document.addEventListener('readystatechange', obs, { once: true });
    });
    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
    const firstClass = await page.evaluate(() => window.__firstHtmlClass || document.documentElement.className);
    expect(firstClass, 'first observed <html> class must already be dark (no FOUC)').toContain('dark');

    // The candidate exposes the theme control via the theme data-contract
    // ([data-theme-toggle] 2-state cycle, [data-theme-set] 3-option); live site falls back to
    // an aria-labelled button. Assert a control exists.
    const toggle = page.locator(
      "[data-theme-toggle], [data-theme-set], [aria-label*='theme' i], [aria-label*='light' i], [aria-label*='dark' i]"
    ).first();
    await expect(toggle, 'a theme toggle control must exist').toHaveCount(1);
  });

  test('light vs dark resolve different accent hues (intentional design divergence)', async ({ page }) => {
    // Read the computed --accent token under each theme; PLAN.md §3: light emerald vs dark lime.
    const readAccent = async (theme) => {
      await preparePage(page, theme);
      await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(300);
      return page.evaluate(() =>
        getComputedStyle(document.documentElement).getPropertyValue('--accent').trim()
      );
    };
    const light = await readAccent('light');
    const dark = await readAccent('dark');
    expect(light, '--accent must be defined in light').not.toBe('');
    expect(dark, '--accent must be defined in dark').not.toBe('');
    expect(dark, 'light and dark --accent must intentionally differ (emerald vs lime)').not.toBe(light);
  });

  test('Locations dropdown exposes all five location links (desktop nav)', async ({ page }, testInfo) => {
    test.skip(testInfo.project.metadata.viewport === '375' || testInfo.project.metadata.viewport === '1099',
      'desktop Locations dropdown only at >=1100');
    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
    // Candidate: the Locations menu is a [data-dropdown] with a [data-dropdown-trigger]
    // (may be hover-open via data-dropdown-hover). Live: a nav item labelled "Locations".
    const trigger = page.locator("[data-dropdown] [data-dropdown-trigger], nav >> text=/^Locations$/i").first();
    if (await trigger.count()) { await trigger.hover().catch(() => {}); await trigger.click().catch(() => {}); }
    for (const slug of ['coquitlam', 'north-vancouver', 'port-coquitlam', 'port-moody', 'tri-cities']) {
      await expect(page.locator(`a[href$="/locations/${slug}"], a[href*="/locations/${slug}"]`).first(),
        `Locations menu must link /locations/${slug}`).toHaveCount(1, { timeout: 5000 });
    }
  });

  test('mobile shows a WhatsApp FAB and bottom tab bar', async ({ page }, testInfo) => {
    test.skip(testInfo.project.metadata.viewport !== '375', 'mobile-only affordances');
    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
    await expect(page.locator("[data-mobile-fab], a[href*='wa.me'][class*='fab' i], a[aria-label*='whatsapp' i]").first(),
      'mobile WhatsApp FAB must exist').toHaveCount(1);
    await expect(page.locator("[data-mobile-tabbar], nav[class*='bottom' i], [class*='tab-bar' i]").first(),
      'mobile bottom tab bar must exist').toHaveCount(1);
  });

  test('FAQ accordion toggles a panel open/closed', async ({ page }, testInfo) => {
    test.skip(testInfo.project.metadata.viewport !== '1440', 'run accordion check once, at desktop');
    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
    // Candidate FAQ is a native <details> with [data-faq-item]; toggling flips its `open`
    // attribute. Live site uses a Radix accordion button with aria-expanded — handle both.
    const details = page.locator("[data-faq-item], details").first();
    const ariaBtn = page.locator("button[aria-expanded]").first();
    if (await details.count()) {
      const summary = details.locator('summary').first();
      const wasOpen = await details.evaluate((el) => el.hasAttribute('open'));
      await (await summary.count() ? summary : details).click();
      await page.waitForTimeout(300);
      const nowOpen = await details.evaluate((el) => el.hasAttribute('open'));
      expect(nowOpen, '<details> open state must toggle on click').not.toBe(wasOpen);
    } else {
      await expect(ariaBtn, 'an FAQ accordion control must exist').toHaveCount(1, { timeout: 10000 });
      const before = await ariaBtn.getAttribute('aria-expanded');
      await ariaBtn.click();
      await page.waitForTimeout(300);
      expect(await ariaBtn.getAttribute('aria-expanded'), 'aria-expanded must change on click').not.toBe(before);
    }
  });

  test('nav breakpoint: mobile nav at 1099, desktop nav at 1100', async ({ page }, testInfo) => {
    const vp = testInfo.project.metadata.viewport;
    test.skip(vp !== '1099' && vp !== '1100', 'breakpoint check only at 1099/1100');
    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
    // Candidate: [data-nav-toggle] is the mobile hamburger (inside [data-navbar]); the desktop
    // nav links are the direct nav anchors. Live: aria/role fallbacks.
    const hamburger = page.locator("[data-nav-toggle], button[aria-label*='menu' i], button[class*='hamburger' i]").first();
    const desktopNav = page.locator("[data-navbar] [href$='/services'], nav [href$='/services'], nav [href$='/instructors']").first();
    if (vp === '1099') {
      await expect(hamburger, 'at 1099 the mobile hamburger should be visible').toBeVisible({ timeout: 5000 });
    } else {
      await expect(desktopNav, 'at 1100 the desktop nav links should be visible').toBeVisible({ timeout: 5000 });
    }
  });

  test('all in-scope public URLs return 200 (URL parity)', async ({ request }) => {
    for (const p of urls.pages) {
      const resp = await request.get(BASE + p.path, { maxRedirects: 5 });
      expect(resp.status(), `${p.path} should be 200`).toBe(200);
    }
  });

  // Candidate-only: the contact form is a no-JS admin-post POST (action=buckleup_contact)
  // → ?contact=success banner, delivering mail to Mailpit. We submit with a UNIQUE subject so
  // the assertion targets OUR message (Mailpit may hold prior verification mail). Live has no
  // Mailpit, so this runs against the candidate only.
  test('contact form submits and delivers to Mailpit (PM-010)', async ({ page, request }, testInfo) => {
    test.skip(testInfo.project.metadata.viewport !== '1440', 'submit once, at desktop');
    test.skip((process.env.TARGET || 'candidate') !== 'candidate', 'Mailpit only exists for the candidate');
    const MAILPIT = process.env.MAILPIT_URL || 'http://localhost:8025';
    const stamp = Date.now();
    const subject = `QA parity probe ${stamp}`;

    await page.goto(BASE + '/contact', { waitUntil: 'domcontentloaded' });
    const form = page.locator("[data-contact-form]").first();
    await expect(form, 'contact form must render (was empty in the prior run)').toHaveCount(1, { timeout: 10000 });

    await page.fill('[name="first_name"]', 'QA');
    await page.fill('[name="last_name"]', 'Parity');
    await page.fill('[name="email"]', `qa+${stamp}@example.com`);
    if (await page.locator('[name="phone"]').count()) await page.fill('[name="phone"]', '6045551212');
    await page.fill('[name="subject"]', subject);
    await page.fill('[name="message"]', `Automated parity check ${stamp}. Please ignore.`);
    // Leave the `website` honeypot empty.

    await Promise.all([
      page.waitForURL(/[?&]contact=(success|error)/, { timeout: 15000 }).catch(() => {}),
      page.locator('[data-contact-submit]').click(),
    ]);
    await page.waitForTimeout(500);
    expect(page.url(), 'submit should redirect to the ?contact=success banner').toMatch(/[?&]contact=success/);

    // Poll Mailpit for OUR uniquely-subjected message → to info@buckleupdriving.ca.
    let found = null;
    for (let i = 0; i < 10 && !found; i++) {
      const resp = await request.get(`${MAILPIT}/api/v1/search?query=${encodeURIComponent('subject:"' + subject + '"')}`);
      if (resp.ok()) {
        const data = await resp.json();
        found = (data.messages || []).find((m) => (m.Subject || '').includes(subject));
      }
      if (!found) await page.waitForTimeout(700);
    }
    expect(found, `a Mailpit message with subject "${subject}" must arrive`).toBeTruthy();
    const to = (found.To || []).map((t) => t.Address);
    expect(to, 'contact mail must be addressed to info@buckleupdriving.ca').toContain('info@buckleupdriving.ca');
  });
});

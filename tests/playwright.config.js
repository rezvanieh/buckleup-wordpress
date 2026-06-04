// Playwright config for the parity harness.
//
// One project per (viewport x browser). The visual suite runs Chromium-only by default
// (deterministic full-page rendering); cross-browser (WebKit/Firefox) is enabled for the
// functional + a11y suites via PW_CROSS_BROWSER=1 (Task #9). Every project emulates
// prefers-reduced-motion: reduce so the app's own motion gating quiets down; freeze.js
// adds belt-and-suspenders CSS on top.
//
// baseURL is intentionally NOT set globally — specs/runners pick live vs candidate per
// run via tests/lib/sites.js, because a single run compares the two.

const { defineConfig, devices } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const viewports = JSON.parse(
  fs.readFileSync(path.join(__dirname, 'config', 'viewports.json'), 'utf8')
).viewports;

const crossBrowser = process.env.PW_CROSS_BROWSER === '1';
const browsers = crossBrowser
  ? [
      { id: 'chromium', use: devices['Desktop Chrome'] },
      { id: 'webkit', use: devices['Desktop Safari'] },
      { id: 'firefox', use: devices['Desktop Firefox'] },
    ]
  : [{ id: 'chromium', use: devices['Desktop Chrome'] }];

const projects = [];
for (const vp of viewports) {
  for (const b of browsers) {
    projects.push({
      name: `${b.id}-${vp.name}`,
      metadata: { viewport: vp.name, browser: b.id },
      use: {
        ...b.use,
        viewport: { width: vp.width, height: vp.height },
        deviceScaleFactor: 1, // pin DPR so screenshots are pixel-comparable across machines
        colorScheme: 'light', // overridden per-test by theme.js
        reducedMotion: 'reduce',
        // Stabilize: ignore HTTPS hiccups on the candidate, give slow first paints room.
        ignoreHTTPSErrors: true,
      },
    });
  }
}

module.exports = defineConfig({
  testDir: './specs',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 2 : undefined,
  timeout: 90 * 1000,
  expect: { timeout: 15 * 1000 },
  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['json', { outputFile: 'results/results.json' }],
  ],
  outputDir: 'results/artifacts',
  use: {
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    navigationTimeout: 45 * 1000,
    actionTimeout: 15 * 1000,
  },
  projects,
});

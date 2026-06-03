// Verifies the compiled Tailwind bundle kept the hard arbitrary-value / custom
// -breakpoint utilities the design system depends on (PLAN.md §7). Run AFTER a
// build: `npm run build && node tests/verify-purge.mjs`. Exits non-zero if any
// expected selector is missing from build/assets/app.*.css.

import { readFileSync, readdirSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const assetsDir = resolve(root, 'build/assets');

const cssFile = readdirSync(assetsDir).find((f) => f.startsWith('app.') && f.endsWith('.css'));
if (!cssFile) {
  console.error('FAIL: no build/assets/app.*.css — run `npm run build` first.');
  process.exit(1);
}
const css = readFileSync(resolve(assetsDir, cssFile), 'utf8');

// Each entry: [human label, substring that must appear in the compiled (minified)
// CSS]. Tailwind v4 minifies — no spaces after colons, `0.75rem` -> `.75rem`,
// `@media(min-width:...)` — so the needles target the emitted form.
const checks = [
  ['lg:text-[5.5rem]', '5.5rem'],
  ['xl:text-[6.5rem]', '6.5rem'],
  ['leading-[0.95]', 'line-height:.95'],
  ['blur-[120px]', 'blur(120px)'],
  ['bg-card/98 (fractional alpha)', '1100px\\]\\:h-32' /* placeholder, replaced below */],
  ['ring-[3px]', 'ring-\\[3px\\]'],
  ['min-[1100px]:h-32 class', '1100px\\]\\:h-32'],
  ['min-[1100px] media query', '@media(min-width:1100px)'],
  ['gradient-text utility', '.gradient-text'],
  ['glass utility', '.glass'],
  ['glow-primary utility', '.glow-primary'],
  ['no-transitions guard', '.no-transitions'],
  ['skeleton shimmer', '.skeleton'],
  ['card-highlight', '.card-highlight'],
  ['Geist @font-face', 'Geist'],
  ['light accent emerald token', '160 84% 39%'],
  ['dark accent lime token', '142 71% 45%'],
  ['radius token', '--radius:.75rem'],
  // Component library (inc/*.php) — behavioral data-state/data-side variants and
  // a few signature component utilities must survive scanning the PHP partials.
  ['Button hover bg-primary/90', 'bg-primary\\/90'],
  ['Dialog/Select data-[state=open]', '[data-state=open]'],
  ['Switch data-[state=checked]', '[data-state=checked]'],
  ['Dropdown data-side=bottom', 'data-side=bottom'],
  ['Tabs bubble mix-blend-overlay', 'mix-blend-overlay'],
  ['FAQ chevron group-open', 'group-open'],
  ['Textarea min-h-[100px]', 'min-height:100px'],
  ['Card shadow-black/5', 'shadow-black\\/5'],
  ['Input aria-invalid border', 'aria-invalid'],
  // Long-form reading style for blog single + page bodies (plain class, not a
  // Tailwind utility — must always be in the bundle).
  ['prose long-form style', '.prose'],
  // Gutenberg button blocks re-skinned to the brand primary token (AA-safe).
  ['wp Button block primary skin', '.wp-element-button'],
];
// bg-card/98 emits an escaped class selector `.bg-card\/98`.
checks[4] = ['bg-card/98 (fractional alpha)', 'bg-card\\/98'];

let failed = 0;
for (const [label, needle] of checks) {
  const present = css.includes(needle);
  if (present) {
    console.log(`  ok   ${label}`);
  } else {
    console.error(`  MISS ${label}  (looked for: ${needle})`);
    failed++;
  }
}

console.log(`\n${cssFile}: ${(css.length / 1024).toFixed(1)} KB`);
if (failed) {
  console.error(`\nFAIL: ${failed} expected utilities missing from the bundle.`);
  process.exit(1);
}
console.log('\nPASS: all expected utilities survived purge.');

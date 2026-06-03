#!/usr/bin/env node
/**
 * SEMANTIC SEO / structured-data parity check.
 *
 *   node bin/seo-diff.js                # candidate (WP build) vs business facts + intended divergences
 *   node bin/seo-diff.js --selfcheck    # live golden master vs its own captured `liveObserved` (regression guard)
 *   node bin/seo-diff.js --target live  # ad-hoc: run the same business-fact assertions against live
 *
 * This does NOT diff raw markup (the WP build legitimately uses Rank Math's serialization,
 * not Next's). It parses each page's title/meta/canonical/OG and JSON-LD graph from the
 * RENDERED DOM and asserts SEMANTIC facts from config/seo-expectations.json:
 *   - LocalBusiness AggregateRating 4.98 / 500, NAP, geo, hours 09:00-18:00, priceRange,
 *     payments, founding 2014, the 3 schema @types, OfferCatalog spanning $100-$620.
 *   - A 14-item FAQPage on the home page (+ locations, per PLAN.md Phase 4).
 *   - The THREE intended divergences hold on the candidate (self-canonicals, www, sitemap>=14)
 *     and are reported as EXPECTED, not failures.
 *
 * Exit code 0 = all assertions pass; 1 = one or more failures. Writes a JSON report to
 * results/seo-report.json and prints a human summary.
 */
const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { baseUrlFor } = require('../lib/sites');
const { loadStable } = require('../lib/load');
const { extractSeo, findNodeByType, flattenJsonLd } = require('../lib/seo-extract');

const ROOT = path.resolve(__dirname, '..');
const urls = JSON.parse(fs.readFileSync(path.join(ROOT, 'config', 'urls.json'), 'utf8'));
const exp = JSON.parse(fs.readFileSync(path.join(ROOT, 'config', 'seo-expectations.json'), 'utf8'));
const FACTS = exp.businessFacts;

const SELFCHECK = process.argv.includes('--selfcheck');
const TARGET = SELFCHECK ? 'live' : (argVal('target') || 'candidate');
const BASE = baseUrlFor(TARGET);
// The candidate's WP site URL is the PRODUCTION host (PLAN.md Phase 4), so canonical/OG/
// sitemap URLs are production absolute URLs even when served from localhost — that is the
// intended www-standardization. Self-canonical assertions compare against PROD_HOST + path,
// NOT against the localhost BASE we fetch from. Overridable via --prod-host.
const PROD_HOST = (argVal('prod-host') || 'https://www.buckleupdriving.ca').replace(/\/$/, '');

function argVal(name) {
  const i = process.argv.indexOf(`--${name}`);
  return i >= 0 ? process.argv[i + 1] : null;
}

// ---- assertion accumulator -------------------------------------------------
const results = [];
function check(page, name, pass, detail, kind = 'assert') {
  results.push({ page, name, pass: !!pass, kind, detail });
}
function expectEq(page, name, actual, expected) {
  check(page, name, String(actual) === String(expected), `expected=${expected} actual=${actual}`);
}
function expectIncludes(page, name, haystack, needle) {
  const ok = typeof haystack === 'string' && haystack.includes(needle);
  check(page, name, ok, `expected to include "${needle}" in "${haystack}"`);
}

// ---- per-page SEO snapshot loader -----------------------------------------
async function snapshot(browser, p) {
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  try {
    await page.setViewportSize({ width: 1440, height: 900 });
    await loadStable(page, BASE + p.path, 'light');
    return await extractSeo(page);
  } finally {
    await ctx.close();
  }
}

// ---- LocalBusiness semantic assertions ------------------------------------
function assertLocalBusiness(pageKey, seo) {
  const lb = findNodeByType(seo.jsonLd, 'LocalBusiness') || findNodeByType(seo.jsonLd, 'DrivingSchool');
  check(pageKey, 'LocalBusiness JSON-LD present', !!lb, lb ? 'found' : 'no LocalBusiness/DrivingSchool node');
  if (!lb) return;

  const types = Array.isArray(lb['@type']) ? lb['@type'] : [lb['@type']];
  for (const t of FACTS.schemaTypes) check(pageKey, `@type includes ${t}`, types.includes(t), `types=${types}`);

  expectEq(pageKey, 'name', lb.name, FACTS.name);
  expectEq(pageKey, 'telephone', (lb.telephone || '').replace(/\s/g, ''), FACTS.telephone);
  expectEq(pageKey, 'email', lb.email, FACTS.email);
  expectEq(pageKey, 'foundingDate', String(lb.foundingDate), FACTS.foundingDate);

  const addr = lb.address || {};
  expectEq(pageKey, 'address.street', addr.streetAddress, FACTS.address.streetAddress);
  expectEq(pageKey, 'address.locality', addr.addressLocality, FACTS.address.addressLocality);
  expectEq(pageKey, 'address.region', addr.addressRegion, FACTS.address.addressRegion);
  expectEq(pageKey, 'address.postal', addr.postalCode, FACTS.address.postalCode);
  expectEq(pageKey, 'address.country', addr.addressCountry, FACTS.address.addressCountry);

  const geo = lb.geo || {};
  expectEq(pageKey, 'geo.lat', geo.latitude, FACTS.geo.latitude);
  expectEq(pageKey, 'geo.lng', geo.longitude, FACTS.geo.longitude);

  const oh = lb.openingHoursSpecification || {};
  expectEq(pageKey, 'hours.opens', oh.opens, FACTS.openingHours.opens);
  expectEq(pageKey, 'hours.closes', oh.closes, FACTS.openingHours.closes);
  const days = oh.dayOfWeek || [];
  expectEq(pageKey, 'hours.days(7)', days.length, 7);

  const ar = lb.aggregateRating || {};
  expectEq(pageKey, 'rating.value 4.98', String(ar.ratingValue), FACTS.aggregateRating.ratingValue);
  expectEq(pageKey, 'rating.count 500', String(ar.reviewCount), FACTS.aggregateRating.reviewCount);

  const pays = lb.paymentAccepted || [];
  for (const pay of FACTS.paymentAccepted) check(pageKey, `payment ${pay}`, pays.includes(pay), `paymentAccepted=${pays}`);

  // OfferCatalog should span the $100-$620 price band. The live site encodes this band in
  // the Offer's Service description text ("$100 single ... $620 full course") rather than as
  // numeric Offer.price fields; accept EITHER form (soft check — the authoritative price band
  // is also asserted via the home pricing section in the visual/content layer).
  const offers = (lb.hasOfferCatalog && lb.hasOfferCatalog.itemListElement) || [];
  const prices = offers
    .map((o) => (o.priceSpecification && Number(o.priceSpecification.price)) || Number(o.price))
    .filter((n) => !Number.isNaN(n));
  if (prices.length) {
    check(pageKey, `OfferCatalog min >= ${FACTS.priceMin}`, Math.min(...prices) >= FACTS.priceMin, `prices=${prices}`, 'soft');
    check(pageKey, `OfferCatalog max <= ${FACTS.priceMax}`, Math.max(...prices) <= FACTS.priceMax, `prices=${prices}`, 'soft');
  } else {
    const blob = JSON.stringify(offers);
    const bandInText = blob.includes(`$${FACTS.priceMin}`) && blob.includes(`$${FACTS.priceMax}`);
    check(pageKey, `OfferCatalog references $${FACTS.priceMin}-$${FACTS.priceMax} band`, bandInText,
      bandInText ? 'price band found in offer text' : 'no numeric prices and no $100-$620 band text in hasOfferCatalog', 'soft');
  }
}

function assertFaqPage(pageKey, seo, expectedCount) {
  const faq = findNodeByType(seo.jsonLd, 'FAQPage');
  check(pageKey, 'FAQPage JSON-LD present', !!faq, faq ? 'found' : 'no FAQPage node');
  if (!faq) return;
  const n = (faq.mainEntity || []).length;
  expectEq(pageKey, `FAQPage has ${expectedCount} Q&A`, n, expectedCount);
}

// ---- intended-divergence checks (candidate only) --------------------------
function assertSelfCanonical(pageKey, seo, expectedUrl) {
  const ok = seo.canonical === expectedUrl;
  check(pageKey, 'EXPECTED: self-referential www canonical', ok,
    `expected ${expectedUrl} actual ${seo.canonical}`, 'expected-divergence');
}
function assertWww(pageKey, seo) {
  const fields = { canonical: seo.canonical, 'og:url': seo.og.url };
  const lb = findNodeByType(seo.jsonLd, 'LocalBusiness') || findNodeByType(seo.jsonLd, 'DrivingSchool');
  if (lb) { fields['jsonLd.url'] = lb.url; fields['jsonLd.logo'] = lb.logo; }
  for (const [k, v] of Object.entries(fields)) {
    if (v == null) continue;
    const ok = /^https:\/\/www\.buckleupdriving\.ca/.test(v);
    check(pageKey, `EXPECTED: www host on ${k}`, ok, `value=${v}`, 'expected-divergence');
  }
}

async function assertSitemap(browser) {
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  try {
    const resp = await ctx.request.get(BASE + '/sitemap.xml');
    const xml = await resp.text();
    const locs = (xml.match(/<loc>([^<]+)<\/loc>/g) || []).map((m) => m.replace(/<\/?loc>/g, ''));

    if (SELFCHECK) {
      // Live site serves a flat sitemap (3 URLs, the known bug).
      expectEq('sitemap', 'live observed url count', locs.length, exp.liveObserved.sitemapUrlCount);
      return;
    }

    // The WP build uses a Rank Math sitemap INDEX: /sitemap.xml lists child sitemaps
    // (post/page/location/category). Follow each child and count the real page URLs so we
    // judge the COMPLETE sitemap, not the count of child sitemaps.
    const isIndex = /<sitemapindex/i.test(xml);
    let total = 0;
    const childCounts = [];
    if (isIndex) {
      for (const child of locs) {
        try {
          const cr = await ctx.request.get(child);
          const cxml = await cr.text();
          const n = (cxml.match(/<loc>/g) || []).length;
          total += n;
          childCounts.push(`${child.split('/').pop()}=${n}`);
        } catch (e) { childCounts.push(`${child}=ERR`); }
      }
    } else {
      total = locs.length;
    }
    const min = exp.intendedDivergences['complete-sitemap'].wpExpectedMinUrls;
    check('sitemap', `EXPECTED: complete sitemap (>=${min} urls)`, total >= min,
      `${isIndex ? 'sitemap index' : 'flat sitemap'}; total page URLs=${total} [${childCounts.join(', ')}]`,
      'expected-divergence');
  } finally { await ctx.close(); }
}

// ---------------------------------------------------------------------------
async function main() {
  console.log(`[seo-diff] target=${TARGET} base=${BASE} ${SELFCHECK ? '(SELFCHECK: validating live baseline facts)' : ''}\n`);
  const browser = await chromium.launch();

  const home = urls.pages.find((p) => p.key === 'home');
  const locations = urls.pages.filter((p) => p.key.startsWith('loc-'));
  const blogPost = urls.pages.find((p) => p.key === 'blog-post');

  // Home: full LocalBusiness + 14-item FAQPage.
  const homeSeo = await snapshot(browser, home);
  assertLocalBusiness('home', homeSeo);
  assertFaqPage('home', homeSeo, FACTS.faqCount);
  if (SELFCHECK) {
    expectEq('home', 'live title', homeSeo.title, exp.liveObserved.home.title);
    expectEq('home', 'live canonical', homeSeo.canonical, exp.liveObserved.home.canonical);
  } else {
    assertWww('home', homeSeo);
    assertSelfCanonical('home', homeSeo, 'https://www.buckleupdriving.ca/');
  }
  // geo meta present on home
  expectEq('home', 'geo.region', homeSeo.geo.region, FACTS.geoMeta.region);
  expectEq('home', 'geo.position', homeSeo.geo.position, FACTS.geoMeta.position);

  // Locations: FAQPage (per PLAN Phase 4) + self-canonical fix (candidate only).
  for (const loc of locations) {
    const seo = await snapshot(browser, loc);
    if (!SELFCHECK) {
      assertFaqPage(loc.key, seo, FACTS.faqCount);
      assertWww(loc.key, seo);
      assertSelfCanonical(loc.key, seo, `${PROD_HOST}${loc.path}`);
    } else {
      // Live regression: record the known canonical bug rather than assert a fix.
      check(loc.key, 'live canonical inherits homepage (known bug)',
        seo.canonical === 'https://www.buckleupdriving.ca',
        `actual ${seo.canonical}`, 'known-live-bug');
    }
  }

  // Blog post: BlogPosting schema + self-canonical fix (candidate only).
  const postSeo = await snapshot(browser, blogPost);
  if (!SELFCHECK) {
    const bp = findNodeByType(postSeo.jsonLd, 'BlogPosting') || findNodeByType(postSeo.jsonLd, 'Article');
    check('blog-post', 'BlogPosting/Article JSON-LD present', !!bp, bp ? 'found' : 'none');
    assertSelfCanonical('blog-post', postSeo, `${PROD_HOST}${blogPost.path}`);
    assertWww('blog-post', postSeo);
  }

  await assertSitemap(browser);
  await browser.close();

  // ---- report ----
  const failures = results.filter((r) => !r.pass && r.kind !== 'soft');
  const softFails = results.filter((r) => !r.pass && r.kind === 'soft');
  const report = {
    target: TARGET, baseUrl: BASE, selfcheck: SELFCHECK, ranAt: new Date().toISOString(),
    total: results.length, passed: results.filter((r) => r.pass).length,
    failed: failures.length, soft: softFails.length, results,
  };
  fs.mkdirSync(path.join(ROOT, 'results'), { recursive: true });
  fs.writeFileSync(path.join(ROOT, 'results', `seo-report${SELFCHECK ? '-selfcheck' : ''}.json`), JSON.stringify(report, null, 2));

  const byKind = (k) => results.filter((r) => r.kind === k);
  console.log(`  PASS ${report.passed}/${report.total}   FAIL ${report.failed}   SOFT-FAIL ${report.soft}`);
  const div = byKind('expected-divergence');
  if (div.length) console.log(`  intended divergences checked: ${div.filter((r) => r.pass).length}/${div.length} as EXPECTED`);
  if (failures.length) {
    console.log('\n  Failures:');
    for (const f of failures) console.log(`   x [${f.page}] ${f.name} — ${f.detail}`);
  }
  if (softFails.length) {
    console.log('\n  Soft (non-blocking):');
    for (const f of softFails) console.log(`   ~ [${f.page}] ${f.name} — ${f.detail}`);
  }
  console.log(`\n[seo-diff] report -> results/seo-report${SELFCHECK ? '-selfcheck' : ''}.json`);
  process.exit(failures.length ? 1 : 0);
}

main().catch((e) => { console.error(e); process.exit(1); });

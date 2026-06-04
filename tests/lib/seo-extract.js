// Extracts the full SEO/meta/structured-data surface of a rendered page via a real DOM
// (Playwright page.evaluate), so it works regardless of SSR vs client-rendered head and
// regardless of markup framework. Returns a normalized, JSON-serializable snapshot that
// both the baseline writer and the semantic-diff comparator consume.
//
// Note: we read the *rendered* DOM (after hydration) so client-injected canonicals/JSON-LD
// on the Next site are captured too.

async function extractSeo(page) {
  return page.evaluate(() => {
    const text = (el) => (el ? (el.textContent || '').trim() : null);
    const attr = (sel, a) => {
      const el = document.querySelector(sel);
      return el ? el.getAttribute(a) : null;
    };
    const metaName = (n) => attr(`meta[name="${n}"]`, 'content');
    const metaProp = (p) => attr(`meta[property="${p}"]`, 'content');

    // All JSON-LD blocks, parsed; record parse errors instead of throwing.
    const jsonLd = [];
    document.querySelectorAll('script[type="application/ld+json"]').forEach((s, i) => {
      try {
        jsonLd.push(JSON.parse(s.textContent));
      } catch (e) {
        jsonLd.push({ __parseError: String(e), __index: i, __raw: (s.textContent || '').slice(0, 200) });
      }
    });

    // hreflang / alternate links
    const alternates = Array.from(document.querySelectorAll('link[rel="alternate"]')).map((l) => ({
      hreflang: l.getAttribute('hreflang'),
      href: l.getAttribute('href'),
    }));

    return {
      url: location.href,
      htmlLang: document.documentElement.getAttribute('lang'),
      title: text(document.querySelector('title')),
      description: metaName('description'),
      robots: metaName('robots'),
      canonical: attr('link[rel="canonical"]', 'href'),
      geo: {
        region: metaName('geo.region'),
        placename: metaName('geo.placename'),
        position: metaName('geo.position'),
        icbm: metaName('ICBM'),
      },
      og: {
        title: metaProp('og:title'),
        description: metaProp('og:description'),
        url: metaProp('og:url'),
        image: metaProp('og:image'),
        type: metaProp('og:type'),
        siteName: metaProp('og:site_name'),
      },
      twitter: {
        card: metaName('twitter:card'),
        title: metaName('twitter:title'),
        description: metaName('twitter:description'),
        image: metaName('twitter:image'),
      },
      alternates,
      jsonLd,
    };
  });
}

// Flatten the @type-soup of a JSON-LD graph (handles top-level arrays, @graph wrappers,
// and single objects) into a flat list of node objects for type-targeted assertions.
function flattenJsonLd(blocks) {
  const out = [];
  const visit = (node) => {
    if (Array.isArray(node)) return node.forEach(visit);
    if (node && typeof node === 'object') {
      if (Array.isArray(node['@graph'])) node['@graph'].forEach(visit);
      out.push(node);
    }
  };
  (blocks || []).forEach(visit);
  return out;
}

// Find the first JSON-LD node whose @type (string or array) includes `type`.
function findNodeByType(blocks, type) {
  return flattenJsonLd(blocks).find((n) => {
    const t = n['@type'];
    return Array.isArray(t) ? t.includes(type) : t === type;
  });
}

module.exports = { extractSeo, flattenJsonLd, findNodeByType };

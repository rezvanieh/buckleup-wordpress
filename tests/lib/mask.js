// Neutralizes dynamic regions before a screenshot by overpainting every element that
// matches a mask selector with an opaque flat block of fixed size. Because the live site
// and the WP build may use different markup, we mask each site INDEPENDENTLY by selector
// (resolved against masks.json maskGroups). As long as both sites expose the region via
// some selector in the group, the region is blanked on both and cannot drive a diff.
//
// We paint in-DOM (rather than use Playwright's screenshot `mask`) so the overpaint
// participates in layout-neutral fixed positioning and survives full-page capture, and so
// a missing region on one side is visible (it simply isn't painted) rather than silently
// shifting the layout.

const fs = require('fs');
const path = require('path');

const masksCfg = JSON.parse(
  fs.readFileSync(path.join(__dirname, '..', 'config', 'masks.json'), 'utf8')
);

// Resolve a page's dynamic-region keys (from urls.json) to the concrete selector list.
function selectorsForGroups(groupKeys) {
  const sels = [];
  for (const key of groupKeys || []) {
    const group = masksCfg.maskGroups[key];
    if (group) sels.push(...group);
  }
  return sels;
}

// Overpaint matched elements. Returns the count of regions masked (for diagnostics — a 0
// where >0 was expected is itself worth surfacing as a possible selector drift).
async function applyMasks(page, groupKeys) {
  const selectors = selectorsForGroups(groupKeys);
  if (selectors.length === 0) return 0;
  return page.evaluate((sels) => {
    let n = 0;
    const seen = new Set();
    for (const sel of sels) {
      let nodes;
      try { nodes = document.querySelectorAll(sel); } catch (e) { continue; }
      nodes.forEach((el) => {
        if (seen.has(el)) return;
        seen.add(el);
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) return;
        const overlay = document.createElement('div');
        overlay.setAttribute('data-buckleup-mask', '');
        Object.assign(overlay.style, {
          position: 'absolute',
          left: `${r.left + window.scrollX}px`,
          top: `${r.top + window.scrollY}px`,
          width: `${r.width}px`,
          height: `${r.height}px`,
          background: '#7f7f7f',
          zIndex: '2147483647',
          pointerEvents: 'none',
        });
        document.body.appendChild(overlay);
        n++;
      });
    }
    return n;
  }, selectors);
}

function thresholdForPage(pageKey) {
  const base = masksCfg.defaultThreshold;
  const per = (masksCfg.perPage && masksCfg.perPage[pageKey]) || {};
  return {
    pixelmatchThreshold: per.pixelmatchThreshold ?? base.pixelmatchThreshold,
    maxDiffPixelRatio: per.maxDiffPixelRatio ?? base.maxDiffPixelRatio,
  };
}

module.exports = { applyMasks, selectorsForGroups, thresholdForPage, masksCfg };

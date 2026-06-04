// Thin pixelmatch wrapper. Compares two PNG buffers and writes a diff image; tolerates
// differing canvas sizes by comparing on the common (min) bounding box and counting the
// non-overlapping margin as fully-diffed pixels (so a height delta is penalized, not
// silently ignored). Returns { width, height, diffPixels, totalPixels, ratio }.

const { PNG } = require('pngjs');
// pixelmatch v6 is ESM (type:module); under CommonJS require() it resolves to
// { __esModule:true, default: fn }, so unwrap the default export to get the callable.
const pixelmatchModule = require('pixelmatch');
const pixelmatch = pixelmatchModule.default || pixelmatchModule;

function readPng(buf) {
  return PNG.sync.read(buf);
}

function compareBuffers(baselineBuf, candidateBuf, { pixelmatchThreshold = 0.1 } = {}) {
  const a = readPng(baselineBuf);
  const b = readPng(candidateBuf);

  const width = Math.min(a.width, b.width);
  const height = Math.min(a.height, b.height);
  const total = Math.max(a.width, b.width) * Math.max(a.height, b.height);

  // Crop both to the common box into fresh PNGs so pixelmatch sees equal dimensions.
  const ca = cropTo(a, width, height);
  const cb = cropTo(b, width, height);
  const diff = new PNG({ width, height });

  const matchedDiff = pixelmatch(ca.data, cb.data, diff.data, width, height, {
    threshold: pixelmatchThreshold,
    includeAA: false,
    alpha: 0.4,
    diffColor: [255, 0, 0],
  });

  // Pixels outside the common box (size mismatch) count as differing.
  const marginDiff = total - width * height;
  const diffPixels = matchedDiff + marginDiff;

  return {
    width: a.width,
    height: a.height,
    candidateWidth: b.width,
    candidateHeight: b.height,
    comparedWidth: width,
    comparedHeight: height,
    diffPixels,
    totalPixels: total,
    ratio: total > 0 ? diffPixels / total : 0,
    diffPng: PNG.sync.write(diff),
    sizeMismatch: a.width !== b.width || a.height !== b.height,
  };
}

function cropTo(png, w, h) {
  if (png.width === w && png.height === h) return png;
  const out = new PNG({ width: w, height: h });
  PNG.bitblt(png, out, 0, 0, w, h, 0, 0);
  return out;
}

module.exports = { compareBuffers };

// Site endpoints. The golden master is the LIVE Next.js site; the candidate is the
// Dockerized WordPress build. Override via env for CI or an alternate host.
//
//   LIVE_BASE_URL       default https://www.buckleupdriving.ca
//   CANDIDATE_BASE_URL  default http://localhost:8080
//
// Most runners take a --target flag (live|candidate) so the same spec captures the
// baseline and validates the build.

const LIVE = process.env.LIVE_BASE_URL || 'https://www.buckleupdriving.ca';
const CANDIDATE = process.env.CANDIDATE_BASE_URL || 'http://localhost:8080';

function baseUrlFor(target) {
  if (target === 'live') return LIVE;
  if (target === 'candidate') return CANDIDATE;
  throw new Error(`Unknown target "${target}" (expected "live" or "candidate")`);
}

module.exports = { LIVE, CANDIDATE, baseUrlFor };

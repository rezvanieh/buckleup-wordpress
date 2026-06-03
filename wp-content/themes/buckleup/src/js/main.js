// BuckleUp theme JS entry. The interactions team adds the framer-motion replacements
// here: IntersectionObserver scroll-reveal, hero 3D mouse-tilt, scroll-aware navbar,
// magic-move active indicators, FAQ accordion, lightbox. This stub wires the theme
// toggle so the dark/light token swap works from day one.

function applyStoredTheme() {
  const stored = localStorage.getItem('buckleup-theme') || 'system';
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const dark = stored === 'dark' || (stored === 'system' && prefersDark);
  document.documentElement.classList.toggle('dark', dark);
  document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
}

document.addEventListener('DOMContentLoaded', () => {
  applyStoredTheme();
  document.querySelectorAll('[data-theme-toggle]').forEach((el) => {
    el.addEventListener('click', () => {
      const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
      localStorage.setItem('buckleup-theme', next);
      applyStoredTheme();
    });
  });
});

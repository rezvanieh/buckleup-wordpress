// Scroll-aware navbar — the source Navbar sets `isScrolled = scrollY > 20` and
// swaps the header chrome (bg-background/95 + blur-2xl + border-b + shadow when
// scrolled, bg-background/80 + blur-xl at top), transitioning over 500ms. The
// height classes (h-16 min-[1100px]:h-32) and logo (h-8 min-[1100px]:h-16) are
// static in markup; the visible "shrink" is the chrome swap + the desktop logo.
//
// We don't bake the class swap into JS (markup lives in the header part, Task #3).
// Instead JS toggles data-scrolled="true|false" on [data-navbar]; the header uses
// data-[scrolled=true]:… Tailwind variants so the exact classes stay in the part.

const SCROLL_THRESHOLD = 20;

export function initNavbar(root = document) {
  const nav = root.querySelector('[data-navbar]');
  if (!nav) return;

  let ticking = false;
  const update = () => {
    nav.setAttribute('data-scrolled', window.scrollY > SCROLL_THRESHOLD ? 'true' : 'false');
    ticking = false;
  };

  const onScroll = () => {
    if (!ticking) {
      window.requestAnimationFrame(update);
      ticking = true;
    }
  };

  update();
  window.addEventListener('scroll', onScroll, { passive: true });

  // Mobile menu (hamburger) — toggles data-state on the panel for tw-animate-css.
  const toggle = nav.querySelector('[data-nav-toggle]');
  const panel = root.querySelector('[data-nav-mobile]');
  if (toggle && panel) {
    toggle.addEventListener('click', () => {
      const open = panel.getAttribute('data-state') === 'open';
      panel.setAttribute('data-state', open ? 'closed' : 'open');
      toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
      if (open) {
        panel.hidden = true;
      } else {
        panel.hidden = false;
      }
    });
  }
}

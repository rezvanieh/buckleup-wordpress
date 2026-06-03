// Graduates lightbox — shared-element image viewer reproducing the source
// GraduatesGallery framer-motion `layoutId="img-{id}"` transition: clicking a
// thumbnail opens a full-screen overlay and FLIP-animates the image from the
// thumbnail's box to the centered viewer. Supports prev/next, Escape, backdrop
// click, and focus trapping basics. Reduced-motion: no FLIP, instant open.
//
// Markup contract (rendered by the Graduates pattern in Task #3):
//   <div data-lightbox>                         ← rail/grid wrapper
//     <button data-lightbox-item                ← each thumbnail
//             data-full="https://…/large.jpg"
//             data-title="…" data-desc="…">
//       <img …>
//     </button>
//   </div>
// The overlay is built once and reused.

import { prefersReducedMotion } from '../lib/motion-prefs.js';

let overlay = null;
let imgEl = null;
let titleEl = null;
let descEl = null;
let items = [];
let index = 0;
let lastFocused = null;

function buildOverlay() {
  overlay = document.createElement('div');
  overlay.setAttribute('data-lightbox-overlay', '');
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.hidden = true;
  overlay.className =
    'fixed inset-0 z-[100] hidden items-center justify-center bg-background/95 backdrop-blur-2xl p-4 data-[open=true]:flex';
  overlay.innerHTML = `
    <button type="button" data-lightbox-close aria-label="Close"
      class="absolute top-6 right-6 md:top-10 md:right-10 p-3 md:p-4 rounded-3xl bg-muted/50 hover:bg-muted text-foreground transition-all z-[110] border border-border/50 hover:rotate-90 active:scale-90">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 md:w-8 md:h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
    </button>
    <div class="relative w-full h-full flex flex-col items-center justify-center gap-6" data-lightbox-stage>
      <div class="relative flex-1 w-full max-w-5xl flex items-center justify-center min-h-0">
        <div data-lightbox-frame class="relative flex items-center justify-center max-h-full w-auto rounded-[2.5rem] overflow-hidden shadow-[0_32px_128px_-12px_rgba(0,0,0,0.5)] border border-border/30">
          <img data-lightbox-image alt="" class="max-h-[70vh] md:max-h-[75vh] w-auto h-auto object-contain rounded-[2.5rem]">
        </div>
      </div>
      <div class="w-full max-w-2xl flex flex-col items-center gap-6 pb-6 text-center">
        <h2 data-lightbox-title class="text-2xl md:text-4xl font-black text-foreground uppercase tracking-tighter leading-none"></h2>
        <p data-lightbox-desc class="text-muted-foreground text-sm md:text-base leading-relaxed max-w-lg mx-auto font-medium"></p>
        <div class="flex items-center gap-4" data-lightbox-nav></div>
      </div>
    </div>`;
  document.body.appendChild(overlay);

  imgEl = overlay.querySelector('[data-lightbox-image]');
  titleEl = overlay.querySelector('[data-lightbox-title]');
  descEl = overlay.querySelector('[data-lightbox-desc]');

  overlay.addEventListener('click', (e) => {
    if (e.target.closest('[data-lightbox-close]') || e.target === overlay) close();
  });

  // prev / next buttons
  const nav = overlay.querySelector('[data-lightbox-nav]');
  const mkBtn = (label, dir) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('aria-label', label);
    b.className =
      'inline-flex items-center justify-center h-11 rounded-lg px-8 border-2 border-border bg-background text-foreground shadow-sm hover:bg-secondary hover:border-primary/50 transition-all duration-200 text-sm font-semibold';
    b.textContent = label;
    b.addEventListener('click', () => go(dir));
    return b;
  };
  nav.append(mkBtn('Previous', -1), mkBtn('Next', 1));
  overlay.dataset.navReady = '1';
}

function render(fromRect) {
  const item = items[index];
  if (!item) return;
  imgEl.src = item.dataset.full || item.querySelector('img')?.src || '';
  imgEl.alt = item.dataset.title || '';
  titleEl.textContent = item.dataset.title || 'Success Story';
  const desc = item.dataset.desc || '';
  descEl.textContent = desc;
  descEl.hidden = !desc;

  const nav = overlay.querySelector('[data-lightbox-nav]');
  nav.hidden = items.length <= 1;

  // FLIP from the thumbnail box (skip under reduced motion).
  if (fromRect && !prefersReducedMotion()) {
    const frame = overlay.querySelector('[data-lightbox-frame]');
    const onLoad = () => {
      const toRect = frame.getBoundingClientRect();
      if (!toRect.width || !toRect.height) return;
      const dx = fromRect.left + fromRect.width / 2 - (toRect.left + toRect.width / 2);
      const dy = fromRect.top + fromRect.height / 2 - (toRect.top + toRect.height / 2);
      const s = fromRect.width / toRect.width;
      frame.animate(
        [
          { transform: `translate(${dx}px, ${dy}px) scale(${s})`, opacity: 0.6 },
          { transform: 'translate(0,0) scale(1)', opacity: 1 },
        ],
        { duration: 420, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' }
      );
    };
    if (imgEl.complete) onLoad();
    else imgEl.addEventListener('load', onLoad, { once: true });
  }
}

function open(i, fromRect) {
  index = i;
  if (!overlay.dataset.navReady) return;
  lastFocused = document.activeElement;
  overlay.hidden = false;
  overlay.dataset.open = 'true';
  document.body.style.overflow = 'hidden';
  render(fromRect);
  overlay.querySelector('[data-lightbox-close]')?.focus();
}

function close() {
  overlay.dataset.open = 'false';
  overlay.hidden = true;
  document.body.style.overflow = '';
  if (lastFocused) lastFocused.focus();
}

function go(dir) {
  index = (index + dir + items.length) % items.length;
  render(null);
}

function onKey(e) {
  if (overlay.dataset.open !== 'true') return;
  if (e.key === 'Escape') close();
  else if (e.key === 'ArrowLeft') go(-1);
  else if (e.key === 'ArrowRight') go(1);
}

export function initLightbox(root = document) {
  const containers = root.querySelectorAll('[data-lightbox]');
  if (!containers.length) return;

  if (!overlay) {
    buildOverlay();
    document.addEventListener('keydown', onKey);
  }

  items = Array.from(root.querySelectorAll('[data-lightbox-item]'));
  items.forEach((item, i) => {
    item.addEventListener('click', () => {
      const rect = (item.querySelector('img') || item).getBoundingClientRect();
      open(i, rect);
    });
  });
}

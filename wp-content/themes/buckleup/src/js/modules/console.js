// Console (portal) shell behavior: the mobile sidebar drawer. The desktop sidebar
// is static; on mobile a hamburger ([data-console-toggle]) opens the slide-in
// drawer ([data-console-drawer]), closed by the X, the overlay, or Escape.

export function initConsole(root = document) {
  const drawer = root.querySelector('[data-console-drawer]');
  if (!drawer) return;

  const open = () => {
    drawer.hidden = false;
    drawer.setAttribute('data-state', 'open');
    document.body.style.overflow = 'hidden';
  };
  const close = () => {
    drawer.setAttribute('data-state', 'closed');
    document.body.style.overflow = '';
    // Hide after the (CSS) transition window.
    window.setTimeout(() => {
      if (drawer.getAttribute('data-state') === 'closed') drawer.hidden = true;
    }, 200);
  };

  root.querySelectorAll('[data-console-toggle]').forEach((b) => b.addEventListener('click', open));
  root.querySelectorAll('[data-console-close]').forEach((b) => b.addEventListener('click', close));
  drawer.querySelector('[data-console-overlay]')?.addEventListener('click', close);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && drawer.getAttribute('data-state') === 'open') close();
  });
}

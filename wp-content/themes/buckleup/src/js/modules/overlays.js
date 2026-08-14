// Dialog / DropdownMenu / Select behavior — toggles the same data-state / data-side
// attributes the Radix originals set, so the `tw-animate-css` enter/exit utilities
// (data-[state=open]:animate-in, data-[side=bottom]:slide-in-from-top-2, …) play
// exactly as before. No animation logic here beyond attribute flips; CSS owns it.

/* ----------------------------- Dialog ------------------------------------ */

function openDialog(dialog) {
  dialog.hidden = false;
  dialog.setAttribute('data-state', 'open');
  dialog.querySelectorAll('[data-state]').forEach((n) => n.setAttribute('data-state', 'open'));
  document.body.style.overflow = 'hidden';
  dialog.querySelector('[data-dialog-close]')?.focus();
}

function closeDialog(dialog) {
  dialog.setAttribute('data-state', 'closed');
  dialog.querySelectorAll('[data-state]').forEach((n) => n.setAttribute('data-state', 'closed'));
  document.body.style.overflow = '';
  // Wait for the close animation, then hide.
  window.setTimeout(() => {
    if (dialog.getAttribute('data-state') === 'closed') dialog.hidden = true;
  }, 200);
}

function initDialogs(root) {
  root.querySelectorAll('[data-dialog-open]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      const dialog = root.querySelector(`[data-dialog="${trigger.getAttribute('data-dialog-open')}"]`);
      if (dialog) openDialog(dialog);
    });
  });

  root.querySelectorAll('[data-dialog]').forEach((dialog) => {
    dialog.addEventListener('click', (e) => {
      if (e.target.closest('[data-dialog-close]') || e.target.matches('[data-dialog-overlay]')) {
        closeDialog(dialog);
      }
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    root.querySelectorAll('[data-dialog][data-state="open"]').forEach(closeDialog);
  });
}

/* --------------------------- DropdownMenu -------------------------------- */
// A [data-dropdown] wraps a [data-dropdown-trigger] and [data-dropdown-content].

function initDropdowns(root) {
  const dropdowns = Array.from(root.querySelectorAll('[data-dropdown]'));

  const closeAll = (except) => {
    dropdowns.forEach((d) => {
      if (d === except) return;
      const c = d.querySelector('[data-dropdown-content]');
      const t = d.querySelector('[data-dropdown-trigger]');
      if (c && c.getAttribute('data-state') === 'open') {
        c.setAttribute('data-state', 'closed');
        t?.setAttribute('aria-expanded', 'false');
        window.setTimeout(() => {
          if (c.getAttribute('data-state') === 'closed') c.hidden = true;
        }, 150);
      }
    });
  };

  dropdowns.forEach((d) => {
    const trigger = d.querySelector('[data-dropdown-trigger]');
    const content = d.querySelector('[data-dropdown-content]');
    if (!trigger || !content) return;
    if (!content.hasAttribute('data-side')) content.setAttribute('data-side', 'bottom');

    const open = () => {
      closeAll(d);
      content.hidden = false;
      content.setAttribute('data-state', 'open');
      trigger.setAttribute('aria-expanded', 'true');
    };
    const close = () => {
      content.setAttribute('data-state', 'closed');
      trigger.setAttribute('aria-expanded', 'false');
      window.setTimeout(() => {
        if (content.getAttribute('data-state') === 'closed') content.hidden = true;
      }, 150);
    };

    // Click-toggle (mobile/keyboard); hover-open is left to CSS on desktop nav if
    // the part opts in. data-dropdown-hover enables JS hover open/close.
    // A trigger that is a real link (the Services pillar) must stay navigable:
    // clicking it goes to the page, hover still opens the menu. Only button-style
    // triggers toggle on click. Without this, making Services a dropdown would
    // silently make the pillar page unreachable from the nav.
    const isLinkTrigger = trigger.tagName === 'A' && trigger.getAttribute('href');
    trigger.addEventListener('click', (e) => {
      if (isLinkTrigger) return; // let the browser follow the href
      e.preventDefault();
      content.getAttribute('data-state') === 'open' ? close() : open();
    });

    if (d.hasAttribute('data-dropdown-hover')) {
      let t;
      d.addEventListener('mouseenter', () => {
        clearTimeout(t);
        open();
      });
      d.addEventListener('mouseleave', () => {
        t = window.setTimeout(close, 120);
      });
    }
  });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-dropdown]')) closeAll(null);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAll(null);
  });
}

/* ------------------------------ Select ----------------------------------- */
// A [data-select] wraps a [data-select-trigger] (with [data-select-value]),
// a hidden <select name> for form submission, and a [data-select-content] listbox
// of [data-select-item value=…] options.

function initSelects(root) {
  root.querySelectorAll('[data-select]').forEach((sel) => {
    const trigger = sel.querySelector('[data-select-trigger]');
    const content = sel.querySelector('[data-select-content]');
    const valueEl = sel.querySelector('[data-select-value]');
    const native = sel.querySelector('select');
    if (!trigger || !content) return;

    const open = () => {
      content.hidden = false;
      content.setAttribute('data-state', 'open');
      trigger.setAttribute('aria-expanded', 'true');
      trigger.setAttribute('data-state', 'open');
    };
    const close = () => {
      content.setAttribute('data-state', 'closed');
      trigger.setAttribute('aria-expanded', 'false');
      trigger.setAttribute('data-state', 'closed');
      window.setTimeout(() => {
        if (content.getAttribute('data-state') === 'closed') content.hidden = true;
      }, 150);
    };

    trigger.addEventListener('click', () => {
      content.getAttribute('data-state') === 'open' ? close() : open();
    });

    content.querySelectorAll('[data-select-item]').forEach((item) => {
      item.addEventListener('click', () => {
        const value = item.getAttribute('data-select-item');
        if (valueEl) {
          valueEl.textContent = item.textContent.trim();
          valueEl.removeAttribute('data-placeholder');
        }
        if (native) {
          native.value = value;
          native.dispatchEvent(new Event('change', { bubbles: true }));
        }
        content.querySelectorAll('[data-select-item]').forEach((o) =>
          o.setAttribute('data-state', o === item ? 'checked' : 'unchecked')
        );
        close();
      });
    });

    document.addEventListener('click', (e) => {
      if (!sel.contains(e.target)) close();
    });
  });
}

export function initOverlays(root = document) {
  initDialogs(root);
  initDropdowns(root);
  initSelects(root);
}

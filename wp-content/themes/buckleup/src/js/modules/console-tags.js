// Tag inputs (certifications/languages on the instructor profile) + the bio
// character counter. Each [data-tag-field] has a [data-tag-list] of chips, a
// [data-tag-input] (Enter to add), and a hidden input[name] holding the JSON
// array that console-profile.js submits. Removing/adding a chip updates the
// hidden input and fires an 'input' event so the dirty-tracker notices.

function syncHidden(field) {
  const hidden = field.querySelector('input[type="hidden"][name]');
  const chips = Array.from(field.querySelectorAll('[data-tag-list] [data-tag-value]')).map((c) => c.dataset.tagValue);
  if (hidden) {
    hidden.value = JSON.stringify(chips);
    hidden.dispatchEvent(new Event('input', { bubbles: true }));
  }
}

function makeChip(field, value) {
  const list = field.querySelector('[data-tag-list]');
  if (!list) return;
  const existing = Array.from(list.querySelectorAll('[data-tag-value]')).map((c) => c.dataset.tagValue.toLowerCase());
  if (existing.includes(value.toLowerCase())) return;
  const chip = document.createElement('span');
  chip.className = 'inline-flex items-center gap-1 text-xs px-3 py-1 rounded-full bg-primary/10 text-primary';
  chip.setAttribute('data-tag-value', value);
  chip.innerHTML = '';
  chip.append(document.createTextNode(value));
  const rm = document.createElement('button');
  rm.type = 'button';
  rm.setAttribute('data-tag-remove', '');
  rm.setAttribute('aria-label', 'Remove');
  rm.className = 'hover:text-destructive';
  rm.textContent = '×';
  chip.appendChild(rm);
  list.appendChild(chip);
  syncHidden(field);
}

export function initConsoleTags(root = document) {
  // Tag the server-rendered chips with their value so JS can read them.
  root.querySelectorAll('[data-tag-field] [data-tag-list] > span').forEach((chip) => {
    if (!chip.hasAttribute('data-tag-value')) {
      chip.setAttribute('data-tag-value', chip.textContent.replace(/×\s*$/, '').trim());
    }
  });

  root.querySelectorAll('[data-tag-field]').forEach((field) => {
    const input = field.querySelector('[data-tag-input]');
    input?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const v = input.value.trim();
        if (v) {
          makeChip(field, v);
          input.value = '';
        }
      }
    });
    field.addEventListener('click', (e) => {
      const rm = e.target.closest('[data-tag-remove]');
      if (rm) {
        rm.closest('[data-tag-value]')?.remove();
        syncHidden(field);
      }
    });
  });

  // Bio character counter.
  const bio = root.querySelector('textarea[name="bio"]');
  const bioCount = root.querySelector('[data-bio-count]');
  if (bio && bioCount) {
    bio.addEventListener('input', () => { bioCount.textContent = String(bio.value.length); });
  }
}

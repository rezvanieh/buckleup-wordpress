// Admin Graduates: upload (multipart) + delete-with-confirm over the public
// `graduate` CPT. Picking a file shows a live object-URL preview and enables the
// submit; submit → POST /graduates (FormData: file, title, category="Graduates")
// → 201 prepends a tile. Hover-delete opens a confirm modal → DELETE
// /graduates/{id}, then removes the tile + updates the count/empty state. Uses
// window.buckleupAuth (X-WP-Nonce on both mutations).

const esc = (s) => {
  const d = document.createElement('div');
  d.textContent = s == null ? '' : String(s);
  return d.innerHTML;
};

export function initConsoleGraduates(root = document) {
  const form = root.querySelector('[data-graduate-form]');
  const grid = root.querySelector('[data-graduate-grid]');
  if (!form || !grid) return;

  const cfg = window.buckleupAuth || {};
  const api = (cfg.restUrl || '/wp-json/buckleup/v1/') + 'graduates';
  const nonce = cfg.nonce || '';

  const fileInput = root.querySelector('[data-graduate-file]');
  const titleInput = root.querySelector('[data-graduate-title]');
  const submit = root.querySelector('[data-graduate-submit]');
  const statusEl = root.querySelector('[data-graduate-status]');
  const placeholder = root.querySelector('[data-graduate-placeholder]');
  const previewWrap = root.querySelector('[data-graduate-preview]');
  const previewImg = root.querySelector('[data-graduate-preview-img]');
  const clearBtn = root.querySelector('[data-graduate-clear]');
  const countEl = root.querySelector('[data-graduate-count]');
  const emptyEl = root.querySelector('[data-graduate-empty]');

  let objectUrl = null;

  const setStatus = (kind, msg) => {
    if (!statusEl) return;
    statusEl.hidden = false;
    statusEl.classList.remove('hidden');
    statusEl.className = 'flex items-center gap-2 text-sm font-medium ' + (kind === 'success' ? 'text-green-600' : 'text-destructive');
    statusEl.textContent = msg;
  };
  const clearStatus = () => {
    if (!statusEl) return;
    statusEl.hidden = true;
    statusEl.classList.add('hidden');
    statusEl.textContent = '';
  };

  const revoke = () => { if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; } };

  const showPreview = (file) => {
    revoke();
    objectUrl = URL.createObjectURL(file);
    if (previewImg) previewImg.src = objectUrl;
    previewWrap?.classList.remove('hidden');
    placeholder?.classList.add('hidden');
  };
  const resetPreview = () => {
    revoke();
    previewWrap?.classList.add('hidden');
    placeholder?.classList.remove('hidden');
    if (previewImg) previewImg.removeAttribute('src');
  };

  const syncSubmit = () => { if (submit) submit.disabled = !(fileInput && fileInput.files && fileInput.files[0]); };

  fileInput?.addEventListener('change', () => {
    clearStatus();
    const file = fileInput.files && fileInput.files[0];
    if (file) showPreview(file);
    else resetPreview();
    syncSubmit();
  });

  clearBtn?.addEventListener('click', () => {
    if (fileInput) fileInput.value = '';
    resetPreview();
    syncSubmit();
  });

  function refreshCountAndEmpty() {
    const tiles = grid.querySelectorAll('[data-graduate]').length;
    if (countEl) countEl.textContent = String(tiles);
    grid.classList.toggle('hidden', tiles === 0);
    emptyEl?.classList.toggle('hidden', tiles !== 0);
  }

  function prependTile(g) {
    const tile = document.createElement('div');
    tile.className = 'group relative aspect-square rounded-xl overflow-hidden border border-border bg-card shadow-sm';
    tile.setAttribute('data-graduate', String(g.id));
    const title = g.title || 'Untitled graduate';
    tile.innerHTML = `${g.url ? `<img src="${esc(g.url)}" alt="${esc(g.title || '')}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" loading="lazy" decoding="async">` : ''}
      <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
        <div class="absolute bottom-0 left-0 right-0 p-3"><p class="text-white text-xs font-bold truncate">${esc(title)}</p></div>
        <button type="button" data-graduate-delete="${esc(g.id)}" aria-label="Delete image" class="absolute top-2 right-2 p-2 bg-destructive/90 text-destructive-foreground rounded-lg hover:bg-destructive shadow-lg transition-colors">×</button>
      </div>`;
    grid.prepend(tile);
    refreshCountAndEmpty();
  }

  /* ----------------------------- upload --------------------------------- */
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const file = fileInput && fileInput.files && fileInput.files[0];
    if (!file) return;
    if (submit) submit.disabled = true;
    clearStatus();

    const fd = new FormData();
    fd.append('file', file);
    fd.append('title', titleInput?.value || '');
    fd.append('category', 'Graduates');

    try {
      const res = await fetch(api, { method: 'POST', headers: { 'X-WP-Nonce': nonce }, credentials: 'same-origin', body: fd });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setStatus('error', data.message || 'Failed to upload image. Please try again.');
        if (submit) submit.disabled = false;
        return;
      }
      prependTile(data);
      // Reset the form.
      if (fileInput) fileInput.value = '';
      if (titleInput) titleInput.value = '';
      resetPreview();
      setStatus('success', 'Graduates image uploaded successfully!');
    } catch (_e) {
      setStatus('error', 'Failed to upload image. Please try again.');
      if (submit) submit.disabled = false;
    }
  });

  /* ---------------------------- delete ---------------------------------- */
  const dialog = root.querySelector('[data-graduate-del-dialog]');
  let pendingId = null;
  const openDialog = (id) => {
    pendingId = id;
    dialog.hidden = false;
    dialog.classList.remove('hidden');
    dialog.classList.add('flex');
    dialog.setAttribute('data-state', 'open');
  };
  const closeDialog = () => {
    pendingId = null;
    dialog.hidden = true;
    dialog.classList.add('hidden');
    dialog.classList.remove('flex');
    dialog.setAttribute('data-state', 'closed');
  };

  // Delegate (tiles are added/removed dynamically).
  grid.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-graduate-delete]');
    if (btn) openDialog(btn.getAttribute('data-graduate-delete'));
  });
  root.querySelector('[data-graduate-del-cancel]')?.addEventListener('click', closeDialog);
  root.querySelector('[data-graduate-del-overlay]')?.addEventListener('click', closeDialog);

  const confirmBtn = root.querySelector('[data-graduate-del-confirm]');
  confirmBtn?.addEventListener('click', async () => {
    if (!pendingId) return;
    confirmBtn.disabled = true;
    try {
      const res = await fetch(`${api}/${pendingId}`, { method: 'DELETE', headers: { 'X-WP-Nonce': nonce }, credentials: 'same-origin' });
      if (res.ok) {
        grid.querySelector(`[data-graduate="${CSS.escape(pendingId)}"]`)?.remove();
        refreshCountAndEmpty();
      }
    } catch (_e) {
      // leave the tile in place on failure.
    } finally {
      confirmBtn.disabled = false;
      closeDialog();
    }
  });
}

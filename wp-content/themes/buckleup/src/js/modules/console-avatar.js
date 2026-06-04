// Standalone avatar upload/remove for the admin Settings profile card
// ([data-avatar-card]) — the student/instructor Profile pages handle their own
// avatar inside console-profile.js (gated on [data-profile-form]), so this only
// wires the admin card, which has no profile-save form. POST /user/avatar
// (multipart) sets the photo; DELETE clears it. Response avatar key is `avatar`
// (team decision; `url` accepted as a fallback). Uses window.buckleupAuth.

export function initConsoleAvatar(root = document) {
  const card = root.querySelector('[data-avatar-card]');
  if (!card) return;

  const cfg = window.buckleupAuth || {};
  const api = (cfg.restUrl || '/wp-json/buckleup/v1/') + 'user/avatar';
  const input = card.querySelector('[data-avatar-input]');
  const preview = card.querySelector('[data-avatar-preview]');
  const remove = card.querySelector('[data-avatar-remove]');
  const statusEl = card.querySelector('[data-avatar-status]');

  const setStatus = (kind, msg) => {
    if (!statusEl) return;
    statusEl.hidden = false;
    statusEl.classList.remove('hidden');
    statusEl.className = 'mt-4 text-sm ' + (kind === 'success' ? 'text-accent' : 'text-destructive');
    statusEl.textContent = msg;
  };

  input?.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fetch(api, { method: 'POST', headers: { 'X-WP-Nonce': cfg.nonce || '' }, credentials: 'same-origin', body: fd })
      .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
      .then(({ ok, data }) => {
        const url = data && (data.avatar || data.url);
        if (ok && url && preview) {
          preview.innerHTML = `<img src="${url}" alt="" class="w-full h-full object-cover">`;
          setStatus('success', 'Profile photo updated!');
        } else {
          setStatus('error', (data && (data.message || data.error)) || 'Upload failed.');
        }
      })
      .catch(() => setStatus('error', 'Upload failed. Please try again.'));
  });

  remove?.addEventListener('click', () => {
    fetch(api, { method: 'DELETE', headers: { 'X-WP-Nonce': cfg.nonce || '' }, credentials: 'same-origin' })
      .then((res) => {
        if (res.ok && preview) {
          preview.innerHTML = '';
          setStatus('success', 'Profile photo removed.');
        } else {
          setStatus('error', 'Could not remove photo.');
        }
      })
      .catch(() => setStatus('error', 'Could not remove photo.'));
  });
}

// Console Profile pages (student + instructor): dirty-tracking with an unsaved-
// changes pill + Save/Discard, the PUT save mutation, and avatar upload/remove.
// Endpoint is data-driven (data-profile-endpoint, e.g. "students/profile" or
// "instructors/profile"). Avatar uses POST/DELETE /user/avatar; the POST response
// avatar key is `avatar` (team decision). All via window.buckleupAuth.

export function initConsoleProfile(root = document) {
  const form = root.querySelector('[data-profile-form]');
  if (!form) return;

  const cfg = window.buckleupAuth || {};
  const endpoint = form.getAttribute('data-profile-endpoint') || 'students/profile';
  const save = form.querySelector('[data-profile-save]');
  const discard = form.querySelector('[data-profile-discard]');
  const dirtyPill = form.querySelector('[data-profile-dirty]');
  const status = root.querySelector('[data-profile-status]');

  // Snapshot the initial field values to detect dirty + support Discard.
  const fields = Array.from(form.querySelectorAll('[name]'));
  const initial = {};
  fields.forEach((f) => { initial[f.name] = f.value; });

  const isDirty = () => fields.some((f) => f.value !== initial[f.name]);
  const refresh = () => {
    const dirty = isDirty();
    if (save) save.disabled = !dirty;
    [discard, dirtyPill].forEach((el) => el && el.classList.toggle('hidden', !dirty));
  };
  fields.forEach((f) => f.addEventListener('input', refresh));

  const setStatus = (kind, msg) => {
    if (!status) return;
    status.hidden = false;
    status.classList.remove('hidden');
    const ok = kind === 'success';
    status.className = 'mb-4 rounded-lg px-4 py-3 text-sm border ' +
      (ok ? 'bg-accent/10 text-accent border-accent/20' : 'bg-destructive/10 text-destructive border-destructive/20');
    status.textContent = msg;
  };

  // Discard → restore snapshot.
  discard?.addEventListener('click', () => {
    fields.forEach((f) => { f.value = initial[f.name]; });
    refresh();
  });

  // Save (PUT).
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!isDirty()) return;
    const payload = {};
    fields.forEach((f) => { payload[f.name] = f.value; });
    if (save) save.disabled = true;

    fetch((cfg.restUrl || '/wp-json/buckleup/v1/') + endpoint, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })
      .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
      .then(({ ok, data }) => {
        if (ok) {
          fields.forEach((f) => { initial[f.name] = f.value; });
          refresh();
          setStatus('success', 'Profile saved.');
        } else {
          setStatus('error', (data && (data.message || data.error)) || 'Could not save. Please try again.');
          if (save) save.disabled = false;
        }
      })
      .catch(() => {
        setStatus('error', 'Something went wrong. Please try again.');
        if (save) save.disabled = false;
      });
  });

  // Avatar upload (POST multipart) + remove (DELETE).
  const avatarInput = form.querySelector('[data-avatar-input]');
  const avatarPreview = form.querySelector('[data-avatar-preview]');
  const avatarRemove = form.querySelector('[data-avatar-remove]');

  avatarInput?.addEventListener('change', () => {
    const file = avatarInput.files && avatarInput.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fetch((cfg.restUrl || '/wp-json/buckleup/v1/') + 'user/avatar', {
      method: 'POST',
      headers: { 'X-WP-Nonce': cfg.nonce || '' },
      credentials: 'same-origin',
      body: fd,
    })
      .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
      .then(({ ok, data }) => {
        if (ok && data && (data.avatar || data.url) && avatarPreview) {
          const url = data.avatar || data.url;
          avatarPreview.innerHTML = '<img src="' + url + '" alt="" class="w-full h-full object-cover">';
          setStatus('success', 'Photo updated.');
        } else {
          setStatus('error', (data && (data.message || data.error)) || 'Upload failed.');
        }
      })
      .catch(() => setStatus('error', 'Upload failed. Please try again.'));
  });

  avatarRemove?.addEventListener('click', () => {
    fetch((cfg.restUrl || '/wp-json/buckleup/v1/') + 'user/avatar', {
      method: 'DELETE',
      headers: { 'X-WP-Nonce': cfg.nonce || '' },
      credentials: 'same-origin',
    })
      .then((res) => {
        if (res.ok && avatarPreview) {
          avatarPreview.innerHTML = '';
          setStatus('success', 'Photo removed.');
        }
      })
      .catch(() => setStatus('error', 'Could not remove photo.'));
  });
}

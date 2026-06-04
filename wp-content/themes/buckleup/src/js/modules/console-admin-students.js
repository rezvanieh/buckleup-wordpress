// Admin Students management: server-rendered page 1, then re-fetch + re-render on
// search / status / license / pagination changes, plus a delete-with-confirm flow.
// GET /admin/students?search&status&licenseType&page → { students, stats,
// pagination }; DELETE /admin/students/{id} cascades (progress, bookings, reviews,
// WP user). Rebuilds the table rows, the 4 stat cards, and the pager from each
// response. Uses window.buckleupAuth (X-WP-Nonce on the delete).

const STATUS_PILL = {
  ACTIVE: 'bg-green-500/10 text-green-600 dark:text-green-400',
  INACTIVE: 'bg-muted text-muted-foreground',
  COMPLETED: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
  SUSPENDED: 'bg-destructive/10 text-destructive',
};

const fmtDate = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};

const initials = (name) =>
  (name || '')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((p) => p[0] || '')
    .join('')
    .toUpperCase();

const esc = (s) => {
  const d = document.createElement('div');
  d.textContent = s == null ? '' : String(s);
  return d.innerHTML;
};

export function initConsoleAdminStudents(root = document) {
  const tbody = root.querySelector('[data-admin-students-rows]');
  if (!tbody) return;

  const cfg = window.buckleupAuth || {};
  const api = (cfg.restUrl || '/wp-json/buckleup/v1/') + 'admin/students';
  const nonceHeaders = { 'X-WP-Nonce': cfg.nonce || '' };
  const statusEl = root.querySelector('[data-admin-students-status]');

  const searchInput = root.querySelector('[data-admin-students-search]');
  const statusSel = root.querySelector('[data-admin-students-status-filter]');
  const licenseSel = root.querySelector('[data-admin-students-license]');
  const pager = root.querySelector('[data-admin-students-pager]');
  const prevBtn = root.querySelector('[data-admin-students-prev]');
  const nextBtn = root.querySelector('[data-admin-students-next]');
  const countEl = root.querySelector('[data-admin-students-count]');
  const pageInfo = root.querySelector('[data-admin-students-pageinfo]');

  let page = 1;
  let lastPagination = { page: 1, pages: 1, total: 0 };

  const setStatus = (kind, msg) => {
    if (!statusEl) return;
    statusEl.hidden = false;
    statusEl.classList.remove('hidden');
    statusEl.className = 'mt-4 text-sm ' + (kind === 'success' ? 'text-accent' : 'text-destructive');
    statusEl.textContent = msg;
  };

  /* ---------------------------- render ---------------------------------- */
  function renderRows(students) {
    if (!students.length) {
      tbody.innerHTML = `<tr><td colspan="8" class="p-4 align-middle text-center text-muted-foreground py-12">No students found.</td></tr>`;
      return;
    }
    tbody.innerHTML = students
      .map((s) => {
        const pill = STATUS_PILL[(s.status || 'INACTIVE').toUpperCase()] || STATUS_PILL.INACTIVE;
        const last = fmtDate(s.lastBooking);
        const joined = fmtDate(s.userCreatedAt);
        const avatar = s.image
          ? `<span data-slot="avatar" class="relative flex size-10 shrink-0 overflow-hidden rounded-full"><img class="aspect-square size-full object-cover" src="${esc(s.image)}" alt="${esc(s.name)}"></span>`
          : `<span data-slot="avatar" class="relative flex size-10 shrink-0 overflow-hidden rounded-full"><span class="bg-primary/10 text-primary flex size-full items-center justify-center rounded-full text-sm font-medium">${esc(initials(s.name))}</span></span>`;
        return `<tr class="border-b transition-colors hover:bg-muted/50" data-admin-student="${esc(s.id)}">
          <td class="p-4 align-middle"><div class="flex items-center gap-3">${avatar}<div><p class="font-medium text-foreground">${esc(s.name)}</p><p class="text-sm text-muted-foreground">${esc(s.email)}</p></div></div></td>
          <td class="p-4 align-middle">${s.phone ? `<span class="text-sm">${esc(s.phone)}</span>` : '<span class="text-sm text-muted-foreground">—</span>'}</td>
          <td class="p-4 align-middle">${s.licenseType ? `<span class="text-sm font-medium text-foreground">${esc(s.licenseType)}</span>` : '<span class="text-sm text-muted-foreground">Not set</span>'}</td>
          <td class="p-4 align-middle"><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${pill}">${esc((s.status || 'INACTIVE').toUpperCase())}</span></td>
          <td class="p-4 align-middle text-center"><span class="font-medium text-foreground">${esc(s.bookingCount || 0)}</span></td>
          <td class="p-4 align-middle">${last ? `<span class="text-sm">${esc(last)}</span>` : '<span class="text-sm text-muted-foreground">Never</span>'}</td>
          <td class="p-4 align-middle"><span class="text-sm">${esc(joined)}</span></td>
          <td class="p-4 align-middle text-right"><button type="button" data-admin-student-delete="${esc(s.id)}" data-admin-student-name="${esc(s.name)}" aria-label="Delete student" class="inline-flex items-center justify-center rounded-md h-9 px-3 text-sm font-semibold text-destructive hover:bg-destructive/10">Delete</button></td>
        </tr>`;
      })
      .join('');
  }

  function renderStats(stats) {
    if (!stats) return;
    const set = (sel, v) => { const el = root.querySelector(sel); if (el) el.textContent = String(v); };
    set('[data-stat-total]', stats.total || 0);
    set('[data-stat-active]', stats.active || 0);
    set('[data-stat-active-pct]', stats.total ? Math.round((stats.active / stats.total) * 100) : 0);
    const lic = root.querySelector('[data-stat-license]');
    if (lic) {
      const entries = Object.entries(stats.byLicenseType || {});
      lic.innerHTML = entries.length
        ? entries.map(([t, c]) => `<div class="flex justify-between text-sm"><span class="text-muted-foreground">${esc(t)}</span><span class="font-medium text-foreground">${esc(c)}</span></div>`).join('')
        : '<p class="text-sm text-muted-foreground">No data</p>';
    }
    const st = root.querySelector('[data-stat-status]');
    if (st) {
      st.innerHTML = Object.entries(stats.byStatus || {})
        .map(([t, c]) => `<div class="flex justify-between text-sm"><span class="text-muted-foreground">${esc(t)}</span><span class="font-medium text-foreground">${esc(c)}</span></div>`)
        .join('');
    }
  }

  function renderPager(pagination, shown) {
    lastPagination = pagination || lastPagination;
    if (pager) pager.classList.toggle('hidden', (pagination.pages || 1) <= 1);
    if (countEl) countEl.textContent = `Showing ${shown} of ${pagination.total || 0} students`;
    if (pageInfo) pageInfo.textContent = `Page ${pagination.page || 1} of ${pagination.pages || 1}`;
    if (prevBtn) prevBtn.disabled = (pagination.page || 1) <= 1;
    if (nextBtn) nextBtn.disabled = (pagination.page || 1) >= (pagination.pages || 1);
  }

  /* ---------------------------- fetch ----------------------------------- */
  async function load() {
    const params = new URLSearchParams();
    params.set('page', String(page));
    if (searchInput?.value.trim()) params.set('search', searchInput.value.trim());
    if (statusSel?.value) params.set('status', statusSel.value);
    if (licenseSel?.value) params.set('licenseType', licenseSel.value);
    try {
      const res = await fetch(`${api}?${params.toString()}`, { headers: nonceHeaders, credentials: 'same-origin' });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setStatus('error', data.message || 'Failed to load students.');
        return;
      }
      renderRows(data.students || []);
      renderStats(data.stats);
      renderPager(data.pagination || { page, pages: 1, total: (data.students || []).length }, (data.students || []).length);
    } catch (_e) {
      setStatus('error', 'Failed to load students.');
    }
  }

  // Debounced search; status/license reset to page 1.
  let timer = null;
  searchInput?.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => { page = 1; load(); }, 300);
  });
  statusSel?.addEventListener('change', () => { page = 1; load(); });
  licenseSel?.addEventListener('change', () => { page = 1; load(); });
  prevBtn?.addEventListener('click', () => { if (page > 1) { page--; load(); } });
  nextBtn?.addEventListener('click', () => { if (page < (lastPagination.pages || 1)) { page++; load(); } });

  /* ------------------------- delete dialog ------------------------------ */
  const dialog = root.querySelector('[data-admin-del-dialog]');
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

  // Delegate delete-button clicks (rows are re-rendered, so listen on tbody).
  tbody.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-admin-student-delete]');
    if (btn) openDialog(btn.getAttribute('data-admin-student-delete'));
  });
  root.querySelector('[data-admin-del-cancel]')?.addEventListener('click', closeDialog);
  root.querySelector('[data-admin-del-overlay]')?.addEventListener('click', closeDialog);

  const confirmBtn = root.querySelector('[data-admin-del-confirm]');
  confirmBtn?.addEventListener('click', async () => {
    if (!pendingId) return;
    confirmBtn.disabled = true;
    try {
      const res = await fetch(`${api}/${pendingId}`, { method: 'DELETE', headers: nonceHeaders, credentials: 'same-origin' });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setStatus('error', data.message || 'Failed to delete student.');
      } else {
        setStatus('success', 'Student and reviews deleted successfully.');
        await load(); // refresh list + stats + pager
      }
    } catch (_e) {
      setStatus('error', 'Failed to delete student.');
    } finally {
      confirmBtn.disabled = false;
      closeDialog();
    }
  });
}

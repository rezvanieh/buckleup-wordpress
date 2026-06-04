// Instructor "My Availability": two tabs.
//   Weekly Schedule — the 7 day rows are server-rendered; here we (a) show/hide
//   each row's time inputs when its switch flips (listening to the bubbling
//   `switch:change` from forms.js), and (b) on Save, PUT each enabled day and
//   DELETE each disabled day on /instructors/availability.
//   Calendar Exceptions — client-rendered month grid from
//   GET /instructors/availability/exceptions; click a future date → dialog →
//   POST (save) / DELETE (remove); an "Upcoming Exceptions" list mirrors it.
// All mutations send X-WP-Nonce (window.buckleupAuth). The weekly state is the
// single source of truth for the calendar's "Available (Weekly)" colouring.

const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const pad = (n) => String(n).padStart(2, '0');
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const today = () => { const t = new Date(); t.setHours(0, 0, 0, 0); return t; };

export function initConsoleAvailability(root = document) {
  const weekly = root.querySelector('[data-weekly]');
  const grid = root.querySelector('[data-cal-grid]');
  if (!weekly && !grid) return;

  const cfg = window.buckleupAuth || {};
  const api = (path) => (cfg.restUrl || '/wp-json/buckleup/v1/') + path;
  const headers = { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' };
  const statusEl = root.querySelector('[data-avail-status]');

  const setStatus = (kind, msg) => {
    if (!statusEl) return;
    statusEl.hidden = false;
    statusEl.classList.remove('hidden');
    statusEl.className = 'mt-4 text-sm ' + (kind === 'success' ? 'text-accent' : 'text-destructive');
    statusEl.textContent = msg;
  };

  /* --------------------------- Tabs ------------------------------------- */
  root.querySelectorAll('[data-avail-tab]').forEach((tab) => {
    tab.addEventListener('click', () => {
      const name = tab.getAttribute('data-avail-tab');
      root.querySelectorAll('[data-avail-tab]').forEach((t) => {
        const on = t === tab;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.classList.toggle('bg-background', on);
        t.classList.toggle('text-foreground', on);
        t.classList.toggle('shadow-sm', on);
        t.classList.toggle('text-muted-foreground', !on);
      });
      root.querySelectorAll('[data-avail-panel]').forEach((p) => {
        p.classList.toggle('hidden', p.getAttribute('data-avail-panel') !== name);
      });
    });
  });

  /* --------------------------- Weekly ----------------------------------- */
  // Read the current weekly state straight off the DOM each time we need it.
  const readWeekly = () =>
    Array.from(root.querySelectorAll('[data-weekly-day]')).map((rowEl) => {
      const sw = rowEl.querySelector('[data-weekly-toggle]');
      return {
        dayOfWeek: Number(rowEl.getAttribute('data-weekly-day')),
        enabled: sw?.getAttribute('data-state') === 'checked',
        startTime: rowEl.querySelector('[data-weekly-start]')?.value || '09:00',
        endTime: rowEl.querySelector('[data-weekly-end]')?.value || '17:00',
      };
    });

  // Switch flips (bubbling event from forms.js) → show/hide the row's controls.
  if (weekly) {
    weekly.addEventListener('switch:change', (e) => {
      const sw = e.target.closest('[data-weekly-toggle]');
      if (!sw) return;
      const rowEl = sw.closest('[data-weekly-day]');
      if (!rowEl) return;
      const on = e.detail && e.detail.checked;
      rowEl.querySelector('[data-weekly-times]')?.classList.toggle('hidden', !on);
      rowEl.querySelector('[data-weekly-off]')?.classList.toggle('hidden', on);
      rowEl.querySelector('[data-weekly-label]')?.classList.toggle('text-foreground', on);
      rowEl.querySelector('[data-weekly-label]')?.classList.toggle('text-muted-foreground', !on);
      rowEl.classList.toggle('bg-accent/5', on);
      rowEl.classList.toggle('border-accent/20', on);
      rowEl.classList.toggle('bg-muted/50', !on);
      rowEl.classList.toggle('border-transparent', !on);
      if (calRendered) renderCalendar(); // weekly colouring feeds the calendar
    });
  }

  const saveBtn = root.querySelector('[data-weekly-save]');
  saveBtn?.addEventListener('click', async () => {
    saveBtn.disabled = true;
    try {
      for (const day of readWeekly()) {
        if (day.enabled) {
          await fetch(api('instructors/availability'), {
            method: 'PUT',
            headers,
            credentials: 'same-origin',
            body: JSON.stringify({ dayOfWeek: day.dayOfWeek, startTime: day.startTime, endTime: day.endTime, isRecurring: true }),
          });
        } else {
          await fetch(api('instructors/availability'), {
            method: 'DELETE',
            headers,
            credentials: 'same-origin',
            body: JSON.stringify({ dayOfWeek: day.dayOfWeek }),
          }); // 404 when the day was already off — harmless.
        }
      }
      setStatus('success', 'Weekly schedule saved successfully.');
    } catch (_e) {
      setStatus('error', 'Failed to save schedule. Please try again.');
    } finally {
      saveBtn.disabled = false;
    }
  });

  /* --------------------------- Calendar --------------------------------- */
  if (!grid) return;

  const titleEl = root.querySelector('[data-cal-title]');
  const excCard = root.querySelector('[data-exc-card]');
  const excList = root.querySelector('[data-exc-list]');
  let viewMonth = new Date(today().getFullYear(), today().getMonth(), 1);
  let exceptions = [];
  let calRendered = false;

  const dialog = root.querySelector('[data-exc-dialog]');
  const dlg = {
    date: root.querySelector('[data-exc-date]'),
    available: root.querySelector('[data-exc-available]'),
    modeLabel: root.querySelector('[data-exc-mode-label]'),
    times: root.querySelector('[data-exc-times]'),
    start: root.querySelector('[data-exc-start]'),
    end: root.querySelector('[data-exc-end]'),
    reasonWrap: root.querySelector('[data-exc-reason-wrap]'),
    reason: root.querySelector('[data-exc-reason]'),
    remove: root.querySelector('[data-exc-remove]'),
  };
  let activeDate = null;

  const excFor = (dateStr) => exceptions.find((x) => x.date === dateStr);
  const weeklyEnabled = (dow) => {
    const rowEl = root.querySelector(`[data-weekly-day="${dow}"]`);
    return rowEl?.querySelector('[data-weekly-toggle]')?.getAttribute('data-state') === 'checked';
  };

  function statusFor(date) {
    const exc = excFor(ymd(date));
    if (exc) return exc.isAvailable ? 'custom' : 'unavailable';
    return weeklyEnabled(date.getDay()) ? 'available' : 'default';
  }

  function renderCalendar() {
    calRendered = true;
    if (titleEl) titleEl.textContent = `${MONTHS[viewMonth.getMonth()]} ${viewMonth.getFullYear()}`;
    grid.innerHTML = '';
    const firstDow = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), 1).getDay();
    const daysInMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth() + 1, 0).getDate();
    const t0 = today();

    for (let i = 0; i < firstDow; i++) {
      const blank = document.createElement('div');
      blank.className = 'aspect-square';
      grid.appendChild(blank);
    }
    for (let d = 1; d <= daysInMonth; d++) {
      const date = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), d);
      const isPast = date < t0;
      const isToday = date.getTime() === t0.getTime();
      const st = statusFor(date);
      const cell = document.createElement('button');
      cell.type = 'button';
      cell.disabled = isPast;
      cell.setAttribute('data-cal-day', ymd(date));
      const palette = {
        available: 'bg-accent/10 border-accent/30 hover:bg-accent/20',
        unavailable: 'bg-destructive/10 border-destructive/30 hover:bg-destructive/20',
        custom: 'bg-yellow-500/10 border-yellow-500/30 hover:bg-yellow-500/20',
        default: 'bg-muted/50 border-transparent hover:bg-muted',
      };
      cell.className =
        'aspect-square p-1 rounded-lg border text-sm transition-all relative ' +
        (isPast ? 'bg-muted/30 text-muted-foreground cursor-not-allowed border-transparent' : palette[st] + ' cursor-pointer') +
        (isToday ? ' ring-2 ring-primary' : '');
      const num = document.createElement('span');
      num.className = 'font-medium ' + (isToday ? 'text-primary' : 'text-foreground');
      num.textContent = String(d);
      cell.appendChild(num);
      const exc = excFor(ymd(date));
      if (exc) {
        const dot = document.createElement('span');
        dot.className = 'absolute bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full ' + (exc.isAvailable ? 'bg-yellow-500' : 'bg-destructive');
        cell.appendChild(dot);
      }
      if (!isPast) cell.addEventListener('click', () => openDialog(date));
      grid.appendChild(cell);
    }
    renderUpcoming();
  }

  function renderUpcoming() {
    if (!excList || !excCard) return;
    const t0 = today();
    const upcoming = exceptions
      .filter((x) => new Date(x.date + 'T00:00:00') >= t0)
      .sort((a, b) => a.date.localeCompare(b.date))
      .slice(0, 5);
    excList.innerHTML = '';
    if (!upcoming.length) {
      excCard.classList.add('hidden');
      return;
    }
    excCard.classList.remove('hidden');
    upcoming.forEach((x) => {
      const d = new Date(x.date + 'T00:00:00');
      const label = `${DAY_NAMES[d.getDay()]}, ${MONTHS[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
      const detail = x.isAvailable
        ? `Custom hours: ${(x.startTime || '').slice(0, 5)} - ${(x.endTime || '').slice(0, 5)}`
        : 'Day off' + (x.reason ? `: ${x.reason}` : '');
      const item = document.createElement('div');
      item.className = 'flex items-center justify-between p-3 rounded-lg bg-muted/50';
      const left = document.createElement('div');
      const p1 = document.createElement('p');
      p1.className = 'font-medium text-foreground';
      p1.textContent = label;
      const p2 = document.createElement('p');
      p2.className = 'text-sm text-muted-foreground';
      p2.textContent = detail;
      left.append(p1, p2);
      const rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'text-muted-foreground hover:text-destructive text-lg leading-none px-2';
      rm.setAttribute('aria-label', 'Remove exception');
      rm.textContent = '×';
      rm.addEventListener('click', () => removeException(x.date));
      item.append(left, rm);
      excList.appendChild(item);
    });
  }

  /* ----- exception dialog ----- */
  function syncDialogMode(isAvailable) {
    if (dlg.modeLabel) dlg.modeLabel.textContent = isAvailable ? 'Available with custom hours' : 'Day off (Unavailable)';
    dlg.times?.classList.toggle('hidden', !isAvailable);
    dlg.reasonWrap?.classList.toggle('hidden', isAvailable);
  }
  function setSwitch(el, checked) {
    if (!el) return;
    el.setAttribute('data-state', checked ? 'checked' : 'unchecked');
    el.setAttribute('aria-checked', checked ? 'true' : 'false');
    el.querySelector('[data-slot="switch-thumb"]')?.setAttribute('data-state', checked ? 'checked' : 'unchecked');
  }

  function openDialog(date) {
    activeDate = date;
    const exc = excFor(ymd(date));
    if (dlg.date) dlg.date.textContent = `${DAY_NAMES[date.getDay()]}, ${MONTHS[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
    const isAvailable = exc ? exc.isAvailable : false;
    setSwitch(dlg.available, isAvailable);
    if (dlg.start) dlg.start.value = (exc && exc.startTime ? exc.startTime : '09:00').slice(0, 5);
    if (dlg.end) dlg.end.value = (exc && exc.endTime ? exc.endTime : '17:00').slice(0, 5);
    if (dlg.reason) dlg.reason.value = (exc && exc.reason) || '';
    dlg.remove?.classList.toggle('hidden', !exc);
    syncDialogMode(isAvailable);
    dialog.hidden = false;
    dialog.classList.remove('hidden');
    dialog.classList.add('flex');
    dialog.setAttribute('data-state', 'open');
  }
  function closeDialog() {
    dialog.hidden = true;
    dialog.classList.add('hidden');
    dialog.classList.remove('flex');
    dialog.setAttribute('data-state', 'closed');
    activeDate = null;
  }

  // The dialog's own availability switch also bubbles switch:change.
  dialog?.addEventListener('switch:change', (e) => {
    if (e.target.closest('[data-exc-available]')) syncDialogMode(e.detail && e.detail.checked);
  });
  root.querySelector('[data-exc-cancel]')?.addEventListener('click', closeDialog);
  root.querySelector('[data-exc-close]')?.addEventListener('click', closeDialog);
  root.querySelector('[data-exc-overlay]')?.addEventListener('click', closeDialog);

  root.querySelector('[data-exc-save]')?.addEventListener('click', async () => {
    if (!activeDate) return;
    const isAvailable = dlg.available?.getAttribute('data-state') === 'checked';
    const body = {
      date: ymd(activeDate),
      isAvailable,
      startTime: dlg.start?.value || '09:00',
      endTime: dlg.end?.value || '17:00',
      reason: isAvailable ? '' : (dlg.reason?.value || ''),
    };
    try {
      const res = await fetch(api('instructors/availability/exceptions'), { method: 'POST', headers, credentials: 'same-origin', body: JSON.stringify(body) });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setStatus('error', data.message || 'Failed to save exception.');
        return;
      }
      closeDialog();
      setStatus('success', 'Exception saved successfully.');
      await loadExceptions();
    } catch (_e) {
      setStatus('error', 'Failed to save exception.');
    }
  });

  dlg.remove?.addEventListener('click', () => { if (activeDate) removeException(ymd(activeDate)); });

  async function removeException(dateStr) {
    try {
      const res = await fetch(api('instructors/availability/exceptions'), { method: 'DELETE', headers, credentials: 'same-origin', body: JSON.stringify({ date: dateStr }) });
      if (!res.ok && res.status !== 404) {
        const data = await res.json().catch(() => ({}));
        setStatus('error', data.message || 'Failed to remove exception.');
        return;
      }
      closeDialog();
      setStatus('success', 'Exception removed successfully.');
      await loadExceptions();
    } catch (_e) {
      setStatus('error', 'Failed to remove exception.');
    }
  }

  root.querySelector('[data-cal-prev]')?.addEventListener('click', () => { viewMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth() - 1, 1); loadExceptions(); });
  root.querySelector('[data-cal-next]')?.addEventListener('click', () => { viewMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth() + 1, 1); loadExceptions(); });

  async function loadExceptions() {
    const start = ymd(new Date(viewMonth.getFullYear(), viewMonth.getMonth(), 1));
    const end = ymd(new Date(viewMonth.getFullYear(), viewMonth.getMonth() + 3, 0));
    try {
      const res = await fetch(api(`instructors/availability/exceptions?startDate=${start}&endDate=${end}`), { headers: { 'X-WP-Nonce': cfg.nonce || '' }, credentials: 'same-origin' });
      const data = await res.json().catch(() => ({}));
      exceptions = (res.ok && Array.isArray(data.exceptions)) ? data.exceptions : [];
    } catch (_e) {
      exceptions = [];
    }
    renderCalendar();
  }

  loadExceptions();
}

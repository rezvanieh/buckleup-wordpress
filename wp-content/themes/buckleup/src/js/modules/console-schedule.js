// Instructor "My Schedule": booking status actions. Confirm/Decline (PENDING) and
// Cancel (CONFIRMED, with a reason). PUT /instructors/bookings/{id}/status
// {status, reason?} → the plugin emails the student. On success, update the row's
// status pill + actions in place (reload on cancel/decline to drop it from the
// upcoming list). Uses window.buckleupAuth.

const PILL = {
  PENDING: ['Pending', 'bg-yellow-500/10 text-yellow-600'],
  CONFIRMED: ['Confirmed', 'bg-accent/10 text-accent'],
  COMPLETED: ['Completed', 'bg-primary/10 text-primary'],
  CANCELLED: ['Cancelled', 'bg-destructive/10 text-destructive'],
};

export function initConsoleSchedule(root = document) {
  const table = root.querySelector('[data-booking]')?.closest('table');
  if (!table && !root.querySelector('[data-booking]')) return;

  const cfg = window.buckleupAuth || {};
  const status = root.querySelector('[data-schedule-status]');

  const setStatus = (kind, msg) => {
    if (!status) return;
    status.hidden = false;
    status.classList.remove('hidden');
    status.className = 'mt-4 text-sm ' + (kind === 'success' ? 'text-accent' : 'text-destructive');
    status.textContent = msg;
  };

  root.querySelectorAll('[data-booking-action]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const row = btn.closest('[data-booking]');
      const id = row && row.getAttribute('data-booking');
      const next = btn.getAttribute('data-booking-action');
      if (!id || !next) return;

      let reason = '';
      if (btn.hasAttribute('data-booking-reason') || next === 'CANCELLED') {
        reason = window.prompt('Reason (optional):') || '';
        if (reason === null) return; // cancelled the prompt
      }

      row.querySelectorAll('button').forEach((b) => { b.disabled = true; });

      fetch((cfg.restUrl || '/wp-json/buckleup/v1/') + 'instructors/bookings/' + id + '/status', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
        credentials: 'same-origin',
        body: JSON.stringify({ status: next, reason }),
      })
        .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
          if (!ok) {
            setStatus('error', (data && (data.message || data.error)) || 'Could not update the booking.');
            row.querySelectorAll('button').forEach((b) => { b.disabled = false; });
            return;
          }
          if (next === 'CONFIRMED') {
            // Update the pill + swap actions to a Cancel button in place.
            const cell = row.querySelector('[data-booking-status]');
            if (cell) {
              const [label, cls] = PILL.CONFIRMED;
              cell.innerHTML = '<span class="text-xs font-medium px-2.5 py-1 rounded-full ' + cls + '">' + label + '</span>';
            }
            setStatus('success', 'Lesson confirmed — the student has been notified.');
            window.setTimeout(() => window.location.reload(), 1000);
          } else {
            // Declined/cancelled → remove from the upcoming list.
            setStatus('success', 'Lesson ' + (next === 'CANCELLED' ? 'cancelled' : 'declined') + ' — the student has been notified.');
            row.style.opacity = '0.5';
            window.setTimeout(() => window.location.reload(), 1000);
          }
        })
        .catch(() => {
          setStatus('error', 'Something went wrong. Please try again.');
          row.querySelectorAll('button').forEach((b) => { b.disabled = false; });
        });
    });
  });
}

// Admin Reviews moderation: client-side search + All/Pending/Approved filter over
// the server-rendered review cards, plus Approve/Unapprove (PATCH /admin/reviews/
// {id} {isApproved}) and Delete (DELETE /admin/reviews/{id}). Approve/Unapprove
// toggles the card's state in place (button swap + data-review-approved + the
// "{N} Pending" badge); Delete removes the card. Uses window.buckleupAuth
// (X-WP-Nonce on both mutations).

export function initConsoleAdminReviews(root = document) {
  const list = root.querySelector('[data-reviews-list]');
  if (!list || !root.querySelector('[data-review]')) return;

  const cfg = window.buckleupAuth || {};
  const api = (cfg.restUrl || '/wp-json/buckleup/v1/') + 'admin/reviews';
  const headers = { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' };
  const statusEl = root.querySelector('[data-reviews-status]');

  const searchInput = root.querySelector('[data-reviews-search]');
  const filterBtns = Array.from(root.querySelectorAll('[data-reviews-filter]'));
  const noResults = root.querySelector('[data-reviews-noresults]');
  const pendingEl = root.querySelector('[data-reviews-pending]');
  const pendingBadge = root.querySelector('[data-reviews-pending-badge]');

  let term = '';
  let filter = 'all';

  const setStatus = (kind, msg) => {
    if (!statusEl) return;
    statusEl.hidden = false;
    statusEl.classList.remove('hidden');
    statusEl.className = 'mt-4 text-sm ' + (kind === 'success' ? 'text-accent' : 'text-destructive');
    statusEl.textContent = msg;
  };

  const cards = () => Array.from(root.querySelectorAll('[data-review]'));

  const matches = (card) => {
    if (term && !(card.getAttribute('data-review-search') || '').includes(term)) return false;
    const approved = card.getAttribute('data-review-approved') === '1';
    if (filter === 'approved') return approved;
    if (filter === 'pending') return !approved;
    return true;
  };

  const applyFilter = () => {
    let shown = 0;
    cards().forEach((card) => {
      const show = matches(card);
      card.classList.toggle('hidden', !show);
      if (show) shown++;
    });
    if (noResults) noResults.classList.toggle('hidden', shown !== 0);
  };

  const refreshPending = () => {
    const n = cards().filter((c) => c.getAttribute('data-review-approved') !== '1').length;
    if (pendingEl) pendingEl.textContent = String(n);
    if (pendingBadge) pendingBadge.classList.toggle('hidden', n === 0);
  };

  searchInput?.addEventListener('input', () => { term = searchInput.value.trim().toLowerCase(); applyFilter(); });

  filterBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      filter = btn.getAttribute('data-reviews-filter') || 'all';
      filterBtns.forEach((b) => {
        const on = b === btn;
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        b.classList.toggle('bg-primary', on);
        b.classList.toggle('text-primary-foreground', on);
        b.classList.toggle('shadow-lg', on);
        b.classList.toggle('shadow-primary/20', on);
        b.classList.toggle('bg-background', !on);
        b.classList.toggle('text-muted-foreground', !on);
        b.classList.toggle('hover:bg-muted', !on);
      });
      applyFilter();
    });
  });

  // Toggle a card's approved UI without re-rendering.
  const setCardApproved = (card, approved) => {
    card.setAttribute('data-review-approved', approved ? '1' : '0');
    // Toggle the `hidden` ATTRIBUTE (base CSS forces [hidden]{display:none!important},
    // so it beats the buttons' inline-flex — using the .hidden class would lose).
    const approvedBtn = card.querySelector('[data-review-approve]');
    const pendingBtn = card.querySelector('[data-review-pending]');
    if (approvedBtn) approvedBtn.hidden = !approved; // "Approved" pill shows only when approved
    if (pendingBtn) pendingBtn.hidden = approved; // "Approve" button shows only when pending
    refreshPending();
    applyFilter(); // a card may drop out of the current filter view
  };

  // Delegate clicks (approve/unapprove/delete) on the list.
  list.addEventListener('click', async (e) => {
    const card = e.target.closest('[data-review]');
    if (!card) return;
    const id = card.getAttribute('data-review');

    const approveBtn = e.target.closest('[data-review-approve]'); // currently approved → unapprove
    const pendingBtn = e.target.closest('[data-review-pending]'); // currently pending → approve
    const deleteBtn = e.target.closest('[data-review-delete]');

    if (approveBtn || pendingBtn) {
      const next = !!pendingBtn; // pending button approves; approved button unapproves
      card.querySelectorAll('button').forEach((b) => { b.disabled = true; });
      try {
        const res = await fetch(`${api}/${id}`, { method: 'PATCH', headers, credentials: 'same-origin', body: JSON.stringify({ isApproved: next }) });
        if (!res.ok) {
          const data = await res.json().catch(() => ({}));
          setStatus('error', data.message || 'Could not update the review.');
        } else {
          setCardApproved(card, next);
        }
      } catch (_e) {
        setStatus('error', 'Something went wrong. Please try again.');
      } finally {
        card.querySelectorAll('button').forEach((b) => { b.disabled = false; });
      }
      return;
    }

    if (deleteBtn) {
      if (!window.confirm('Are you sure you want to delete this review?')) return;
      card.querySelectorAll('button').forEach((b) => { b.disabled = true; });
      try {
        const res = await fetch(`${api}/${id}`, { method: 'DELETE', headers: { 'X-WP-Nonce': cfg.nonce || '' }, credentials: 'same-origin' });
        if (!res.ok) {
          const data = await res.json().catch(() => ({}));
          setStatus('error', data.message || 'Could not delete the review.');
          card.querySelectorAll('button').forEach((b) => { b.disabled = false; });
        } else {
          card.remove();
          refreshPending();
          applyFilter();
        }
      } catch (_e) {
        setStatus('error', 'Something went wrong. Please try again.');
        card.querySelectorAll('button').forEach((b) => { b.disabled = false; });
      }
    }
  });
}

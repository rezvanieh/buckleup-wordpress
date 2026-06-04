// Instructor "My Students": client-side search + filter over the server-rendered
// roster cards (no network — the data is already on the page). Search matches
// name/email (data-student-search); filters are All / Has Upcoming (data-student-
// upcoming) / Active (data-student-active). When the visible set is empty we show
// the [data-students-noresults] card. Mirrors the source filter semantics.

export function initConsoleStudents(root = document) {
  const list = root.querySelector('[data-students-list]');
  if (!list || !root.querySelector('[data-student]')) return;

  const searchInput = root.querySelector('[data-students-search]');
  const filterBtns = Array.from(root.querySelectorAll('[data-students-filter]'));
  const cards = Array.from(root.querySelectorAll('[data-student]'));
  const noResults = root.querySelector('[data-students-noresults]');

  let term = '';
  let filter = 'all';

  const matches = (card) => {
    if (term && !(card.getAttribute('data-student-search') || '').includes(term)) return false;
    if (filter === 'upcoming') return card.getAttribute('data-student-upcoming') === '1';
    if (filter === 'active') return card.getAttribute('data-student-active') === '1';
    return true;
  };

  const apply = () => {
    let shown = 0;
    cards.forEach((card) => {
      const show = matches(card);
      card.classList.toggle('hidden', !show);
      if (show) shown++;
    });
    if (noResults) noResults.classList.toggle('hidden', shown !== 0);
  };

  searchInput?.addEventListener('input', () => {
    term = searchInput.value.trim().toLowerCase();
    apply();
  });

  filterBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      filter = btn.getAttribute('data-students-filter') || 'all';
      // Reflect the active filter in the button styling (default vs outline).
      filterBtns.forEach((b) => {
        const on = b === btn;
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        // Swap the variant skin: 'default' (filled primary) vs 'outline'.
        b.classList.toggle('bg-primary', on);
        b.classList.toggle('text-primary-foreground', on);
        b.classList.toggle('shadow-md', on);
        b.classList.toggle('hover:bg-primary/90', on);
        b.classList.toggle('border-2', !on);
        b.classList.toggle('border-border', !on);
        b.classList.toggle('bg-background', !on);
        b.classList.toggle('text-foreground', !on);
        b.classList.toggle('hover:bg-secondary', !on);
      });
      apply();
    });
  });
}

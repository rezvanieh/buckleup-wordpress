// Student "Leave a Review" page: star rating, character counter, submit-enable
// validation, and the POST mutation. Read list is server-rendered; this only
// handles the form. POST /reviews {rating,comment,isPublic} with X-WP-Nonce.

export function initConsoleReviews(root = document) {
  const form = root.querySelector('[data-review-form]');
  if (!form) return;

  const ratingInput = form.querySelector('input[name="rating"]');
  const stars = Array.from(form.querySelectorAll('[data-star]'));
  const comment = form.querySelector('textarea[name="comment"]');
  const counter = root.querySelector('[data-review-count]');
  const submit = form.querySelector('[data-review-submit]');
  const status = root.querySelector('[data-review-status]');

  const paintStars = (value) => {
    stars.forEach((b) => {
      const on = Number(b.dataset.star) <= value;
      b.classList.toggle('text-yellow-500', on);
      b.classList.toggle('text-muted-foreground/30', !on);
      const svg = b.querySelector('svg');
      if (svg) svg.classList.toggle('fill-yellow-500', on);
    });
  };

  const rating = () => Number(ratingInput.value) || 0;
  const valid = () => rating() > 0 && comment.value.trim().length >= 10;

  const refresh = () => {
    if (counter) counter.textContent = String(comment.value.length);
    if (submit) submit.disabled = !valid();
  };

  stars.forEach((b) => {
    b.addEventListener('mouseenter', () => paintStars(Number(b.dataset.star)));
    b.addEventListener('mouseleave', () => paintStars(rating()));
    b.addEventListener('click', () => {
      ratingInput.value = b.dataset.star;
      paintStars(rating());
      refresh();
    });
  });
  comment.addEventListener('input', refresh);
  refresh();

  const setStatus = (kind, msg) => {
    if (!status) return;
    status.hidden = false;
    status.classList.remove('hidden');
    const ok = kind === 'success';
    status.classList.toggle('bg-accent/10', ok);
    status.classList.toggle('text-accent', ok);
    status.classList.toggle('border', true);
    status.classList.toggle('border-accent/20', ok);
    status.classList.toggle('bg-destructive/10', !ok);
    status.classList.toggle('text-destructive', !ok);
    status.classList.toggle('border-destructive/20', !ok);
    status.textContent = msg;
  };

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!valid()) return;
    const cfg = window.buckleupAuth || {};
    if (submit) submit.disabled = true;

    fetch((cfg.restUrl || '/wp-json/buckleup/v1/') + 'reviews', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
      credentials: 'same-origin',
      body: JSON.stringify({
        rating: rating(),
        comment: comment.value.trim(),
        isPublic: form.querySelector('input[name="isPublic"]')?.checked ?? true,
      }),
    })
      .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
      .then(({ ok, data }) => {
        if (ok) {
          setStatus('success', 'Thanks! Your review was submitted and is pending approval.');
          form.reset();
          ratingInput.value = '0';
          paintStars(0);
          refresh();
          // Optimistically reload so the new "Pending" entry shows in My Reviews.
          window.setTimeout(() => window.location.reload(), 1200);
        } else {
          setStatus('error', (data && (data.message || data.error)) || 'Could not submit your review. Please try again.');
          if (submit) submit.disabled = false;
        }
      })
      .catch(() => {
        setStatus('error', 'Something went wrong. Please try again.');
        if (submit) submit.disabled = false;
      });
  });
}

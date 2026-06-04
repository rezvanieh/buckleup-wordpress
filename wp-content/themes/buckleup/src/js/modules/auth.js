// Auth UI behavior: password show/hide toggles (login + register) and the
// register form's client-validate + REST submit.
//
// Login posts natively to wp-login.php (no JS needed). Register submits via
// fetch → POST /wp-json/buckleup/v1/auth/register with the wp_rest nonce
// (window.buckleupAuth.nonce). On 201 → /login/?registered=true; non-2xx → show
// data.error in [data-register-status].

function initPasswordToggles(root) {
  root.querySelectorAll('[data-password-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const field = btn.closest('[data-password-field]');
      const input = field && field.querySelector('input');
      if (!input) return;
      const toText = input.type === 'password';
      input.type = toText ? 'text' : 'password';
      const show = btn.querySelector('[data-pw-show]');
      const hide = btn.querySelector('[data-pw-hide]');
      if (show) show.hidden = toText;
      if (hide) hide.hidden = !toText;
      btn.setAttribute('aria-label', toText ? 'Hide password' : 'Show password');
    });
  });
}

function initRegister(root) {
  const form = root.querySelector('[data-register-form]');
  if (!form) return;

  const status = root.querySelector('[data-register-status]');
  const submit = form.querySelector('[data-register-submit]');

  const showError = (msg) => {
    if (!status) return;
    status.hidden = false;
    status.classList.remove('hidden');
    status.textContent = msg;
  };
  const clearError = () => {
    if (!status) return;
    status.hidden = true;
    status.classList.add('hidden');
    status.textContent = '';
  };

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    clearError();

    const name = form.name?.value.trim() || '';
    const email = form.email?.value.trim() || '';
    const phone = form.phone?.value.trim() || '';
    const password = form.password?.value || '';
    const confirm = form.confirm?.value || '';

    if (!name || !email || !password) {
      showError('Please fill in your name, email, and password.');
      return;
    }
    if (password.length < 8) {
      showError('Password must be at least 8 characters.');
      return;
    }
    if (password !== confirm) {
      showError('Passwords do not match.');
      return;
    }

    const cfg = window.buckleupAuth || {};
    const url = (cfg.restUrl || '/wp-json/buckleup/v1/') + 'auth/register';

    if (submit) {
      submit.disabled = true;
      submit.dataset.loading = 'true';
    }

    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ name, email, phone, password }),
    })
      .then((res) => res.json().then((data) => ({ status: res.status, data })))
      .then(({ status: code, data }) => {
        if (code === 201 || (code >= 200 && code < 300)) {
          window.location.assign('/login/?registered=true');
        } else {
          showError((data && (data.error || data.message)) || 'Registration failed. Please try again.');
        }
      })
      .catch(() => showError('Something went wrong. Please try again.'))
      .finally(() => {
        if (submit) {
          submit.disabled = false;
          delete submit.dataset.loading;
        }
      });
  });
}

export function initAuth(root = document) {
  initPasswordToggles(root);
  initRegister(root);
}

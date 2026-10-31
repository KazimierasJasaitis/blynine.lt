// A history restoration of the random homepage is a fresh visit as well.
window.addEventListener('pageshow', function (event) {
  if (event.persisted && window.location.pathname === '/') window.location.reload();
});

document.querySelectorAll('form[data-submit-once]').forEach(function (form) {
  form.addEventListener('submit', function () {
    if (!form.checkValidity()) return;
    const button = form.querySelector('button[type="submit"]');
    if (button) {
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      window.setTimeout(function () { button.disabled = false; button.removeAttribute('aria-busy'); }, 15000);
    }
  });
});

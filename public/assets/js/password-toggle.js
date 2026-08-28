(function () {
    'use strict';

    // Standalone (not part of app.js) so the guest/auth pages — which don't
    // load the rest of app.js, since that file assumes the full app shell
    // (sidebar, modals, toasts) exists — can use it too. Delegated on
    // document so it also covers any .js-password-toggle button injected
    // later into a modal form, with no re-scan needed.
    document.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('.js-password-toggle');
        if (!button) {
            return;
        }
        var input = document.getElementById(button.dataset.target);
        if (!input) {
            return;
        }
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.classList.toggle('is-visible', !showing);
        button.setAttribute('aria-label', showing ? 'Mostrar contraseña' : 'Ocultar contraseña');
        button.setAttribute('aria-pressed', String(!showing));
    });
})();

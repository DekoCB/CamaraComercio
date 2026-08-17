(function () {
    'use strict';

    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
        document.addEventListener('click', function (event) {
            if (window.innerWidth < 992 && sidebar.classList.contains('open')
                && !sidebar.contains(event.target) && event.target !== toggle) {
                sidebar.classList.remove('open');
            }
        });
    }

    // Simple confirmation for destructive/critical actions: add
    // data-confirm="mensaje" to any form/button.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form instanceof HTMLFormElement && form.dataset.confirm) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        }
    });
})();

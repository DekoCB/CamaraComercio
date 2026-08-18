(function () {
    'use strict';

    /* ---------------------------------------------------------------
     * Mobile sidebar drawer
     * --------------------------------------------------------------- */
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });
        document.addEventListener('click', function (event) {
            if (window.innerWidth < 992 && sidebar.classList.contains('is-open')
                && !sidebar.contains(event.target) && event.target !== toggle && !toggle.contains(event.target)) {
                sidebar.classList.remove('is-open');
            }
        });
    }

    /* ---------------------------------------------------------------
     * Desktop sidebar collapse (persisted per browser via localStorage —
     * purely a display preference, not worth a server round-trip)
     * --------------------------------------------------------------- */
    var collapseToggle = document.getElementById('sidebarCollapseToggle');
    var shell = document.querySelector('.app-shell');
    var COLLAPSE_KEY = 'cc_sidebar_collapsed';

    if (shell && localStorage.getItem(COLLAPSE_KEY) === '1') {
        shell.classList.add('is-collapsed');
    }

    if (collapseToggle && shell) {
        collapseToggle.addEventListener('click', function () {
            var collapsed = shell.classList.toggle('is-collapsed');
            localStorage.setItem(COLLAPSE_KEY, collapsed ? '1' : '0');
        });
    }

    /* ---------------------------------------------------------------
     * Confirmation modal — replaces window.confirm() for data-confirm
     * forms (native browser alerts are explicitly out per the design
     * brief). Falls back to submitting immediately if the modal
     * markup isn't present on the page for some reason.
     * --------------------------------------------------------------- */
    var confirmBackdrop = document.getElementById('confirmModalBackdrop');
    var confirmDialog = document.getElementById('confirmModalDialog');
    var confirmTitle = document.getElementById('confirmModalTitle');
    var confirmMessage = document.getElementById('confirmModalMessage');
    var confirmAcceptBtn = document.getElementById('confirmModalAccept');
    var confirmCancelBtn = document.getElementById('confirmModalCancel');
    var pendingForm = null;
    var lastFocusedEl = null;

    function openConfirmModal(form) {
        pendingForm = form;
        lastFocusedEl = document.activeElement;
        confirmTitle.textContent = form.dataset.confirmTitle || '¿Confirmar acción?';
        confirmMessage.textContent = form.dataset.confirm;
        confirmBackdrop.classList.add('is-open');
        confirmDialog.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        confirmAcceptBtn.focus();
        document.addEventListener('keydown', onConfirmKeydown);
    }

    function closeConfirmModal() {
        confirmBackdrop.classList.remove('is-open');
        confirmDialog.classList.remove('is-open');
        document.body.style.overflow = '';
        document.removeEventListener('keydown', onConfirmKeydown);
        if (lastFocusedEl) {
            lastFocusedEl.focus();
        }
        pendingForm = null;
    }

    function onConfirmKeydown(event) {
        if (event.key === 'Escape') {
            closeConfirmModal();
        }
        if (event.key === 'Tab') {
            // simple focus trap between the two dialog buttons
            var focusables = [confirmCancelBtn, confirmAcceptBtn];
            var currentIndex = focusables.indexOf(document.activeElement);
            event.preventDefault();
            var nextIndex = event.shiftKey
                ? (currentIndex <= 0 ? focusables.length - 1 : currentIndex - 1)
                : (currentIndex === focusables.length - 1 ? 0 : currentIndex + 1);
            focusables[nextIndex].focus();
        }
    }

    if (confirmBackdrop && confirmDialog) {
        confirmAcceptBtn.addEventListener('click', function () {
            var form = pendingForm;
            closeConfirmModal();
            if (form) {
                setButtonLoading(form.querySelector('[type="submit"]'));
                HTMLFormElement.prototype.submit.call(form);
            }
        });
        confirmCancelBtn.addEventListener('click', closeConfirmModal);
        confirmBackdrop.addEventListener('click', closeConfirmModal);
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) {
            return;
        }
        if (form.dataset.confirmed === '1') {
            // already approved programmatically above
            delete form.dataset.confirmed;
            return;
        }
        event.preventDefault();
        if (confirmBackdrop && confirmDialog) {
            openConfirmModal(form);
        } else if (window.confirm(form.dataset.confirm)) {
            form.submit();
        }
    });

    /* ---------------------------------------------------------------
     * Button loading state on submit (perceived performance — no
     * request should look "frozen" with no feedback)
     * --------------------------------------------------------------- */
    function setButtonLoading(button) {
        if (button) {
            button.classList.add('is-loading');
            button.disabled = true;
        }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.confirm) {
            return; // confirm-guarded forms set loading state after acceptance
        }
        var submitBtn = form.querySelector('[type="submit"]');
        // Defer so a validation-blocked submit (e.g. required fields) never
        // leaves the button stuck in a loading state.
        window.setTimeout(function () {
            if (form.checkValidity && form.checkValidity() === false) {
                return;
            }
            setButtonLoading(submitBtn);
        }, 0);
    });

    /* ---------------------------------------------------------------
     * Toasts — reads the flash payload the layout embeds as JSON and
     * renders auto-dismissing notifications instead of a static banner.
     * --------------------------------------------------------------- */
    var toastStack = document.getElementById('toastStack');
    var flashDataEl = document.getElementById('flashData');

    var TOAST_ICONS = {
        success: 'check-circle-2',
        danger: 'x-circle',
        warning: 'alert-triangle',
        info: 'info',
    };

    function showToast(type, message, title) {
        if (!toastStack) {
            return;
        }
        var item = document.createElement('div');
        item.className = 'toast-item toast-' + type;
        item.setAttribute('role', 'status');
        item.innerHTML =
            '<span class="icon-wrap" data-icon="' + TOAST_ICONS[type] + '"></span>' +
            '<div class="toast-body">' +
                (title ? '<div class="toast-title"></div>' : '') +
                '<div class="toast-message"></div>' +
            '</div>' +
            '<button type="button" class="toast-close" aria-label="Cerrar notificación">&times;</button>';

        if (title) {
            item.querySelector('.toast-title').textContent = title;
        }
        item.querySelector('.toast-message').textContent = message;

        var iconSlot = item.querySelector('.icon-wrap');
        if (window.__iconSvgs && window.__iconSvgs[TOAST_ICONS[type]]) {
            iconSlot.innerHTML = window.__iconSvgs[TOAST_ICONS[type]];
        }

        toastStack.appendChild(item);

        var remove = function () {
            item.classList.add('is-leaving');
            window.setTimeout(function () {
                item.remove();
            }, 180);
        };

        item.querySelector('.toast-close').addEventListener('click', remove);
        window.setTimeout(remove, 6000);
    }

    if (flashDataEl) {
        try {
            var flashes = JSON.parse(flashDataEl.textContent || '{}');
            Object.keys(flashes).forEach(function (type) {
                (flashes[type] || []).forEach(function (message) {
                    showToast(type === 'error' ? 'danger' : type, message);
                });
            });
        } catch (e) {
            // malformed flash payload — fail silently, nothing to show
        }
    }

    window.__showToast = showToast;

    /* ---------------------------------------------------------------
     * Export feedback — the download itself is a plain link (no JS
     * fetch/blob handling needed for a report this size), but the
     * design brief still wants a "preparing document" moment instead
     * of a link that silently does something.
     * --------------------------------------------------------------- */
    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-export-toast]');
        if (link) {
            showToast('info', link.dataset.exportToast);
        }
    });
})();

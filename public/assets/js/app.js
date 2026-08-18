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
     * Theme toggle (dark/light) — the actual switch happened before
     * first paint via the inline script in <head> (reads localStorage,
     * sets [data-theme] on <html>); this just handles the click and
     * keeps the button's aria-label honest about what it does next.
     * --------------------------------------------------------------- */
    var themeToggle = document.getElementById('themeToggle');
    var THEME_KEY = 'cc_theme';

    function isDarkActive() {
        var explicit = document.documentElement.getAttribute('data-theme');
        if (explicit === 'dark') { return true; }
        if (explicit === 'light') { return false; }
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function updateThemeToggleLabel() {
        if (themeToggle) {
            themeToggle.setAttribute('aria-label', isDarkActive() ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');
        }
    }

    if (themeToggle) {
        updateThemeToggleLabel();
        themeToggle.addEventListener('click', function () {
            var next = isDarkActive() ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem(THEME_KEY, next); } catch (e) {}
            updateThemeToggleLabel();
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

    // A modal-form submission (below) already consumes the server's session
    // flash the moment fetch() follows its redirect, before the browser ever
    // navigates there — so the flash the user would normally see never
    // reaches a real page. That handler stashes the message here instead,
    // for this one page load, right before triggering the actual navigation.
    try {
        var pendingFlash = sessionStorage.getItem('cc_pending_flash');
        if (pendingFlash) {
            sessionStorage.removeItem('cc_pending_flash');
            var pendingFlashes = JSON.parse(pendingFlash);
            Object.keys(pendingFlashes).forEach(function (type) {
                (pendingFlashes[type] || []).forEach(function (message) {
                    showToast(type === 'error' ? 'danger' : type, message);
                });
            });
        }
    } catch (e) {
        // malformed/absent payload — fail silently, nothing to show
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

    /* ---------------------------------------------------------------
     * Shared popup registry — opening a select/datepicker popup closes
     * any other one that's currently open (custom selects + datepickers
     * below both register their closer here).
     * --------------------------------------------------------------- */
    var openPopups = [];

    function closeOtherPopups(except) {
        openPopups.slice().forEach(function (closer) {
            if (closer !== except) {
                closer();
            }
        });
    }

    function registerPopup(closer) {
        openPopups.push(closer);
    }

    function unregisterPopup(closer) {
        var idx = openPopups.indexOf(closer);
        if (idx !== -1) {
            openPopups.splice(idx, 1);
        }
    }

    // A popup positioned left:0 of a narrow trigger sitting near the right
    // edge of the viewport (e.g. the second date filter in a toolbar) would
    // render past the edge. That still happens while the popup is closed —
    // position:absolute geometry counts toward the page's scrollable width
    // even at opacity:0 — so this must run at build time too, not only when
    // the popup is actually opened.
    function updateHorizontalFlip(field, popupWidth) {
        var rect = field.getBoundingClientRect();
        field.classList.toggle('opens-left', rect.left + popupWidth > window.innerWidth);
    }

    /* ---------------------------------------------------------------
     * Custom select — progressively enhances every select.form-select
     * into a styled trigger + listbox matching the design system. The
     * native <select> stays in the DOM (visually hidden, tabindex -1)
     * so form submission and any inline onchange="" filter wiring keep
     * working exactly as before.
     * --------------------------------------------------------------- */
    function enhanceSelects(root) {
        var selects = (root || document).querySelectorAll('select.form-select:not(.is-enhanced)');
        Array.prototype.forEach.call(selects, buildCustomSelect);
    }

    function buildCustomSelect(select) {
        select.classList.add('is-enhanced');
        select.tabIndex = -1;

        var field = document.createElement('div');
        field.className = 'select-field';

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        if (select.disabled) {
            trigger.disabled = true;
        }

        var labelSpan = document.createElement('span');
        labelSpan.className = 'select-trigger-label';
        trigger.appendChild(labelSpan);

        var chevron = document.createElement('span');
        chevron.className = 'icon';
        chevron.innerHTML = (window.__iconSvgs && window.__iconSvgs['chevron-down']) || '';
        trigger.appendChild(chevron);

        var popup = document.createElement('ul');
        popup.className = 'select-popup';
        popup.setAttribute('role', 'listbox');
        popup.tabIndex = -1;

        var optionEls = [];
        Array.prototype.forEach.call(select.options, function (opt, index) {
            var li = document.createElement('li');
            li.className = 'select-option';
            li.setAttribute('role', 'option');
            li.dataset.index = String(index);

            var textSpan = document.createElement('span');
            textSpan.textContent = opt.textContent;
            var checkIcon = document.createElement('span');
            checkIcon.className = 'icon';
            checkIcon.innerHTML = (window.__iconSvgs && window.__iconSvgs['check']) || '';
            li.appendChild(textSpan);
            li.appendChild(checkIcon);

            if (opt.selected) {
                li.classList.add('is-selected');
                labelSpan.textContent = opt.textContent;
            }
            popup.appendChild(li);
            optionEls.push(li);
        });

        field.appendChild(trigger);
        field.appendChild(popup);
        select.parentNode.insertBefore(field, select);
        field.insertBefore(select, trigger);
        updateHorizontalFlip(field, Math.max(popup.scrollWidth, field.offsetWidth));

        var activeIndex = select.selectedIndex >= 0 ? select.selectedIndex : 0;
        var typeaheadBuffer = '';
        var typeaheadTimer = null;

        function setActive(index) {
            activeIndex = index;
            optionEls.forEach(function (li, i) {
                li.classList.toggle('is-active', i === index);
            });
            if (optionEls[index] && optionEls[index].scrollIntoView) {
                optionEls[index].scrollIntoView({ block: 'nearest' });
            }
        }

        function selectOption(index) {
            var opt = select.options[index];
            if (!opt) {
                return;
            }
            select.selectedIndex = index;
            labelSpan.textContent = opt.textContent;
            optionEls.forEach(function (li, i) {
                li.classList.toggle('is-selected', i === index);
            });
            select.dispatchEvent(new Event('change', { bubbles: true }));
            closePopup();
            trigger.focus();
        }

        function onDocClick(event) {
            if (!field.contains(event.target)) {
                closePopup();
            }
        }

        function onKeydown(event) {
            if (event.key === 'Escape') {
                closePopup();
                trigger.focus();
            } else if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive(Math.min(activeIndex + 1, optionEls.length - 1));
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(Math.max(activeIndex - 1, 0));
            } else if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                selectOption(activeIndex);
            } else if (event.key === 'Home') {
                event.preventDefault();
                setActive(0);
            } else if (event.key === 'End') {
                event.preventDefault();
                setActive(optionEls.length - 1);
            } else if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                typeaheadBuffer += event.key.toLowerCase();
                window.clearTimeout(typeaheadTimer);
                typeaheadTimer = window.setTimeout(function () { typeaheadBuffer = ''; }, 600);
                for (var i = 0; i < optionEls.length; i++) {
                    if (optionEls[i].textContent.trim().toLowerCase().indexOf(typeaheadBuffer) === 0) {
                        setActive(i);
                        break;
                    }
                }
            }
        }

        function closePopup() {
            field.classList.remove('is-open', 'opens-up');
            trigger.setAttribute('aria-expanded', 'false');
            document.removeEventListener('click', onDocClick);
            document.removeEventListener('keydown', onKeydown);
            unregisterPopup(closePopup);
        }

        function openPopup() {
            closeOtherPopups(closePopup);
            field.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');

            var rect = trigger.getBoundingClientRect();
            var popupHeight = Math.min(popup.scrollHeight || 260, 260);
            field.classList.toggle('opens-up', rect.bottom + popupHeight > window.innerHeight && rect.top > popupHeight);
            updateHorizontalFlip(field, Math.max(popup.scrollWidth, field.offsetWidth));

            setActive(select.selectedIndex >= 0 ? select.selectedIndex : 0);
            document.addEventListener('click', onDocClick);
            document.addEventListener('keydown', onKeydown);
            registerPopup(closePopup);
        }

        trigger.addEventListener('click', function () {
            if (field.classList.contains('is-open')) {
                closePopup();
            } else {
                openPopup();
            }
        });

        optionEls.forEach(function (li, index) {
            li.addEventListener('click', function () { selectOption(index); });
            li.addEventListener('mouseenter', function () { setActive(index); });
        });
    }

    /* ---------------------------------------------------------------
     * Custom date/month picker — progressively enhances
     * input[type="date"] and input[type="month"]. Same pattern as the
     * custom select: the native input stays in the DOM (hidden,
     * tabindex -1) holding the real ISO value the form submits.
     * --------------------------------------------------------------- */
    var MONTH_NAMES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var WEEKDAY_ABBR = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'];

    function pad2(n) { return (n < 10 ? '0' : '') + n; }
    function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
    function isSameDay(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }

    function parseISODate(value) {
        if (!value) { return null; }
        var parts = value.split('-');
        if (parts.length !== 3) { return null; }
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    }

    function parseISOMonth(value) {
        if (!value) { return null; }
        var parts = value.split('-');
        if (parts.length !== 2) { return null; }
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, 1);
    }

    function formatISODate(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }
    function formatISOMonth(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1); }
    function formatDisplayDate(d) { return pad2(d.getDate()) + '/' + pad2(d.getMonth() + 1) + '/' + d.getFullYear(); }
    function formatDisplayMonth(d) { return capitalize(MONTH_NAMES[d.getMonth()]) + ' ' + d.getFullYear(); }

    function enhanceDateInputs(root) {
        var inputs = (root || document).querySelectorAll('input[type="date"]:not(.is-enhanced), input[type="month"]:not(.is-enhanced)');
        Array.prototype.forEach.call(inputs, function (input) {
            buildDatePicker(input, input.type === 'month' ? 'month' : 'date');
        });
    }

    function buildDatePicker(input, kind) {
        input.classList.add('is-enhanced');
        input.tabIndex = -1;

        var field = document.createElement('div');
        field.className = 'datepicker-field';

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'datepicker-trigger';
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');
        if (input.disabled) {
            trigger.disabled = true;
        }

        var labelSpan = document.createElement('span');
        labelSpan.className = 'datepicker-trigger-label';
        trigger.appendChild(labelSpan);

        var calIcon = document.createElement('span');
        calIcon.className = 'icon';
        calIcon.innerHTML = (window.__iconSvgs && window.__iconSvgs.calendar) || '';
        trigger.appendChild(calIcon);

        var popup = document.createElement('div');
        popup.className = 'datepicker-popup';
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-label', kind === 'month' ? 'Elegir período' : 'Elegir fecha');

        var header = document.createElement('div');
        header.className = 'datepicker-header';
        var prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'datepicker-nav';
        prevBtn.setAttribute('aria-label', 'Anterior');
        prevBtn.innerHTML = (window.__iconSvgs && window.__iconSvgs['chevron-left']) || '&lsaquo;';
        var nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'datepicker-nav';
        nextBtn.setAttribute('aria-label', 'Siguiente');
        nextBtn.innerHTML = (window.__iconSvgs && window.__iconSvgs['chevron-right']) || '&rsaquo;';
        var titleSpan = document.createElement('span');
        titleSpan.className = 'datepicker-title';
        header.appendChild(prevBtn);
        header.appendChild(titleSpan);
        header.appendChild(nextBtn);
        popup.appendChild(header);

        var bodyContainer = document.createElement('div');
        if (kind === 'date') {
            var weekdaysRow = document.createElement('div');
            weekdaysRow.className = 'datepicker-weekdays';
            WEEKDAY_ABBR.forEach(function (d) {
                var s = document.createElement('span');
                s.textContent = d;
                weekdaysRow.appendChild(s);
            });
            popup.appendChild(weekdaysRow);
            bodyContainer.className = 'datepicker-grid';
        } else {
            bodyContainer.className = 'datepicker-months-grid';
        }
        popup.appendChild(bodyContainer);

        field.appendChild(trigger);
        field.appendChild(popup);
        input.parentNode.insertBefore(field, input);
        field.insertBefore(input, trigger);
        updateHorizontalFlip(field, 272);

        var seed = (kind === 'date' ? parseISODate(input.value) : parseISOMonth(input.value)) || new Date();
        var viewDate = new Date(seed.getFullYear(), seed.getMonth(), 1);

        function updateLabel() {
            var current = kind === 'date' ? parseISODate(input.value) : parseISOMonth(input.value);
            if (current) {
                labelSpan.textContent = kind === 'date' ? formatDisplayDate(current) : formatDisplayMonth(current);
                labelSpan.classList.remove('is-placeholder');
            } else {
                labelSpan.textContent = kind === 'date' ? 'dd/mm/aaaa' : 'Selecciona un período';
                labelSpan.classList.add('is-placeholder');
            }
        }
        updateLabel();

        function render() {
            bodyContainer.innerHTML = '';

            if (kind === 'date') {
                titleSpan.textContent = capitalize(MONTH_NAMES[viewDate.getMonth()]) + ' ' + viewDate.getFullYear();
                var year = viewDate.getFullYear();
                var month = viewDate.getMonth();
                var firstOfMonth = new Date(year, month, 1);
                var startOffset = (firstOfMonth.getDay() + 6) % 7; // Monday-first grid
                var gridStart = new Date(year, month, 1 - startOffset);
                var selected = parseISODate(input.value);
                var today = new Date();
                today.setHours(0, 0, 0, 0);

                for (var i = 0; i < 42; i++) {
                    var cellDate = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + i);
                    (function (d) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'datepicker-day';
                        btn.textContent = String(d.getDate());
                        if (d.getMonth() !== month) { btn.classList.add('is-outside'); }
                        if (isSameDay(d, today)) { btn.classList.add('is-today'); }
                        if (selected && isSameDay(d, selected)) { btn.classList.add('is-selected'); }
                        btn.addEventListener('click', function () {
                            input.value = formatISODate(d);
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                            updateLabel();
                            closePopup();
                            trigger.focus();
                        });
                        bodyContainer.appendChild(btn);
                    })(cellDate);
                }
            } else {
                titleSpan.textContent = String(viewDate.getFullYear());
                var selectedMonth = parseISOMonth(input.value);
                for (var m = 0; m < 12; m++) {
                    (function (mm) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'datepicker-month-cell';
                        btn.textContent = MONTH_NAMES[mm].slice(0, 3);
                        if (selectedMonth && selectedMonth.getFullYear() === viewDate.getFullYear() && selectedMonth.getMonth() === mm) {
                            btn.classList.add('is-selected');
                        }
                        btn.addEventListener('click', function () {
                            input.value = formatISOMonth(new Date(viewDate.getFullYear(), mm, 1));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                            updateLabel();
                            closePopup();
                            trigger.focus();
                        });
                        bodyContainer.appendChild(btn);
                    })(m);
                }
            }
        }

        prevBtn.addEventListener('click', function () {
            viewDate = kind === 'date' ? new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1) : new Date(viewDate.getFullYear() - 1, 0, 1);
            render();
        });
        nextBtn.addEventListener('click', function () {
            viewDate = kind === 'date' ? new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1) : new Date(viewDate.getFullYear() + 1, 0, 1);
            render();
        });

        function onDocClick(event) {
            if (!field.contains(event.target)) {
                closePopup();
            }
        }

        function onKeydown(event) {
            if (event.key === 'Escape') {
                closePopup();
                trigger.focus();
            }
        }

        function closePopup() {
            field.classList.remove('is-open', 'opens-up');
            trigger.setAttribute('aria-expanded', 'false');
            document.removeEventListener('click', onDocClick);
            document.removeEventListener('keydown', onKeydown);
            unregisterPopup(closePopup);
        }

        function openPopup() {
            closeOtherPopups(closePopup);
            var current = kind === 'date' ? parseISODate(input.value) : parseISOMonth(input.value);
            viewDate = current ? new Date(current.getFullYear(), current.getMonth(), 1) : new Date(new Date().getFullYear(), new Date().getMonth(), 1);
            render();
            field.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            var rect = trigger.getBoundingClientRect();
            field.classList.toggle('opens-up', rect.bottom + 300 > window.innerHeight && rect.top > 300);
            updateHorizontalFlip(field, 272);
            document.addEventListener('click', onDocClick);
            document.addEventListener('keydown', onKeydown);
            registerPopup(closePopup);
        }

        trigger.addEventListener('click', function () {
            if (field.classList.contains('is-open')) {
                closePopup();
            } else {
                openPopup();
            }
        });
    }

    enhanceSelects(document);
    enhanceDateInputs(document);

    /* ---------------------------------------------------------------
     * Form modal — overlays small create/edit forms on top of the list
     * page that opened them instead of navigating to a dedicated
     * screen. The fetched form is the exact same partial the full-page
     * fallback route renders (see the _form.blade.php partials),
     * so validation/authorization logic is never duplicated in JS: a
     * 422 response (forced via the Accept: application/json header,
     * Laravel's built-in behavior for ValidationException — no
     * controller changes needed) is rendered inline, and anything else
     * (a real redirect Laravel already issues on success) is followed
     * to its destination so the flash toast still shows there.
     * --------------------------------------------------------------- */
    var formModalBackdrop = document.getElementById('formModalBackdrop');
    var formModalDialog = document.getElementById('formModalDialog');
    var formModalTitle = document.getElementById('formModalTitle');
    var formModalBody = document.getElementById('formModalBody');
    var formModalClose = document.getElementById('formModalClose');
    var formModalLastFocused = null;

    function resetSubmitButton(button) {
        if (button) {
            button.classList.remove('is-loading');
            button.disabled = false;
        }
    }

    function clearFormErrors(form) {
        Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'), function (el) {
            el.classList.remove('is-invalid');
        });
        Array.prototype.forEach.call(form.querySelectorAll('[data-js-error]'), function (el) {
            el.remove();
        });
    }

    function applyFormErrors(form, errors) {
        var firstWrapper = null;
        Object.keys(errors).forEach(function (key) {
            var field = form.querySelector('[name="' + key + '"]');
            if (!field || !errors[key] || !errors[key][0]) {
                return;
            }
            field.classList.add('is-invalid');
            var wrapper = field.closest('.select-field, .datepicker-field') || field;

            var errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.setAttribute('data-js-error', '1');
            errorDiv.innerHTML = (window.__iconSvgs && window.__iconSvgs['alert-triangle']) || '';
            var msgSpan = document.createElement('span');
            msgSpan.textContent = errors[key][0];
            errorDiv.appendChild(msgSpan);
            wrapper.insertAdjacentElement('afterend', errorDiv);

            if (!firstWrapper) {
                firstWrapper = wrapper;
            }
        });
        if (firstWrapper) {
            var focusTarget = firstWrapper.matches('.select-field, .datepicker-field')
                ? firstWrapper.querySelector('button')
                : firstWrapper;
            if (focusTarget && focusTarget.focus) {
                focusTarget.focus();
            }
        }
    }

    function wireModalForm(form) {
        if (!form) {
            return;
        }
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearFormErrors(form);
            var submitBtn = form.querySelector('[type="submit"]');
            setButtonLoading(submitBtn);

            fetch(form.action, {
                method: 'POST', // Laravel reads the _method spoof field from body regardless of Accept header
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: new FormData(form),
            }).then(function (response) {
                if (response.status === 422) {
                    return response.json().then(function (data) {
                        applyFormErrors(form, data.errors || {});
                        resetSubmitButton(submitBtn);
                    });
                }
                if (response.ok) {
                    // fetch() already followed the redirect and, in doing so,
                    // consumed the server's one-shot session flash — carry
                    // its message forward (see the sessionStorage relay near
                    // the toast init above) so it still reaches the page the
                    // browser is about to load for real.
                    return response.text().then(function (html) {
                        try {
                            var doc = new DOMParser().parseFromString(html, 'text/html');
                            var flashEl = doc.getElementById('flashData');
                            var flash = flashEl ? JSON.parse(flashEl.textContent || '{}') : null;
                            if (flash && ((flash.success && flash.success.length) || (flash.error && flash.error.length))) {
                                sessionStorage.setItem('cc_pending_flash', JSON.stringify(flash));
                            }
                        } catch (e) {
                            // no flash to carry forward — navigate anyway
                        }
                        window.location.assign(response.url);
                    });
                }
                resetSubmitButton(submitBtn);
                window.__showToast && window.__showToast('danger', 'No pudimos guardar los cambios. Intenta nuevamente.');
            }).catch(function () {
                resetSubmitButton(submitBtn);
                window.__showToast && window.__showToast('danger', 'No pudimos guardar los cambios. Intenta nuevamente.');
            });
        });

        var cancelBtn = form.querySelector('.js-modal-cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function (event) {
                event.preventDefault();
                closeFormModal();
            });
        }
    }

    function openFormModal(url, title) {
        if (!formModalBackdrop || !formModalDialog) {
            window.location.href = url; // no modal markup on this page — plain navigation fallback
            return;
        }
        closeOtherPopups(null);
        formModalLastFocused = document.activeElement;
        formModalTitle.textContent = title || '';
        formModalBody.innerHTML = '<div class="modal-form-loading"><span class="spinner spinner-lg"></span></div>';
        formModalBackdrop.classList.add('is-open');
        formModalDialog.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onFormModalKeydown);

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('load-failed');
            }
            return response.text();
        }).then(function (html) {
            formModalBody.innerHTML = html;
            enhanceSelects(formModalBody);
            enhanceDateInputs(formModalBody);
            wireModalForm(formModalBody.querySelector('form'));
            var firstField = formModalBody.querySelector('input:not([type="hidden"]), .select-trigger, .datepicker-trigger, textarea');
            if (firstField) {
                firstField.focus();
            }
        }).catch(function () {
            formModalBody.innerHTML = '<div class="error-state"><p>No pudimos cargar el formulario. Intenta nuevamente.</p></div>';
        });
    }

    function closeFormModal() {
        formModalBackdrop.classList.remove('is-open');
        formModalDialog.classList.remove('is-open');
        document.body.style.overflow = '';
        document.removeEventListener('keydown', onFormModalKeydown);
        if (formModalLastFocused) {
            formModalLastFocused.focus();
        }
        formModalLastFocused = null;
    }

    function onFormModalKeydown(event) {
        if (event.key === 'Escape') {
            closeFormModal();
            return;
        }
        if (event.key === 'Tab') {
            var panel = formModalDialog.querySelector('.modal-panel-form');
            var focusables = panel.querySelectorAll('button:not([disabled]):not([tabindex="-1"]), [href], input:not([disabled]):not([tabindex="-1"]), select:not([disabled]):not([tabindex="-1"]), textarea:not([disabled])');
            if (!focusables.length) {
                return;
            }
            var first = focusables[0];
            var last = focusables[focusables.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    }

    if (formModalBackdrop && formModalDialog) {
        formModalClose.addEventListener('click', closeFormModal);
        formModalBackdrop.addEventListener('click', closeFormModal);
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('.js-modal-link');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) {
            return;
        }
        event.preventDefault();
        openFormModal(link.href, link.dataset.modalTitle || link.textContent.trim());
    });
})();

/**
 * Modal dinámico global: un solo overlay en body, sin Bootstrap, z-index alto.
 * Uso: AppModal.open({ title: 'Título', contentSelector: '#idContenido', size: 'md', onShow: fn, onClose: fn })
 *      AppModal.open({ title: 'Título', content: '<p>HTML</p>', size: 'lg' })
 */
(function () {
    'use strict';

    var overlay = null;
    var body = null;
    var titleEl = null;
    var bodyEl = null;
    var closeBtn = null;
    var backdrop = null;
    var currentOnClose = null;

    function getEl() {
        if (!overlay) {
            overlay = document.getElementById('app-modal-overlay');
            if (!overlay) return null;
            body = overlay.querySelector('.app-modal-body');
            titleEl = overlay.querySelector('.app-modal-title');
            closeBtn = overlay.querySelector('.app-modal-close');
            backdrop = overlay.querySelector('.app-modal-backdrop');
        }
        return overlay;
    }

    function close() {
        var el = getEl();
        if (!el || el.style.display === 'none') return;
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
        if (body) body.innerHTML = '';
        if (titleEl) titleEl.textContent = '';
        el.className = el.className.replace(/\bapp-modal-size-\w+/g, '').trim();
        if (typeof currentOnClose === 'function') {
            currentOnClose();
            currentOnClose = null;
        }
    }

    function open(options) {
        options = options || {};
        var el = getEl();
        if (!el) return;

        var title = options.title || '';
        var size = options.size || 'md';
        var contentHtml = options.content;
        var contentSelector = options.contentSelector;
        var onShow = options.onShow;
        var onClose = options.onClose;

        currentOnClose = onClose;

        if (titleEl) titleEl.textContent = title;
        if (body) {
            if (contentHtml !== undefined) {
                body.innerHTML = contentHtml;
            } else if (contentSelector) {
                var source = document.querySelector(contentSelector);
                if (source) {
                    body.innerHTML = source.innerHTML;
                } else {
                    body.innerHTML = '';
                }
            } else {
                body.innerHTML = '';
            }
        }

        el.className = 'app-modal-overlay app-modal-size-' + size;
        el.style.display = 'flex';
        el.setAttribute('aria-hidden', 'false');

        if (closeBtn && !closeBtn._bound) {
            closeBtn._bound = true;
            closeBtn.addEventListener('click', close);
        }
        if (backdrop && !backdrop._bound) {
            backdrop._bound = true;
            backdrop.addEventListener('click', close);
        }

        if (typeof onShow === 'function') {
            setTimeout(function () { onShow(el); }, 0);
        }
    }

    window.AppModal = {
        open: open,
        close: close
    };
})();

/**
 * Claude Connector by Piskee - back office page behaviour.
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function () {
    var root = document.querySelector('.pkc');
    if (!root) { return; }

    function activate(selector, attr, value) {
      root.querySelectorAll(selector).forEach(function (el) {
        el.classList.toggle('is-active', el.getAttribute(attr) === value);
      });
    }

    function openTab(name) {
      activate('.pkc-tab', 'data-tab', name);
      activate('.pkc-panel', 'data-panel', name);
      try { window.sessionStorage.setItem('pkc-tab', name); } catch (e) { /* storage unavailable */ }
    }

    root.querySelectorAll('.pkc-tab').forEach(function (tab) {
      tab.addEventListener('click', function () { openTab(tab.getAttribute('data-tab')); });
    });
    root.querySelectorAll('[data-open-tab]').forEach(function (button) {
      button.addEventListener('click', function () {
        openTab(button.getAttribute('data-open-tab'));
        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
    try {
      var saved = window.sessionStorage.getItem('pkc-tab');
      if (saved && root.querySelector('[data-panel="' + saved + '"]')) { openTab(saved); }
    } catch (e) { /* storage unavailable */ }

    root.querySelectorAll('[data-doc]').forEach(function (chip) {
      chip.addEventListener('click', function () {
        var lang = chip.getAttribute('data-doc');
        activate('[data-doc]', 'data-doc', lang);
        activate('[data-doc-body]', 'data-doc-body', lang);
      });
    });

    root.querySelectorAll('[data-copy]').forEach(function (button) {
      button.addEventListener('click', function () {
        var target = root.querySelector(button.getAttribute('data-copy'));
        if (!target) { return; }
        var text = target.textContent.trim();
        var label = button.textContent;
        var done = function () {
          button.textContent = root.getAttribute('data-copied') || 'Copied';
          setTimeout(function () { button.textContent = label; }, 1800);
        };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(done);
        } else {
          var range = document.createRange();
          range.selectNodeContents(target);
          var selection = window.getSelection();
          selection.removeAllRanges();
          selection.addRange(range);
          try { document.execCommand('copy'); done(); } catch (e) { /* user can copy by hand */ }
        }
      });
    });

    root.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!window.confirm(form.getAttribute('data-confirm'))) { event.preventDefault(); }
      });
    });
  });
}());

/* Pro pricing: monthly / yearly switch */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.pkc-billing').forEach(function (card) {
      card.querySelectorAll('[data-billing-set]').forEach(function (button) {
        button.addEventListener('click', function () {
          var mode = button.getAttribute('data-billing-set');
          card.setAttribute('data-billing', mode);
          card.querySelectorAll('[data-billing-set]').forEach(function (b) {
            b.classList.toggle('is-active', b === button);
            b.setAttribute('aria-selected', b === button ? 'true' : 'false');
          });
          card.querySelectorAll('[data-billing-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-billing-panel') !== mode;
          });
          card.querySelectorAll('[data-href-' + mode + ']').forEach(function (link) {
            link.setAttribute('href', link.getAttribute('data-href-' + mode));
          });
        });
      });
    });
  });
}());

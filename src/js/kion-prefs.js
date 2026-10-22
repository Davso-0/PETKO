/* =====================================================
   KION · Preferencias globales (modo oscuro + idioma)
   Archivo: src/js/kion-prefs.js
   
   Uso en cada página:
     <script src="[ruta_relativa]/src/js/kion-prefs.js"></script>
   
   Luego en los botones:
     onclick="KionPrefs.toggleDark()"
     onclick="KionPrefs.toggleLang()"
===================================================== */
(function () {
    'use strict';

    /* ── Claves de localStorage ── */
    var DARK_KEY = 'kion_dark';
    var LANG_KEY = 'kion_lang';

    /* ── Estado interno ── */
    var _isDark = localStorage.getItem(DARK_KEY) === '1';
    var _isEN   = localStorage.getItem(LANG_KEY) === 'en';

    /* ── Aplicar modo oscuro inmediatamente (antes del paint) ── */
    function _applyDark(on) {
        document.documentElement.classList.toggle('kion-dark', on);
        document.body && document.body.classList.toggle('kion-dark', on);
    }

    /* Si ya hay preferencia guardada, aplicarla al momento de cargar */
    if (_isDark) {
        document.documentElement.classList.add('kion-dark');
    }

    /* ── Traducciones por página ── */
    /*
     * Cada página puede registrar su tabla de traducciones llamando:
     *   KionPrefs.registerTranslations({ 'Español': 'English', ... });
     * Al hacer esto, si el idioma activo es EN, se aplica de inmediato.
     */
    var _translations   = {};
    var _originalTexts  = new Map();

    function _applyTranslations() {
        var entries = Object.entries(_translations).sort(function (a, b) {
            return b[0].length - a[0].length; /* más largas primero */
        });

        document.querySelectorAll('body *:not(script):not(style)').forEach(function (el) {
            el.childNodes.forEach(function (node) {
                if (node.nodeType !== Node.TEXT_NODE) return;
                if (el.hasAttribute && el.hasAttribute('data-no-i18n')) return;

                if (!_originalTexts.has(node)) {
                    _originalTexts.set(node, node.textContent);
                }

                var original  = _originalTexts.get(node);
                var leading   = original.match(/^\s*/)[0];
                var trailing  = original.match(/\s*$/)[0];
                var trimmed   = original.trim().replace(/\s+/g, ' ');

                if (!_isEN) {
                    node.textContent = original;
                    return;
                }

                var translated = trimmed;
                entries.forEach(function (pair) {
                    translated = translated.split(pair[0]).join(pair[1]);
                });
                node.textContent = leading + translated + trailing;
            });
        });

        document.documentElement.lang = _isEN ? 'en' : 'es';

        /* Actualizar todos los botones que muestran el idioma actual */
        document.querySelectorAll('[data-kion-lang-btn]').forEach(function (btn) {
            btn.textContent = _isEN ? 'ES' : 'EN';
        });
        document.querySelectorAll('[data-kion-dark-btn]').forEach(function (btn) {
            btn.textContent = _isDark ? '☀ Claro' : '☾ Oscuro';
        });
    }

    /* ── API pública ── */
    window.KionPrefs = {

        /* Registrar traducciones y aplicar si idioma EN está activo */
        registerTranslations: function (table) {
            _translations = table || {};
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    _applyDark(_isDark);
                    if (_isEN) _applyTranslations();
                    _syncButtons();
                });
            } else {
                _applyDark(_isDark);
                if (_isEN) _applyTranslations();
                _syncButtons();
            }
        },

        /* Inicializar sin traducciones (solo dark mode) */
        init: function () {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    _applyDark(_isDark);
                    _syncButtons();
                });
            } else {
                _applyDark(_isDark);
                _syncButtons();
            }
        },

        /* Toggle modo oscuro */
        toggleDark: function () {
            _isDark = !_isDark;
            localStorage.setItem(DARK_KEY, _isDark ? '1' : '0');
            _applyDark(_isDark);
            _syncButtons();
        },

        /* Toggle idioma */
        toggleLang: function () {
            _isEN = !_isEN;
            localStorage.setItem(LANG_KEY, _isEN ? 'en' : 'es');
            _applyTranslations();
        },

        /* Consultar estado */
        isDark: function () { return _isDark; },
        isEN:   function () { return _isEN;   }
    };

    /* Sincronizar textos de botones */
    function _syncButtons() {
        document.querySelectorAll('[data-kion-dark-btn]').forEach(function (btn) {
            btn.textContent = _isDark ? '☀ Claro' : '☾ Oscuro';
        });
        document.querySelectorAll('[data-kion-lang-btn]').forEach(function (btn) {
            btn.textContent = _isEN ? 'ES' : 'EN';
        });
    }

})();

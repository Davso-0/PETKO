/* PETKO · Preferencias compartidas (modo oscuro + idioma)
   Mismas claves que el home, login y registro: petko_dark ('1'/'0') y petko_lang ('es'/'en').
   Cualquier botón con data-petko-theme / data-petko-lang queda conectado automáticamente. */
(function () {
    'use strict';
    var DK = 'petko_dark', LK = 'petko_lang';
    var subs = { dark: [], lang: [] };

    function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

    function readDark() {
        var d = get(DK);
        if (d === null) return !!(window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
        return d === '1';
    }
    function readLang() { return get(LK) === 'en' ? 'en' : 'es'; }

    var dark = readDark(), lang = readLang();

    function paint() {
        if (document.body) document.body.classList.toggle('dark-mode', dark);
        document.documentElement.lang = lang;
        var en = lang === 'en';
        var tTxt = en ? (dark ? 'Switch to light mode' : 'Switch to dark mode')
                      : (dark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
        var lTxt = en ? 'Cambiar a español' : 'Switch to English';
        Array.prototype.forEach.call(document.querySelectorAll('[data-petko-theme]'), function (b) {
            b.textContent = dark ? '☀️' : '🌙';
            b.title = tTxt; b.setAttribute('aria-label', tTxt); b.setAttribute('aria-pressed', String(dark));
        });
        Array.prototype.forEach.call(document.querySelectorAll('[data-petko-lang]'), function (b) {
            b.textContent = en ? 'ES' : 'EN';
            b.title = lTxt; b.setAttribute('aria-label', lTxt);
        });
    }
    function emit(kind) {
        subs[kind].forEach(function (fn) { try { fn(kind === 'dark' ? dark : lang); } catch (e) {} });
    }
    function setDark(v) { dark = !!v; set(DK, dark ? '1' : '0'); paint(); emit('dark'); }
    function setLang(v) { lang = v === 'en' ? 'en' : 'es'; set(LK, lang); paint(); emit('lang'); }

    window.PetkoPrefs = {
        isDark: function () { return dark; },
        lang: function () { return lang; },
        setDark: setDark, setLang: setLang,
        toggleDark: function () { setDark(!dark); },
        toggleLang: function () { setLang(lang === 'en' ? 'es' : 'en'); },
        onDark: function (fn) { subs.dark.push(fn); },
        onLang: function (fn) { subs.lang.push(fn); },
        refresh: paint
    };

    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('[data-petko-theme],[data-petko-lang]') : null;
        if (!t) return;
        if (t.hasAttribute('data-petko-theme')) window.PetkoPrefs.toggleDark();
        else window.PetkoPrefs.toggleLang();
    });
    // Sincroniza otras pestañas y el botón "atrás" del navegador
    window.addEventListener('storage', function (e) {
        if (e.key === DK) { dark = readDark(); paint(); emit('dark'); }
        if (e.key === LK) { lang = readLang(); paint(); emit('lang'); }
    });
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        var d = readDark(), l = readLang();
        if (d !== dark) { dark = d; emit('dark'); }
        if (l !== lang) { lang = l; emit('lang'); }
        paint();
    });

    paint();
    document.addEventListener('DOMContentLoaded', paint);
})();

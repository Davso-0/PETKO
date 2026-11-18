/* ==========================================================================
   KION · VERSIÓN MÓVIL · movil.js
   Capa de comportamiento móvil. No reemplaza la lógica original: dispara los
   mismos botones/enlaces del original (.click()) y solo reorganiza la interfaz.
   ========================================================================== */
(() => {
  'use strict';
  const root = document.documentElement;
  const T = root.dataset.km, MOD = root.dataset.kmMod;
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const ready = fn => document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn();
  const isEN = () => /^ES$/i.test(($('[data-petko-lang],#languageToggle,#btnLang') || {}).textContent?.trim() || '');
  const tr = (es, en) => isEN() ? en : es;

  /* Enlaces #ancla: <base> apunta al original, así que se resuelven a mano */
  document.addEventListener('click', e => {
    const a = e.target.closest && e.target.closest('a[href^="#"]');
    if (!a) return;
    const h = a.getAttribute('href');
    e.preventDefault();
    if (h.length < 2) return;
    const el = document.getElementById(decodeURIComponent(h.slice(1)));
    if (el) {
      el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
      try { history.replaceState(null, '', location.pathname + location.search + h); } catch (_) {}
    }
  }, true);

  /* Teclado en pantalla: oculta la navegación inferior */
  if (window.visualViewport) {
    const f = () => {
      const t = document.activeElement && /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName);
      root.classList.toggle('km-kb', !!t && visualViewport.height < innerHeight * 0.78);
    };
    visualViewport.addEventListener('resize', f);
    document.addEventListener('focusin', f);
    document.addEventListener('focusout', () => setTimeout(f, 60));
  }

  /* ---------- Navegación inferior ---------- */
  const SVG = {
    home: '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>',
    grid: '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
    paw: '<circle cx="6" cy="10" r="1.8"/><circle cx="10" cy="6" r="1.8"/><circle cx="14" cy="6" r="1.8"/><circle cx="18" cy="10" r="1.8"/><path d="M12 12c-3 0-5 3-5 5 0 1.6 1.4 2.5 3 2.2 1-.2 1.4-.4 2-.4s1 .2 2 .4c1.6.3 3-.6 3-2.2 0-2-2-5-5-5z"/>',
    pin: '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.800 12 21 12 21z"/><circle cx="12" cy="9.500" r="2.500"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
    more: '<circle cx="5" cy="12" r="1.500"/><circle cx="12" cy="12" r="1.500"/><circle cx="19" cy="12" r="1.500"/>'
  };
  const svg = k => `<svg viewBox="0 0 24 24" aria-hidden="true">${SVG[k]}</svg>`;

  function navItem(o) {
    const el = document.createElement(o.href ? 'a' : 'button');
    el.className = 'km-nav__it';
    if (o.href) el.href = o.href; else el.type = 'button';
    el.innerHTML = `<span class="km-nav__ic">${o.svg ? svg(o.svg) : `<i class="ph ${o.ph}"></i>`}<b class="km-nav__badge" hidden></b></span><span class="km-nav__lb"></span>`;
    const lb = $('.km-nav__lb', el);
    const setLb = () => { lb.textContent = (typeof o.label === 'function' ? o.label() : o.label) || (o.src && o.src.textContent.trim()) || ''; el.setAttribute('aria-label', lb.textContent); };
    setLb();
    if (o.src) new MutationObserver(setLb).observe(o.src, { childList: true, characterData: true, subtree: true });
    el._set = setLb; el._o = o;
    if (o.onClick) el.addEventListener('click', o.onClick);
    return el;
  }
  function mountNav(items) {
    const nav = document.createElement('nav');
    nav.className = 'km-nav'; nav.setAttribute('aria-label', tr('Navegación principal', 'Main navigation'));
    items.forEach(i => nav.appendChild(i));
    document.body.appendChild(nav);
    document.body.classList.add('km-has-nav');
    const langBtn = $('[data-petko-lang],#languageToggle,#btnLang');
    if (langBtn) langBtn.addEventListener('click', () => setTimeout(() => items.forEach(i => i._set()), 150));
    return nav;
  }
  const setActive = (items, fn) => items.forEach(i => i.classList.toggle('is-active', !!fn(i._o)));

  /* ---------- Hoja inferior genérica ---------- */
  function makeSheet(title) {
    const scrim = document.createElement('div'); scrim.className = 'km-scrim';
    const sh = document.createElement('section'); sh.className = 'km-sheet'; sh.setAttribute('role', 'dialog'); sh.setAttribute('aria-modal', 'true');
    sh.innerHTML = `<div class="km-sheet__grip"></div><div class="km-sheet__head"><div class="km-sheet__title"></div><button type="button" class="km-sheet__x" aria-label="${tr('Cerrar', 'Close')}">✕</button></div><div class="km-sheet__body"></div>`;
    $('.km-sheet__title', sh).textContent = title;
    document.body.append(scrim, sh);
    const open = () => { scrim.classList.add('is-open'); sh.classList.add('is-open'); };
    const close = () => { scrim.classList.remove('is-open'); sh.classList.remove('is-open'); };
    scrim.addEventListener('click', close); $('.km-sheet__x', sh).addEventListener('click', close);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    sh.addEventListener('click', e => { if (e.target.closest('a[href]')) close(); });
    return { sh, body: $('.km-sheet__body', sh), open, close };
  }

  /* ---------- Tablas → tarjetas (etiquetas desde los <th>) ---------- */
  function labelTables() {
    $$('table').forEach(t => {
      if (t.closest('.swal2-container')) return;
      const heads = $$('th', t.querySelector('thead') || t.rows[0] || t).map(th => th.textContent.trim());
      if (!heads.length) return;
      t.classList.add('km-ct');
      $$('tr', t).forEach(tr => {
        if (tr.querySelector(':scope > th')) return;
        [...tr.children].forEach((td, i) => {
          if (td.tagName !== 'TD' || td.hasAttribute('colspan')) return;
          if (heads[i]) { td.dataset.label = heads[i]; td.classList.remove('km-act'); } else td.classList.add('km-act');
        });
      });
    });
  }
  let raf = 0;
  const schedule = fn => { cancelAnimationFrame(raf); raf = requestAnimationFrame(fn); };

  /* ---------- Dashboards (admin / gerente / usuario) ---------- */
  const DASH = {
    dashboard: [
      { t: 'view-general', ph: 'ph-squares-four', label: ['Resumen', 'Summary'] },
      { t: 'view-gestion-productos', ph: 'ph-package', label: ['Productos', 'Products'] },
      { t: 'view-ventas', ph: 'ph-chart-line-up', label: ['Ventas', 'Sales'] },
      { t: 'view-sucursales', ph: 'ph-storefront', label: ['Sucurs.', 'Branch'] }
    ],
    dashboard_gerente: [
      { t: 'view-dash', ph: 'ph-squares-four', label: ['Panel', 'Home'] },
      { t: 'view-inventario', ph: 'ph-package', label: ['Inventario', 'Inventory'] },
      { sel: '#btnIrPOS', view: 'view-pos', ph: 'ph-monitor', label: ['Caja', 'POS'] },
      { t: 'view-ventas', ph: 'ph-chart-line-up', label: ['Ventas', 'Sales'] }
    ],
    dashboard_usuario: [
      { t: 'view-mi-cuenta', ph: 'ph-shopping-bag', label: ['Apartados', 'Orders'] },
      { t: 'view-consultas', ph: 'ph-stethoscope', label: ['Citas', 'Visits'] },
      { t: 'view-mi-cuenta-config', ph: 'ph-gear', label: ['Ajustes', 'Settings'] },
      { sel: 'a.nav-btn[href*="catalogo"]', ph: 'ph-storefront', label: ['Catálogo', 'Catalog'], link: true }
    ]
  };
  function initDash() {
    const layout = $('#appLayout') || $('.app-layout');
    const cfg = DASH[MOD] || [];
    const items = [];
    cfg.forEach(c => {
      const src = c.t ? $(`.nav-btn[data-target="${c.t}"]`) : $(c.sel);
      if (!src) return;
      const lab = $('.nav-label', src) || src;
      items.push(navItem({
        ph: c.ph, src: lab, label: () => tr(c.label[0], c.label[1]), view: c.view || c.t, srcBtn: src,
        href: c.link ? src.href : null,
        onClick: c.link ? null : () => src.click()
      }));
    });
    const more = navItem({ ph: 'ph-squares-four', svg: 'more', label: () => tr('Más', 'More'), onClick: () => (layout || document.body).classList.add('mobile-open') });
    items.push(more);
    mountNav(items);

    const refresh = () => setActive(items, o => o.view && ($('#' + o.view) || {}).classList?.contains('active'));
    refresh();
    let last = $('.view-section.active')?.id;
    const obs = new MutationObserver(() => {
      refresh();
      const cur = $('.view-section.active')?.id;
      if (cur && cur !== last) { last = cur; window.scrollTo({ top: 0 }); schedule(labelTables); }
    });
    $$('.view-section').forEach(v => obs.observe(v, { attributes: true, attributeFilter: ['class'] }));

    const sheetNav = $('.sidebar .nav-menu');
    if (sheetNav && root.dataset.kmEscritorio && !$('.km-desk', sheetNav)) {
      const a = document.createElement('a');
      a.className = 'nav-btn km-desk'; a.href = root.dataset.kmEscritorio;
      a.innerHTML = '<i class="ph ph-desktop"></i><span class="nav-label">' + tr('Versión escritorio', 'Desktop version') + '</span>';
      sheetNav.appendChild(a);
    }
    const content = $('.content') || document.body;
    new MutationObserver(() => schedule(labelTables)).observe(content, { childList: true, subtree: true });
    labelTables();
    window.addEventListener('load', () => labelTables());
  }

  /* ---------- Catálogo ---------- */
  function initCatalogo() {
    const homeUrl = root.dataset.kmHome;
    const cartBtn = $('#open-cart'), panel = $('#btn-nav-panel'), search = $('#busqueda-catalogo'), count = $('#cart-count');
    const items = [
      navItem({ svg: 'home', label: () => tr('Inicio', 'Home'), href: homeUrl, key: 'home' }),
      navItem({ svg: 'grid', label: () => tr('Catálogo', 'Catalog'), key: 'cat', onClick: () => $('#catalogo')?.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth' }) }),
      navItem({ ph: 'ph-magnifying-glass', label: () => tr('Buscar', 'Search'), key: 'q', onClick: () => { if (search) { search.scrollIntoView({ block: 'center', behavior: reduce ? 'auto' : 'smooth' }); setTimeout(() => search.focus({ preventScroll: true }), 300); } } }),
      navItem({ ph: 'ph-shopping-cart', label: () => tr('Carrito', 'Cart'), key: 'cart', onClick: () => cartBtn && cartBtn.click() }),
      navItem(panel ? { svg: 'user', src: $('span', panel), href: panel.href, key: 'acc' } : { svg: 'user', label: () => tr('Cuenta', 'Account'), key: 'acc' })
    ];
    mountNav(items);
    items[1].classList.add('is-active');
    const badge = $('.km-nav__badge', items[3]);
    const syncBadge = () => { const n = parseInt(count?.textContent || '0', 10) || 0; badge.textContent = n > 99 ? '99+' : n; badge.hidden = n < 1; };
    if (count) new MutationObserver(syncBadge).observe(count, { childList: true, characterData: true, subtree: true });
    syncBadge();
    const cartPanel = $('#cart-panel');
    if (cartPanel) new MutationObserver(() => items[3].classList.toggle('is-active', cartPanel.classList.contains('is-open') || cartPanel.classList.contains('open'))).observe(cartPanel, { attributes: true, attributeFilter: ['class'] });
    const a11y = () => $$('.btn-apartar').forEach(b => b.setAttribute('aria-label', b.textContent.trim()));
    a11y(); const lb = $('[data-petko-lang]'); if (lb) lb.addEventListener('click', () => setTimeout(a11y, 200));
    $('.filtros__enlace--activo')?.scrollIntoView({ inline: 'center', block: 'nearest' });
  }

  /* ---------- Home ---------- */
  function initHome() {
    const menu = $('.menu'); if (!menu) return;
    const anchors = $$('a[href^="#"]', menu);
    const home = anchors.find(a => a.getAttribute('href') === '#inicio');
    const catalog = $('.ps-btns .ps-b1');
    const items = [];
    if (home) items.push(navItem({ svg: 'home', src: home, href: '#inicio', key: '#inicio' }));
    if (catalog) items.push(navItem({ svg: 'grid', label: () => tr('Catálogo', 'Catalog'), href: catalog.href, key: '#productos' }));

    const sheet = makeSheet(tr('Cuenta', 'Account'));
    const rest = anchors.filter(a => a !== home);
    if (rest.length) {
      const g = document.createElement('div'); g.className = 'km-sheet__grid';
      rest.forEach(a => { const t = document.createElement('a'); t.className = 'km-tile'; t.href = a.getAttribute('href'); t.innerHTML = svg('grid'); t.append(a.textContent.trim()); g.appendChild(t); });
      sheet.body.appendChild(g);
    }
    const acc = document.createElement('div'); acc.className = 'km-sheet__list';
    const login = $('.btn-login', menu), logout = $('.logout-form', menu);
    if (login) acc.appendChild(login);      // nodos originales: conservan su acción real
    if (logout) acc.appendChild(logout);
    if (root.dataset.kmEscritorio) { const d = document.createElement('a'); d.className = 'km-link-desk'; d.href = root.dataset.kmEscritorio; d.textContent = tr('Ver versión de escritorio', 'View desktop version'); acc.appendChild(d); }
    sheet.body.appendChild(acc);

    items.push(navItem({ svg: 'user', label: () => tr('Cuenta', 'Account'), onClick: sheet.open }));
    mountNav(items);

    if ('IntersectionObserver' in window) {
      const map = new Map(items.filter(i => i._o.key).map(i => [i._o.key.slice(1), i]));
      const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) setActive(items, o => o.key && o.key.slice(1) === e.target.id); }), { rootMargin: '-45% 0px -50% 0px' });
      map.forEach((_, id) => { const s = document.getElementById(id); if (s) io.observe(s); });
    }
    items[0] && items[0].classList.add('is-active');
  }

  ready(() => {
    if (T === 'dash' || T === 'usuario') initDash();
    else if (T === 'catalogo') initCatalogo();
    else if (T === 'home') initHome();
  });
})();

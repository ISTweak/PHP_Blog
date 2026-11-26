/* site.js — 閲覧側：スマホ用メニューの開閉、記事の画像の拡大表示 */
(() => {
  'use strict';

  /* ---------- スマホ用メニュー ---------- */
  const toggle = document.querySelector('.nav-toggle');
  if (toggle) {
    const setOpen = (open) => {
      document.body.classList.toggle('nav-open', open);
      toggle.setAttribute('aria-expanded', String(open));
    };
    toggle.addEventListener('click', () => setOpen(!document.body.classList.contains('nav-open')));
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') setOpen(false);
    });
    window.matchMedia('(min-width: 901px)').addEventListener('change', (e) => {
      if (e.matches) setOpen(false);
    });
  }

  const article = document.querySelector('.entry');
  if (!article) return;

  /* ---------- ギャラリーのページ送り ---------- */
  const galleries = [];

  function setupGallery(gallery) {
    const items = [...gallery.children].filter((el) => el.tagName === 'A');
    const cols = Number((gallery.className.match(/gallery--cols-(\d)/) || [])[1]) || 4;
    const perPage = cols * 6;                       // 6 段分
    if (items.length <= perPage) return;

    const pages = Math.ceil(items.length / perPage);
    const nav = document.createElement('nav');
    nav.className = 'pagination gallery-nav';
    nav.setAttribute('aria-label', 'ギャラリーのページ');
    const info = document.createElement('p');
    info.className = 'gallery-info';
    info.setAttribute('aria-live', 'polite');
    gallery.after(info, nav);

    const state = { gallery, items, perPage, pages, current: 1 };

    function button(label, page, { current = false, aria = '' } = {}) {
      const b = document.createElement(current ? 'span' : 'button');
      b.className = `pagination__item${current ? ' is-current' : ''}`;
      b.textContent = label;
      if (current) {
        b.setAttribute('aria-current', 'page');
      } else {
        b.type = 'button';
        b.addEventListener('click', () => show(page, true));
      }
      if (aria) b.setAttribute('aria-label', aria);
      return b;
    }

    function gap() {
      const s = document.createElement('span');
      s.className = 'pagination__gap';
      s.textContent = '…';
      return s;
    }

    function show(page, scroll) {
      state.current = Math.min(Math.max(1, page), pages);
      const start = (state.current - 1) * perPage;
      items.forEach((a, i) => { a.hidden = i < start || i >= start + perPage; });
      info.textContent = `${start + 1}〜${Math.min(start + perPage, items.length)} 枚目 / 全 ${items.length} 枚`;

      const parts = [];
      if (state.current > 1) parts.push(button('‹', state.current - 1, { aria: '前のページ' }));
      const from = Math.max(1, state.current - 2);
      const to = Math.min(pages, state.current + 2);
      if (from > 1) parts.push(button('1', 1));
      if (from > 2) parts.push(gap());
      for (let p = from; p <= to; p += 1) parts.push(button(String(p), p, { current: p === state.current }));
      if (to < pages - 1) parts.push(gap());
      if (to < pages) parts.push(button(String(pages), pages));
      if (state.current < pages) parts.push(button('›', state.current + 1, { aria: '次のページ' }));
      nav.replaceChildren(...parts);

      // ページを切り替えたら、ギャラリーの先頭が見える位置へ
      if (scroll && gallery.getBoundingClientRect().top < 0) {
        gallery.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      }
    }

    state.show = show;
    galleries.push(state);
    show(1, false);
  }

  /** 拡大表示を閉じたとき、最後に見た写真があるページに合わせる */
  function revealInGallery(el) {
    const g = galleries.find((s) => s.items.includes(el));
    if (!g) return;
    const page = Math.floor(g.items.indexOf(el) / g.perPage) + 1;
    if (page !== g.current) g.show(page, false);
    el.scrollIntoView({ block: 'nearest' });
  }

  article.querySelectorAll('.entry-content .gallery').forEach(setupGallery);

  /* ---------- 画像の拡大表示（ライトボックス） ---------- */

  const MEDIA_RE = /\/media\/(\d+)(?:\/(?:thumb|medium))?(?:[?#].*)?$/;
  const IMAGE_RE = /\.(?:jpe?g|png|gif|webp)(?:[?#].*)?$/i;

  /** 拡大表示の対象（画像を囲んだリンクとアイキャッチ）を集める */
  function collect() {
    const items = [];
    const eye = article.querySelector('.entry__eyecatch img');
    if (eye) {
      const src = eye.getAttribute('src') || '';
      items.push({ el: eye, view: src, full: src.replace(/\/(?:thumb|medium)(?=[?#]|$)/, ''), caption: eye.alt || '' });
    }
    article.querySelectorAll('.entry-content a[href]').forEach((a) => {
      const img = a.querySelector('img');
      const href = a.getAttribute('href') || '';
      if (!img) return;
      let view;
      if (MEDIA_RE.test(href)) {
        // ブログのメディアは中サイズ（最大 1280px）で表示して軽くする
        view = href.replace(MEDIA_RE, (m, id) => m.replace(/^\/media\/\d+(?:\/(?:thumb|medium))?/, `/media/${id}/medium`));
      } else if (IMAGE_RE.test(href)) {
        view = href;
      } else {
        return;
      }
      const fig = a.closest('figure');
      const caption = (fig && fig.querySelector('figcaption')?.textContent.trim()) || img.alt || '';
      items.push({ el: a, view, full: href, caption });
    });
    return items;
  }

  let items = [];
  let index = 0;
  let box = null;
  let lastFocus = null;
  const ui = {};

  function build() {
    box = document.createElement('div');
    box.className = 'lb';
    box.hidden = true;
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.setAttribute('aria-label', '画像の拡大表示');
    box.innerHTML = `
      <div class="lb__backdrop" data-lb-close></div>
      <figure class="lb__stage">
        <img class="lb__img" alt="">
        <figcaption class="lb__caption"></figcaption>
      </figure>
      <div class="lb__spinner" aria-hidden="true"></div>
      <button type="button" class="lb__nav lb__nav--prev" aria-label="前の画像">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <button type="button" class="lb__nav lb__nav--next" aria-label="次の画像">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <button type="button" class="lb__close" aria-label="閉じる" data-lb-close>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
      </button>
      <div class="lb__bar">
        <span class="lb__count" aria-live="polite"></span>
        <a class="lb__orig" target="_blank" rel="noopener">元のサイズで開く</a>
      </div>`;
    document.body.append(box);
    ui.img = box.querySelector('.lb__img');
    ui.caption = box.querySelector('.lb__caption');
    ui.count = box.querySelector('.lb__count');
    ui.orig = box.querySelector('.lb__orig');
    ui.prev = box.querySelector('.lb__nav--prev');
    ui.next = box.querySelector('.lb__nav--next');
    ui.close = box.querySelector('.lb__close');

    box.addEventListener('click', (e) => {
      if (e.target.closest('[data-lb-close]')) close();
    });
    ui.prev.addEventListener('click', () => show(index - 1));
    ui.next.addEventListener('click', () => show(index + 1));
    ui.img.addEventListener('load', () => box.classList.remove('is-loading'));
    ui.img.addEventListener('error', () => {
      box.classList.remove('is-loading');
      ui.caption.textContent = '画像を読み込めませんでした';
    });

    // スワイプで前後へ
    let startX = null;
    let startY = null;
    box.addEventListener('touchstart', (e) => {
      if (e.touches.length === 1) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
      }
    }, { passive: true });
    box.addEventListener('touchend', (e) => {
      if (startX === null) return;
      const dx = e.changedTouches[0].clientX - startX;
      const dy = e.changedTouches[0].clientY - startY;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) show(index + (dx < 0 ? 1 : -1));
      startX = null;
    });
  }

  function show(i) {
    if (!items.length) return;
    index = (i + items.length) % items.length;
    const it = items[index];
    box.classList.add('is-loading');
    ui.img.alt = it.caption;
    ui.img.src = it.view;
    ui.caption.textContent = it.caption;
    ui.caption.hidden = it.caption === '';
    ui.count.textContent = items.length > 1 ? `${index + 1} / ${items.length}` : '';
    ui.orig.href = it.full;
    ui.prev.hidden = ui.next.hidden = items.length < 2;
    // 前後の画像を先読み
    [index - 1, index + 1].forEach((n) => {
      const pre = items[(n + items.length) % items.length];
      if (pre) new Image().src = pre.view;
    });
  }

  function open(i) {
    if (!box) build();
    lastFocus = document.activeElement;
    box.hidden = false;
    document.body.classList.add('lb-open');
    show(i);
    ui.close.focus();
  }

  function close() {
    if (!box || box.hidden) return;
    box.hidden = true;
    document.body.classList.remove('lb-open');
    ui.img.removeAttribute('src');
    const viewed = items[index] && items[index].el;
    if (viewed) revealInGallery(viewed);
    // 最後に見た写真にフォーカスを戻す（ページ送りで隠れていない場合）
    const focusTo = viewed && !viewed.hidden ? viewed : lastFocus;
    if (focusTo && typeof focusTo.focus === 'function') focusTo.focus({ preventScroll: true });
  }

  document.addEventListener('click', (e) => {
    // Ctrl / ⌘ / Shift クリックは通常どおり新しいタブなどで開く
    if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    const target = e.target.closest('.entry-content a[href], .entry__eyecatch img');
    if (!target || !article.contains(target)) return;
    items = collect();
    const i = items.findIndex((it) => it.el === target);
    if (i === -1) return;
    e.preventDefault();
    open(i);
  });

  document.addEventListener('keydown', (e) => {
    if (!box || box.hidden) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowLeft') show(index - 1);
    else if (e.key === 'ArrowRight') show(index + 1);
    else if (e.key === 'Tab') {
      // フォーカスをダイアログ内に閉じ込める
      const focusables = [...box.querySelectorAll('button:not([hidden]), a[href]')];
      const first = focusables[0];
      const last = focusables[focusables.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
})();

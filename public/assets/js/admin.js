/* ==========================================================================
   admin.js — 管理画面
   - サイドバー開閉 / 削除確認 / URL コピー
   - 本文エディタ（クイックタグ・プレビュー）
   - メディア選択ダイアログ（アップロード・挿入・アイキャッチ選択）
   ========================================================================== */
(() => {
  'use strict';

  const body = document.body;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const urls = {
    list: body.dataset.mediaList,
    upload: body.dataset.mediaUpload,
    preview: body.dataset.previewUrl,
  };
  const maxBytes = Number(body.dataset.maxBytes || 0);

  /* ---------- ユーティリティ ---------- */

  const fmtBytes = (n) => {
    if (n >= 1048576) return `${(n / 1048576).toFixed(1).replace(/\.0$/, '')}MB`;
    if (n >= 1024) return `${Math.round(n / 1024)}KB`;
    return `${n}B`;
  };

  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));

  /** 要素を作成する小さなヘルパー */
  function h(tag, attrs = {}, ...children) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (v === null || v === undefined || v === false) continue;
      if (k === 'class') el.className = v;
      else if (k === 'text') el.textContent = v;
      else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2), v);
      else el.setAttribute(k, v === true ? '' : String(v));
    }
    for (const c of children.flat()) {
      if (c !== null && c !== undefined) el.append(c);
    }
    return el;
  }

  async function request(url, options = {}) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrf,
        ...(options.headers || {}),
      },
    });
    let data = null;
    try { data = await res.json(); } catch (_) { /* JSON 以外 */ }
    if (!res.ok || !data || data.ok === false) {
      throw new Error((data && data.error) || `通信エラーが発生しました（${res.status}）`);
    }
    return data;
  }

  /** ファイルを 1 つずつアップロードし、結果を statusEl に表示 */
  async function uploadFiles(files, statusEl, onUploaded) {
    let okCount = 0;
    for (const file of files) {
      const line = h('div', { class: 'upload-status__item', text: `${file.name}：アップロード中…` });
      statusEl.prepend(line);
      if (maxBytes && file.size > maxBytes) {
        line.textContent = `${file.name}：ファイルサイズが上限（${fmtBytes(maxBytes)}）を超えています。`;
        line.classList.add('is-error');
        continue;
      }
      const fd = new FormData();
      fd.append('_csrf', csrf);
      fd.append('file', file);
      try {
        const data = await request(urls.upload, { method: 'POST', body: fd });
        line.textContent = `${file.name}：アップロードしました。`;
        line.classList.add('is-ok');
        okCount += 1;
        if (onUploaded) onUploaded(data.media);
      } catch (err) {
        line.textContent = `${file.name}：${err.message}`;
        line.classList.add('is-error');
      }
    }
    return okCount;
  }

  function bindDropzone(zone, onFiles) {
    ['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, (e) => {
      e.preventDefault();
      zone.classList.add('is-over');
    }));
    zone.addEventListener('dragleave', (e) => {
      if (!zone.contains(e.relatedTarget)) zone.classList.remove('is-over');
    });
    zone.addEventListener('drop', (e) => {
      e.preventDefault();
      zone.classList.remove('is-over');
      const files = [...(e.dataTransfer?.files || [])];
      if (files.length) onFiles(files);
    });
  }

  /* ---------- サイドバー（スマホ） ---------- */

  const sbBtn = document.querySelector('[data-sidebar-toggle]');
  const setNav = (open) => {
    body.classList.toggle('adm-nav-open', open);
    sbBtn?.setAttribute('aria-expanded', String(open));
  };
  sbBtn?.addEventListener('click', () => setNav(!body.classList.contains('adm-nav-open')));
  document.querySelector('[data-sidebar-close]')?.addEventListener('click', () => setNav(false));

  /* ---------- 未保存の変更の警告 ---------- */

  const postForm = document.querySelector('[data-post-form]');
  let dirty = false;
  const markDirty = () => { dirty = true; };
  if (postForm) {
    postForm.addEventListener('input', markDirty);
    postForm.addEventListener('change', markDirty);
    window.addEventListener('beforeunload', (e) => {
      if (dirty) {
        e.preventDefault();
        e.returnValue = '';
      }
    });
    // Ctrl+S / ⌘+S で保存
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        postForm.requestSubmit();
      }
    });
  }

  /* ---------- 送信前の確認（data-confirm） ---------- */

  document.addEventListener('submit', (e) => {
    const form = e.target;
    const msg = (e.submitter && e.submitter.dataset.confirm) || form.dataset.confirm;
    if (msg && !window.confirm(msg)) {
      e.preventDefault();
      return;
    }
    dirty = false;
  });

  /* ---------- URL コピー ---------- */

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    const text = btn.dataset.copy;
    let ok = false;
    try {
      await navigator.clipboard.writeText(text);
      ok = true;
    } catch (_) {
      // http 環境では Clipboard API が使えないため旧方式で
      const t = h('textarea', { class: 'visually-hidden' });
      t.value = text;
      body.append(t);
      t.select();
      try { ok = document.execCommand('copy'); } catch (__) { ok = false; }
      t.remove();
    }
    const original = btn.textContent;
    btn.textContent = ok ? 'コピーしました' : 'コピーできませんでした';
    setTimeout(() => { btn.textContent = original; }, 1500);
  });

  /* ---------- メディア選択ダイアログ ---------- */

  const MediaPicker = (() => {
    let modal; let grid; let moreBtn; let statusEl; let footer; let titleEl; let filterBar;
    let sizeSel; let alignSel; let linkChk; let closeBtn;
    let galleryFoot; let galleryCount; let galleryCols; let galleryInsert;
    let selected = [];          // ギャラリー用に選んだメディア（選んだ順）
    let page = 1;
    let kind = '';
    let mode = 'insert';
    let onPick = null;
    let loading = false;
    let lastFocus = null;

    function build() {
      const fileInput = h('input', {
        type: 'file', multiple: true, class: 'visually-hidden',
        accept: 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime,application/pdf',
      });
      const zone = h('label', { class: 'dropzone' },
        fileInput,
        h('span', { class: 'dropzone__text', text: 'ファイルをドロップ、またはクリックしてアップロード' }),
        h('span', { class: 'dropzone__note', text: `1 ファイル ${fmtBytes(maxBytes)} まで ・ 画像 / 動画（MP4・WebM・MOV）/ PDF` }));
      statusEl = h('div', { class: 'upload-status', 'aria-live': 'polite' });
      filterBar = h('div', { class: 'tabs modal__filters' });
      grid = h('div', { class: 'pick-grid' });
      moreBtn = h('button', { type: 'button', class: 'btn btn--sm', hidden: true, text: 'さらに読み込む', onclick: () => load(false) });

      sizeSel = h('select', {},
        h('option', { value: 'medium', text: '中（最大 1280px）' }),
        h('option', { value: 'original', text: 'オリジナル' }),
        h('option', { value: 'thumb', text: '小（最大 600px）' }));
      alignSel = h('select', {},
        h('option', { value: '', text: 'なし' }),
        h('option', { value: 'aligncenter', text: '中央' }),
        h('option', { value: 'alignleft', text: '左寄せ' }),
        h('option', { value: 'alignright', text: '右寄せ' }));
      linkChk = h('input', { type: 'checkbox', checked: true });
      footer = h('div', { class: 'modal__foot' },
        h('label', {}, '画像サイズ ', sizeSel),
        h('label', {}, '配置 ', alignSel),
        h('label', { class: 'check' }, linkChk, '元画像にリンク'),
        h('span', { class: 'modal__hint', text: 'クリックで本文に挿入' }));

      // ギャラリー作成時のフッター（複数選択）
      galleryCount = h('span', { class: 'modal__count', 'aria-live': 'polite', text: '0 枚選択' });
      galleryCols = h('select', {},
        h('option', { value: '', text: '自動' }),
        ...[2, 3, 4, 5, 6].map((n) => h('option', { value: String(n), text: `${n} 列` })));
      galleryInsert = h('button', { type: 'button', class: 'btn btn--primary btn--sm', disabled: true, text: 'ギャラリーを挿入', onclick: insertGallery });
      galleryFoot = h('div', { class: 'modal__foot', hidden: true },
        galleryCount,
        h('label', {}, '列数 ', galleryCols),
        h('button', { type: 'button', class: 'btn btn--sm', text: '選択を解除', onclick: () => { selected = []; refreshSelection(); } }),
        h('span', { class: 'modal__hint', text: 'クリックで選択（選んだ順に並びます）' }),
        galleryInsert);

      titleEl =h('h2', { class: 'modal__title', id: 'media-modal-title', text: 'メディア' });
      closeBtn = h('button', { type: 'button', class: 'modal__close', 'aria-label': '閉じる', text: '×', onclick: close });
      modal = h('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'media-modal-title', hidden: true },
        h('div', { class: 'modal__backdrop', onclick: close }),
        h('div', { class: 'modal__panel' },
          h('div', { class: 'modal__head' }, titleEl, closeBtn),
          h('div', { class: 'modal__body' }, zone, statusEl, filterBar, grid, h('div', { class: 'modal__more' }, moreBtn)),
          footer, galleryFoot));
      body.append(modal);

      fileInput.addEventListener('change', () => {
        const files = [...fileInput.files];
        fileInput.value = '';
        handleFiles(files);
      });
      bindDropzone(zone, handleFiles);
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden) close();
      });
    }

    function renderFilters() {
      filterBar.replaceChildren();
      filterBar.hidden = mode !== 'insert';
      if (mode !== 'insert') return;
      [['', 'すべて'], ['image', '画像'], ['video', '動画'], ['file', 'PDF']].forEach(([k, label]) => {
        filterBar.append(h('button', {
          type: 'button',
          class: `tabs__item${k === kind ? ' is-active' : ''}`,
          text: label,
          onclick: () => { kind = k; renderFilters(); load(true); },
        }));
      });
    }

    function card(m) {
      let visual;
      if (m.kind === 'image') {
        visual = h('img', { src: m.thumb, alt: '', loading: 'lazy' });
      } else if (m.kind === 'video') {
        visual = h('span', { class: 'media-card__icon media-card__icon--video' }, '▶', h('small', { text: 'VIDEO' }));
      } else {
        visual = h('span', { class: 'media-card__icon', text: 'PDF' });
      }
      return h('button', { type: 'button', class: 'pick', 'data-id': m.id, title: `${m.name}（${m.sizeLabel}）`, onclick: () => pick(m) },
        h('span', { class: 'pick__img' }, visual, h('span', { class: 'pick__order', 'aria-hidden': 'true' })),
        h('span', { class: 'pick__name', text: m.name }));
    }

    /** ギャラリー用の選択状態を画面に反映（選んだ順の番号を表示） */
    function refreshSelection() {
      grid.querySelectorAll('.pick').forEach((el) => {
        const i = selected.indexOf(Number(el.dataset.id));
        el.classList.toggle('is-selected', i !== -1);
        el.setAttribute('aria-pressed', String(i !== -1));
        el.querySelector('.pick__order').textContent = i !== -1 ? String(i + 1) : '';
      });
      galleryCount.textContent = `${selected.length} 枚選択`;
      galleryInsert.disabled = selected.length === 0;
    }

    function insertGallery() {
      if (!selected.length) return;
      const cb = onPick;
      const ids = selected.slice();
      const columns = galleryCols.value;
      close();
      if (cb) cb(ids, { columns });
    }

    async function load(reset) {
      if (loading) return;
      loading = true;
      if (reset) {
        page = 1;
        grid.replaceChildren();
      }
      moreBtn.hidden = true;
      try {
        const u = new URL(urls.list, window.location.href);
        u.searchParams.set('page', String(page));
        if (kind) u.searchParams.set('kind', kind);
        const data = await request(u.toString());
        data.items.forEach((m) => grid.append(card(m)));
        if (mode === 'gallery') refreshSelection();
        if (!grid.children.length) {
          grid.append(h('p', { class: 'pick-empty', text: 'メディアがありません。上の枠からアップロードしてください。' }));
        }
        moreBtn.hidden = !data.hasMore;
        page += 1;
      } catch (err) {
        grid.append(h('p', { class: 'pick-empty', text: err.message }));
      } finally {
        loading = false;
      }
    }

    function handleFiles(files) {
      uploadFiles(files, statusEl, (m) => {
        if (mode !== 'insert' && m.kind !== 'image') return;
        if (kind && m.kind !== kind) return;
        grid.querySelector('.pick-empty')?.remove();
        grid.prepend(card(m));
        if (mode === 'gallery') {
          // アップロードした画像は選択済みにする
          selected.push(m.id);
          refreshSelection();
        }
      });
    }

    function pick(m) {
      if (mode !== 'insert' && m.kind !== 'image') return;
      if (mode === 'gallery') {
        const i = selected.indexOf(m.id);
        if (i === -1) selected.push(m.id); else selected.splice(i, 1);
        refreshSelection();
        return;
      }
      const cb = onPick;
      const opts = { size: sizeSel.value, align: alignSel.value, link: linkChk.checked };
      close();
      if (cb) cb(m, opts);
    }

    function open(options) {
      if (!modal) build();
      lastFocus = document.activeElement;
      mode = options.mode;
      onPick = options.onPick;
      kind = mode === 'insert' ? '' : 'image';
      titleEl.textContent = { eyecatch: 'アイキャッチ画像を選択', gallery: 'ギャラリーを作成' }[mode] || 'メディアを追加';
      footer.hidden = mode !== 'insert';
      galleryFoot.hidden = mode !== 'gallery';
      selected = [];
      galleryCols.value = '';
      refreshSelection();
      statusEl.replaceChildren();
      renderFilters();
      modal.hidden = false;
      body.classList.add('modal-open');
      load(true);
      closeBtn.focus();
    }

    function close() {
      if (!modal || modal.hidden) return;
      modal.hidden = true;
      body.classList.remove('modal-open');
      if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    }

    return { open };
  })();

  /** 本文に挿入する HTML（メディアの URL は "/media/..." 形式で保存し、表示時に設置パスを補う） */
  function mediaSnippet(m, o) {
    if (m.kind === 'image') {
      const src = o.size === 'original' ? m.ref : `${m.ref}/${o.size}`;
      const cls = o.align ? ` class="${o.align}"` : '';
      const img = `<img src="${src}" alt=""${cls}>`;
      return o.link && o.size !== 'original' ? `<a href="${m.ref}">${img}</a>` : img;
    }
    if (m.kind === 'video') {
      return `<video src="${m.ref}" controls preload="metadata" playsinline></video>`;
    }
    return `<a href="${m.ref}">${esc(m.name)}</a>`;
  }

  /* ---------- 本文エディタ ---------- */

  function initEditor(root) {
    const ta = root.querySelector('textarea');
    const bar = root.querySelector('[data-quicktags]');
    const preview = root.querySelector('[data-editor-preview]');
    const openTags = [];
    const buttons = new Map();

    /** 選択範囲を text に置き換える（可能なら元に戻す操作に対応する方法で） */
    function insertText(text, cursorOffset = null) {
      const s = ta.selectionStart;
      const e = ta.selectionEnd;
      ta.focus();
      ta.setSelectionRange(s, e);
      let ok = false;
      try { ok = document.execCommand('insertText', false, text); } catch (_) { ok = false; }
      if (!ok) {
        ta.setRangeText(text, s, e, 'end');
        ta.dispatchEvent(new Event('input', { bubbles: true }));
      }
      if (cursorOffset !== null) {
        const p = s + cursorOffset;
        ta.setSelectionRange(p, p);
      }
    }

    /** 前後に改行を補って、独立した行として挿入 */
    function insertBlock(text) {
      const s = ta.selectionStart;
      const before = ta.value.slice(0, s);
      const after = ta.value.slice(ta.selectionEnd);
      const pre = before === '' || before.endsWith('\n') ? '' : '\n';
      const post = after.startsWith('\n') ? '' : '\n';
      insertText(pre + text + post);
    }

    function refresh() {
      buttons.forEach((btn, t) => {
        const isOpen = openTags.includes(t);
        btn.classList.toggle('is-open', isOpen);
        btn.textContent = isOpen ? `/${t.label}` : t.label;
      });
    }

    function closeAll() {
      const closing = openTags.slice().reverse().map((t) => t.close).join('');
      if (closing) insertText(closing);
      openTags.length = 0;
      refresh();
    }

    function insertLink() {
      const url = window.prompt('リンク先の URL を入力してください', 'https://');
      if (!url || url === 'https://') return;
      const sel = ta.value.slice(ta.selectionStart, ta.selectionEnd);
      insertText(`<a href="${esc(url.trim())}">${sel || esc(url.trim())}</a>`);
    }

    function insertImage() {
      const src = window.prompt('画像の URL を入力してください', 'https://');
      if (!src || src === 'https://') return;
      const alt = window.prompt('画像の説明（代替テキスト）を入力してください', '') ?? '';
      insertText(`<img src="${esc(src.trim())}" alt="${esc(alt)}">`);
    }

    function insertYoutube() {
      const u = (window.prompt('YouTube 動画の URL を入力してください\n例: https://www.youtube.com/watch?v=xxxxxxxxxxx', '') || '').trim();
      if (!u) return;
      if (!/^https?:\/\/(www\.|m\.)?(youtube\.com\/(watch\?|shorts\/|live\/)|youtu\.be\/)/i.test(u)) {
        window.alert('YouTube の動画 URL を入力してください。');
        return;
      }
      insertBlock(u);
    }

    const TAGS = [
      { label: 'b', title: '太字', open: '<strong>', close: '</strong>' },
      { label: 'i', title: '斜体', open: '<em>', close: '</em>' },
      { label: 'link', title: 'リンク', run: insertLink },
      { label: 'h2', title: '見出し（大）', open: '<h2>', close: '</h2>' },
      { label: 'h3', title: '見出し（中）', open: '<h3>', close: '</h3>' },
      { label: 'b-quote', title: '引用', open: '<blockquote>', close: '</blockquote>' },
      { label: 'del', title: '取り消し線', open: '<del>', close: '</del>' },
      { label: 'ul', title: '箇条書き', open: '<ul>\n', close: '\n</ul>' },
      { label: 'ol', title: '番号付きリスト', open: '<ol>\n', close: '\n</ol>' },
      { label: 'li', title: 'リストの項目', open: '\t<li>', close: '</li>' },
      { label: 'code', title: 'コード', open: '<code>', close: '</code>' },
      { label: 'pre', title: '整形済みテキスト（コードブロック）', open: '<pre><code>', close: '</code></pre>' },
      { label: 'hr', title: '区切り線', run: () => insertBlock('<hr>') },
      { label: 'img', title: '画像（URL を指定）', run: insertImage },
      { label: 'YouTube', title: 'YouTube 動画を埋め込む', run: insertYoutube, wide: true },
      { label: 'タグを閉じる', title: '開いているタグをすべて閉じる', run: closeAll, wide: true },
    ];

    TAGS.forEach((t) => {
      const btn = h('button', { type: 'button', class: `qt-btn${t.wide ? ' qt-btn--wide' : ''}`, title: t.title, text: t.label });
      btn.addEventListener('click', () => {
        if (t.run) {
          t.run();
          return;
        }
        if (ta.selectionStart !== ta.selectionEnd) {
          const sel = ta.value.slice(ta.selectionStart, ta.selectionEnd);
          insertText(t.open + sel + t.close);
          return;
        }
        const idx = openTags.indexOf(t);
        if (idx === -1) {
          insertText(t.open);
          openTags.push(t);
        } else {
          insertText(t.close);
          openTags.splice(idx, 1);
        }
        refresh();
      });
      if (!t.run) buttons.set(t, btn);
      bar.append(btn);
    });

    // メディアを追加
    root.querySelector('[data-media-open="insert"]')?.addEventListener('click', () => {
      MediaPicker.open({ mode: 'insert', onPick: (m, o) => insertBlock(mediaSnippet(m, o)) });
    });

    // ギャラリー（WordPress と同じ [gallery ids="..."] 形式で挿入）
    root.querySelector('[data-media-open="gallery"]')?.addEventListener('click', () => {
      MediaPicker.open({
        mode: 'gallery',
        onPick: (ids, o) => insertBlock(`[gallery ids="${ids.join(',')}"${o.columns ? ` columns="${o.columns}"` : ''}]`),
      });
    });

    // コード / プレビュー切り替え
    const tabs = root.querySelectorAll('[data-editor-tab]');
    tabs.forEach((tab) => tab.addEventListener('click', async () => {
      const isPreview = tab.dataset.editorTab === 'preview';
      tabs.forEach((t) => {
        const on = t === tab;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', String(on));
      });
      root.classList.toggle('is-preview', isPreview);
      ta.hidden = isPreview;
      preview.hidden = !isPreview;
      if (!isPreview) {
        ta.focus();
        return;
      }
      preview.replaceChildren(h('p', { class: 'muted', text: '読み込み中…' }));
      try {
        const fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('content', ta.value);
        const data = await request(urls.preview, { method: 'POST', body: fd });
        if (data.html) {
          preview.innerHTML = data.html; // サーバー側でサニタイズ済み
        } else {
          preview.replaceChildren(h('p', { class: 'muted', text: '（本文がありません）' }));
        }
      } catch (err) {
        preview.replaceChildren(h('p', { class: 'muted', text: err.message }));
      }
    }));
  }

  document.querySelectorAll('[data-editor]').forEach(initEditor);

  /* ---------- アイキャッチ画像 ---------- */

  const eye = document.querySelector('[data-eyecatch]');
  if (eye) {
    const input = eye.querySelector('[data-eyecatch-input]');
    const prev = eye.querySelector('[data-eyecatch-preview]');
    const clearBtn = eye.querySelector('[data-eyecatch-clear]');
    eye.querySelector('[data-media-open="eyecatch"]')?.addEventListener('click', () => {
      MediaPicker.open({
        mode: 'eyecatch',
        onPick: (m) => {
          input.value = String(m.id);
          prev.replaceChildren(h('img', { src: m.thumb, alt: '' }));
          clearBtn.hidden = false;
          markDirty();
        },
      });
    });
    clearBtn?.addEventListener('click', () => {
      input.value = '';
      prev.replaceChildren();
      clearBtn.hidden = true;
      markDirty();
    });
  }

  /* ---------- メディア一覧ページのアップロード ---------- */

  const uploader = document.querySelector('[data-uploader]');
  if (uploader) {
    const zone = uploader.querySelector('[data-dropzone]');
    const input = uploader.querySelector('[data-file-input]');
    const status = uploader.querySelector('[data-upload-status]');
    const run = async (files) => {
      const ok = await uploadFiles(files, status);
      if (ok === files.length) {
        window.location.reload();
      } else if (ok > 0) {
        status.prepend(h('div', { class: 'upload-status__item' },
          h('button', { type: 'button', class: 'btn btn--sm', text: '一覧を更新', onclick: () => window.location.reload() })));
      }
    };
    input?.addEventListener('change', () => {
      const files = [...input.files];
      input.value = '';
      if (files.length) run(files);
    });
    bindDropzone(zone, run);
  }

  /* ---------- ユーザー追加フォーム ---------- */

  const userForm = document.querySelector('[data-user-form]');
  if (userForm) {
    const sync = () => {
      const type = userForm.querySelector('input[name="auth_type"]:checked')?.value
        || userForm.querySelector('input[name="auth_type"]')?.value
        || 'local';
      userForm.querySelectorAll('[data-show-for]').forEach((el) => {
        el.hidden = el.dataset.showFor !== type;
      });
    };
    userForm.addEventListener('change', sync);
    sync();
  }
})();

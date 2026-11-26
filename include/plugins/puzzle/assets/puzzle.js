/* 15 パズル（スライドパズル） */
(() => {
  'use strict';
  const board = document.querySelector('[data-pz-board]');
  if (!board) return;

  const sizeSel = document.querySelector('[data-pz-size]');
  const newBtn = document.querySelector('[data-pz-new]');
  const movesEl = document.querySelector('[data-pz-moves]');
  const timeEl = document.querySelector('[data-pz-time]');
  const bestEl = document.querySelector('[data-pz-best]');
  const msgEl = document.querySelector('[data-pz-message]');

  let n = 4;
  let tiles = [];      // tiles[位置] = 数字（0 は空き）
  let moves = 0;
  let startedAt = 0;
  let timer = null;
  let solved = false;

  const fmt = (sec) => `${Math.floor(sec / 60)}:${String(sec % 60).padStart(2, '0')}`;
  const bestKey = () => `pz-best-${n}`;
  const elapsed = () => (startedAt ? Math.floor((Date.now() - startedAt) / 1000) : 0);

  function loadBest() {
    try { return JSON.parse(localStorage.getItem(bestKey()) || 'null'); } catch (_) { return null; }
  }
  function saveBest(rec) {
    try { localStorage.setItem(bestKey(), JSON.stringify(rec)); } catch (_) { /* 保存できない環境 */ }
  }
  function showBest() {
    const b = loadBest();
    bestEl.textContent = b ? `${b.moves} 手 / ${fmt(b.time)}` : '-';
  }

  /** 揃った状態から空きマスをランダムに動かして混ぜる（必ず解ける配置になる） */
  function shuffle() {
    tiles = Array.from({ length: n * n }, (_, i) => (i + 1) % (n * n));
    let empty = n * n - 1;
    let prev = -1;
    const steps = n * n * 40;
    for (let s = 0; s < steps; s += 1) {
      const options = neighbors(empty).filter((p) => p !== prev);
      const next = options[Math.floor(Math.random() * options.length)];
      [tiles[empty], tiles[next]] = [tiles[next], tiles[empty]];
      prev = empty;
      empty = next;
    }
    if (isSolved()) shuffle();
  }

  function neighbors(pos) {
    const r = Math.floor(pos / n);
    const c = pos % n;
    const list = [];
    if (r > 0) list.push(pos - n);
    if (r < n - 1) list.push(pos + n);
    if (c > 0) list.push(pos - 1);
    if (c < n - 1) list.push(pos + 1);
    return list;
  }

  const isSolved = () => tiles.every((v, i) => v === (i + 1) % (n * n));

  function render() {
    board.style.setProperty('--n', String(n));
    board.replaceChildren(...tiles.map((v, i) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pz-tile';
      btn.setAttribute('role', 'gridcell');
      if (v === 0) {
        btn.classList.add('is-empty');
        btn.tabIndex = -1;
        btn.setAttribute('aria-label', '空き');
      } else {
        btn.textContent = String(v);
        if (v === i + 1) btn.classList.add('is-correct');
        btn.addEventListener('click', () => move(i));
      }
      return btn;
    }));
    board.classList.toggle('is-solved', solved);
    movesEl.textContent = String(moves);
  }

  function move(pos) {
    if (solved) return;
    const empty = tiles.indexOf(0);
    if (!neighbors(empty).includes(pos)) return;
    [tiles[empty], tiles[pos]] = [tiles[pos], tiles[empty]];
    moves += 1;
    if (!startedAt) {
      startedAt = Date.now();
      timer = setInterval(() => { timeEl.textContent = fmt(elapsed()); }, 500);
    }
    if (isSolved()) finish();
    render();
  }

  function finish() {
    solved = true;
    clearInterval(timer);
    const time = elapsed();
    timeEl.textContent = fmt(time);
    const best = loadBest();
    const isBest = !best || moves < best.moves || (moves === best.moves && time < best.time);
    if (isBest) saveBest({ moves, time });
    showBest();
    msgEl.textContent = `完成！ ${moves} 手 / ${fmt(time)}${isBest ? '（自己ベスト更新）' : ''}`;
    msgEl.hidden = false;
  }

  function newGame() {
    n = Number(sizeSel.value) || 4;
    clearInterval(timer);
    moves = 0;
    startedAt = 0;
    solved = false;
    timeEl.textContent = fmt(0);
    msgEl.hidden = true;
    shuffle();
    showBest();
    render();
  }

  // 矢印キー：空きマスの隣のタイルを空きマスへ動かす
  document.addEventListener('keydown', (e) => {
    const tag = (e.target && e.target.tagName) || '';
    if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') return;
    const empty = tiles.indexOf(0);
    const map = { ArrowUp: empty + n, ArrowDown: empty - n, ArrowLeft: empty + 1, ArrowRight: empty - 1 };
    const target = map[e.key];
    if (target === undefined) return;
    if (neighbors(empty).includes(target)) {
      e.preventDefault();
      move(target);
    }
  });

  newBtn.addEventListener('click', newGame);
  sizeSel.addEventListener('change', newGame);
  newGame();
})();

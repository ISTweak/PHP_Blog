/* 2048 */
(() => {
  'use strict';
  const root = document.querySelector('[data-g2]');
  if (!root) return;

  const SIZE = 4, GOAL = 2048;
  const q = (s) => root.querySelector(s);
  const boardEl = q('[data-g2-board]');
  const tilesEl = q('[data-g2-tiles]');
  const overEl = q('[data-g2-over]');
  const form = q('[data-g2-form]');

  // 方向：0 上 / 1 右 / 2 下 / 3 左
  const VECTORS = [{ x: 0, y: -1 }, { x: 1, y: 0 }, { x: 0, y: 1 }, { x: -1, y: 0 }];
  const KEYS = {
    ArrowUp: 0, ArrowRight: 1, ArrowDown: 2, ArrowLeft: 3,
    Numpad8: 0, Numpad6: 1, Numpad2: 2, Numpad4: 3,
  };

  let grid;            // grid[x][y] = タイル or null
  let score, maxTile;
  let over = false, won = false, keepPlaying = false, submitted = false;
  let startedAt = 0, endedAt = 0;

  /* ---------- タイルと盤面 ---------- */
  const tile = (x, y, value) => ({ x, y, value, prev: null, mergedFrom: null });
  const inside = (p) => p.x >= 0 && p.x < SIZE && p.y >= 0 && p.y < SIZE;
  const at = (p) => (inside(p) ? grid[p.x][p.y] : null);

  function emptyCells() {
    const list = [];
    for (let x = 0; x < SIZE; x++) for (let y = 0; y < SIZE; y++) if (!grid[x][y]) list.push({ x, y });
    return list;
  }

  function addRandomTile() {
    const cells = emptyCells();
    if (!cells.length) return;
    const c = cells[Math.floor(Math.random() * cells.length)];
    grid[c.x][c.y] = tile(c.x, c.y, Math.random() < 0.9 ? 2 : 4);
  }

  function canMove() {
    if (emptyCells().length) return true;
    for (let x = 0; x < SIZE; x++) {
      for (let y = 0; y < SIZE; y++) {
        const v = grid[x][y].value;
        if ((x < SIZE - 1 && grid[x + 1][y].value === v) || (y < SIZE - 1 && grid[x][y + 1].value === v)) return true;
      }
    }
    return false;
  }

  function move(dir) {
    if (over || submitted || (won && !keepPlaying)) return;
    const v = VECTORS[dir];
    const xs = [...Array(SIZE).keys()];
    const ys = [...Array(SIZE).keys()];
    if (v.x === 1) xs.reverse();
    if (v.y === 1) ys.reverse();

    for (let x = 0; x < SIZE; x++) {
      for (let y = 0; y < SIZE; y++) {
        const t = grid[x][y];
        if (t) { t.prev = { x: t.x, y: t.y }; t.mergedFrom = null; }
      }
    }

    let moved = false;
    for (const x of xs) {
      for (const y of ys) {
        const t = grid[x][y];
        if (!t) continue;
        // 進める一番遠いマスと、その先のタイル
        let far = { x, y };
        let next = { x: x + v.x, y: y + v.y };
        while (inside(next) && !at(next)) {
          far = next;
          next = { x: next.x + v.x, y: next.y + v.y };
        }
        const other = at(next);
        if (other && other.value === t.value && !other.mergedFrom) {
          const merged = tile(next.x, next.y, t.value * 2);
          merged.mergedFrom = [t, other];
          grid[x][y] = null;
          grid[next.x][next.y] = merged;
          t.x = next.x; t.y = next.y;
          score += merged.value;
          maxTile = Math.max(maxTile, merged.value);
          moved = true;
        } else if (far.x !== x || far.y !== y) {
          grid[x][y] = null;
          grid[far.x][far.y] = t;
          t.x = far.x; t.y = far.y;
          moved = true;
        }
      }
    }
    if (!moved) return;

    if (!startedAt) startedAt = Date.now();
    addRandomTile();
    render();

    if (!won && maxTile >= GOAL) {
      won = true;
      showOver('win');
    } else if (!canMove()) {
      over = true;
      showOver('over');
    }
  }

  function newGame() {
    grid = Array.from({ length: SIZE }, () => Array(SIZE).fill(null));
    score = 0;
    over = won = keepPlaying = submitted = false;
    startedAt = endedAt = 0;
    addRandomTile();
    addRandomTile();
    maxTile = Math.max(...grid.flat().filter(Boolean).map((t) => t.value));
    overEl.hidden = true;
    render();
  }

  /* ---------- 表示 ---------- */
  const posClass = (p) => `g2-p-${p.x}-${p.y}`;

  function tileEl(t) {
    const el = document.createElement('div');
    const digits = String(t.value).length;
    const base = ['g2-tile', `g2-tile--${t.value > GOAL ? 'super' : t.value}`, `g2-tile--d${Math.min(digits, 5)}`];
    const inner = document.createElement('div');
    inner.className = 'g2-tile__in';
    inner.textContent = t.value;
    el.appendChild(inner);

    if (t.prev) {
      // 元の位置に置いてから、次のフレームで新しい位置へ（CSS の transition で動く）
      el.className = [...base, posClass(t.prev)].join(' ');
      requestAnimationFrame(() => requestAnimationFrame(() => { el.className = [...base, posClass(t)].join(' '); }));
    } else if (t.mergedFrom) {
      el.className = [...base, posClass(t), 'is-merged'].join(' ');
      t.mergedFrom.forEach((m) => tilesEl.appendChild(tileEl(m)));
    } else {
      el.className = [...base, posClass(t), 'is-new'].join(' ');
    }
    return el;
  }

  function render() {
    tilesEl.replaceChildren();
    for (let x = 0; x < SIZE; x++) for (let y = 0; y < SIZE; y++) if (grid[x][y]) tilesEl.appendChild(tileEl(grid[x][y]));
    q('[data-g2-score]').textContent = score.toLocaleString();
    q('[data-g2-max]').textContent = maxTile.toLocaleString();
  }

  function showOver(kind) {
    endedAt = Date.now();
    q('[data-g2-over-title]').textContent = kind === 'win' ? `${GOAL} 達成！おめでとう！` : 'ゲームオーバー！';
    q('[data-g2-final]').textContent = score.toLocaleString();
    q('[data-g2-continue]').hidden = kind !== 'win';
    q('[data-g2-again]').hidden = kind === 'win';
    // アニメーションが終わってから表示
    setTimeout(() => {
      overEl.hidden = false;
      (kind === 'win' ? q('[data-g2-continue]') : q('[data-g2-submit]')).focus();
    }, 250);
  }

  /* ---------- 操作 ---------- */
  document.addEventListener('keydown', (e) => {
    if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
    const target = e.target instanceof Element ? e.target : document.body;
    if (target.closest('input, textarea, select, [contenteditable="true"]')) return;
    const dir = KEYS[e.code] ?? KEYS[e.key];
    if (dir !== undefined) {
      e.preventDefault();
      move(dir);
    } else if ((e.key === 'r' || e.key === 'R') && !target.closest('button, a')) {
      restart();
    }
  });

  let touch = null;
  boardEl.addEventListener('touchstart', (e) => {
    if (e.touches.length !== 1 || e.target.closest('.mg-over')) { touch = null; return; }
    touch = { x: e.touches[0].clientX, y: e.touches[0].clientY };
  }, { passive: true });
  boardEl.addEventListener('touchmove', (e) => {
    if (touch) e.preventDefault();   // スワイプ中に画面がスクロールしないように
  }, { passive: false });
  boardEl.addEventListener('touchend', (e) => {
    if (!touch) return;
    const dx = e.changedTouches[0].clientX - touch.x;
    const dy = e.changedTouches[0].clientY - touch.y;
    touch = null;
    if (Math.max(Math.abs(dx), Math.abs(dy)) < 20) return;
    move(Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 1 : 3) : (dy > 0 ? 2 : 0));
  });

  function restart() {
    if (startedAt && !over && !(won && !keepPlaying) && !window.confirm('最初からやり直しますか？')) return;
    newGame();
  }

  q('[data-g2-restart]').addEventListener('click', restart);
  q('[data-g2-again]').addEventListener('click', newGame);
  q('[data-g2-continue]').addEventListener('click', () => {
    keepPlaying = true;
    overEl.hidden = true;
    boardEl.focus();
  });
  q('[data-g2-submit]').addEventListener('click', () => {
    if (submitted) return;
    submitted = true;
    form.elements.score.value = score;
    form.elements.stage.value = maxTile;
    form.elements.gametime.value = startedAt ? Math.round(((endedAt || Date.now()) - startedAt) / 1000) : 0;
    form.submit();
  });

  boardEl.tabIndex = 0;
  newGame();
})();

/* さめがめ */
(() => {
  'use strict';
  const root = document.querySelector('[data-ws]');
  if (!root) return;

  const COLS = 20, ROWS = 10, KINDS = 5, EMPTY = -1, BONUS = 1000;
  const q = (s) => root.querySelector(s);
  const boardEl = q('[data-ws-board]');
  const overEl = q('[data-ws-over]');
  const msgEl = q('[data-ws-msg]');
  const form = q('[data-ws-form]');
  const countEls = root.querySelectorAll('[data-ws-count]');
  const imgBase = root.dataset.img;
  const imgA = Array.from({ length: KINDS }, (_, i) => `${imgBase}k${i + 1}a.gif`);
  const imgB = Array.from({ length: KINDS }, (_, i) => `${imgBase}k${i + 1}b.gif`);
  const imgNone = `${imgBase}none.gif`;

  let board;          // board[x][y]（y = 0 が一番上）
  let score, stage, rest;
  let over = false, clearing = false, submitted = false;
  let startedAt = 0;  // 最初にコマを消した時刻
  let endedAt = 0;
  let backup = null;  // 1 手前の状態
  let shown = null;   // ハイライト中のコマ（"x,y" の Set）
  let pointerType = 'mouse';

  /* ---------- 盤面のマス（img）を作る ---------- */
  const cells = Array.from({ length: COLS }, () => Array(ROWS));
  for (let y = 0; y < ROWS; y++) {
    for (let x = 0; x < COLS; x++) {
      const img = document.createElement('img');
      img.className = 'ws-cell';
      img.alt = '';
      img.width = 24;
      img.height = 24;
      img.dataset.x = x;
      img.dataset.y = y;
      img.draggable = false;
      cells[x][y] = img;
      boardEl.appendChild(img);
    }
  }

  /* ---------- ゲームの処理 ---------- */
  const randomBoard = () => Array.from({ length: COLS }, () =>
    Array.from({ length: ROWS }, () => Math.floor(Math.random() * KINDS)));
  const clone = (b) => b.map((col) => col.slice());
  const pointsFor = (n) => (n - 2) ** 2;

  /** (x, y) とつながっている同じコマ */
  function group(x, y) {
    const kind = board[x][y];
    if (kind === EMPTY) return [];
    const seen = new Set([`${x},${y}`]);
    const stack = [[x, y]];
    const result = [];
    while (stack.length) {
      const [cx, cy] = stack.pop();
      result.push([cx, cy]);
      for (const [dx, dy] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
        const nx = cx + dx, ny = cy + dy;
        const key = `${nx},${ny}`;
        if (nx < 0 || ny < 0 || nx >= COLS || ny >= ROWS || seen.has(key) || board[nx][ny] !== kind) continue;
        seen.add(key);
        stack.push([nx, ny]);
      }
    }
    return result;
  }

  function hintCell() {
    for (let x = 0; x < COLS; x++) {
      for (let y = 0; y < ROWS; y++) {
        const v = board[x][y];
        if (v === EMPTY) continue;
        if ((x < COLS - 1 && board[x + 1][y] === v) || (y < ROWS - 1 && board[x][y + 1] === v)) return [x, y];
      }
    }
    return null;
  }

  function remove(list) {
    backup = { board: clone(board), score, rest };
    for (const [x, y] of list) board[x][y] = EMPTY;
    // 下に詰める
    for (let x = 0; x < COLS; x++) {
      const col = board[x].filter((v) => v !== EMPTY);
      board[x] = Array(ROWS - col.length).fill(EMPTY).concat(col);
    }
    // 空いた列を左に詰める
    const cols = board.filter((col) => col.some((v) => v !== EMPTY));
    while (cols.length < COLS) cols.push(Array(ROWS).fill(EMPTY));
    board = cols;
    score += pointsFor(list.length);
    rest -= list.length;
  }

  function newGame() {
    board = randomBoard();
    score = 0;
    stage = 1;
    rest = COLS * ROWS;
    over = clearing = submitted = false;
    startedAt = 0;
    backup = null;
    shown = null;
    overEl.hidden = true;
    draw();
    say('さぁ、がんばっていこう！');
  }

  function nextStage() {
    stage++;
    board = randomBoard();
    rest = COLS * ROWS;
    backup = null;
    clearing = false;
    draw();
    say(`ステージ ${stage}！`);
  }

  /* ---------- 表示 ---------- */
  function draw(highlight = null) {
    shown = highlight;
    for (let x = 0; x < COLS; x++) {
      for (let y = 0; y < ROWS; y++) {
        const v = board[x][y];
        const src = v === EMPTY ? imgNone : (highlight && highlight.has(`${x},${y}`) ? imgB[v] : imgA[v]);
        if (cells[x][y].getAttribute('src') !== src) cells[x][y].src = src;
        cells[x][y].classList.toggle('is-empty', v === EMPTY);
      }
    }
    q('[data-ws-score]').textContent = score.toLocaleString();
    q('[data-ws-stage]').textContent = stage;
    q('[data-ws-rest]').textContent = rest;
    const counts = Array(KINDS).fill(0);
    board.forEach((col) => col.forEach((v) => { if (v !== EMPTY) counts[v]++; }));
    countEls.forEach((el, i) => { el.textContent = counts[i]; });
  }

  function say(text) {
    msgEl.textContent = text || ' ';
  }

  function highlight(x, y) {
    const list = group(x, y);
    if (list.length < 2) {
      draw();
      return list;
    }
    draw(new Set(list.map(([a, b]) => `${a},${b}`)));
    say(`そこなら ${list.length} コで ${pointsFor(list.length)} 点だよ`);
    return list;
  }

  /* ---------- 操作 ---------- */
  function play(x, y) {
    if (over || clearing) return;
    const list = group(x, y);
    if (list.length === 0) return;
    if (list.length === 1) {
      draw();
      say('1 コじゃ取れないよ');
      return;
    }
    if (!startedAt) startedAt = Date.now();
    remove(list);

    if (rest === 0) {
      score += BONUS;
      clearing = true;
      draw();
      say(`全クリア！ ボーナス ${BONUS} 点。次のステージもがんばれ！`);
      setTimeout(nextStage, 1500);
    } else if (!hintCell()) {
      over = true;
      endedAt = Date.now();
      draw();
      say('ゲームオーバー！');
      q('[data-ws-final]').textContent = score.toLocaleString();
      overEl.hidden = false;
      q('[data-ws-submit]').focus();
    } else {
      draw();
      say(`${list.length} コ消した！ +${pointsFor(list.length)} 点`);
    }
  }

  const cellOf = (e) => {
    const t = e.target.closest('.ws-cell');
    return t ? [Number(t.dataset.x), Number(t.dataset.y)] : null;
  };

  boardEl.addEventListener('pointerdown', (e) => { pointerType = e.pointerType || 'mouse'; });
  boardEl.addEventListener('pointerover', (e) => {
    if (e.pointerType !== 'mouse' || over || clearing) return;
    const c = cellOf(e);
    if (c) highlight(c[0], c[1]);
  });
  boardEl.addEventListener('pointerleave', (e) => {
    if (e.pointerType === 'mouse' && !over && !clearing) draw();
  });
  boardEl.addEventListener('click', (e) => {
    const c = cellOf(e);
    if (!c || over || clearing) return;
    // タッチ操作は 1 回目で消えるコマを表示し、同じところをもう一度タップしたら消す
    if (pointerType !== 'mouse' && !(shown && shown.has(`${c[0]},${c[1]}`))) {
      const list = highlight(c[0], c[1]);
      if (list.length === 1) say('1 コじゃ取れないよ');
      else if (list.length > 1) say(`${list.length} コで ${pointsFor(list.length)} 点。もう一度タップで消すよ`);
      return;
    }
    play(c[0], c[1]);
    // マウスなら、消したあとにカーソルの下にあるコマを表示し直す
    if (pointerType === 'mouse' && !over && !clearing) {
      const after = group(c[0], c[1]);
      if (after.length > 1) draw(new Set(after.map(([a, b]) => `${a},${b}`)));
    }
  });

  q('[data-ws-reset]').addEventListener('click', () => {
    if (startedAt && !over && !window.confirm('最初からやり直しますか？')) return;
    newGame();
  });
  q('[data-ws-undo]').addEventListener('click', () => {
    if (!backup || clearing || submitted) {
      say('これ以上もどせないよ');
      return;
    }
    board = backup.board;
    score = backup.score;
    rest = backup.rest;
    backup = null;
    over = false;
    overEl.hidden = true;
    draw();
    say('1 手もどしました');
  });
  q('[data-ws-hint]').addEventListener('click', () => {
    if (over || clearing) return;
    if (shown) {
      draw();
      say('がんばれ！');
      return;
    }
    const c = hintCell();
    if (c) {
      highlight(c[0], c[1]);
      say('ここが消せるよ！');
    }
  });
  q('[data-ws-again]').addEventListener('click', newGame);
  q('[data-ws-submit]').addEventListener('click', () => {
    if (submitted) return;
    submitted = true;
    form.elements.score.value = score;
    form.elements.stage.value = stage;
    form.elements.gametime.value = startedAt ? Math.round((endedAt - startedAt) / 1000) : 0;
    form.submit();
  });

  newGame();
})();

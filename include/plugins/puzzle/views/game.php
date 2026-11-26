<?php /** @var PluginApi $api */ ?>
<div class="layout">
  <div class="layout__main">
    <article class="entry pz">
      <header class="entry__header">
        <h1 class="entry__title">15 パズル</h1>
        <p class="muted">数字のタイルを空いているマスに動かして、1 から順に並べてください。タイルをクリック（タップ）するか、矢印キーで動かせます。</p>
      </header>

      <div class="pz-controls">
        <label class="pz-size">サイズ
          <select class="form-control" data-pz-size>
            <option value="3">3 × 3</option>
            <option value="4" selected>4 × 4</option>
            <option value="5">5 × 5</option>
          </select>
        </label>
        <button class="btn btn--primary" type="button" data-pz-new>シャッフル</button>
      </div>

      <div class="pz-stats" aria-live="polite">
        <span>手数 <strong data-pz-moves>0</strong></span>
        <span>時間 <strong data-pz-time>0:00</strong></span>
        <span>自己ベスト <strong data-pz-best>-</strong></span>
      </div>

      <div class="pz-board" data-pz-board role="grid" aria-label="パズル盤面"></div>
      <p class="pz-message" data-pz-message hidden></p>

      <noscript><p class="alert alert--error">このゲームを遊ぶには JavaScript を有効にしてください。</p></noscript>
    </article>
  </div>
  <?= render('front/_sidebar') ?>
</div>

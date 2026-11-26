<?php
/**
 * @var PluginApi $api
 * @var MiniGame $g
 */
?>
<div class="layout">
  <div class="layout__main">
    <?= render('games/_tabs', ['g' => $g, 'current' => 'game']) ?>

    <div class="pz">
      <div class="pz-controls">
        <label class="pz-size">サイズ
          <select class="form-control" data-pz-size>
            <?php foreach ($g->cfg['variants'] as $n => $label): ?>
              <option value="<?= e((string) $n) ?>"<?= (string) $n === $g->cfg['defaultVariant'] ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn btn--primary" type="button" data-pz-new>シャッフル</button>
      </div>

      <div class="mg-stats" aria-live="polite">
        <span>手数 <strong data-pz-moves>0</strong></span>
        <span>時間 <strong data-pz-time>0:00</strong></span>
        <span>自己ベスト <strong data-pz-best>-</strong></span>
      </div>

      <div class="pz-board" data-pz-board role="grid" aria-label="パズル盤面"></div>
      <div class="pz-message" data-pz-message hidden>
        <p data-pz-message-text></p>
        <div class="mg-over__buttons">
          <button class="btn btn--primary" type="button" data-pz-submit>結果を見る</button>
          <button class="btn" type="button" data-pz-again>もう一度</button>
        </div>
      </div>

      <form method="post" action="<?= e($g->url('/end')) ?>" data-pz-form hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="score" value="">
        <input type="hidden" name="stage" value="">
        <input type="hidden" name="gametime" value="">
      </form>
      <noscript><p class="alert alert--error">このゲームを遊ぶには JavaScript を有効にしてください。</p></noscript>
    </div>

    <details class="mg-rules">
      <summary>あそびかた</summary>
      <dl>
        <dt>ルール</dt>
        <dd>数字のタイルを空いているマスに動かして、左上から 1, 2, 3 … の順に並べてください。</dd>
        <dt>操作</dt>
        <dd>動かしたいタイルをクリック（タップ）するか、矢印キーで空きマスの隣のタイルを動かします。</dd>
        <dt>ランキング</dt>
        <dd>
          サイズごとに、手数の少ない順に並びます（同じ手数なら時間の短い方が上）。<br>
          完成したときに今月の上位 <?= MiniGame::RANK ?> 位に入っていれば、コメントを付けて記録できます（ログインしていない人は名前も入れます）。
        </dd>
      </dl>
    </details>
  </div>
  <?= render('front/_sidebar') ?>
</div>

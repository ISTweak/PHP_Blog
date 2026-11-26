<?php
/**
 * @var PluginApi $api
 * @var Game2048Game $g
 */
?>
<div class="layout">
  <div class="layout__main">
    <?= $api->render('_tabs', ['g' => $g, 'current' => 'game']) ?>

    <div class="g2" data-g2>
      <div class="g2-top">
        <div class="mg-stats" aria-live="polite">
          <span>スコア <strong data-g2-score>0</strong></span>
          <span>最大タイル <strong data-g2-max>2</strong></span>
        </div>
        <button class="btn btn--sm" type="button" data-g2-restart>最初から</button>
      </div>

      <div class="g2-board" data-g2-board role="application" aria-label="2048 の盤面。矢印キーかスワイプで動かします">
        <?php for ($y = 0; $y < 4; $y++): for ($x = 0; $x < 4; $x++): ?>
          <div class="g2-cell g2-p-<?= $x ?>-<?= $y ?>"></div>
        <?php endfor; endfor; ?>
        <div class="g2-tiles" data-g2-tiles></div>

        <div class="mg-over" data-g2-over hidden>
          <p class="mg-over__title" data-g2-over-title>ゲームオーバー！</p>
          <p class="mg-over__score"><span data-g2-final>0</span> 点</p>
          <div class="mg-over__buttons">
            <button class="btn btn--primary" type="button" data-g2-submit>結果を見る</button>
            <button class="btn" type="button" data-g2-again>もう一度</button>
            <button class="btn" type="button" data-g2-continue hidden>続ける</button>
          </div>
        </div>
      </div>

      <form method="post" action="<?= e($g->url('/end')) ?>" data-g2-form hidden>
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
        <dd>
          4×4 のマスで、すべてのタイルを上下左右のどれかにスライドさせます。<br>
          同じ数字のタイルがぶつかると合体して、2 倍の数字のタイルになります。動かすたびに新しいタイル（2 か 4）が 1 枚出てきます。<br>
          「2048」のタイルを作るのが目標です。マスが埋まって動かせなくなったらゲームオーバーです（2048 を作ったあとも続けられます）。
        </dd>
        <dt>操作</dt>
        <dd>
          矢印キー（［↑］［↓］［←］［→］）か、テンキーの［8］［2］［4］［6］で動かします。<br>
          スマホでは盤面を上下左右にスワイプします。［R］キーで最初からやり直せます。
        </dd>
        <dt>得点</dt>
        <dd>合体してできたタイルの数字が、そのまま得点になります（4 を作ると 4 点、2048 を作ると 2048 点）。</dd>
        <dt>ランキング</dt>
        <dd>
          ゲームオーバーになったとき、今月の上位 <?= Game2048Game::RANK ?> 位に入っていれば、ログインしている人はコメントを付けて記録できます。<br>
          同じスコアのときは、先に記録した人が上になります。
        </dd>
      </dl>
    </details>
  </div>
  <?= render('front/_sidebar') ?>
</div>

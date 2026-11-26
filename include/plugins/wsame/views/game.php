<?php
/**
 * @var PluginApi $api
 * @var WsameGame $g
 */
?>
<div class="layout">
  <div class="layout__main">
    <?= $api->render('_tabs', ['g' => $g, 'current' => 'game']) ?>

    <div class="ws" data-ws data-img="<?= e($api->url('/plugin-assets/' . $api->id . '/img/')) ?>">
      <div class="mg-stats" aria-live="polite">
        <span>ステージ <strong data-ws-stage>1</strong></span>
        <span>スコア <strong data-ws-score>0</strong></span>
        <span>残り <strong data-ws-rest>200</strong></span>
      </div>
      <p class="ws-msg" data-ws-msg aria-live="polite">&nbsp;</p>

      <div class="ws-controls">
        <button class="btn btn--sm" type="button" data-ws-reset>リセット</button>
        <button class="btn btn--sm" type="button" data-ws-undo>もどす</button>
        <button class="btn btn--sm" type="button" data-ws-hint>ヒント</button>
      </div>

      <div class="ws-stage">
        <div class="ws-board" data-ws-board role="grid" aria-label="さめがめの盤面"></div>
        <div class="mg-over" data-ws-over hidden>
          <p class="mg-over__title">ゲームオーバー！</p>
          <p class="mg-over__score"><span data-ws-final>0</span> 点</p>
          <div class="mg-over__buttons">
            <button class="btn btn--primary" type="button" data-ws-submit>結果を見る</button>
            <button class="btn" type="button" data-ws-again>もう一度</button>
          </div>
        </div>
      </div>

      <ul class="ws-counts" aria-label="残りのコマ数">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <li><img src="<?= e($api->asset('img/k' . $i . 'a.gif')) ?>" alt="" width="20" height="20"><span data-ws-count><?= 0 ?></span></li>
        <?php endfor; ?>
      </ul>

      <form method="post" action="<?= e($g->url('/end')) ?>" data-ws-form hidden>
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
          同じ絵柄のコマが縦・横に 2 つ以上つながっているところをクリックすると、まとめて消せます。<br>
          消したところには上のコマが落ちてきて、空いた列は左に詰まります。<br>
          消せるところがなくなったらゲームオーバー。全部消すと次のステージに進めます。
        </dd>
        <dt>操作</dt>
        <dd>
          マウスを乗せると、消えるコマと得点が表示されます。<br>
          スマホでは、1 回目のタップで消えるコマを表示し、同じところをもう一度タップすると消します。<br>
          ［もどす］で 1 手だけ戻せます。［ヒント］で消せるところを 1 か所表示します。
        </dd>
        <dt>得点</dt>
        <dd>
          1 回に消した数を n として (n − 2)² 点（2 個は 0 点、3 個は 1 点、4 個は 4 点、10 個は 64 点…）。<br>
          全部消すとボーナス 1000 点。まとめて大きく消すほど高得点です。
        </dd>
        <dt>ランキング</dt>
        <dd>
          ゲームオーバーになったとき、今月の上位 <?= WsameGame::RANK ?> 位に入っていれば、ログインしている人はコメントを付けて記録できます。<br>
          同じスコアのときは、先に記録した人が上になります。
        </dd>
      </dl>
    </details>
  </div>
  <?= render('front/_sidebar') ?>
</div>

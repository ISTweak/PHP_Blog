<?php
/**
 * ゲーム終了後の結果
 * @var PluginApi $api
 * @var WsameGame $g
 * @var array $r score / stage / gametime
 * @var int $rank 今月の順位（記録した場合）
 * @var int $rankAll これまでの順位（記録した場合）
 * @var bool $rankIn
 * @var array|null $user
 * @var array $rows
 */
?>
<div class="layout">
  <div class="layout__main">
    <?= $api->render('_tabs', ['g' => $g, 'current' => 'result']) ?>

    <section class="mg-result" aria-label="今回の結果">
      <p class="mg-result__label">今回の結果</p>
      <p class="mg-result__score"><?= number_format($r['score']) ?><span>点</span></p>
      <p class="mg-result__meta">
        <span><?= e($g->cfg['stageLabel']) ?> <strong><?= number_format($r['stage']) ?></strong></span>
        <span>プレイ時間 <strong><?= e(WsameGame::time($r['gametime'])) ?></strong></span>
      </p>
    </section>

    <?php if (!$rankIn): ?>
      <p class="alert alert--info">今月のランキング（上位 <?= WsameGame::RANK ?> 位）には届きませんでした。またチャレンジしてください！</p>
    <?php elseif (!$user): ?>
      <p class="alert alert--info">
        今月の <?= $rank ?> 位に入るスコアです！
        ランキングに記録するには<a href="<?= e($api->url('/login', ['next' => $g->cfg['path'] . '/result'])) ?>">ログイン</a>してください。
      </p>
    <?php else: ?>
      <div class="alert alert--success">
        おめでとうございます！ 今月の <strong><?= $rank ?> 位</strong>（これまでの <?= $rankAll ?> 位）に入りました。
        ひとことコメントを付けて記録できます。
      </div>
      <form class="mg-save" method="post" action="<?= e($g->url('/save')) ?>">
        <?= csrf_field() ?>
        <label class="visually-hidden" for="mg-message">コメント</label>
        <input class="form-control" id="mg-message" type="text" name="message" maxlength="40" placeholder="ひとこと（40 文字まで・省略可）">
        <button class="btn btn--primary" type="submit">ランキングに記録する</button>
      </form>
      <p class="muted small">記録する前に他の人が上のスコアを出すと、ランキングから外れることがあります。</p>
    <?php endif; ?>

    <p class="mg-again">
      <a class="btn btn--primary" href="<?= e($g->url()) ?>">もう一度遊ぶ</a>
      <a class="btn" href="<?= e($g->url('/ranking')) ?>">ランキングを見る</a>
    </p>

    <h2 class="mg-subtitle">今月のランキング</h2>
    <?= $api->render('_table', ['g' => $g, 'rows' => $rows]) ?>
  </div>
  <?= render('front/_sidebar') ?>
</div>

<?php
/**
 * @var PluginApi $api
 * @var WsameGame $g
 * @var string $period
 * @var array $rows
 */
?>
<div class="layout">
  <div class="layout__main">
    <?= $api->render('_tabs', ['g' => $g, 'current' => 'ranking']) ?>

    <div class="mg-period">
      <a class="btn btn--sm<?= $period === 'month' ? ' btn--primary' : '' ?>" href="<?= e($g->url('/ranking')) ?>">今月（<?= (int) date('n') ?>月）</a>
      <a class="btn btn--sm<?= $period === 'all' ? ' btn--primary' : '' ?>" href="<?= e($g->url('/ranking', ['period' => 'all'])) ?>">これまで</a>
    </div>

    <?= $api->render('_table', ['g' => $g, 'rows' => $rows]) ?>

    <p class="muted small">上位 <?= WsameGame::RANK ?> 位まで表示しています。同じスコアのときは、先に記録した人が上になります。</p>
    <p><a class="btn btn--primary" href="<?= e($g->url()) ?>"><?= e($g->cfg['title']) ?>で遊ぶ</a></p>
  </div>
  <?= render('front/_sidebar') ?>
</div>

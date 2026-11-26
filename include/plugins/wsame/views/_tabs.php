<?php
/**
 * ゲームの見出しと「遊ぶ／ランキング」の切り替え
 * @var WsameGame $g
 * @var string $current game|ranking|result
 */
?>
<header class="page-heading mg-heading">
  <p class="page-heading__label">ミニゲーム</p>
  <h1 class="page-heading__title"><?= e($g->cfg['title']) ?></h1>
  <nav class="mg-tabs" aria-label="<?= e($g->cfg['title']) ?>">
    <a class="mg-tabs__item<?= $current === 'game' ? ' is-active' : '' ?>" href="<?= e($g->url()) ?>"<?= $current === 'game' ? ' aria-current="page"' : '' ?>>遊ぶ</a>
    <a class="mg-tabs__item<?= $current === 'ranking' ? ' is-active' : '' ?>" href="<?= e($g->url('/ranking')) ?>"<?= $current === 'ranking' ? ' aria-current="page"' : '' ?>>ランキング</a>
  </nav>
</header>

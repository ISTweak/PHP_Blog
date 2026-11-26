<?php
/**
 * ミニゲームの見出しと「遊ぶ／ランキング」の切り替え
 * @var MiniGame $g
 * @var string $current game|ranking|result
 */
$tab = static fn(string $name, string $href, string $label): string => sprintf(
    '<a class="mg-tabs__item%s" href="%s"%s>%s</a>',
    $current === $name ? ' is-active' : '', e($href), $current === $name ? ' aria-current="page"' : '', e($label)
);
?>
<header class="page-heading mg-heading">
  <p class="page-heading__label">ミニゲーム</p>
  <h1 class="page-heading__title"><?= e($g->cfg['title']) ?></h1>
  <nav class="mg-tabs" aria-label="<?= e($g->cfg['title']) ?>">
    <?= $tab('game', $g->url(), '遊ぶ') ?>
    <?= $tab('ranking', $g->url('/ranking'), 'ランキング') ?>
  </nav>
</header>

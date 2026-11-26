<?php
/**
 * @var MiniGame $g
 * @var string $period month|all
 * @var string|null $variant
 * @var array $rows
 */
$q = static fn(array $over): array => array_filter($over + ['v' => $variant, 'period' => $period === 'all' ? 'all' : null], static fn($v) => $v !== null);
?>
<div class="layout">
  <div class="layout__main">
    <?= render('games/_tabs', ['g' => $g, 'current' => 'ranking']) ?>

    <?php if ($g->cfg['variants']): ?>
      <div class="mg-period" aria-label="種類">
        <?php foreach ($g->cfg['variants'] as $v => $label): ?>
          <a class="btn btn--sm<?= (string) $v === $variant ? ' btn--primary' : '' ?>" href="<?= e($g->url('/ranking', $q(['v' => (string) $v]))) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="mg-period" aria-label="期間">
      <a class="btn btn--sm<?= $period === 'month' ? ' btn--primary' : '' ?>" href="<?= e($g->url('/ranking', $q(['period' => null]))) ?>">今月（<?= (int) date('n') ?>月）</a>
      <a class="btn btn--sm<?= $period === 'all' ? ' btn--primary' : '' ?>" href="<?= e($g->url('/ranking', $q(['period' => 'all']))) ?>">これまで</a>
    </div>

    <?= render('games/_table', ['g' => $g, 'rows' => $rows]) ?>

    <p class="muted small">
      上位 <?= MiniGame::RANK ?> 位まで表示しています。
      <?= $g->cfg['lowerIsBetter'] ? '同じ' . e($g->cfg['scoreLabel']) . 'のときは時間の短い方、それも同じなら先に記録した人が上になります。' : '同じスコアのときは、先に記録した人が上になります。' ?>
    </p>
    <p><a class="btn btn--primary" href="<?= e($g->url()) ?>"><?= e($g->cfg['title']) ?>で遊ぶ</a></p>
  </div>
  <?= render('front/_sidebar') ?>
</div>

<?php
/**
 * サイドバー：今月の上位 3 人
 * @var MiniGame $g
 * @var string|null $variant
 * @var array $rows
 */
?>
<?php if ($variant !== null): ?><p class="mg-w-variant">今月の <?= e($g->variantLabel($variant)) ?></p><?php endif; ?>
<?php if ($rows): ?>
  <ol class="mg-w-list">
    <?php foreach ($rows as $row): ?>
      <li>
        <span class="mg-w-list__name"><?= e($row['player']) ?></span>
        <span class="mg-w-list__score"><?= number_format((int) $row['score']) ?><?= $g->cfg['lowerIsBetter'] ? e($g->cfg['unit']) : '' ?></span>
      </li>
    <?php endforeach; ?>
  </ol>
<?php else: ?>
  <p class="widget__empty">今月の記録はまだありません。</p>
<?php endif; ?>
<p class="mg-w-links">
  <a class="btn btn--sm" href="<?= e($g->url()) ?>">遊ぶ</a>
  <a href="<?= e($g->url('/ranking')) ?>">ランキング</a>
</p>

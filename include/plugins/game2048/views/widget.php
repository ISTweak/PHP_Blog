<?php
/**
 * サイドバー：今月の上位 3 人
 * @var PluginApi $api
 * @var Game2048Game $g
 * @var array $rows
 */
?>
<?php if ($rows): ?>
  <ol class="mg-w-list">
    <?php foreach ($rows as $row): ?>
      <li>
        <span class="mg-w-list__name"><?= e($row['name']) ?></span>
        <span class="mg-w-list__score"><?= number_format((int) $row['score']) ?></span>
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

<?php
/**
 * @var array  $pager  paginate() の戻り値
 * @var string $path   ベースのパス
 * @var array  $query  引き継ぐクエリ
 */
if ($pager['pages'] <= 1) {
    return;
}
$query = $query ?? [];
$link = static fn(int $p): string => url($path, $p > 1 ? $query + ['page' => $p] : $query);
$cur = $pager['page'];
$last = $pager['pages'];
$from = max(1, $cur - 2);
$to = min($last, $cur + 2);
?>
<nav class="pagination" aria-label="ページ送り">
  <?php if ($cur > 1): ?>
    <a class="pagination__item" href="<?= e($link($cur - 1)) ?>" rel="prev" aria-label="前のページ">&lsaquo;</a>
  <?php endif; ?>
  <?php if ($from > 1): ?>
    <a class="pagination__item" href="<?= e($link(1)) ?>">1</a>
    <?php if ($from > 2): ?><span class="pagination__gap">…</span><?php endif; ?>
  <?php endif; ?>
  <?php for ($i = $from; $i <= $to; $i++): ?>
    <?php if ($i === $cur): ?>
      <span class="pagination__item is-current" aria-current="page"><?= $i ?></span>
    <?php else: ?>
      <a class="pagination__item" href="<?= e($link($i)) ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($to < $last): ?>
    <?php if ($to < $last - 1): ?><span class="pagination__gap">…</span><?php endif; ?>
    <a class="pagination__item" href="<?= e($link($last)) ?>"><?= $last ?></a>
  <?php endif; ?>
  <?php if ($cur < $last): ?>
    <a class="pagination__item" href="<?= e($link($cur + 1)) ?>" rel="next" aria-label="次のページ">&rsaquo;</a>
  <?php endif; ?>
</nav>

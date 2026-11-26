<?php
$sideTags = Repo::tagCloud(40);
$maxCnt = $sideTags ? max(array_map(static fn($t) => (int) $t['cnt'], $sideTags)) : 1;
?>
<?php if ($sideTags): ?>
<section class="widget">
  <h2 class="widget__title">タグ</h2>
  <ul class="tag-cloud">
    <?php foreach ($sideTags as $t):
      $level = $maxCnt > 1 ? (int) ceil(((int) $t['cnt'] / $maxCnt) * 3) : 1; ?>
      <li><a class="tag tag--lv<?= $level ?>" href="<?= e(tag_url($t['name'])) ?>">#<?= e($t['name']) ?><span class="tag__count"><?= (int) $t['cnt'] ?></span></a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

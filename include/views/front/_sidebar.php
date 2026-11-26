<?php
$sideCats = Repo::categories();
$sideTags = Repo::tagCloud(40);
$sideRecent = Repo::recentPosts(5);
$maxCnt = $sideTags ? max(array_map(static fn($t) => (int) $t['cnt'], $sideTags)) : 1;
?>
<aside class="sidebar" aria-label="サイドバー">
  <section class="widget">
    <h2 class="widget__title">最近の投稿</h2>
    <?php if ($sideRecent): ?>
      <ul class="widget-list">
        <?php foreach ($sideRecent as $r): ?>
          <li>
            <a class="widget-list__link" href="<?= e(post_url($r['id'])) ?>">
              <span class="widget-list__text"><?= e($r['title']) ?></span>
              <time class="widget-list__sub"><?= e(fmt_date($r['published_at'])) ?></time>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="widget__empty">まだ記事がありません。</p>
    <?php endif; ?>
  </section>

  <?php if ($sideCats): ?>
  <section class="widget">
    <h2 class="widget__title">カテゴリー</h2>
    <ul class="widget-list">
      <?php foreach ($sideCats as $c): ?>
        <li>
          <a class="widget-list__link widget-list__link--row" href="<?= e(category_url($c['slug'])) ?>">
            <span class="widget-list__text"><?= e($c['name']) ?></span>
            <span class="count"><?= (int) $c['post_count'] ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

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

  <?php foreach (Plugins::widgets() as $w): ?>
  <section class="widget widget--plugin widget--<?= e($w['plugin']) ?>">
    <h2 class="widget__title"><?= e($w['title']) ?></h2>
    <?= $w['html'] ?>
  </section>
  <?php endforeach; ?>
</aside>

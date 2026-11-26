<?php $sideRecent = Repo::recentPosts(5); ?>
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

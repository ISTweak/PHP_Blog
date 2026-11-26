<?php
/**
 * @var array $post
 * @var array $tags
 * @var bool  $showSticky
 */
$sticky = $showSticky && (int) $post['is_sticky'] === 1;
?>
<article class="post-card<?= $sticky ? ' post-card--sticky' : '' ?>">
  <a class="post-card__thumb" href="<?= e(post_url($post['id'])) ?>" tabindex="-1" aria-hidden="true">
    <?php if ($post['eyecatch_media_id']): ?>
      <img src="<?= e(media_url($post['eyecatch_media_id'], 'thumb')) ?>" alt="" loading="lazy">
    <?php else: ?>
      <span class="post-card__noimage"><?= e(mb_substr((string) $post['title'], 0, 1)) ?></span>
    <?php endif; ?>
  </a>
  <div class="post-card__body">
    <div class="post-meta">
      <?php if ($sticky): ?><span class="badge badge--accent">固定</span><?php endif; ?>
      <?php if ($post['category_slug']): ?>
        <a class="badge badge--primary" href="<?= e(category_url($post['category_slug'])) ?>"><?= e($post['category_name']) ?></a>
      <?php endif; ?>
      <time class="post-meta__date" datetime="<?= e(fmt_date($post['published_at'], 'c')) ?>"><?= e(fmt_date($post['published_at'])) ?></time>
    </div>
    <h2 class="post-card__title"><a href="<?= e(post_url($post['id'])) ?>"><?= e($post['title']) ?></a></h2>
    <p class="post-card__excerpt"><?= e(Html::excerpt((string) $post['content'])) ?></p>
    <div class="post-card__foot">
      <?php if ($tags): ?>
        <ul class="tag-list">
          <?php foreach ($tags as $t): ?>
            <li><a class="tag" href="<?= e(tag_url($t['name'])) ?>">#<?= e($t['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ((int) $post['comment_count'] > 0): ?>
        <span class="post-card__comments">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.6A8 8 0 1 1 21 12Z"/></svg>
          <span class="visually-hidden">コメント</span><?= (int) $post['comment_count'] ?>
        </span>
      <?php endif; ?>
    </div>
  </div>
</article>

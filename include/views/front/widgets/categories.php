<?php $sideCats = Repo::categories(); ?>
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

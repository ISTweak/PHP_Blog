<?php
/**
 * @var array $posts
 * @var array $tagMap
 * @var array $pager
 * @var string $path
 * @var array $query
 * @var bool $isHome
 */
?>
<div class="layout">
  <div class="layout__main">
    <?php if (!empty($heading)): ?>
      <header class="page-heading">
        <?php if (!empty($label)): ?><p class="page-heading__label"><?= e($label) ?></p><?php endif; ?>
        <h1 class="page-heading__title"><?= e($heading) ?></h1>
        <?php if (!empty($description)): ?><p class="page-heading__desc"><?= e($description) ?></p><?php endif; ?>
        <?php if (isset($search)): ?>
          <form class="search-box" action="<?= e(url('/search')) ?>" method="get" role="search">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="キーワード（スペース区切りで AND 検索）" aria-label="キーワード">
            <button class="btn btn--primary" type="submit">検索</button>
          </form>
          <?php if ($search !== ''): ?><p class="page-heading__count"><?= (int) $pager['total'] ?> 件</p><?php endif; ?>
        <?php endif; ?>
      </header>
    <?php endif; ?>

    <?php if (!$posts): ?>
      <p class="empty-state"><?= e($emptyMessage ?? 'まだ記事がありません。') ?></p>
    <?php else: ?>
      <div class="post-list">
        <?php foreach ($posts as $post): ?>
          <?= render('front/_card', ['post' => $post, 'tags' => $tagMap[(int) $post['id']] ?? [], 'showSticky' => $isHome && $pager['page'] === 1]) ?>
        <?php endforeach; ?>
      </div>
      <?= render('partials/pagination', ['pager' => $pager, 'path' => $path, 'query' => $query]) ?>
    <?php endif; ?>
  </div>
  <?= render('front/_sidebar') ?>
</div>

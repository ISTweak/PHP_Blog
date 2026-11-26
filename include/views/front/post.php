<?php
/**
 * @var array $post
 * @var string $html
 * @var array $tags
 * @var array $comments
 * @var array|null $prev
 * @var array|null $next
 */
$user = current_user();
?>
<div class="layout">
  <div class="layout__main">
    <article class="entry">
      <header class="entry__header">
        <div class="post-meta">
          <?php if ($post['category_slug']): ?>
            <a class="badge badge--primary" href="<?= e(category_url($post['category_slug'])) ?>"><?= e($post['category_name']) ?></a>
          <?php endif; ?>
          <time class="post-meta__date" datetime="<?= e(fmt_date($post['published_at'], 'c')) ?>"><?= e(fmt_date($post['published_at'], 'Y.m.d H:i')) ?></time>
          <?php if ($post['updated_at'] !== $post['created_at']): ?>
            <span class="post-meta__updated">更新 <?= e(fmt_date($post['updated_at'])) ?></span>
          <?php endif; ?>
        </div>
        <h1 class="entry__title"><?= e($post['title']) ?></h1>
        <p class="entry__author"><?= e($post['author_name'] ?? '不明なユーザー') ?></p>
        <?php if ($user && Auth::canEditPost($post)): ?>
          <a class="btn btn--sm" href="<?= e(url('/admin/posts/' . (int) $post['id'] . '/edit')) ?>">この記事を編集</a>
        <?php endif; ?>
      </header>

      <?php if ($post['eyecatch_media_id']): ?>
        <figure class="entry__eyecatch">
          <img src="<?= e(media_url($post['eyecatch_media_id'], 'medium')) ?>" alt="">
        </figure>
      <?php endif; ?>

      <div class="entry-content">
        <?= $html ?>
      </div>

      <?php if ($tags): ?>
        <footer class="entry__footer">
          <ul class="tag-list">
            <?php foreach ($tags as $t): ?>
              <li><a class="tag" href="<?= e(tag_url($t['name'])) ?>">#<?= e($t['name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </footer>
      <?php endif; ?>
    </article>

    <?php if ($prev || $next): ?>
      <nav class="post-nav" aria-label="前後の記事">
        <?php if ($prev): ?>
          <a class="post-nav__item post-nav__item--prev" href="<?= e(post_url($prev['id'])) ?>">
            <span class="post-nav__label">&lsaquo; 前の記事</span>
            <span class="post-nav__title"><?= e($prev['title']) ?></span>
          </a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if ($next): ?>
          <a class="post-nav__item post-nav__item--next" href="<?= e(post_url($next['id'])) ?>">
            <span class="post-nav__label">次の記事 &rsaquo;</span>
            <span class="post-nav__title"><?= e($next['title']) ?></span>
          </a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

    <section class="comments" id="comments">
      <h2 class="comments__title">コメント<?php if ($comments): ?> <span class="count"><?= count($comments) ?></span><?php endif; ?></h2>

      <?php if ($comments): ?>
        <ol class="comment-list">
          <?php foreach ($comments as $c): ?>
            <li class="comment" id="comment-<?= (int) $c['id'] ?>">
              <div class="comment__head">
                <span class="comment__author"><?= e($c['author_name']) ?></span>
                <time class="comment__date"><?= e(fmt_date($c['created_at'], 'Y.m.d H:i')) ?></time>
              </div>
              <div class="comment__body"><?= nl2br(e($c['body']), false) ?></div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php elseif ((int) $post['allow_comments']): ?>
        <p class="comments__empty">まだコメントはありません。</p>
      <?php endif; ?>

      <?php if ((int) $post['allow_comments']): ?>
        <form class="comment-form" id="comment-form" action="<?= e(url('/post/' . (int) $post['id'] . '/comment')) ?>" method="post">
          <?= csrf_field() ?>
          <h3 class="comment-form__title">コメントを書く</h3>
          <?php if ($user): ?>
            <p class="comment-form__as"><?= e($user['display_name']) ?> としてコメントします。</p>
          <?php else: ?>
            <div class="form-row">
              <label class="form-label" for="author_name">お名前 <span class="req">必須</span></label>
              <input class="form-control" type="text" id="author_name" name="author_name" maxlength="50" required value="<?= e(old('author_name')) ?>">
            </div>
          <?php endif; ?>
          <div class="form-row">
            <label class="form-label" for="comment_body">コメント <span class="req">必須</span></label>
            <textarea class="form-control" id="comment_body" name="body" rows="5" maxlength="2000" required><?= e(old('body')) ?></textarea>
          </div>
          <div class="hp" aria-hidden="true">
            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
          </div>
          <?php if (!$user && App::setting('comment_moderation') === '1'): ?>
            <p class="form-help">コメントは管理者の承認後に表示されます。</p>
          <?php endif; ?>
          <button class="btn btn--primary" type="submit">コメントを送信</button>
        </form>
      <?php else: ?>
        <p class="comments__closed">この記事へのコメントは受け付けていません。</p>
      <?php endif; ?>
    </section>
  </div>
  <?= render('front/_sidebar') ?>
</div>

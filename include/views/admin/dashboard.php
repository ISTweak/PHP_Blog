<?php
/**
 * @var array $stats
 * @var array $recent
 * @var array $pendingComments
 * @var array $checks
 * @var array|null $backup
 */
?>
<div class="adm-head">
  <h1 class="adm-head__title">ダッシュボード</h1>
  <a class="btn btn--primary" href="<?= e(url('/admin/posts/new')) ?>">＋ 新規投稿</a>
</div>

<div class="stat-grid">
  <a class="stat" href="<?= e(url('/admin/posts')) ?>"><span class="stat__num"><?= $stats['posts'] ?></span><span class="stat__label">記事</span></a>
  <a class="stat" href="<?= e(url('/admin/posts', ['mine' => 1])) ?>"><span class="stat__num"><?= $stats['myPosts'] ?></span><span class="stat__label">自分の記事</span></a>
  <a class="stat" href="<?= e(url('/admin/media')) ?>"><span class="stat__num"><?= $stats['media'] ?></span><span class="stat__label">メディア</span></a>
  <?php if (Auth::atLeast('editor')): ?>
    <a class="stat<?= $stats['pending'] > 0 ? ' stat--alert' : '' ?>" href="<?= e(url('/admin/comments', ['status' => 'pending'])) ?>">
      <span class="stat__num"><?= $stats['pending'] ?></span><span class="stat__label">承認待ちコメント</span>
    </a>
  <?php endif; ?>
</div>

<div class="panel-grid">
  <section class="panel">
    <h2 class="panel__title">最近更新された記事</h2>
    <?php if ($recent): ?>
      <ul class="simple-list">
        <?php foreach ($recent as $p): ?>
          <li class="simple-list__item">
            <?php if (Auth::canEditPost($p)): ?>
              <a href="<?= e(url('/admin/posts/' . (int) $p['id'] . '/edit')) ?>"><?= e($p['title']) ?></a>
            <?php else: ?>
              <a href="<?= e(post_url($p['id'])) ?>"><?= e($p['title']) ?></a>
            <?php endif; ?>
            <span class="simple-list__sub"><?= e($p['author_name'] ?? '不明') ?> ・ <?= e(fmt_date($p['updated_at'], 'Y.m.d H:i')) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted">まだ記事がありません。</p>
    <?php endif; ?>
  </section>

  <?php if (Auth::atLeast('editor')): ?>
  <section class="panel">
    <h2 class="panel__title">承認待ちのコメント</h2>
    <?php if ($pendingComments): ?>
      <ul class="simple-list">
        <?php foreach ($pendingComments as $c): ?>
          <li class="simple-list__item">
            <span><?= e(mb_strimwidth((string) $c['body'], 0, 80, '…')) ?></span>
            <span class="simple-list__sub"><?= e($c['author_name']) ?> ・ <?= e($c['post_title']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <p><a class="btn btn--sm" href="<?= e(url('/admin/comments', ['status' => 'pending'])) ?>">コメント管理へ</a></p>
    <?php else: ?>
      <p class="muted">承認待ちのコメントはありません。</p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($checks): ?>
  <section class="panel">
    <h2 class="panel__title">動作環境チェック</h2>
    <ul class="check-list">
      <?php foreach ($checks as $c): ?>
        <li class="check-list__item <?= $c['ok'] ? 'is-ok' : 'is-ng' ?>">
          <span class="check-list__mark" aria-hidden="true"><?= $c['ok'] ? '✓' : '!' ?></span>
          <span class="check-list__label"><?= e($c['label']) ?></span>
          <span class="check-list__detail"><?= e($c['detail']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="muted small">
      最新のバックアップ:
      <?= $backup ? e($backup['time']) . '（' . (int) $backup['count'] . ' 世代保持）' : 'まだありません（記事の保存時に作成されます）' ?>
    </p>
  </section>
  <?php endif; ?>
</div>

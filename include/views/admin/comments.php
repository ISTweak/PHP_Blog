<?php
/**
 * @var array $comments
 * @var array $pager
 * @var string $status
 * @var int $pending
 */
$tabs = ['' => 'すべて', 'pending' => '承認待ち', 'approved' => '承認済み'];
$keep = array_filter(['status' => $status, 'page' => $pager['page'] > 1 ? (string) $pager['page'] : '']);
?>
<div class="adm-head">
  <h1 class="adm-head__title">コメント</h1>
</div>

<nav class="tabs" aria-label="状態で絞り込み">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="tabs__item<?= $status === $k ? ' is-active' : '' ?>" href="<?= e(url('/admin/comments', $k !== '' ? ['status' => $k] : [])) ?>">
      <?= e($label) ?><?php if ($k === 'pending' && $pending > 0): ?> <span class="adm-nav__count"><?= $pending ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<?php if (!$comments): ?>
  <p class="empty-box">コメントはありません。</p>
<?php else: ?>
  <ul class="comment-admin-list">
    <?php foreach ($comments as $c): ?>
      <li class="comment-admin<?= $c['status'] === 'pending' ? ' is-pending' : '' ?>">
        <div class="comment-admin__head">
          <strong><?= e($c['author_name']) ?></strong>
          <?php if ($c['status'] === 'pending'): ?><span class="badge badge--warn">承認待ち</span><?php endif; ?>
          <?php if (!$c['user_id']): ?><span class="badge">ゲスト</span><?php endif; ?>
          <span class="muted small"><?= e(fmt_date($c['created_at'], 'Y.m.d H:i')) ?><?= $c['ip'] !== '' ? ' ・ ' . e($c['ip']) : '' ?></span>
        </div>
        <p class="comment-admin__post">記事: <a href="<?= e(post_url($c['post_id'])) ?>#comment-<?= (int) $c['id'] ?>" target="_blank" rel="noopener"><?= e($c['post_title']) ?></a></p>
        <div class="comment-admin__body"><?= nl2br(e($c['body']), false) ?></div>
        <div class="comment-admin__actions">
          <form class="inline-form" method="post" action="<?= e(url('/admin/comments/' . (int) $c['id'] . '/approve', $keep)) ?>">
            <?= csrf_field() ?>
            <?php if ($c['status'] === 'pending'): ?>
              <button class="btn btn--sm btn--primary" type="submit">承認</button>
            <?php else: ?>
              <button class="btn btn--sm" type="submit">非公開に戻す</button>
            <?php endif; ?>
          </form>
          <form class="inline-form" method="post" action="<?= e(url('/admin/comments/' . (int) $c['id'] . '/delete', $keep)) ?>" data-confirm="このコメントを削除します。よろしいですか？">
            <?= csrf_field() ?>
            <button class="btn btn--sm btn--danger" type="submit">削除</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <?= render('partials/pagination', ['pager' => $pager, 'path' => '/admin/comments', 'query' => $status !== '' ? ['status' => $status] : []]) ?>
<?php endif; ?>

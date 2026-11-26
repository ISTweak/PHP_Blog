<?php
/**
 * @var array $posts
 * @var array $pager
 * @var string $q
 * @var int $category
 * @var bool $mine
 * @var array $categories
 * @var array $query
 */
?>
<div class="adm-head">
  <h1 class="adm-head__title">投稿一覧 <span class="muted small">（<?= (int) $pager['total'] ?> 件）</span></h1>
  <a class="btn btn--primary" href="<?= e(url('/admin/posts/new')) ?>">＋ 新規投稿</a>
</div>

<form class="filter-bar" method="get" action="<?= e(url('/admin/posts')) ?>">
  <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="タイトルで検索">
  <select class="form-control" name="category" aria-label="カテゴリー">
    <option value="">すべてのカテゴリー</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= (int) $c['id'] ?>"<?= $category === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if (Auth::atLeast('editor')): ?>
    <label class="check"><input type="checkbox" name="mine" value="1"<?= $mine ? ' checked' : '' ?>> 自分の記事のみ</label>
  <?php endif; ?>
  <button class="btn" type="submit">絞り込み</button>
</form>

<?php if (!$posts): ?>
  <p class="empty-box">記事が見つかりません。</p>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>タイトル</th><th>カテゴリー</th><th>投稿者</th><th class="nowrap">コメント</th><th>日付</th><th class="actions-col"><span class="visually-hidden">操作</span></th></tr>
      </thead>
      <tbody>
        <?php foreach ($posts as $p): $canEdit = Auth::canEditPost($p); ?>
          <tr>
            <td class="table__title">
              <?php if ((int) $p['is_sticky']): ?><span class="badge badge--accent">固定</span><?php endif; ?>
              <?php if ($canEdit): ?>
                <a href="<?= e(url('/admin/posts/' . (int) $p['id'] . '/edit')) ?>"><?= e($p['title']) ?></a>
              <?php else: ?>
                <?= e($p['title']) ?>
              <?php endif; ?>
            </td>
            <td data-label="カテゴリー"><?= e($p['category_name'] ?? '未分類') ?></td>
            <td data-label="投稿者"><?= e($p['author_name'] ?? '不明') ?></td>
            <td data-label="コメント"><?= (int) $p['comment_count'] ?></td>
            <td data-label="日付" class="nowrap"><?= e(fmt_date($p['published_at'], 'Y.m.d H:i')) ?></td>
            <td class="actions">
              <a class="btn btn--sm" href="<?= e(post_url($p['id'])) ?>" target="_blank" rel="noopener">表示</a>
              <?php if ($canEdit): ?>
                <a class="btn btn--sm" href="<?= e(url('/admin/posts/' . (int) $p['id'] . '/edit')) ?>">編集</a>
                <form class="inline-form" method="post" action="<?= e(url('/admin/posts/' . (int) $p['id'] . '/delete')) ?>"
                      data-confirm="「<?= e($p['title']) ?>」を削除します。元に戻せません。よろしいですか？">
                  <?= csrf_field() ?>
                  <button class="btn btn--sm btn--danger" type="submit">削除</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= render('partials/pagination', ['pager' => $pager, 'path' => '/admin/posts', 'query' => $query]) ?>
<?php endif; ?>

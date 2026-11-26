<?php
/**
 * @var array $tags
 * @var array $pager
 */
?>
<div class="adm-head">
  <h1 class="adm-head__title">タグ <span class="muted small">（<?= (int) $pager['total'] ?> 件）</span></h1>
  <form method="post" action="<?= e(url('/admin/tags/prune')) ?>" class="inline-form" data-confirm="どの記事にも使われていないタグをすべて削除します。よろしいですか？">
    <?= csrf_field() ?>
    <button class="btn" type="submit">未使用のタグを削除</button>
  </form>
</div>
<p class="muted small">タグは記事の編集画面で入力すると自動で作成されます。既存のタグと同じ名前に変更すると、2 つのタグが統合されます。</p>

<?php if (!$tags): ?>
  <p class="empty-box">タグはまだありません。</p>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>タグ名</th><th class="nowrap">記事数</th><th class="actions-col"><span class="visually-hidden">操作</span></th></tr></thead>
      <tbody>
        <?php foreach ($tags as $t): ?>
          <tr>
            <td>
              <form method="post" action="<?= e(url('/admin/tags/' . (int) $t['id'])) ?>" class="inline-edit">
                <?= csrf_field() ?>
                <input class="form-control form-control--sm" type="text" name="name" value="<?= e($t['name']) ?>" maxlength="50" required aria-label="タグ名">
                <button class="btn btn--sm" type="submit">名前を変更</button>
              </form>
            </td>
            <td data-label="記事数"><a href="<?= e(tag_url($t['name'])) ?>" target="_blank" rel="noopener"><?= (int) $t['cnt'] ?></a></td>
            <td class="actions">
              <form method="post" action="<?= e(url('/admin/tags/' . (int) $t['id'] . '/delete')) ?>" class="inline-form"
                    data-confirm="タグ「<?= e($t['name']) ?>」を削除します。よろしいですか？">
                <?= csrf_field() ?>
                <button class="btn btn--sm btn--danger" type="submit">削除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= render('partials/pagination', ['pager' => $pager, 'path' => '/admin/tags', 'query' => []]) ?>
<?php endif; ?>

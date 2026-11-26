<?php /** @var array $categories */ ?>
<div class="adm-head">
  <h1 class="adm-head__title">カテゴリー</h1>
</div>

<div class="split">
  <section class="panel split__side">
    <h2 class="panel__title">新しいカテゴリーを追加</h2>
    <form method="post" action="<?= e(url('/admin/categories')) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label class="form-label" for="cat-name">名前 <span class="req">必須</span></label>
        <input class="form-control" type="text" id="cat-name" name="name" maxlength="50" required value="<?= e(old('name')) ?>">
      </div>
      <div class="form-row">
        <label class="form-label" for="cat-slug">スラッグ <span class="req">必須</span></label>
        <input class="form-control" type="text" id="cat-slug" name="slug" maxlength="50" required pattern="[a-z0-9][a-z0-9\-]*"
               value="<?= e(old('slug')) ?>" placeholder="例: news">
        <p class="form-help">URL に使われます（半角英小文字・数字・ハイフン）</p>
      </div>
      <div class="form-row">
        <label class="form-label" for="cat-desc">説明</label>
        <textarea class="form-control" id="cat-desc" name="description" rows="3" maxlength="300"><?= e(old('description')) ?></textarea>
      </div>
      <div class="form-row">
        <label class="form-label" for="cat-order">表示順</label>
        <input class="form-control form-control--short" type="number" id="cat-order" name="sort_order" value="<?= e(old('sort_order', '0')) ?>">
        <p class="form-help">小さい数字ほど先に表示されます</p>
      </div>
      <button class="btn btn--primary" type="submit">追加</button>
    </form>
  </section>

  <section class="split__main">
    <?php if (!$categories): ?>
      <p class="empty-box">カテゴリーはまだありません。</p>
    <?php else: ?>
      <div class="term-list">
        <?php foreach ($categories as $c): ?>
          <details class="term">
            <summary class="term__summary">
              <span class="term__name"><?= e($c['name']) ?></span>
              <code class="term__slug"><?= e($c['slug']) ?></code>
              <span class="term__count"><?= (int) $c['post_count'] ?> 件</span>
              <span class="term__edit">編集</span>
            </summary>
            <div class="term__body">
              <form method="post" action="<?= e(url('/admin/categories/' . (int) $c['id'])) ?>" class="term__form">
                <?= csrf_field() ?>
                <div class="form-grid">
                  <div class="form-row">
                    <label class="form-label">名前</label>
                    <input class="form-control" type="text" name="name" maxlength="50" required value="<?= e($c['name']) ?>">
                  </div>
                  <div class="form-row">
                    <label class="form-label">スラッグ</label>
                    <input class="form-control" type="text" name="slug" maxlength="50" required pattern="[a-z0-9][a-z0-9\-]*" value="<?= e($c['slug']) ?>">
                  </div>
                  <div class="form-row form-grid__full">
                    <label class="form-label">説明</label>
                    <textarea class="form-control" name="description" rows="2" maxlength="300"><?= e($c['description']) ?></textarea>
                  </div>
                  <div class="form-row">
                    <label class="form-label">表示順</label>
                    <input class="form-control form-control--short" type="number" name="sort_order" value="<?= (int) $c['sort_order'] ?>">
                  </div>
                </div>
                <div class="term__actions">
                  <button class="btn btn--primary btn--sm" type="submit">保存</button>
                  <a class="btn btn--sm" href="<?= e(category_url($c['slug'])) ?>" target="_blank" rel="noopener">表示</a>
                  <button class="btn btn--danger btn--sm" type="submit" form="del-cat-<?= (int) $c['id'] ?>">削除</button>
                </div>
              </form>
              <form id="del-cat-<?= (int) $c['id'] ?>" method="post" action="<?= e(url('/admin/categories/' . (int) $c['id'] . '/delete')) ?>"
                    data-confirm="カテゴリー「<?= e($c['name']) ?>」を削除します。記事は「未分類」になります。よろしいですか？">
                <?= csrf_field() ?>
              </form>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

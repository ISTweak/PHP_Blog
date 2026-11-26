<?php
/**
 * @var array $post
 * @var string $tags
 * @var array $categories
 * @var array $allTags
 * @var array|null $eyecatch
 * @var array $errors
 */
$isNew = empty($post['id']);
$action = $isNew ? url('/admin/posts/new') : url('/admin/posts/' . (int) $post['id'] . '/edit');
?>
<div class="adm-head">
  <h1 class="adm-head__title"><?= $isNew ? '新規投稿' : '投稿を編集' ?></h1>
  <?php if (!$isNew): ?>
    <div class="adm-head__actions">
      <a class="btn" href="<?= e(post_url($post['id'])) ?>" target="_blank" rel="noopener">記事を表示 ↗</a>
      <a class="btn" href="<?= e(url('/admin/posts/new')) ?>">＋ 新規投稿</a>
    </div>
  <?php endif; ?>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error" role="alert">
    <ul class="alert__list"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form class="post-form" method="post" action="<?= e($action) ?>" data-post-form>
  <?= csrf_field() ?>
  <div class="post-form__main">
    <div class="form-row">
      <label class="visually-hidden" for="title">タイトル</label>
      <input class="form-control form-control--title" type="text" id="title" name="title" maxlength="200" required
             placeholder="タイトルを入力" value="<?= e($post['title']) ?>">
    </div>

    <div class="editor" data-editor>
      <div class="editor__bar">
        <button type="button" class="btn btn--sm" data-media-open="insert">
          <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm1 2v8.6l4-4 3 3 2-2 5 5V7H5Zm10.5 1a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z"/></svg>
          メディアを追加
        </button>
        <div class="editor__tabs" role="tablist">
          <button type="button" class="editor__tab is-active" role="tab" aria-selected="true" data-editor-tab="code">コード</button>
          <button type="button" class="editor__tab" role="tab" aria-selected="false" data-editor-tab="preview">プレビュー</button>
        </div>
      </div>
      <div class="qt-toolbar" data-quicktags aria-label="タグの挿入"></div>
      <label class="visually-hidden" for="content">本文</label>
      <textarea class="editor__textarea" id="content" name="content" rows="22" spellcheck="false"
                placeholder="本文を入力（HTML タグが使えます。空行で段落、改行はそのまま改行になります）"><?= e($post['content']) ?></textarea>
      <div class="editor__preview entry-content" data-editor-preview hidden></div>
    </div>
    <p class="form-help">
      空行で段落が分かれ、改行はそのまま改行になります。YouTube の URL を 1 行だけで書くと動画が埋め込まれます。
    </p>
  </div>

  <aside class="post-form__side">
    <section class="panel">
      <h2 class="panel__title">公開</h2>
      <?php if (!$isNew): ?>
        <p class="muted small">
          公開日時: <?= e(fmt_date($post['published_at'] ?? null, 'Y.m.d H:i')) ?><br>
          最終更新: <?= e(fmt_date($post['updated_at'] ?? null, 'Y.m.d H:i')) ?>
        </p>
      <?php endif; ?>
      <div class="panel__actions">
        <button class="btn btn--primary btn--block" type="submit"><?= $isNew ? '公開する' : '更新する' ?></button>
        <?php if (!$isNew): ?>
          <button class="btn btn--danger btn--block" type="submit" form="delete-post-form">この記事を削除</button>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel">
      <h2 class="panel__title"><label for="category_id">カテゴリー</label></h2>
      <select class="form-control" id="category_id" name="category_id">
        <option value="">未分類</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>"<?= (int) ($post['category_id'] ?? 0) === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (Auth::atLeast('editor')): ?>
        <p class="form-help"><a href="<?= e(url('/admin/categories')) ?>">カテゴリーを管理</a></p>
      <?php endif; ?>
    </section>

    <section class="panel">
      <h2 class="panel__title"><label for="tags">タグ</label></h2>
      <input class="form-control" type="text" id="tags" name="tags" value="<?= e($tags) ?>" list="tag-suggestions"
             placeholder="例: お知らせ, 社内イベント" data-tag-input>
      <datalist id="tag-suggestions">
        <?php foreach ($allTags as $t): ?><option value="<?= e($t['name']) ?>"><?php endforeach; ?>
      </datalist>
      <p class="form-help">カンマ（,）区切りで複数入力できます。</p>
    </section>

    <section class="panel">
      <h2 class="panel__title">アイキャッチ画像</h2>
      <div class="eyecatch" data-eyecatch>
        <input type="hidden" name="eyecatch_media_id" value="<?= $eyecatch ? (int) $eyecatch['id'] : '' ?>" data-eyecatch-input>
        <div class="eyecatch__preview" data-eyecatch-preview>
          <?php if ($eyecatch): ?>
            <img src="<?= e(media_url($eyecatch['id'], 'thumb')) ?>" alt="">
          <?php endif; ?>
        </div>
        <div class="eyecatch__buttons">
          <button type="button" class="btn btn--sm" data-media-open="eyecatch">画像を選択</button>
          <button type="button" class="btn btn--sm btn--danger" data-eyecatch-clear<?= $eyecatch ? '' : ' hidden' ?>>解除</button>
        </div>
      </div>
    </section>

    <section class="panel">
      <h2 class="panel__title">オプション</h2>
      <label class="check"><input type="checkbox" name="is_sticky" value="1"<?= (int) $post['is_sticky'] ? ' checked' : '' ?>> トップページの先頭に固定表示</label>
      <label class="check"><input type="checkbox" name="allow_comments" value="1"<?= (int) $post['allow_comments'] ? ' checked' : '' ?>> コメントを受け付ける</label>
    </section>
  </aside>
</form>

<?php if (!$isNew): ?>
  <form id="delete-post-form" method="post" action="<?= e(url('/admin/posts/' . (int) $post['id'] . '/delete')) ?>"
        data-confirm="この記事を削除します。コメントも削除され、元に戻せません。よろしいですか？">
    <?= csrf_field() ?>
  </form>
<?php endif; ?>

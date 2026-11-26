<?php /** @var array $settings */ ?>
<div class="adm-head">
  <h1 class="adm-head__title">サイト設定</h1>
</div>

<section class="panel panel--narrow">
  <form method="post" action="<?= e(url('/admin/settings')) ?>">
    <?= csrf_field() ?>
    <div class="form-row">
      <label class="form-label" for="site_name">サイト名</label>
      <input class="form-control" type="text" id="site_name" name="site_name" maxlength="500" required value="<?= e($settings['site_name']) ?>">
      <p class="form-help">
        ロゴ画像などの HTML タグが使えます（img・span・strong・b・em・i・small・br のみ）。<br>
        例: <code>&lt;img src="/assets/logo.png" alt="〇〇ブログ"&gt;</code>（public/assets/ に置いたファイル）や、
        メディアにアップロードした画像の <code>/media/番号</code>。<br>
        画像だけにする場合は、ブラウザのタブなどに表示されるので alt にサイト名を書いてください。
      </p>
    </div>
    <div class="form-row">
      <label class="form-label" for="site_description">サイトの説明</label>
      <input class="form-control" type="text" id="site_description" name="site_description" maxlength="200" value="<?= e($settings['site_description']) ?>">
      <p class="form-help">ヘッダーのサイト名の下と、検索エンジン向けの説明に使われます。</p>
    </div>
    <div class="form-row">
      <label class="form-label" for="posts_per_page">1 ページに表示する記事数</label>
      <input class="form-control form-control--short" type="number" id="posts_per_page" name="posts_per_page" min="1" max="50" value="<?= e($settings['posts_per_page']) ?>">
    </div>
    <div class="form-row">
      <label class="check">
        <input type="checkbox" name="comment_moderation" value="1"<?= $settings['comment_moderation'] === '1' ? ' checked' : '' ?>>
        ログインしていない人のコメントは承認してから表示する
      </label>
      <p class="form-help">ログイン中のユーザーのコメントは常にすぐ表示されます。</p>
    </div>
    <button class="btn btn--primary" type="submit">保存</button>
  </form>
</section>

<section class="panel panel--narrow">
  <h2 class="panel__title">サイトの種類</h2>
  <p>
    現在: <strong><?= App::isPrivate() ? '会員限定サイト（ログインした人のみ閲覧可）' : '一般公開サイト（誰でも閲覧可）' ?></strong>
  </p>
  <p class="muted small">変更する場合はサーバー上の include/config.php の <code>site_mode</code> を編集してください。</p>
</section>

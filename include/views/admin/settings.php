<?php
/**
 * @var array $settings
 * @var array $sidebar   Layout::sidebar()
 * @var array $navLinks  Plugins::navDefs()
 * @var array $hiddenNav Layout::hiddenNav()
 */
?>
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

<section class="panel panel--narrow" id="sidebar">
  <h2 class="panel__title">サイドバー</h2>
  <p class="muted small">↑↓ で表示する順番を、チェックで表示・非表示を変えられます。後から有効にしたプラグインの項目は一番下に追加されます。</p>
  <form method="post" action="<?= e(url('/admin/settings/sidebar')) ?>">
    <?= csrf_field() ?>
    <ol class="order-list" data-order-list>
      <?php foreach ($sidebar as $b): ?>
        <li class="order-list__item">
          <input type="hidden" name="order[]" value="<?= e($b['key']) ?>">
          <label class="check order-list__label">
            <input type="checkbox" name="visible[]" value="<?= e($b['key']) ?>"<?= $b['visible'] ? ' checked' : '' ?>>
            <span><?= e($b['title']) ?> <span class="muted small">（<?= e($b['source']) ?>）</span></span>
          </label>
          <span class="order-list__buttons">
            <button type="button" class="btn btn--sm" data-move="up" aria-label="「<?= e($b['title']) ?>」を上へ">↑</button>
            <button type="button" class="btn btn--sm" data-move="down" aria-label="「<?= e($b['title']) ?>」を下へ">↓</button>
          </span>
        </li>
      <?php endforeach; ?>
    </ol>
    <button class="btn btn--primary" type="submit">保存</button>
  </form>
</section>

<section class="panel panel--narrow" id="nav">
  <h2 class="panel__title">ヘッダーのメニュー</h2>
  <?php if (!$navLinks): ?>
    <p class="muted small">ヘッダーにリンクを追加している、有効なプラグインはありません。</p>
  <?php else: ?>
    <p class="muted small">プラグインが追加するヘッダーのリンクのうち、表示するものにチェックを付けてください。</p>
    <form method="post" action="<?= e(url('/admin/settings/nav')) ?>">
      <?= csrf_field() ?>
      <?php foreach ($navLinks as $l): ?>
        <label class="check">
          <input type="checkbox" name="shown[]" value="<?= e($l['key']) ?>"<?= in_array($l['key'], $hiddenNav, true) ? '' : ' checked' ?>>
          <span><?= e($l['label']) ?> <span class="muted small">（<?= e($l['pluginName']) ?> ・ <?= e($l['path']) ?>）</span></span>
        </label>
      <?php endforeach; ?>
      <button class="btn btn--primary nav-save" type="submit">保存</button>
    </form>
  <?php endif; ?>
</section>

<section class="panel panel--narrow">
  <h2 class="panel__title">サイトの種類</h2>
  <p>
    現在: <strong><?= App::isPrivate() ? '会員限定サイト（ログインした人のみ閲覧可）' : '一般公開サイト（誰でも閲覧可）' ?></strong>
  </p>
  <p class="muted small">変更する場合はサーバー上の include/config.php の <code>site_mode</code> を編集してください。</p>
</section>

<?php /** @var bool $keyReady */ ?>
<h1 class="auth-card__title">初期設定</h1>
<?php if (!$keyReady): ?>
  <div class="alert alert--error">
    include/config.php の <code>setup_key</code> にランダムな文字列を設定してから、このページを再読み込みしてください。
  </div>
<?php else: ?>
  <p class="auth-card__lead">最初の管理者アカウント（ローカルアカウント）を作成します。</p>
  <form action="<?= e(url('/setup')) ?>" method="post" class="auth-form">
    <?= csrf_field() ?>
    <div class="form-row">
      <label class="form-label" for="setup_key">セットアップキー</label>
      <input class="form-control" type="password" id="setup_key" name="setup_key" required autocomplete="off">
      <p class="form-help">config.php の setup_key に設定した値</p>
    </div>
    <div class="form-row">
      <label class="form-label" for="username">ユーザー名</label>
      <input class="form-control" type="text" id="username" name="username" required pattern="[A-Za-z0-9._\-]{3,32}"
             autocomplete="username" value="<?= e(old('username')) ?>">
      <p class="form-help">半角英数字と . _ - で 3〜32 文字</p>
    </div>
    <div class="form-row">
      <label class="form-label" for="display_name">表示名</label>
      <input class="form-control" type="text" id="display_name" name="display_name" maxlength="50" value="<?= e(old('display_name')) ?>">
    </div>
    <div class="form-row">
      <label class="form-label" for="password">パスワード</label>
      <input class="form-control" type="password" id="password" name="password" required minlength="10" autocomplete="new-password">
      <p class="form-help">10 文字以上</p>
    </div>
    <div class="form-row">
      <label class="form-label" for="password_confirm">パスワード（確認）</label>
      <input class="form-control" type="password" id="password_confirm" name="password_confirm" required minlength="10" autocomplete="new-password">
    </div>
    <button class="btn btn--primary btn--block" type="submit">管理者を作成</button>
  </form>
<?php endif; ?>

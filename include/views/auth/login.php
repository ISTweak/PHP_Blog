<?php /** @var string $next */ ?>
<h1 class="auth-card__title">ログイン</h1>
<?php if (App::ldapEnabled()): ?>
  <p class="auth-card__lead">Windows（AD）のユーザー名とパスワードでログインしてください。</p>
<?php else: ?>
  <p class="auth-card__lead">管理者アカウントでログインしてください。</p>
<?php endif; ?>
<form action="<?= e(url('/login')) ?>" method="post" class="auth-form">
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <div class="form-row">
    <label class="form-label" for="username">ユーザー名</label>
    <input class="form-control" type="text" id="username" name="username" required autofocus
           autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= e(old('username')) ?>">
  </div>
  <div class="form-row">
    <label class="form-label" for="password">パスワード</label>
    <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
  </div>
  <button class="btn btn--primary btn--block" type="submit">ログイン</button>
</form>

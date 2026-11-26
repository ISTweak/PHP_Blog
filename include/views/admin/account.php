<?php /** @var array $user */ ?>
<div class="adm-head">
  <h1 class="adm-head__title">アカウント</h1>
</div>

<section class="panel panel--narrow">
  <dl class="info-list">
    <dt>ユーザー名</dt><dd><?= e($user['username']) ?></dd>
    <dt>ロール</dt><dd><?= e(role_label($user['role'])) ?></dd>
    <dt>認証</dt><dd><?= $user['auth_type'] === 'ldap' ? 'AD（Windows アカウント）' : 'ローカルアカウント' ?></dd>
    <?php if ($user['email'] !== ''): ?><dt>メール</dt><dd><?= e($user['email']) ?></dd><?php endif; ?>
  </dl>

  <?php if ($user['auth_type'] === 'ldap'): ?>
    <p class="muted small">表示名とパスワードは AD（Windows）側の設定が使われます。</p>
  <?php else: ?>
    <form method="post" action="<?= e(url('/admin/account')) ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <label class="form-label" for="display_name">表示名</label>
        <input class="form-control" type="text" id="display_name" name="display_name" maxlength="50" value="<?= e($user['display_name']) ?>">
      </div>
      <div class="form-row">
        <label class="form-label" for="password">新しいパスワード</label>
        <input class="form-control" type="password" id="password" name="password" minlength="10" autocomplete="new-password" placeholder="変更する場合のみ入力">
      </div>
      <div class="form-row">
        <label class="form-label" for="password_confirm">新しいパスワード（確認）</label>
        <input class="form-control" type="password" id="password_confirm" name="password_confirm" minlength="10" autocomplete="new-password">
      </div>
      <div class="form-row">
        <label class="form-label" for="current_password">現在のパスワード <span class="req">必須</span></label>
        <input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password">
      </div>
      <button class="btn btn--primary" type="submit">保存</button>
    </form>
  <?php endif; ?>
</section>

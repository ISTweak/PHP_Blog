<?php
/** @var array $users */
$me = current_user();
$ldap = App::ldapEnabled();
$oldType = old('auth_type', $ldap ? 'ldap' : 'local');
?>
<div class="adm-head">
  <h1 class="adm-head__title">ユーザー</h1>
</div>

<div class="split">
  <section class="panel split__side">
    <h2 class="panel__title">ユーザーを追加</h2>
    <?php if ($ldap): ?>
      <p class="muted small">AD ユーザーは初回ログイン時に「投稿者」として自動で登録されます。あらかじめ編集者にしたい場合はここで登録してください。</p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/admin/users')) ?>" data-user-form>
      <?= csrf_field() ?>
      <?php if ($ldap): ?>
        <div class="form-row">
          <span class="form-label">種類</span>
          <label class="check"><input type="radio" name="auth_type" value="ldap"<?= $oldType === 'ldap' ? ' checked' : '' ?>> AD ユーザーを事前登録</label>
          <label class="check"><input type="radio" name="auth_type" value="local"<?= $oldType === 'local' ? ' checked' : '' ?>> 管理者（ローカルアカウント）</label>
        </div>
      <?php else: ?>
        <input type="hidden" name="auth_type" value="local">
        <p class="muted small">一般公開サイトでは、管理者（ローカルアカウント）のみ追加できます。</p>
      <?php endif; ?>
      <div class="form-row">
        <label class="form-label" for="u-name">ユーザー名 <span class="req">必須</span></label>
        <input class="form-control" type="text" id="u-name" name="username" required autocomplete="off" value="<?= e(old('username')) ?>">
        <p class="form-help" data-show-for="ldap">AD のログオン名（例: taro.yamada）</p>
        <p class="form-help" data-show-for="local">半角英数字と . _ - で 3〜32 文字</p>
      </div>
      <div class="form-row">
        <label class="form-label" for="u-display">表示名</label>
        <input class="form-control" type="text" id="u-display" name="display_name" maxlength="50" value="<?= e(old('display_name')) ?>">
      </div>
      <?php if ($ldap): ?>
        <div class="form-row" data-show-for="ldap">
          <label class="form-label" for="u-role">ロール</label>
          <select class="form-control" id="u-role" name="role">
            <option value="author"<?= old('role') !== 'editor' ? ' selected' : '' ?>>投稿者</option>
            <option value="editor"<?= old('role') === 'editor' ? ' selected' : '' ?>>編集者</option>
          </select>
        </div>
      <?php endif; ?>
      <div class="form-row" data-show-for="local">
        <label class="form-label" for="u-pass">パスワード <span class="req">必須</span></label>
        <input class="form-control" type="password" id="u-pass" name="password" minlength="10" autocomplete="new-password">
        <p class="form-help">10 文字以上</p>
      </div>
      <div class="form-row" data-show-for="local">
        <label class="form-label" for="u-pass2">パスワード（確認）</label>
        <input class="form-control" type="password" id="u-pass2" name="password_confirm" minlength="10" autocomplete="new-password">
      </div>
      <button class="btn btn--primary" type="submit">追加</button>
    </form>
  </section>

  <section class="split__main">
    <div class="term-list">
      <?php foreach ($users as $u): $isSelf = (int) $u['id'] === (int) $me['id']; ?>
        <details class="term<?= (int) $u['is_active'] ? '' : ' is-disabled' ?>">
          <summary class="term__summary">
            <span class="term__name"><?= e($u['display_name']) ?></span>
            <code class="term__slug"><?= e($u['username']) ?></code>
            <span class="badge badge--<?= e($u['role']) ?>"><?= e(role_label($u['role'])) ?></span>
            <span class="badge"><?= $u['auth_type'] === 'ldap' ? 'AD' : 'ローカル' ?></span>
            <?php if (!(int) $u['is_active']): ?><span class="badge badge--danger">無効</span><?php endif; ?>
            <?php if ($isSelf): ?><span class="badge badge--accent">あなた</span><?php endif; ?>
            <span class="term__count"><?= (int) $u['post_count'] ?> 記事</span>
            <span class="term__edit">編集</span>
          </summary>
          <div class="term__body">
            <p class="muted small">
              登録: <?= e(fmt_date($u['created_at'], 'Y.m.d H:i')) ?> ・
              最終ログイン: <?= $u['last_login_at'] ? e(fmt_date($u['last_login_at'], 'Y.m.d H:i')) : '未ログイン' ?>
              <?php if ($u['email'] !== ''): ?> ・ <?= e($u['email']) ?><?php endif; ?>
            </p>
            <form method="post" action="<?= e(url('/admin/users/' . (int) $u['id'])) ?>" class="term__form">
              <?= csrf_field() ?>
              <div class="form-grid">
                <div class="form-row">
                  <label class="form-label">表示名</label>
                  <input class="form-control" type="text" name="display_name" maxlength="50" value="<?= e($u['display_name']) ?>">
                  <?php if ($u['auth_type'] === 'ldap'): ?><p class="form-help">ログインのたびに AD の表示名で上書きされます</p><?php endif; ?>
                </div>
                <?php if ($u['auth_type'] === 'ldap'): ?>
                  <div class="form-row">
                    <label class="form-label">ロール</label>
                    <select class="form-control" name="role">
                      <option value="author"<?= $u['role'] === 'author' ? ' selected' : '' ?>>投稿者</option>
                      <option value="editor"<?= $u['role'] === 'editor' ? ' selected' : '' ?>>編集者</option>
                    </select>
                  </div>
                <?php else: ?>
                  <div class="form-row">
                    <label class="form-label">新しいパスワード</label>
                    <input class="form-control" type="password" name="password" minlength="10" autocomplete="new-password" placeholder="変更する場合のみ入力">
                  </div>
                <?php endif; ?>
                <?php if (!$isSelf): ?>
                  <div class="form-row form-grid__full">
                    <label class="check"><input type="checkbox" name="is_active" value="1"<?= (int) $u['is_active'] ? ' checked' : '' ?>> 有効（チェックを外すとログインできなくなります）</label>
                  </div>
                <?php endif; ?>
              </div>
              <div class="term__actions">
                <button class="btn btn--primary btn--sm" type="submit">保存</button>
                <?php if (!$isSelf): ?>
                  <button class="btn btn--danger btn--sm" type="submit" form="del-user-<?= (int) $u['id'] ?>">削除</button>
                <?php endif; ?>
              </div>
            </form>
            <?php if (!$isSelf): ?>
              <form id="del-user-<?= (int) $u['id'] ?>" method="post" action="<?= e(url('/admin/users/' . (int) $u['id'] . '/delete')) ?>"
                    data-confirm="ユーザー「<?= e($u['username']) ?>」を削除します。よろしいですか？">
                <?= csrf_field() ?>
              </form>
            <?php endif; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
  </section>
</div>

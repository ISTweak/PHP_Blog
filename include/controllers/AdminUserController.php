<?php
declare(strict_types=1);

/**
 * ユーザー管理（管理者のみ）
 *
 * - ローカルアカウント: 管理者専用
 * - AD アカウント: 初回ログインで投稿者として自動登録。事前登録してロールを決めておくことも可能
 */
final class AdminUserController
{
    public function index(): void
    {
        require_role('admin');
        $users = db_all(
            "SELECT u.*, (SELECT COUNT(*) FROM posts p WHERE p.author_id = u.id) AS post_count
               FROM users u ORDER BY CASE u.role WHEN 'admin' THEN 0 WHEN 'editor' THEN 1 ELSE 2 END, u.username"
        );
        view('admin/users', ['title' => 'ユーザー', 'users' => $users], 'admin/layout');
    }

    public function store(): void
    {
        require_role('admin');
        $type = input('auth_type') === 'ldap' && App::ldapEnabled() ? 'ldap' : 'local';
        $displayName = mb_substr(input('display_name'), 0, 50);

        if ($type === 'local') {
            $username = input('username');
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $confirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';
            $errors = self::validateLocalAccount($username, $password, $confirm);
            if ($errors) {
                $this->fail($errors, ['auth_type' => $type, 'username' => $username, 'display_name' => $displayName]);
            }
            db_exec(
                "INSERT INTO users (username, display_name, role, auth_type, password_hash, is_active, created_at)
                 VALUES (?, ?, 'admin', 'local', ?, 1, ?)",
                [$username, $displayName !== '' ? $displayName : $username, password_hash($password, PASSWORD_DEFAULT), now()]
            );
            flash('success', '管理者「' . $username . '」を追加しました。');
            redirect('/admin/users');
        }

        $username = Ldap::normalizeUsername(input('username'));
        $role = input('role') === 'editor' ? 'editor' : 'author';
        $errors = [];
        if ($username === null) {
            $errors[] = 'AD のユーザー名（sAMAccountName）を入力してください。';
        } elseif (db_val('SELECT 1 FROM users WHERE username = ?', [$username])) {
            $errors[] = 'このユーザー名はすでに登録されています。';
        }
        if ($errors) {
            $this->fail($errors, ['auth_type' => $type, 'username' => input('username'), 'display_name' => $displayName, 'role' => $role]);
        }
        db_exec(
            "INSERT INTO users (username, display_name, role, auth_type, is_active, created_at) VALUES (?, ?, ?, 'ldap', 1, ?)",
            [$username, $displayName !== '' ? $displayName : $username, $role, now()]
        );
        flash('success', 'AD ユーザー「' . $username . '」を' . role_label($role) . 'として登録しました。初回ログイン時に表示名が AD の情報で更新されます。');
        redirect('/admin/users');
    }

    public function update(string $id): void
    {
        $me = require_role('admin');
        $user = db_one('SELECT * FROM users WHERE id = ?', [(int) $id]) ?? abort(404);
        $isSelf = (int) $user['id'] === (int) $me['id'];

        $active = $isSelf ? 1 : (checked('is_active') ? 1 : 0);
        $displayName = mb_substr(input('display_name'), 0, 50);
        $displayName = $displayName !== '' ? $displayName : (string) $user['display_name'];

        if ($user['auth_type'] === 'local') {
            if (!$active && $this->activeAdminCount((int) $user['id']) === 0) {
                flash('error', '有効な管理者が 1 人もいなくなるため、無効化できません。');
                redirect('/admin/users');
            }
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if ($password !== '') {
                $errors = self::validatePassword($password, $password);
                if ($errors) {
                    $this->fail($errors, []);
                }
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            db_exec('UPDATE users SET display_name = ?, is_active = ? WHERE id = ?', [$displayName, $active, $user['id']]);
        } else {
            $role = input('role') === 'editor' ? 'editor' : 'author';
            db_exec('UPDATE users SET role = ?, display_name = ?, is_active = ? WHERE id = ?', [$role, $displayName, $active, $user['id']]);
        }
        flash('success', '「' . $user['username'] . '」を更新しました。');
        redirect('/admin/users');
    }

    public function destroy(string $id): void
    {
        $me = require_role('admin');
        $user = db_one('SELECT * FROM users WHERE id = ?', [(int) $id]) ?? abort(404);
        if ((int) $user['id'] === (int) $me['id']) {
            flash('error', '自分自身は削除できません。');
            redirect('/admin/users');
        }
        if ((int) db_val('SELECT COUNT(*) FROM posts WHERE author_id = ?', [$user['id']]) > 0) {
            flash('error', '記事を投稿しているユーザーは削除できません。代わりに「無効」にしてください。');
            redirect('/admin/users');
        }
        if ($user['auth_type'] === 'local' && $this->activeAdminCount((int) $user['id']) === 0) {
            flash('error', '有効な管理者が 1 人もいなくなるため、削除できません。');
            redirect('/admin/users');
        }
        db_exec('DELETE FROM users WHERE id = ?', [$user['id']]);
        flash('success', '「' . $user['username'] . '」を削除しました。');
        redirect('/admin/users');
    }

    /** 指定ユーザー以外の有効な管理者の数 */
    private function activeAdminCount(int $exceptId): int
    {
        return (int) db_val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id != ?", [$exceptId]);
    }

    public static function validateLocalAccount(string $username, string $password, string $confirm): array
    {
        $errors = [];
        if (!preg_match('/^[A-Za-z0-9._\-]{3,32}$/', $username)) {
            $errors[] = 'ユーザー名は半角英数字と . _ - で 3〜32 文字にしてください。';
        } elseif (db_val('SELECT 1 FROM users WHERE username = ?', [$username])) {
            $errors[] = 'このユーザー名はすでに使われています。';
        }
        return array_merge($errors, self::validatePassword($password, $confirm));
    }

    public static function validatePassword(string $password, string $confirm): array
    {
        $errors = [];
        if (mb_strlen($password) < 10) {
            $errors[] = 'パスワードは 10 文字以上にしてください。';
        } elseif (strlen($password) > 72) {
            $errors[] = 'パスワードは 72 バイト以内にしてください。';
        }
        if ($password !== $confirm) {
            $errors[] = 'パスワード（確認）が一致しません。';
        }
        return $errors;
    }

    private function fail(array $errors, array $old): never
    {
        foreach ($errors as $err) {
            flash('error', $err);
        }
        with_old($old);
        redirect('/admin/users');
    }
}

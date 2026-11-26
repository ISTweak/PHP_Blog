<?php
declare(strict_types=1);

final class AuthController
{
    public function showLogin(): void
    {
        if (current_user()) {
            redirect(App::isPrivate() ? '/' : '/admin');
        }
        if ((int) db_val('SELECT COUNT(*) FROM users') === 0) {
            redirect('/setup');
        }
        view('auth/login', [
            'title' => 'ログイン',
            'next'  => safe_next(query('next')),
        ], 'auth/layout');
    }

    public function login(): void
    {
        $next = safe_next(input('next'));
        $username = input('username');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        $error = Auth::attempt($username, $password);
        if ($error !== null) {
            flash('error', $error);
            with_old(['username' => $username]);
            redirect('/login', $next !== '' ? ['next' => $next] : []);
        }
        redirect($next !== '' ? $next : (App::isPrivate() ? '/' : '/admin'));
    }

    public function logout(): void
    {
        Auth::logout();
        flash('success', 'ログアウトしました。');
        redirect('/login');
    }

    /** 初回のみ：最初の管理者アカウントを作成 */
    public function showSetup(): void
    {
        $this->guardSetup();
        view('auth/setup', ['title' => '初期設定', 'keyReady' => $this->setupKey() !== ''], 'auth/layout');
    }

    public function setup(): void
    {
        $this->guardSetup();
        $key = $this->setupKey();
        $username = input('username');
        $displayName = input('display_name');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

        $errors = [];
        if ($key === '' || !hash_equals($key, input('setup_key'))) {
            $errors[] = 'セットアップキーが正しくありません。';
        }
        $errors = array_merge($errors, AdminUserController::validateLocalAccount($username, $password, $confirm));

        if ($errors) {
            foreach ($errors as $err) {
                flash('error', $err);
            }
            with_old(['username' => $username, 'display_name' => $displayName]);
            redirect('/setup');
        }

        db_exec(
            "INSERT INTO users (username, display_name, role, auth_type, password_hash, is_active, created_at)
             VALUES (?, ?, 'admin', 'local', ?, 1, ?)",
            [$username, $displayName !== '' ? $displayName : $username, password_hash($password, PASSWORD_DEFAULT), now()]
        );
        $user = db_one('SELECT * FROM users WHERE id = ?', [(int) db()->lastInsertId()]);
        Auth::login($user);
        flash('success', '管理者アカウントを作成しました。config.php の setup_key は空にしておくことをおすすめします。');
        redirect('/admin');
    }

    private function guardSetup(): void
    {
        if ((int) db_val('SELECT COUNT(*) FROM users') > 0) {
            abort(404);
        }
    }

    private function setupKey(): string
    {
        $key = trim((string) App::config('setup_key', ''));
        return $key === 'change-me' ? '' : $key;
    }
}

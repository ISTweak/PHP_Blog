<?php
declare(strict_types=1);

/**
 * ダッシュボード・サイト設定・アカウント
 */
final class AdminController
{
    public function dashboard(): void
    {
        $user = require_login();
        $stats = [
            'posts'    => (int) db_val('SELECT COUNT(*) FROM posts'),
            'myPosts'  => (int) db_val('SELECT COUNT(*) FROM posts WHERE author_id = ?', [$user['id']]),
            'media'    => (int) db_val('SELECT COUNT(*) FROM media'),
            'comments' => (int) db_val("SELECT COUNT(*) FROM comments WHERE status = 'approved'"),
            'pending'  => Repo::pendingCommentCount(),
        ];
        $recent = db_all(Repo::POST_SELECT . ' ORDER BY p.updated_at DESC, p.id DESC LIMIT 8');
        $pendingComments = Auth::atLeast('editor')
            ? db_all("SELECT c.*, p.title AS post_title FROM comments c JOIN posts p ON p.id = c.post_id
                       WHERE c.status = 'pending' ORDER BY c.id DESC LIMIT 5")
            : [];

        view('admin/dashboard', [
            'title'           => 'ダッシュボード',
            'stats'           => $stats,
            'recent'          => $recent,
            'pendingComments' => $pendingComments,
            'checks'          => Auth::atLeast('admin') ? $this->environmentChecks() : [],
            'backup'          => Auth::atLeast('admin') ? Backup::latest() : null,
        ], 'admin/layout');
    }

    private function environmentChecks(): array
    {
        $maxUpload = MediaService::maxBytes();
        $checks = [];
        $add = static function (string $label, bool $ok, string $detail) use (&$checks): void {
            $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
        };
        $add('PHP バージョン', PHP_VERSION_ID >= 80300, PHP_VERSION);
        $add('pdo_sqlite', extension_loaded('pdo_sqlite'), 'SQLite ' . (db()->query('SELECT sqlite_version()')->fetchColumn() ?: '?'));
        $add('fileinfo', extension_loaded('fileinfo'), 'ファイル形式の判定に必要');
        $add('mbstring', extension_loaded('mbstring'), '日本語処理に必要');
        $add('imagick', extension_loaded('imagick'), extension_loaded('imagick') ? 'サムネイル生成が有効' : 'サムネイルは作成されません（元画像を表示）');
        if (App::isPrivate()) {
            $add('ldap', extension_loaded('ldap'), 'AD 認証に必要');
        }
        $u = ini_bytes((string) ini_get('upload_max_filesize'));
        $p = ini_bytes((string) ini_get('post_max_size'));
        $add('upload_max_filesize', $u >= $maxUpload, ini_get('upload_max_filesize') . '（' . format_bytes($maxUpload) . ' 以上が必要）');
        $add('post_max_size', $p > $maxUpload, ini_get('post_max_size') . '（upload_max_filesize より大きい値が必要）');
        foreach (['include/data' => DATA, 'include/data/backup' => (string) App::config('backup_dir'), 'include/data/uploads' => MediaService::dir()] as $label => $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $add("書き込み権限: $label", is_dir($dir) && is_writable($dir), $dir);
        }
        $add('HTTPS', is_https(), is_https() ? '有効' : 'HTTP で接続中（本番では HTTPS を推奨）');
        return $checks;
    }

    public function settings(): void
    {
        require_role('admin');
        view('admin/settings', [
            'title'    => 'サイト設定',
            'settings' => [
                'site_name'          => App::setting('site_name'),
                'site_description'   => App::setting('site_description'),
                'posts_per_page'     => App::setting('posts_per_page'),
                'comment_moderation' => App::setting('comment_moderation'),
            ],
            'sidebar'   => Layout::sidebar(),
            'navLinks'  => Plugins::navDefs(),
            'hiddenNav' => Layout::hiddenNav(),
        ], 'admin/layout');
    }

    /** サイドバーの項目の並び順・表示 */
    public function saveSidebar(): void
    {
        require_role('admin');
        $strings = static fn($v): array => array_values(array_filter((array) $v, 'is_string'));
        Layout::saveSidebar($strings($_POST['order'] ?? []), $strings($_POST['visible'] ?? []));
        flash('success', 'サイドバーの設定を保存しました。');
        header('Location: ' . url('/admin/settings') . '#sidebar', true, 303);
        exit;
    }

    /** ヘッダーに表示するプラグインのリンク */
    public function saveNav(): void
    {
        require_role('admin');
        Layout::saveNav(array_values(array_filter((array) ($_POST['shown'] ?? []), 'is_string')));
        flash('success', 'ヘッダーのメニューの設定を保存しました。');
        header('Location: ' . url('/admin/settings') . '#nav', true, 303);
        exit;
    }

    public function saveSettings(): void
    {
        require_role('admin');
        $name = input('site_name');
        $desc = input('site_description');
        $per = (int) input('posts_per_page', '10');
        $errors = [];
        // HTML タグ（ロゴ）を含められるので、タグ込みの長さと表示される文字数を別々に確認
        if ($name === '' || mb_strlen($name) > 500) {
            $errors[] = 'サイト名を入力してください（HTML タグを含めて 500 文字以内）。';
        } elseif (mb_strlen(trim(strip_tags($name))) > 60) {
            $errors[] = 'サイト名の文字部分は 60 文字以内にしてください。';
        }
        if (mb_strlen($desc) > 200) {
            $errors[] = 'サイトの説明は 200 文字以内で入力してください。';
        }
        if ($per < 1 || $per > 50) {
            $errors[] = '1 ページの表示件数は 1〜50 の範囲で指定してください。';
        }
        if ($errors) {
            foreach ($errors as $err) {
                flash('error', $err);
            }
            redirect('/admin/settings');
        }
        App::saveSetting('site_name', $name);
        App::saveSetting('site_description', $desc);
        App::saveSetting('posts_per_page', (string) $per);
        App::saveSetting('comment_moderation', checked('comment_moderation') ? '1' : '0');
        flash('success', '設定を保存しました。');
        redirect('/admin/settings');
    }

    public function account(): void
    {
        $user = require_login();
        view('admin/account', ['title' => 'アカウント', 'user' => $user], 'admin/layout');
    }

    public function saveAccount(): void
    {
        $user = require_login();
        if ($user['auth_type'] !== 'local') {
            abort(403, 'AD アカウントのパスワードは Windows 側で変更してください。');
        }
        $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';
        $displayName = mb_substr(input('display_name'), 0, 50);

        $errors = [];
        if (!password_verify($current, (string) $user['password_hash'])) {
            $errors[] = '現在のパスワードが正しくありません。';
        }
        if ($password !== '') {
            $errors = array_merge($errors, AdminUserController::validatePassword($password, $confirm));
        }
        if ($errors) {
            foreach ($errors as $err) {
                flash('error', $err);
            }
            redirect('/admin/account');
        }
        db_exec('UPDATE users SET display_name = ? WHERE id = ?', [$displayName !== '' ? $displayName : $user['username'], $user['id']]);
        if ($password !== '') {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
        }
        flash('success', 'アカウント情報を更新しました。');
        redirect('/admin/account');
    }
}

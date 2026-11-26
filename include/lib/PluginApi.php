<?php
declare(strict_types=1);

/**
 * プラグインに渡される窓口。boot / install 関数の引数として受け取ります。
 *
 * アクセス権（$access / $role）に指定できる値:
 *   'public' … 誰でも（会員限定サイトではログインした人）
 *   'login'  … ログインした人
 *   'author' / 'editor' / 'admin' … そのロール以上
 */
final class PluginApi
{
    /** 本体が使っているため、プラグインが登録できないパス */
    private const RESERVED = ['/admin', '/media', '/login', '/logout', '/setup', '/assets', '/plugin-assets',
        '/post', '/category', '/tag', '/search'];

    public function __construct(
        public readonly string $id,
        public readonly string $dir,
    ) {
    }

    /* ---------------- ページの追加 ---------------- */

    /** 閲覧側のページを追加。$path の {name} はハンドラの引数 $name として渡される */
    public function route(string $method, string $path, Closure $handler, string $access = 'public'): void
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new InvalidArgumentException("未対応のメソッドです: $method");
        }
        if (!preg_match('#^/[A-Za-z0-9_\-./{}]*$#', $path)) {
            throw new InvalidArgumentException("パスが正しくありません: $path");
        }
        foreach (self::RESERVED as $r) {
            if ($path === $r || str_starts_with($path, $r . '/') || $path === '/') {
                throw new InvalidArgumentException("このパスは本体で使われています: $path");
            }
        }
        $this->checkAccess($access);
        Plugins::addRoute($this->id, $method, $path, $handler, $access);
    }

    public function get(string $path, Closure $handler, string $access = 'public'): void
    {
        $this->route('GET', $path, $handler, $access);
    }

    public function post(string $path, Closure $handler, string $access = 'public'): void
    {
        $this->route('POST', $path, $handler, $access);
    }

    /**
     * 管理画面のページを追加（/admin/plugin/{ID}/{slug}）。メニューにも表示される。
     * GET と POST の両方でハンドラが呼ばれるので、$_SERVER['REQUEST_METHOD'] で分岐する。
     */
    public function adminPage(string $slug, string $label, Closure $handler, string $role = 'admin'): void
    {
        if ($slug !== '' && !preg_match('/^[a-z0-9][a-z0-9_\-]{0,39}$/', $slug)) {
            throw new InvalidArgumentException("slug が正しくありません: $slug");
        }
        $this->checkAccess($role);
        Plugins::addAdminPage($this->id, $slug, $label, $handler, $role === 'public' ? 'author' : $role);
    }

    /** サイドバーにウィジェットを追加。$render は HTML 文字列を返す（空文字なら表示しない） */
    public function widget(string $title, Closure $render, int $order = 50, string $access = 'public'): void
    {
        $this->checkAccess($access);
        Plugins::addWidget($this->id, $title, $render, $order, $access);
    }

    /** ヘッダーのメニューにリンクを追加 */
    public function navLink(string $label, string $path, string $access = 'public'): void
    {
        $this->checkAccess($access);
        Plugins::addNavLink($this->id, $label, $path, $access);
    }

    /** このリクエストのページに CSS / JS を読み込む（assets/ 内のファイル） */
    public function addStyle(string $file): void
    {
        Plugins::addStyle($this->asset($file));
    }

    public function addScript(string $file): void
    {
        Plugins::addScript($this->asset($file));
    }

    /* ---------------- 表示 ---------------- */

    /** 閲覧側のレイアウトで views/{$template}.php を表示して終了 */
    public function view(string $template, array $vars = [], string $title = ''): never
    {
        $vars += ['api' => $this, 'title' => $title];
        $vars['content'] = $this->render($template, $vars);
        echo render('front/layout', $vars);
        exit;
    }

    /** 管理画面のレイアウトで views/{$template}.php を表示して終了 */
    public function adminView(string $template, array $vars = [], string $title = ''): never
    {
        $vars += ['api' => $this, 'title' => $title];
        $vars['content'] = $this->render($template, $vars);
        echo render('admin/layout', $vars);
        exit;
    }

    /** views/{$template}.php を描画して文字列で返す（ウィジェットなど） */
    public function render(string $template, array $vars = []): string
    {
        if (!preg_match('#^[A-Za-z0-9_\-/]+$#', $template) || str_contains($template, '..')) {
            throw new InvalidArgumentException("テンプレート名が正しくありません: $template");
        }
        return render_file($this->dir . '/views/' . $template . '.php', $vars + ['api' => $this]);
    }

    /* ---------------- URL ---------------- */

    /** サイト内の URL（設置パス付き） */
    public function url(string $path = '/', array $query = []): string
    {
        return url($path, $query);
    }

    /** このプラグインの管理ページの URL */
    public function adminUrl(string $slug = '', array $query = []): string
    {
        return url('/admin/plugin/' . $this->id . ($slug !== '' ? '/' . $slug : ''), $query);
    }

    /** assets/ 内のファイルの URL */
    public function asset(string $file): string
    {
        $abs = $this->dir . '/assets/' . ltrim($file, '/');
        $ver = is_file($abs) ? (string) filemtime($abs) : '0';
        return url('/plugin-assets/' . $this->id . '/' . ltrim($file, '/')) . '?v=' . $ver;
    }

    /* ---------------- データ ---------------- */

    public function db(): PDO
    {
        return db();
    }

    /** このプラグイン用のテーブル名（plg_{ID}_{name}）。他と名前がぶつからないように使う */
    public function table(string $name): string
    {
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new InvalidArgumentException("テーブル名が正しくありません: $name");
        }
        return 'plg_' . str_replace('-', '_', $this->id) . '_' . $name;
    }

    /** このプラグイン用の設定値（settings テーブルに保存） */
    public function setting(string $key, string $default = ''): string
    {
        $v = App::setting('plugin.' . $this->id . '.' . $key);
        return $v !== '' ? $v : $default;
    }

    public function saveSetting(string $key, string $value): void
    {
        App::saveSetting('plugin.' . $this->id . '.' . $key, $value);
    }

    /* ---------------- ユーザー ---------------- */

    public function user(): ?array
    {
        return current_user();
    }

    /** 'login' / 'author' / 'editor' / 'admin' の権限があるか */
    public function can(string $access): bool
    {
        return Plugins::canAccess($access);
    }

    private function checkAccess(string $access): void
    {
        if (!in_array($access, ['public', 'login', 'author', 'editor', 'admin'], true)) {
            throw new InvalidArgumentException("アクセス権の指定が正しくありません: $access");
        }
    }
}

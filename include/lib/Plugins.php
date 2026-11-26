<?php
declare(strict_types=1);

/**
 * プラグインの読み込みと登録内容の管理
 *
 * プラグインは include/plugins/{ID}/plugin.php に置き、管理画面の「プラグイン」で有効にします。
 * plugin.php は次の形の配列を return します（書き方は include/plugins/README.md を参照）。
 *
 *   return [
 *       'name'        => '表示名',
 *       'version'     => '1.0.0',
 *       'description' => '説明',
 *       'author'      => '作者',
 *       'install'     => function (PluginApi $api): void { ... },  // 任意：有効化時に 1 回（テーブル作成など）
 *       'boot'        => function (PluginApi $api): void { ... },  // 必須：有効な間、毎リクエスト
 *   ];
 */
final class Plugins
{
    private const ID_PATTERN = '/^[a-z0-9][a-z0-9_\-]{0,39}$/';

    /** @var array<string, array> 読み込んだ plugin.php の内容 */
    private static array $manifests = [];
    /** @var array<string, PluginApi> 起動に成功したプラグイン */
    private static array $active = [];
    /** @var array<string, string> 起動に失敗したプラグインとエラー内容 */
    private static array $errors = [];

    private static array $routes = [];
    private static array $adminPages = [];
    private static array $widgets = [];
    private static array $navLinks = [];
    private static array $styles = [];
    private static array $scripts = [];

    public static function dir(): string
    {
        return INC . '/plugins';
    }

    /* ---------------------------------------------------------------
     * 読み込み・有効化
     * --------------------------------------------------------------- */

    /** include/plugins/ にあるすべてのプラグイン（管理画面用） */
    public static function discover(): array
    {
        $list = [];
        foreach (glob(self::dir() . '/*/plugin.php') ?: [] as $file) {
            $id = basename(dirname($file));
            if (!preg_match(self::ID_PATTERN, $id)) {
                continue;
            }
            $manifest = self::load($id);
            $list[$id] = [
                'id'          => $id,
                'name'        => (string) ($manifest['name'] ?? $id),
                'version'     => (string) ($manifest['version'] ?? ''),
                'description' => (string) ($manifest['description'] ?? ''),
                'author'      => (string) ($manifest['author'] ?? ''),
                'valid'       => $manifest !== null,
                'enabled'     => in_array($id, self::enabledIds(), true),
                'error'       => self::$errors[$id] ?? null,
            ];
        }
        ksort($list);
        return $list;
    }

    private static function load(string $id): ?array
    {
        if (array_key_exists($id, self::$manifests)) {
            return self::$manifests[$id];
        }
        $file = self::dir() . '/' . $id . '/plugin.php';
        $manifest = null;
        try {
            if (is_file($file)) {
                $loaded = (static fn(string $__f) => require $__f)($file);
                if (is_array($loaded) && isset($loaded['boot']) && is_callable($loaded['boot'])) {
                    $manifest = $loaded;
                } else {
                    self::$errors[$id] = 'plugin.php が正しい形式ではありません（boot 関数が必要です）。';
                }
            }
        } catch (Throwable $e) {
            self::$errors[$id] = 'plugin.php の読み込みに失敗しました: ' . $e->getMessage();
            error_log("Plugin [$id] load failed: " . $e);
        }
        return self::$manifests[$id] = $manifest;
    }

    /** @return list<string> */
    public static function enabledIds(): array
    {
        $ids = json_decode(App::setting('plugins.enabled') ?: '[]', true);
        return is_array($ids) ? array_values(array_filter($ids, static fn($v) => is_string($v) && preg_match(self::ID_PATTERN, $v))) : [];
    }

    private static function saveEnabled(array $ids): void
    {
        App::saveSetting('plugins.enabled', json_encode(array_values(array_unique($ids))));
    }

    /** 有効なプラグインを起動する（毎リクエスト、ルーティングの前に呼ぶ） */
    public static function boot(): void
    {
        foreach (self::enabledIds() as $id) {
            $manifest = self::load($id);
            if ($manifest === null) {
                continue;
            }
            $api = new PluginApi($id, self::dir() . '/' . $id);
            try {
                ($manifest['boot'])($api);
                self::$active[$id] = $api;
            } catch (Throwable $e) {
                self::$errors[$id] = '起動時にエラーが発生しました: ' . $e->getMessage();
                error_log("Plugin [$id] boot failed: " . $e);
                self::forget($id);
            }
        }
    }

    /** 有効にする。失敗時はエラーメッセージを返す */
    public static function enable(string $id): ?string
    {
        if (!preg_match(self::ID_PATTERN, $id)) {
            return 'プラグインが見つかりません。';
        }
        $manifest = self::load($id);
        if ($manifest === null) {
            return self::$errors[$id] ?? 'プラグインが見つかりません。';
        }
        if (isset($manifest['install']) && is_callable($manifest['install'])) {
            try {
                ($manifest['install'])(new PluginApi($id, self::dir() . '/' . $id));
            } catch (Throwable $e) {
                error_log("Plugin [$id] install failed: " . $e);
                return 'インストール処理でエラーが発生しました: ' . $e->getMessage();
            }
        }
        self::saveEnabled([...self::enabledIds(), $id]);
        return null;
    }

    public static function disable(string $id): void
    {
        self::saveEnabled(array_diff(self::enabledIds(), [$id]));
    }

    public static function isActive(string $id): bool
    {
        return isset(self::$active[$id]);
    }

    /* ---------------------------------------------------------------
     * PluginApi から呼ばれる登録処理
     * --------------------------------------------------------------- */

    public static function addRoute(string $plugin, string $method, string $path, Closure $handler, string $access): void
    {
        self::$routes[] = compact('plugin', 'method', 'path', 'handler', 'access');
    }

    public static function addAdminPage(string $plugin, string $slug, string $label, Closure $handler, string $role): void
    {
        self::$adminPages[$plugin . '/' . $slug] = compact('plugin', 'slug', 'label', 'handler', 'role');
    }

    public static function addWidget(string $plugin, string $title, Closure $render, int $order, string $access): void
    {
        $key = 'plugin:' . $plugin . ':' . $title;      // 並び順・表示の設定に使う
        self::$widgets[] = compact('key', 'plugin', 'title', 'render', 'order', 'access');
    }

    public static function addNavLink(string $plugin, string $label, string $path, string $access): void
    {
        $key = 'plugin:' . $plugin . ':' . $path;       // ヘッダーの表示設定に使う
        self::$navLinks[] = compact('key', 'plugin', 'label', 'path', 'access');
    }

    private static function pluginName(string $id): string
    {
        return (string) (self::$manifests[$id]['name'] ?? $id);
    }

    public static function addStyle(string $url): void
    {
        self::$styles[$url] = $url;
    }

    public static function addScript(string $url): void
    {
        self::$scripts[$url] = $url;
    }

    /** 失敗したプラグインの登録内容を取り消す */
    private static function forget(string $id): void
    {
        $keep = static fn(array $r) => $r['plugin'] !== $id;
        self::$routes = array_values(array_filter(self::$routes, $keep));
        self::$adminPages = array_filter(self::$adminPages, $keep);
        self::$widgets = array_values(array_filter(self::$widgets, $keep));
        self::$navLinks = array_values(array_filter(self::$navLinks, $keep));
    }

    /* ---------------------------------------------------------------
     * 画面側から使う
     * --------------------------------------------------------------- */

    /** プラグインのルートをルーターに登録（本体のルートの後に呼ぶので、本体が優先される） */
    public static function registerRoutes(Router $router): void
    {
        foreach (self::$routes as $r) {
            $router->add($r['method'], $r['path'], self::guarded($r['handler'], $r['access']));
        }
        foreach (self::$adminPages as $p) {
            $handler = self::guarded($p['handler'], $p['role']);
            $path = '/admin/plugin/' . $p['plugin'] . ($p['slug'] !== '' ? '/' . $p['slug'] : '');
            $router->add('GET', $path, $handler);
            $router->add('POST', $path, $handler);
        }
    }

    private static function guarded(Closure $handler, string $access): Closure
    {
        return static function (...$params) use ($handler, $access): void {
            self::requireAccess($access);
            $handler(...$params);
        };
    }

    /** 'public' / 'login' / 'author' / 'editor' / 'admin' */
    public static function requireAccess(string $access): void
    {
        if ($access === 'public') {
            return;
        }
        if ($access === 'login') {
            require_login();
            return;
        }
        require_role($access);
    }

    public static function canAccess(string $access): bool
    {
        return match ($access) {
            'public' => true,
            'login'  => current_user() !== null,
            default  => Auth::atLeast($access),
        };
    }

    /** 管理画面のメニューに出すページ */
    public static function adminMenu(): array
    {
        $items = [];
        foreach (self::$adminPages as $p) {
            if (self::canAccess($p['role'])) {
                $items[] = [
                    'path'  => '/admin/plugin/' . $p['plugin'] . ($p['slug'] !== '' ? '/' . $p['slug'] : ''),
                    'label' => $p['label'],
                ];
            }
        }
        return $items;
    }

    /** ヘッダーに表示するリンク（権限があり、サイト設定で非表示にしていないもの） */
    public static function navLinks(): array
    {
        $hidden = Layout::hiddenNav();
        return array_values(array_filter(self::$navLinks,
            static fn($l) => self::canAccess($l['access']) && !in_array($l['key'], $hidden, true)));
    }

    /** 登録されているヘッダーのリンクすべて（サイト設定の画面用） */
    public static function navDefs(): array
    {
        return array_map(static fn($l) => [
            'key' => $l['key'], 'label' => $l['label'], 'path' => $l['path'], 'pluginName' => self::pluginName($l['plugin']),
        ], self::$navLinks);
    }

    /** 登録されているウィジェットすべて（並び順の初期値＝order 順。サイト設定の画面用） */
    public static function widgetDefs(): array
    {
        $widgets = self::$widgets;
        usort($widgets, static fn($a, $b) => $a['order'] <=> $b['order']);
        return array_map(static fn($w) => [
            'key' => $w['key'], 'title' => $w['title'], 'plugin' => $w['plugin'], 'pluginName' => self::pluginName($w['plugin']),
        ], $widgets);
    }

    /**
     * サイドバーに表示するウィジェット（描画済み HTML）。キー => 内容。
     * 並び順は Layout::sidebar() が決める。
     */
    public static function widgets(): array
    {
        $out = [];
        foreach (self::$widgets as $w) {
            if (!self::canAccess($w['access'])) {
                continue;
            }
            try {
                $html = (string) ($w['render'])();
            } catch (Throwable $e) {
                error_log("Plugin [{$w['plugin']}] widget failed: " . $e);
                continue;
            }
            if (trim($html) !== '') {
                $out[$w['key']] = ['key' => $w['key'], 'title' => $w['title'], 'html' => $html, 'plugin' => $w['plugin']];
            }
        }
        return $out;
    }

    public static function styles(): array
    {
        return array_values(self::$styles);
    }

    public static function scripts(): array
    {
        return array_values(self::$scripts);
    }

    /* ---------------------------------------------------------------
     * プラグインの静的ファイル（include/plugins/{ID}/assets/ 内）
     * --------------------------------------------------------------- */

    private const ASSET_TYPES = [
        'css' => 'text/css; charset=UTF-8', 'js' => 'text/javascript; charset=UTF-8', 'json' => 'application/json',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2',
        'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav',
    ];

    /** /plugin-assets/{ID}/{ファイル} を配信 */
    public static function serveAsset(string $path): never
    {
        if (!preg_match('#^/plugin-assets/([a-z0-9][a-z0-9_\-]{0,39})/([A-Za-z0-9_\-./]+)$#', $path, $m)
            || str_contains($m[2], '..') || !in_array($m[1], self::enabledIds(), true)) {
            abort(404);
        }
        $base = realpath(self::dir() . '/' . $m[1] . '/assets');
        $file = $base ? realpath($base . '/' . $m[2]) : false;
        $ext = strtolower(pathinfo((string) $file, PATHINFO_EXTENSION));
        if (!$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file) || !isset(self::ASSET_TYPES[$ext])) {
            abort(404);
        }
        MediaService::send($file, self::ASSET_TYPES[$ext], basename($file), true);
    }
}

<?php
/**
 * 共通ヘルパー関数
 */
declare(strict_types=1);

/* ------------------------------------------------------------------
 * 出力・URL
 * ------------------------------------------------------------------ */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** public/index.php が置かれている URL 上のパス（例: "" や "/blog"） */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $base = rtrim($dir, '/');
        if ($base === '.') {
            $base = '';
        }
    }
    return $base;
}

function url(string $path = '/', array $query = []): string
{
    $url = base_path() . '/' . ltrim($path, '/');
    if ($query) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }
    return $url;
}

function asset(string $path): string
{
    $file = ROOT . '/public/assets/' . $path;
    $ver = is_file($file) ? (string) filemtime($file) : '0';
    return url('assets/' . $path) . '?v=' . $ver;
}

function post_url(int|string $id): string
{
    return url('/post/' . (int) $id);
}

function category_url(string $slug): string
{
    return url('/category/' . rawurlencode($slug));
}

function tag_url(string $name): string
{
    return url('/tag/' . rawurlencode($name));
}

function media_url(int|string $id, string $size = ''): string
{
    return url('/media/' . (int) $id . ($size !== '' ? '/' . $size : ''));
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** ベースパスを除いたリクエストパス（例: "/post/3"） */
function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uri = is_string($uri) ? $uri : '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    $path = '/' . trim(rawurldecode($uri), '/');
    return $path === '/index.php' ? '/' : $path;
}

/** ログイン後に戻る先として使う、現在のパス＋クエリ */
function current_path(): string
{
    $path = $GLOBALS['__path'] ?? '/';
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    return $qs !== '' ? $path . '?' . $qs : $path;
}

/** 戻り先 URL の検証（サイト内のパスのみ許可） */
function safe_next(string $next): string
{
    if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')
        || str_contains($next, '\\') || preg_match('/[\x00-\x1F]/', $next)
        || str_starts_with($next, '/login') || str_starts_with($next, '/logout')) {
        return '';
    }
    return $next;
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query), true, 303);
    exit;
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

function json_response(array $data, int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ------------------------------------------------------------------
 * ビュー
 * ------------------------------------------------------------------ */

function render(string $template, array $vars = []): string
{
    return render_file(INC . '/views/' . $template . '.php', $vars);
}

/** 任意のパスのテンプレートを描画（プラグインのテンプレート用） */
function render_file(string $__file, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    try {
        require $__file;
    } catch (Throwable $t) {
        ob_end_clean();
        throw $t;
    }
    return (string) ob_get_clean();
}

function view(string $template, array $vars = [], string $layout = 'front/layout'): never
{
    $vars['content'] = render($template, $vars);
    echo render($layout, $vars);
    exit;
}

function abort(int $code, string $message = ''): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $defaults = [
        400 => '不正なリクエストです。ページを再読み込みしてもう一度お試しください。',
        401 => 'ログインが必要です。',
        403 => 'このページを表示する権限がありません。',
        404 => 'ページが見つかりません。',
        405 => '許可されていない操作です。',
        413 => '送信データが大きすぎます。',
    ];
    $message = $message !== '' ? $message : ($defaults[$code] ?? 'エラーが発生しました。');
    http_response_code($code);
    if (wants_json()) {
        json_response(['ok' => false, 'error' => $message], $code);
    }
    view('error', ['title' => 'エラー', 'code' => $code, 'message' => $message]);
}

/* ------------------------------------------------------------------
 * リクエスト入力
 * ------------------------------------------------------------------ */

function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function query(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function checked(string $key): bool
{
    return isset($_POST[$key]) && $_POST[$key] !== '' && $_POST[$key] !== '0';
}

function like_escape(string $s): string
{
    return strtr($s, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
}

/* ------------------------------------------------------------------
 * セッション・CSRF・フラッシュ
 * ------------------------------------------------------------------ */

function start_session(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $dir = DATA . '/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
    }
    $lifetime = (int) App::config('session_lifetime', 28800);
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name((string) App::config('session_name', 'BLOGSESSID'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() === '' ? '/' : base_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $GLOBALS['__old'] = $_SESSION['_old'] ?? [];
    unset($_SESSION['_old']);
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    // post_max_size を超えると $_POST / $_FILES が空になる
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        abort(413, '送信データがサーバーの上限（post_max_size）を超えています。');
    }
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        abort(400, 'セッションの有効期限が切れたか、不正なリクエストです。ページを再読み込みしてもう一度お試しください。');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** 次のリクエストで old() から読み出せる入力値を保存する */
function with_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function old(string $key, string $default = ''): string
{
    $v = $GLOBALS['__old'][$key] ?? $default;
    return is_scalar($v) ? (string) $v : $default;
}

/* ------------------------------------------------------------------
 * 認証
 * ------------------------------------------------------------------ */

function current_user(): ?array
{
    return Auth::user();
}

function require_login(): array
{
    $user = Auth::user();
    if (!$user) {
        if (wants_json() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            abort(401, 'ログインの有効期限が切れました。再度ログインしてください。');
        }
        redirect('/login', ['next' => current_path()]);
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if (!Auth::atLeast($role)) {
        abort(403);
    }
    return $user;
}

/* ------------------------------------------------------------------
 * サイト名（ロゴ用に一部の HTML タグを許可）
 * ------------------------------------------------------------------ */

/** 設定値そのもの（script・style は中身ごと除いておく） */
function site_name_raw(): string
{
    return (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', App::setting('site_name'));
}

/** ヘッダー等に表示する HTML（img・span・strong などのみ許可し、サニタイズ済み） */
function site_name_html(): string
{
    static $html = null;
    if ($html === null) {
        $raw = site_name_raw();
        $html = trim(Html::sanitize(strip_tags($raw, '<img><span><strong><b><em><i><small><br>')));
        // ロゴはページ最上部なので遅延読み込みしない
        $html = str_replace(' loading="lazy"', '', $html);
        if ($html === '') {
            $html = e(site_name_text());
        }
    }
    return $html;
}

/** <title>・フッターなど、タグを使えない場所用のテキスト。ロゴだけの場合は alt を使う */
function site_name_text(): string
{
    static $text = null;
    if ($text === null) {
        $raw = site_name_raw();
        $text = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '' && preg_match('/\balt\s*=\s*(["\'])(.*?)\1/is', $raw, $m)) {
            $text = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if ($text === '') {
            $text = (string) App::config('site_name', 'ブログ');
        }
    }
    return $text;
}

function role_label(string $role): string
{
    return match ($role) {
        'admin'  => '管理者',
        'editor' => '編集者',
        'author' => '投稿者',
        default  => $role,
    };
}

/* ------------------------------------------------------------------
 * データベース
 * ------------------------------------------------------------------ */

function db(): PDO
{
    return App::db();
}

function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function db_val(string $sql, array $params = []): mixed
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

function db_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/* ------------------------------------------------------------------
 * その他
 * ------------------------------------------------------------------ */

/** ページング情報（?page= を参照） */
function paginate(int $total, int $perPage): array
{
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, (int) query('page', '1')), $pages);
    return [
        'page'   => $page,
        'pages'  => $pages,
        'per'    => $perPage,
        'offset' => ($page - 1) * $perPage,
        'total'  => $total,
    ];
}

function fmt_date(?string $datetime, string $format = 'Y.m.d'): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : '';
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . 'MB';
    }
    if ($bytes >= 1024) {
        return (int) round($bytes / 1024) . 'KB';
    }
    return $bytes . 'B';
}

/** php.ini のサイズ表記（"8M" など）をバイト数に変換 */
function ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $num = (int) $value;
    return match (strtolower(substr($value, -1))) {
        'g'     => $num * 1073741824,
        'm'     => $num * 1048576,
        'k'     => $num * 1024,
        default => $num,
    };
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; media-src 'self'; "
        . "frame-src https://www.youtube-nocookie.com https://www.youtube.com; script-src 'self'; "
        . "style-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    if (App::isPrivate()) {
        header('X-Robots-Tag: noindex, nofollow');
    }
}

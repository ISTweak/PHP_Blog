<?php
/**
 * フロントコントローラー：すべてのリクエストはここを通ります。
 */
declare(strict_types=1);

require dirname(__DIR__) . '/include/bootstrap.php';

send_security_headers();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = request_path();
$GLOBALS['__path'] = $path;

// アクセス制御
if (str_starts_with($path, '/admin')) {
    require_login();
    header('Cache-Control: no-store');
} elseif (App::isPrivate() && !in_array($path, ['/login', '/logout', '/setup'], true) && !current_user()) {
    if ($method === 'POST' || wants_json()) {
        abort(401);
    }
    redirect('/login', $path !== '/' ? ['next' => current_path()] : []);
}

$r = new Router();

// 閲覧
$r->get('/', [FrontController::class, 'home']);
$r->get('/post/{id}', [FrontController::class, 'post']);
$r->post('/post/{id}/comment', [FrontController::class, 'comment']);
$r->get('/category/{slug}', [FrontController::class, 'category']);
$r->get('/tag/{name}', [FrontController::class, 'tag']);
$r->get('/search', [FrontController::class, 'search']);
$r->get('/media/{id}', [FrontController::class, 'media']);
$r->get('/media/{id}/{size}', [FrontController::class, 'media']);

// 認証
$r->get('/login', [AuthController::class, 'showLogin']);
$r->post('/login', [AuthController::class, 'login']);
$r->post('/logout', [AuthController::class, 'logout']);
$r->get('/setup', [AuthController::class, 'showSetup']);
$r->post('/setup', [AuthController::class, 'setup']);

// 管理画面
$r->get('/admin', [AdminController::class, 'dashboard']);
$r->get('/admin/settings', [AdminController::class, 'settings']);
$r->post('/admin/settings', [AdminController::class, 'saveSettings']);
$r->get('/admin/account', [AdminController::class, 'account']);
$r->post('/admin/account', [AdminController::class, 'saveAccount']);

$r->get('/admin/posts', [AdminPostController::class, 'index']);
$r->get('/admin/posts/new', [AdminPostController::class, 'create']);
$r->post('/admin/posts/new', [AdminPostController::class, 'store']);
$r->post('/admin/posts/preview', [AdminPostController::class, 'preview']);
$r->get('/admin/posts/{id}/edit', [AdminPostController::class, 'edit']);
$r->post('/admin/posts/{id}/edit', [AdminPostController::class, 'update']);
$r->post('/admin/posts/{id}/delete', [AdminPostController::class, 'destroy']);

$r->get('/admin/media', [AdminMediaController::class, 'index']);
$r->get('/admin/media/list', [AdminMediaController::class, 'listJson']);
$r->post('/admin/media/upload', [AdminMediaController::class, 'upload']);
$r->post('/admin/media/{id}/delete', [AdminMediaController::class, 'destroy']);

$r->get('/admin/categories', [AdminTermController::class, 'categories']);
$r->post('/admin/categories', [AdminTermController::class, 'storeCategory']);
$r->post('/admin/categories/{id}', [AdminTermController::class, 'updateCategory']);
$r->post('/admin/categories/{id}/delete', [AdminTermController::class, 'destroyCategory']);
$r->get('/admin/tags', [AdminTermController::class, 'tags']);
$r->post('/admin/tags/prune', [AdminTermController::class, 'pruneTags']);
$r->post('/admin/tags/{id}', [AdminTermController::class, 'updateTag']);
$r->post('/admin/tags/{id}/delete', [AdminTermController::class, 'destroyTag']);

$r->get('/admin/comments', [AdminCommentController::class, 'index']);
$r->post('/admin/comments/{id}/approve', [AdminCommentController::class, 'approve']);
$r->post('/admin/comments/{id}/delete', [AdminCommentController::class, 'destroy']);

$r->get('/admin/users', [AdminUserController::class, 'index']);
$r->post('/admin/users', [AdminUserController::class, 'store']);
$r->post('/admin/users/{id}', [AdminUserController::class, 'update']);
$r->post('/admin/users/{id}/delete', [AdminUserController::class, 'destroy']);

$r->dispatch($method, $path);

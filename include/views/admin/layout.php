<?php
/** @var string $content */
$me = current_user();
$siteName = site_name_text();
$path = $GLOBALS['__path'] ?? '';
$pendingCount = Auth::atLeast('editor') ? Repo::pendingCommentCount() : 0;

$nav = [
    ['/admin', 'ダッシュボード', 'author', true],
    ['/admin/posts', '投稿一覧', 'author', false],
    ['/admin/posts/new', '新規投稿', 'author', true],
    ['/admin/media', 'メディア', 'author', false],
    ['/admin/categories', 'カテゴリー', 'editor', false],
    ['/admin/tags', 'タグ', 'editor', false],
    ['/admin/comments', 'コメント', 'editor', false],
    ['/admin/users', 'ユーザー', 'admin', false],
    ['/admin/settings', 'サイト設定', 'admin', false],
    ['/admin/plugins', 'プラグイン', 'admin', false],
    ['/admin/account', 'アカウント', 'author', false],
];
$pluginMenu = Plugins::adminMenu();
$isActive = static function (string $href, bool $exact) use ($path): bool {
    if ($exact) {
        return $path === $href;
    }
    if ($href === '/admin/posts' && $path === '/admin/posts/new') {
        return false;
    }
    return $path === $href || str_starts_with($path, $href . '/');
};
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e(($title ?? '管理画面') . ' | ' . $siteName) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
<?php foreach (Plugins::styles() as $href): ?><link rel="stylesheet" href="<?= e($href) ?>">
<?php endforeach; ?>
<?php foreach (Plugins::scripts() as $src): ?><script src="<?= e($src) ?>" defer></script>
<?php endforeach; ?>
</head>
<body class="adm"
      data-media-list="<?= e(url('/admin/media/list')) ?>"
      data-media-upload="<?= e(url('/admin/media/upload')) ?>"
      data-preview-url="<?= e(url('/admin/posts/preview')) ?>"
      data-max-bytes="<?= (int) MediaService::maxBytes() ?>">
<header class="adm-topbar">
  <button class="adm-topbar__menu" type="button" data-sidebar-toggle aria-controls="adm-sidebar" aria-expanded="false">
    <span class="adm-burger" aria-hidden="true"></span><span class="visually-hidden">メニュー</span>
  </button>
  <a class="adm-topbar__site" href="<?= e(url('/')) ?>" title="サイトを表示"><?= site_name_html() ?> <span class="adm-topbar__ext">サイトを表示 ↗</span></a>
  <div class="adm-topbar__user">
    <span class="adm-topbar__name"><?= e($me['display_name'] ?? '') ?></span>
    <span class="badge badge--<?= e($me['role'] ?? '') ?>"><?= e(role_label($me['role'] ?? '')) ?></span>
    <form action="<?= e(url('/logout')) ?>" method="post" class="inline-form">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn--sm btn--ghost-light">ログアウト</button>
    </form>
  </div>
</header>

<div class="adm-shell">
  <nav class="adm-sidebar" id="adm-sidebar" aria-label="管理メニュー">
    <ul class="adm-nav">
      <?php foreach ($nav as [$href, $label, $role, $exact]): ?>
        <?php if (!Auth::atLeast($role)) continue; ?>
        <li>
          <a class="adm-nav__link<?= $isActive($href, $exact) ? ' is-active' : '' ?>" href="<?= e(url($href)) ?>">
            <?= e($label) ?>
            <?php if ($href === '/admin/comments' && $pendingCount > 0): ?>
              <span class="adm-nav__count" title="承認待ち"><?= $pendingCount ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($pluginMenu): ?>
      <p class="adm-nav__heading">プラグイン</p>
      <ul class="adm-nav">
        <?php foreach ($pluginMenu as $item): ?>
          <li><a class="adm-nav__link<?= $isActive($item['path'], false) ? ' is-active' : '' ?>" href="<?= e(url($item['path'])) ?>"><?= e($item['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p class="adm-sidebar__mode"><?= App::isPrivate() ? '会員限定サイト' : '一般公開サイト' ?></p>
  </nav>
  <div class="adm-backdrop" data-sidebar-close></div>

  <main class="adm-main">
    <?= render('partials/flash') ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>

<?php
/** @var string $content */
$siteName = site_name_text();
$siteDesc = App::setting('site_description');
$user = current_user();
$showNav = !App::isPrivate() || $user !== null;
$navCats = $showNav ? Repo::categories() : [];
$pageTitle = isset($title) && $title !== '' && $title !== null ? $title . ' | ' . $siteName : $siteName;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<?php if ($siteDesc !== ''): ?><meta name="description" content="<?= e($siteDesc) ?>"><?php endif; ?>
<?php if (App::isPrivate()): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
<?php foreach (Plugins::styles() as $href): ?><link rel="stylesheet" href="<?= e($href) ?>">
<?php endforeach; ?>
<?php foreach (Plugins::scripts() as $src): ?><script src="<?= e($src) ?>" defer></script>
<?php endforeach; ?>
</head>
<body class="site">
<header class="site-header">
  <div class="container site-header__inner">
    <div class="site-brand">
      <a class="site-brand__name" href="<?= e(url('/')) ?>"><?= site_name_html() ?></a>
      <?php if ($siteDesc !== ''): ?><p class="site-brand__desc"><?= e($siteDesc) ?></p><?php endif; ?>
    </div>
    <?php if ($showNav): ?>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">
      <span class="nav-toggle__bar" aria-hidden="true"></span>
      <span class="visually-hidden">メニュー</span>
    </button>
    <nav class="site-nav" id="site-nav" aria-label="メインメニュー">
      <ul class="site-nav__list">
        <li><a class="site-nav__link" href="<?= e(url('/')) ?>">ホーム</a></li>
        <?php foreach ($navCats as $c): ?>
          <li><a class="site-nav__link" href="<?= e(category_url($c['slug'])) ?>"><?= e($c['name']) ?></a></li>
        <?php endforeach; ?>
        <?php foreach (Plugins::navLinks() as $link): ?>
          <li><a class="site-nav__link" href="<?= e(url($link['path'])) ?>"><?= e($link['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="site-nav__tools">
        <form class="search-mini" action="<?= e(url('/search')) ?>" method="get" role="search">
          <input class="search-mini__input" type="search" name="q" placeholder="記事を検索" value="<?= e($search ?? '') ?>" aria-label="キーワード">
          <button class="search-mini__btn" type="submit" aria-label="検索">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M10 2a8 8 0 0 1 6.32 12.9l5.39 5.4-1.41 1.4-5.4-5.38A8 8 0 1 1 10 2Zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12Z"/></svg>
          </button>
        </form>
        <?php if ($user): ?>
          <a class="site-nav__link site-nav__link--admin" href="<?= e(url('/admin')) ?>">管理画面</a>
          <form action="<?= e(url('/logout')) ?>" method="post" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="site-nav__link site-nav__link--btn">ログアウト</button>
          </form>
        <?php endif; ?>
      </div>
    </nav>
    <?php endif; ?>
  </div>
</header>

<main class="site-main">
  <div class="container">
    <?= render('partials/flash') ?>
    <?= $content ?>
  </div>
</main>

<footer class="site-footer">
  <div class="container site-footer__inner">
    <p class="site-footer__copy">&copy; <?= date('Y') ?> <?= e($siteName) ?></p>
    <p class="site-footer__credit">Built with <a href="https://claude.com/claude-code" target="_blank" rel="noopener">Claude Code</a></p>
  </div>
</footer>
</body>
</html>

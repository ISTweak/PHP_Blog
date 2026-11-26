<?php
/** @var string $content */
$siteName = site_name_text();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($title ?? 'ログイン') . ' | ' . $siteName) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body class="auth">
<main class="auth-card">
  <p class="auth-card__site"><?php if (!App::isPrivate()): ?><a href="<?= e(url('/')) ?>"><?= site_name_html() ?></a><?php else: ?><?= site_name_html() ?><?php endif; ?></p>
  <?= render('partials/flash') ?>
  <?= $content ?>
</main>
</body>
</html>

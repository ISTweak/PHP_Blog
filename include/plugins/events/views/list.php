<?php
/**
 * @var PluginApi $api
 * @var array $upcoming
 * @var array $past
 */
$card = static function (array $e) use ($api): string {
    ob_start(); ?>
    <a class="ev-card" href="<?= e($api->url('/events/' . (int) $e['id'])) ?>">
      <span class="ev-card__date">
        <span class="ev-card__month"><?= e(date('n月', strtotime($e['starts_at']))) ?></span>
        <span class="ev-card__day"><?= e(date('j', strtotime($e['starts_at']))) ?></span>
      </span>
      <span class="ev-card__body">
        <span class="ev-card__title"><?= e($e['title']) ?></span>
        <span class="ev-card__meta"><?= e(EventsPlugin::formatDate($e['starts_at'])) ?><?= $e['place'] !== '' ? ' ・ ' . e($e['place']) : '' ?></span>
        <span class="ev-counts">
          <span class="ev-count ev-count--yes">参加 <?= (int) $e['counts']['yes'] ?></span>
          <span class="ev-count ev-count--maybe">未定 <?= (int) $e['counts']['maybe'] ?></span>
          <span class="ev-count ev-count--no">不参加 <?= (int) $e['counts']['no'] ?></span>
        </span>
      </span>
    </a>
    <?php return (string) ob_get_clean();
};
?>
<div class="layout">
  <div class="layout__main">
    <header class="page-heading">
      <h1 class="page-heading__title">イベント</h1>
    </header>

    <h2 class="ev-section">これからのイベント</h2>
    <?php if ($upcoming): ?>
      <div class="ev-list"><?php foreach ($upcoming as $e) echo $card($e); ?></div>
    <?php else: ?>
      <p class="empty-state">予定されているイベントはありません。</p>
    <?php endif; ?>

    <?php if ($past): ?>
      <h2 class="ev-section">終了したイベント</h2>
      <div class="ev-list ev-list--past"><?php foreach ($past as $e) echo $card($e); ?></div>
    <?php endif; ?>
  </div>
  <?= render('front/_sidebar') ?>
</div>

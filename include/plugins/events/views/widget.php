<?php
/**
 * @var PluginApi $api
 * @var array $events
 */
?>
<ul class="widget-list">
  <?php foreach ($events as $e): ?>
    <li>
      <a class="widget-list__link" href="<?= e($api->url('/events/' . (int) $e['id'])) ?>">
        <span class="widget-list__text"><?= e($e['title']) ?></span>
        <span class="widget-list__sub"><?= e(EventsPlugin::formatDate($e['starts_at'])) ?> ・ 参加 <?= (int) $e['counts']['yes'] ?> 人</span>
      </a>
    </li>
  <?php endforeach; ?>
</ul>
<p class="ev-widget-more"><a href="<?= e($api->url('/events')) ?>">すべてのイベント</a></p>

<?php
/**
 * サイドバー「次の試合」
 * @var PluginApi $api
 * @var string $team
 * @var array|null $next
 */
?>
<?php if ($next): ?>
  <div class="bb-w-next">
    <p class="bb-w-next__date">
      <?= e(BaseballPlugin::day($next['game_date'])) ?><?= $next['start_time'] !== '' ? ' ' . e($next['start_time']) : '' ?>
      <?php if ($next['status'] === 'postponed'): ?><span class="bb-res bb-res--off">順延</span><?php endif; ?>
    </p>
    <p class="bb-w-next__vs"><span class="bb-w-next__team"><?= e($team) ?></span> vs <?= e($next['opponent']) ?></p>
    <?php if ($next['competition'] !== '' || $next['venue'] !== ''): ?>
      <p class="bb-w-next__venue">
        <?php if ($next['competition'] !== ''): ?><span class="bb-comp"><?= e($next['competition']) ?></span><?php endif; ?>
        <?= e($next['venue']) ?>
      </p>
    <?php endif; ?>
    <?php if ($next['comment'] !== ''): ?><p class="bb-w-next__comment"><?= nl2br(e($next['comment']), false) ?></p><?php endif; ?>
  </div>
<?php else: ?>
  <p class="widget__empty">予定されている試合はありません。</p>
<?php endif; ?>
<p class="bb-w-more"><a href="<?= e($api->url('/baseball')) ?>">試合日程を見る</a></p>

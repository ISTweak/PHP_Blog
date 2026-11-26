<?php
/**
 * @var PluginApi $api
 * @var string $team
 * @var array|null $next
 * @var array $recent
 * @var array $record
 * @var int $year
 */
?>
<?php if ($next): ?>
  <div class="bb-w-next">
    <p class="bb-w-next__label">次の試合</p>
    <p class="bb-w-next__date"><?= e(BaseballPlugin::day($next['game_date'])) ?><?= $next['start_time'] !== '' ? ' ' . e($next['start_time']) : '' ?></p>
    <p class="bb-w-next__vs">vs <?= e($next['opponent']) ?></p>
    <?php if ($next['venue'] !== ''): ?><p class="bb-w-next__venue"><?= e($next['venue']) ?></p><?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($recent): ?>
  <ul class="bb-w-list">
    <?php foreach ($recent as $g): $res = BaseballPlugin::result($g); ?>
      <li>
        <span class="bb-w-list__date"><?= e(BaseballPlugin::day($g['game_date'])) ?></span>
        <span class="bb-w-list__vs">vs <?= e($g['opponent']) ?></span>
        <span class="bb-res bb-res--<?= e((string) $res) ?>"><?= e(BaseballPlugin::RESULTS[$res] ?? '') ?></span>
        <span class="bb-score"><?= (int) $g['our_score'] ?>-<?= (int) $g['opp_score'] ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<p class="bb-w-record"><?= $year ?>年：<?= (int) $record['win'] ?>勝 <?= (int) $record['lose'] ?>敗 <?= (int) $record['draw'] ?>分</p>
<p class="bb-w-more"><a href="<?= e($api->url('/baseball')) ?>">試合日程・結果を見る</a></p>

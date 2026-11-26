<?php
/**
 * サイドバー「試合結果」：直近 3 試合と今年の成績
 * @var PluginApi $api
 * @var array $recent
 * @var array $record
 * @var int $year
 */
?>
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
  <p class="bb-w-record"><?= $year ?>年：<?= (int) $record['win'] ?>勝 <?= (int) $record['lose'] ?>敗 <?= (int) $record['draw'] ?>分</p>
<?php else: ?>
  <p class="widget__empty">まだ試合結果はありません。</p>
<?php endif; ?>
<p class="bb-w-more"><a href="<?= e($api->url('/baseball')) ?>">試合結果をすべて見る</a></p>

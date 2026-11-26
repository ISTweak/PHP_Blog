<?php
/**
 * 試合 1 件の行
 * @var PluginApi $api
 * @var array $g
 * @var string $team
 * @var array $posts  記事 ID => タイトル
 */
$res = BaseballPlugin::result($g);
$past = $g['game_date'] < date('Y-m-d');
?>
<li class="bb-game<?= $res ? ' bb-game--' . $res : '' ?><?= in_array($g['status'], ['cancelled', 'postponed'], true) ? ' bb-game--off' : '' ?>">
  <div class="bb-game__when">
    <span class="bb-game__date"><?= e(BaseballPlugin::day($g['game_date'])) ?></span>
    <?php if ($g['start_time'] !== ''): ?><span class="bb-game__time"><?= e($g['start_time']) ?></span><?php endif; ?>
  </div>
  <div class="bb-game__main">
    <p class="bb-game__match">
      <?php if ($g['competition'] !== ''): ?><span class="bb-comp"><?= e($g['competition']) ?></span><?php endif; ?>
      <span class="bb-game__vs">vs <?= e($g['opponent']) ?></span>
    </p>
    <?php if ($g['venue'] !== ''): ?><p class="bb-game__venue"><?= e($g['venue']) ?></p><?php endif; ?>
    <?php if ($g['comment'] !== ''): ?><p class="bb-game__comment"><?= nl2br(e($g['comment']), false) ?></p><?php endif; ?>
    <?php if ($g['post_id'] && isset($posts[(int) $g['post_id']])): ?>
      <p class="bb-game__post"><a href="<?= e(post_url($g['post_id'])) ?>">試合レポート：<?= e($posts[(int) $g['post_id']]) ?></a></p>
    <?php endif; ?>
  </div>
  <div class="bb-game__result">
    <?php if ($res): ?>
      <span class="bb-res bb-res--<?= e($res) ?>"><?= e(BaseballPlugin::RESULTS[$res]) ?></span>
      <span class="bb-score"><?= (int) $g['our_score'] ?> - <?= (int) $g['opp_score'] ?></span>
    <?php elseif ($g['status'] === 'cancelled'): ?>
      <span class="bb-res bb-res--off">中止</span>
    <?php elseif ($g['status'] === 'postponed'): ?>
      <span class="bb-res bb-res--off">順延</span>
    <?php elseif ($past): ?>
      <span class="bb-res bb-res--none">結果未入力</span>
    <?php endif; ?>
  </div>
</li>

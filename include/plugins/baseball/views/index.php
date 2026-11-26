<?php
/**
 * @var PluginApi $api
 * @var string $team
 * @var array|null $next
 * @var array $upcoming
 * @var array $results
 * @var array $record
 * @var int $year
 * @var array $years
 * @var array $posts
 */
$row = static fn(array $g): string => $api->render('_game', ['g' => $g, 'team' => $team, 'posts' => $posts]);
?>
<div class="layout">
  <div class="layout__main">
    <header class="page-heading">
      <p class="page-heading__label"><?= e($team) ?></p>
      <h1 class="page-heading__title">試合日程・結果</h1>
    </header>

    <?php if ($next): ?>
      <section class="bb-next" aria-label="次の試合">
        <p class="bb-next__label">次の試合</p>
        <p class="bb-next__date">
          <?= e(BaseballPlugin::day($next['game_date'], true)) ?>
          <?php if ($next['start_time'] !== ''): ?><span><?= e($next['start_time']) ?></span><?php endif; ?>
          <?php if ($next['status'] === 'postponed'): ?><span class="bb-res bb-res--off">順延</span><?php endif; ?>
        </p>
        <p class="bb-next__match">
          <span class="bb-next__team"><?= e($team) ?></span>
          <span class="bb-next__vs">vs</span>
          <span class="bb-next__team"><?= e($next['opponent']) ?></span>
        </p>
        <p class="bb-next__meta">
          <?php if ($next['competition'] !== ''): ?><span class="bb-comp bb-comp--light"><?= e($next['competition']) ?></span><?php endif; ?>
          <?php if ($next['venue'] !== ''): ?><span><?= e($next['venue']) ?></span><?php endif; ?>
        </p>
        <?php if ($next['comment'] !== ''): ?><p class="bb-next__comment"><?= nl2br(e($next['comment']), false) ?></p><?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="bb-section">
      <h2 class="bb-section__title">試合予定</h2>
      <?php if ($upcoming): ?>
        <ul class="bb-list"><?php foreach ($upcoming as $g) echo $row($g); ?></ul>
      <?php elseif (!$next): ?>
        <p class="empty-state">予定されている試合はありません。</p>
      <?php else: ?>
        <p class="muted small">次の試合のあとの予定はまだありません。</p>
      <?php endif; ?>
    </section>

    <section class="bb-section">
      <div class="bb-section__head">
        <h2 class="bb-section__title"><?= $year ?>年の試合結果</h2>
        <?php if (count($years) > 1): ?>
          <form class="bb-year" method="get" action="<?= e($api->url('/baseball')) ?>">
            <label class="visually-hidden" for="bb-y">年</label>
            <select class="form-control" id="bb-y" name="y">
              <?php foreach ($years as $y): ?><option value="<?= $y ?>"<?= $y === $year ? ' selected' : '' ?>><?= $y ?>年</option><?php endforeach; ?>
            </select>
            <button class="btn btn--sm" type="submit">表示</button>
          </form>
        <?php endif; ?>
      </div>

      <dl class="bb-record">
        <div><dt>試合</dt><dd><?= (int) $record['games'] ?></dd></div>
        <div class="bb-record--win"><dt>勝</dt><dd><?= (int) $record['win'] ?></dd></div>
        <div class="bb-record--lose"><dt>敗</dt><dd><?= (int) $record['lose'] ?></dd></div>
        <div class="bb-record--draw"><dt>分</dt><dd><?= (int) $record['draw'] ?></dd></div>
        <div><dt>勝率</dt><dd><?= e(BaseballPlugin::rate($record['rate'])) ?></dd></div>
      </dl>

      <?php if ($results): ?>
        <ul class="bb-list"><?php foreach ($results as $g) echo $row($g); ?></ul>
      <?php else: ?>
        <p class="empty-state">この年の試合結果はまだありません。</p>
      <?php endif; ?>
    </section>
  </div>
  <?= render('front/_sidebar') ?>
</div>

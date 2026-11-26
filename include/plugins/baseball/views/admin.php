<?php
/**
 * @var PluginApi $api
 * @var string $team
 * @var array $games
 * @var array|null $editing
 * @var int $year
 * @var array $years
 * @var array $record
 * @var array $posts
 * @var bool $canTeam
 * @var array $opponents
 * @var array $competitions
 * @var array $venues
 */
// 入力エラーで戻ってきたときは入力値を優先する
$val = static function (string $k, string $def = '') use ($editing): string {
    $o = old($k, "\0");
    if ($o !== "\0") {
        return $o;
    }
    return $editing !== null && $editing[$k] !== null ? (string) $editing[$k] : $def;
};
$status = $val('status', 'scheduled');
?>
<div class="adm-head">
  <h1 class="adm-head__title">試合日程・結果 <span class="muted small">（<?= e($team) ?>）</span></h1>
  <a class="btn" href="<?= e($api->url('/baseball')) ?>" target="_blank" rel="noopener">試合日程を表示 ↗</a>
</div>

<div class="split">
  <section class="panel split__side" id="game-form">
    <h2 class="panel__title"><?= $editing ? '試合を編集・結果を入力' : '試合を登録' ?></h2>
    <form method="post" action="<?= e($api->adminUrl()) ?>" data-bb-form>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
      <input type="hidden" name="y" value="<?= $year ?>">
      <div class="form-grid">
        <div class="form-row">
          <label class="form-label" for="bb-date">日付 <span class="req">必須</span></label>
          <input class="form-control" type="date" id="bb-date" name="game_date" required value="<?= e($val('game_date', date('Y-m-d'))) ?>">
        </div>
        <div class="form-row">
          <label class="form-label" for="bb-time">開始時刻</label>
          <input class="form-control" type="time" id="bb-time" name="start_time" value="<?= e($val('start_time')) ?>">
        </div>
      </div>
      <div class="form-row">
        <label class="form-label" for="bb-opp">対戦相手 <span class="req">必須</span></label>
        <input class="form-control" type="text" id="bb-opp" name="opponent" maxlength="60" required list="bb-opponents" value="<?= e($val('opponent')) ?>">
      </div>
      <div class="form-row">
        <label class="form-label" for="bb-venue">会場</label>
        <input class="form-control" type="text" id="bb-venue" name="venue" maxlength="100" list="bb-venues" value="<?= e($val('venue')) ?>">
      </div>
      <div class="form-row">
        <label class="form-label" for="bb-comp">大会名・種別</label>
        <input class="form-control" type="text" id="bb-comp" name="competition" maxlength="60" list="bb-competitions" placeholder="例: 公式戦、練習試合" value="<?= e($val('competition')) ?>">
      </div>

      <fieldset class="bb-status">
        <legend class="form-label">状態</legend>
        <?php foreach (BaseballPlugin::STATUSES as $k => $label): ?>
          <label class="check"><input type="radio" name="status" value="<?= e($k) ?>"<?= $status === $k ? ' checked' : '' ?>> <?= e($label) ?></label>
        <?php endforeach; ?>
      </fieldset>

      <div class="bb-scores" data-bb-scores>
        <div class="form-row">
          <label class="form-label" for="bb-our"><?= e($team) ?>の得点</label>
          <input class="form-control form-control--short" type="number" id="bb-our" name="our_score" min="0" max="99" inputmode="numeric" value="<?= e($val('our_score')) ?>">
        </div>
        <span class="bb-scores__dash">-</span>
        <div class="form-row">
          <label class="form-label" for="bb-opp-score">相手の得点</label>
          <input class="form-control form-control--short" type="number" id="bb-opp-score" name="opp_score" min="0" max="99" inputmode="numeric" value="<?= e($val('opp_score')) ?>">
        </div>
      </div>
      <p class="form-help" data-bb-score-help>得点は「試合終了」のときに入力します。勝敗は得点から自動で決まります。</p>

      <div class="form-row">
        <label class="form-label" for="bb-comment">コメント</label>
        <textarea class="form-control" id="bb-comment" name="comment" rows="3" maxlength="2000" placeholder="集合時間、試合の感想など"><?= e($val('comment')) ?></textarea>
      </div>
      <div class="form-row">
        <label class="form-label" for="bb-post">試合レポートの記事</label>
        <select class="form-control" id="bb-post" name="post_id">
          <option value="">なし</option>
          <?php $postId = (int) $val('post_id'); foreach ($posts as $p): ?>
            <option value="<?= (int) $p['id'] ?>"<?= $postId === (int) $p['id'] ? ' selected' : '' ?>><?= e(fmt_date($p['published_at'], 'Y/m/d')) ?> <?= e(mb_strimwidth((string) $p['title'], 0, 50, '…')) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="term__actions">
        <button class="btn btn--primary" type="submit"><?= $editing ? '更新' : '登録' ?></button>
        <?php if ($editing): ?><a class="btn" href="<?= e($api->adminUrl('', ['y' => $year])) ?>">キャンセル</a><?php endif; ?>
      </div>
    </form>
    <datalist id="bb-opponents"><?php foreach ($opponents as $v): ?><option value="<?= e($v) ?>"><?php endforeach; ?></datalist>
    <datalist id="bb-venues"><?php foreach ($venues as $v): ?><option value="<?= e($v) ?>"><?php endforeach; ?></datalist>
    <datalist id="bb-competitions"><?php foreach ($competitions as $v): ?><option value="<?= e($v) ?>"><?php endforeach; ?></datalist>
  </section>

  <section class="split__main">
    <div class="bb-admin-head">
      <form method="get" action="<?= e($api->adminUrl()) ?>" class="bb-year">
        <label class="visually-hidden" for="bb-admin-y">年</label>
        <select class="form-control" id="bb-admin-y" name="y">
          <?php foreach ($years as $y): ?><option value="<?= $y ?>"<?= $y === $year ? ' selected' : '' ?>><?= $y ?>年</option><?php endforeach; ?>
        </select>
        <button class="btn btn--sm" type="submit">表示</button>
      </form>
      <p class="bb-admin-record"><?= $year ?>年：<?= (int) $record['games'] ?>試合 <?= (int) $record['win'] ?>勝 <?= (int) $record['lose'] ?>敗 <?= (int) $record['draw'] ?>分（勝率 <?= e(BaseballPlugin::rate($record['rate'])) ?>）</p>
    </div>

    <?php if (!$games): ?>
      <p class="empty-box">この年の試合はまだ登録されていません。</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table bb-admin-table">
          <thead><tr><th>日付</th><th>対戦相手</th><th>結果</th><th class="actions-col"><span class="visually-hidden">操作</span></th></tr></thead>
          <tbody>
            <?php foreach ($games as $g): $res = BaseballPlugin::result($g); ?>
              <tr<?= $editing && (int) $editing['id'] === (int) $g['id'] ? ' class="is-editing"' : '' ?>>
                <td class="nowrap" data-label="日付"><?= e(BaseballPlugin::day($g['game_date'])) ?><?php if ($g['start_time'] !== ''): ?><br><span class="muted small"><?= e($g['start_time']) ?></span><?php endif; ?></td>
                <td class="table__title">
                  vs <?= e($g['opponent']) ?>
                  <?php if ($g['competition'] !== ''): ?><span class="bb-comp"><?= e($g['competition']) ?></span><?php endif; ?>
                </td>
                <td data-label="結果" class="nowrap">
                  <?php if ($res): ?>
                    <span class="bb-res bb-res--<?= e($res) ?>"><?= e(BaseballPlugin::RESULTS[$res]) ?></span>
                    <span class="bb-score"><?= (int) $g['our_score'] ?> - <?= (int) $g['opp_score'] ?></span>
                  <?php elseif ($g['status'] !== 'scheduled'): ?>
                    <span class="bb-res bb-res--off"><?= e(BaseballPlugin::STATUSES[$g['status']]) ?></span>
                  <?php elseif ($g['game_date'] < date('Y-m-d')): ?>
                    <span class="bb-res bb-res--none">結果未入力</span>
                  <?php else: ?>
                    <span class="muted small">予定</span>
                  <?php endif; ?>
                </td>
                <td class="actions">
                  <a class="btn btn--sm" href="<?= e($api->adminUrl('', ['edit' => (int) $g['id'], 'y' => $year])) ?>#game-form"><?= $g['status'] === 'scheduled' && $g['game_date'] <= date('Y-m-d') ? '結果を入力' : '編集' ?></a>
                  <form method="post" action="<?= e($api->adminUrl()) ?>" class="inline-form"
                        data-confirm="<?= e(BaseballPlugin::day($g['game_date'])) ?> vs <?= e($g['opponent']) ?> を削除します。よろしいですか？">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                    <input type="hidden" name="y" value="<?= $year ?>">
                    <button class="btn btn--sm btn--danger" type="submit">削除</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($canTeam): ?>
      <section class="panel bb-team">
        <h2 class="panel__title">チーム名</h2>
        <form method="post" action="<?= e($api->adminUrl()) ?>" class="inline-edit">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="team">
          <label class="visually-hidden" for="bb-team">チーム名</label>
          <input class="form-control" type="text" id="bb-team" name="team" maxlength="50" value="<?= e($team) ?>">
          <button class="btn btn--sm btn--primary" type="submit">保存</button>
        </form>
        <p class="form-help">試合日程のページや得点の欄に表示されます（編集者以上が変更できます）。</p>
      </section>
    <?php endif; ?>
  </section>
</div>

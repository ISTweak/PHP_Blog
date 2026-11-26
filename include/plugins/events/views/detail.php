<?php
/**
 * @var PluginApi $api
 * @var array $event
 * @var array $responses
 * @var array|null $mine
 * @var bool $closed
 */
$user = $api->user();
$current = $mine['status'] ?? old('status');
?>
<div class="layout">
  <div class="layout__main">
    <article class="entry">
      <header class="entry__header">
        <p class="page-heading__label"><a href="<?= e($api->url('/events')) ?>">イベント</a></p>
        <h1 class="entry__title"><?= e($event['title']) ?></h1>
      </header>

      <dl class="ev-info">
        <dt>日時</dt><dd><?= e(EventsPlugin::formatDate($event['starts_at'])) ?></dd>
        <?php if ($event['place'] !== ''): ?><dt>場所</dt><dd><?= e($event['place']) ?></dd><?php endif; ?>
        <dt>回答締切</dt>
        <dd>
          <?= e(EventsPlugin::formatDate($event['deadline'] ?: $event['starts_at'])) ?>
          <?php if ($closed): ?><span class="badge">締め切りました</span><?php endif; ?>
        </dd>
      </dl>

      <?php if ($event['description'] !== ''): ?>
        <div class="entry-content ev-desc"><?= nl2br(e($event['description']), false) ?></div>
      <?php endif; ?>

      <section class="ev-respond" id="respond">
        <h2 class="ev-respond__title">出欠の回答</h2>
        <?php if ($closed): ?>
          <p class="muted">回答の受付は終了しました。</p>
          <?php if ($mine): ?><p>あなたの回答: <strong><?= e(EventsPlugin::STATUSES[$mine['status']]) ?></strong></p><?php endif; ?>
        <?php else: ?>
          <form method="post" action="<?= e($api->url('/events/' . (int) $event['id'] . '/respond')) ?>">
            <?= csrf_field() ?>
            <?php if ($user): ?>
              <p class="muted small"><?= e($user['display_name']) ?> として回答します。</p>
            <?php else: ?>
              <div class="form-row">
                <label class="form-label" for="ev-name">お名前 <span class="req">必須</span></label>
                <input class="form-control" type="text" id="ev-name" name="name" maxlength="50" required value="<?= e($mine['name'] ?? old('name')) ?>">
              </div>
            <?php endif; ?>
            <fieldset class="ev-choice">
              <legend class="form-label">出欠</legend>
              <?php foreach (EventsPlugin::STATUSES as $key => $label): ?>
                <label class="ev-choice__item ev-choice__item--<?= e($key) ?>">
                  <input type="radio" name="status" value="<?= e($key) ?>" required<?= $current === $key ? ' checked' : '' ?>>
                  <span><?= e($label) ?></span>
                </label>
              <?php endforeach; ?>
            </fieldset>
            <div class="form-row">
              <label class="form-label" for="ev-comment">ひとこと</label>
              <input class="form-control" type="text" id="ev-comment" name="comment" maxlength="200" value="<?= e($mine['comment'] ?? old('comment')) ?>" placeholder="遅れて参加します、など">
            </div>
            <div class="ev-respond__actions">
              <button class="btn btn--primary" type="submit"><?= $mine ? '回答を更新' : '回答する' ?></button>
            </div>
          </form>
          <?php if ($mine): ?>
            <form method="post" action="<?= e($api->url('/events/' . (int) $event['id'] . '/cancel')) ?>" class="ev-cancel">
              <?= csrf_field() ?>
              <button class="btn btn--sm btn--danger" type="submit">回答を取り消す</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <section class="ev-result">
        <h2 class="ev-respond__title">回答状況</h2>
        <div class="ev-counts ev-counts--lg">
          <span class="ev-count ev-count--yes">参加 <?= (int) $event['counts']['yes'] ?></span>
          <span class="ev-count ev-count--maybe">未定 <?= (int) $event['counts']['maybe'] ?></span>
          <span class="ev-count ev-count--no">不参加 <?= (int) $event['counts']['no'] ?></span>
        </div>
        <?php if ($responses): ?>
          <ul class="ev-people">
            <?php foreach ($responses as $r): ?>
              <li class="ev-person">
                <span class="ev-count ev-count--<?= e($r['status']) ?>"><?= e(EventsPlugin::STATUSES[$r['status']]) ?></span>
                <span class="ev-person__name"><?= e($r['name']) ?></span>
                <?php if ($r['comment'] !== ''): ?><span class="ev-person__comment"><?= e($r['comment']) ?></span><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted">まだ回答はありません。</p>
        <?php endif; ?>
      </section>
    </article>
  </div>
  <?= render('front/_sidebar') ?>
</div>

<?php
/**
 * @var PluginApi $api
 * @var array $events
 * @var array|null $editing
 */
$toInput = static fn(?string $v): string => $v ? str_replace(' ', 'T', substr($v, 0, 16)) : '';
?>
<div class="adm-head">
  <h1 class="adm-head__title">イベント</h1>
  <a class="btn" href="<?= e($api->url('/events')) ?>" target="_blank" rel="noopener">イベント一覧を表示 ↗</a>
</div>

<div class="split">
  <section class="panel split__side">
    <h2 class="panel__title"><?= $editing ? 'イベントを編集' : 'イベントを登録' ?></h2>
    <form method="post" action="<?= e($api->adminUrl()) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
      <div class="form-row">
        <label class="form-label" for="ev-title">イベント名 <span class="req">必須</span></label>
        <input class="form-control" type="text" id="ev-title" name="title" maxlength="100" required value="<?= e($editing['title'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label class="form-label" for="ev-starts">開催日時 <span class="req">必須</span></label>
        <input class="form-control" type="datetime-local" id="ev-starts" name="starts_at" required value="<?= e($toInput($editing['starts_at'] ?? null)) ?>">
      </div>
      <div class="form-row">
        <label class="form-label" for="ev-place">場所</label>
        <input class="form-control" type="text" id="ev-place" name="place" maxlength="100" value="<?= e($editing['place'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label class="form-label" for="ev-deadline">回答締切</label>
        <input class="form-control" type="datetime-local" id="ev-deadline" name="deadline" value="<?= e($toInput($editing['deadline'] ?? null)) ?>">
        <p class="form-help">空欄の場合は開催日時まで回答できます。</p>
      </div>
      <div class="form-row">
        <label class="form-label" for="ev-desc">説明</label>
        <textarea class="form-control" id="ev-desc" name="description" rows="6" maxlength="5000"><?= e($editing['description'] ?? '') ?></textarea>
      </div>
      <div class="term__actions">
        <button class="btn btn--primary" type="submit"><?= $editing ? '更新' : '登録' ?></button>
        <?php if ($editing): ?><a class="btn" href="<?= e($api->adminUrl()) ?>">キャンセル</a><?php endif; ?>
      </div>
    </form>
  </section>

  <section class="split__main">
    <?php if (!$events): ?>
      <p class="empty-box">イベントはまだありません。</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>イベント</th><th>日時</th><th class="nowrap">参加/未定/不参加</th><th class="actions-col"><span class="visually-hidden">操作</span></th></tr></thead>
          <tbody>
            <?php foreach ($events as $ev): ?>
              <tr>
                <td class="table__title"><?= e($ev['title']) ?></td>
                <td data-label="日時" class="nowrap"><?= e(EventsPlugin::formatDate($ev['starts_at'])) ?></td>
                <td data-label="回答"><?= (int) $ev['counts']['yes'] ?> / <?= (int) $ev['counts']['maybe'] ?> / <?= (int) $ev['counts']['no'] ?></td>
                <td class="actions">
                  <a class="btn btn--sm" href="<?= e($api->url('/events/' . (int) $ev['id'])) ?>" target="_blank" rel="noopener">表示</a>
                  <a class="btn btn--sm" href="<?= e($api->adminUrl('', ['edit' => (int) $ev['id']])) ?>">編集</a>
                  <form method="post" action="<?= e($api->adminUrl()) ?>" class="inline-form"
                        data-confirm="「<?= e($ev['title']) ?>」と回答をすべて削除します。よろしいですか？">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                    <button class="btn btn--sm btn--danger" type="submit">削除</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

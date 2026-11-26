<?php /** @var array $plugins */ ?>
<div class="adm-head">
  <h1 class="adm-head__title">プラグイン</h1>
</div>
<p class="muted small">
  プラグインは <code>include/plugins/</code> にフォルダごと置くと、ここに表示されます。
  無効にしても、プラグインが保存したデータ（イベントや回答など）は消えません。
</p>

<?php if (!$plugins): ?>
  <p class="empty-box">プラグインがありません。</p>
<?php else: ?>
  <div class="plugin-list">
    <?php foreach ($plugins as $p): ?>
      <section class="plugin-card<?= $p['enabled'] ? ' is-enabled' : '' ?>">
        <div class="plugin-card__body">
          <h2 class="plugin-card__name">
            <?= e($p['name']) ?>
            <?php if ($p['version'] !== ''): ?><span class="muted small">v<?= e($p['version']) ?></span><?php endif; ?>
            <?php if ($p['enabled']): ?><span class="badge badge--editor">有効</span><?php endif; ?>
          </h2>
          <?php if ($p['description'] !== ''): ?><p class="plugin-card__desc"><?= e($p['description']) ?></p><?php endif; ?>
          <p class="plugin-card__meta muted small">
            ID: <code><?= e($p['id']) ?></code><?php if ($p['author'] !== ''): ?> ・ 作者: <?= e($p['author']) ?><?php endif; ?>
          </p>
          <?php if ($p['error']): ?>
            <div class="alert alert--error"><?= e($p['error']) ?></div>
          <?php endif; ?>
        </div>
        <div class="plugin-card__actions">
          <?php if ($p['enabled']): ?>
            <form method="post" action="<?= e(url('/admin/plugins/' . $p['id'] . '/disable')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <button class="btn btn--sm" type="submit">無効にする</button>
            </form>
          <?php elseif ($p['valid']): ?>
            <form method="post" action="<?= e(url('/admin/plugins/' . $p['id'] . '/enable')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <button class="btn btn--sm btn--primary" type="submit">有効にする</button>
            </form>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

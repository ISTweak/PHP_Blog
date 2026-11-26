<?php
/**
 * @var array $items
 * @var array $pager
 * @var string $kind
 */
$kinds = ['' => 'すべて', 'image' => '画像', 'video' => '動画', 'file' => 'PDF'];
?>
<div class="adm-head">
  <h1 class="adm-head__title">メディア <span class="muted small">（<?= (int) $pager['total'] ?> 件）</span></h1>
</div>

<div class="uploader" data-uploader>
  <form class="dropzone" method="post" action="<?= e(url('/admin/media/upload')) ?>" enctype="multipart/form-data" data-dropzone>
    <?= csrf_field() ?>
    <p class="dropzone__text">ここにファイルをドラッグ＆ドロップ</p>
    <p class="dropzone__or">または</p>
    <label class="btn btn--primary">
      ファイルを選択
      <input class="visually-hidden" type="file" name="file" multiple data-file-input
             accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime,application/pdf">
    </label>
    <noscript><button class="btn" type="submit">アップロード</button></noscript>
    <p class="dropzone__note">1 ファイル <?= e(format_bytes(MediaService::maxBytes())) ?> まで ・ 画像（JPEG / PNG / GIF / WebP）・動画（MP4 / WebM / MOV）・PDF</p>
  </form>
  <div class="upload-status" data-upload-status aria-live="polite"></div>
</div>

<nav class="tabs" aria-label="種類で絞り込み">
  <?php foreach ($kinds as $k => $label): ?>
    <a class="tabs__item<?= $kind === $k ? ' is-active' : '' ?>" href="<?= e(url('/admin/media', $k !== '' ? ['kind' => $k] : [])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if (!$items): ?>
  <p class="empty-box">メディアはまだありません。</p>
<?php else: ?>
  <ul class="media-grid">
    <?php foreach ($items as $m): ?>
      <li class="media-card">
        <a class="media-card__thumb" href="<?= e(media_url($m['id'])) ?>" target="_blank" rel="noopener">
          <?php if ($m['kind'] === 'image'): ?>
            <img src="<?= e(media_url($m['id'], 'thumb')) ?>" alt="" loading="lazy">
          <?php elseif ($m['kind'] === 'video'): ?>
            <span class="media-card__icon media-card__icon--video">▶<small>VIDEO</small></span>
          <?php else: ?>
            <span class="media-card__icon">PDF</span>
          <?php endif; ?>
        </a>
        <div class="media-card__body">
          <p class="media-card__name" title="<?= e($m['original_name']) ?>"><?= e($m['original_name']) ?></p>
          <p class="media-card__meta">
            <?= e(format_bytes((int) $m['size'])) ?>
            <?php if ($m['width']): ?> ・ <?= (int) $m['width'] ?>×<?= (int) $m['height'] ?><?php endif; ?>
            <br><?= e($m['uploader'] ?? '不明') ?> ・ <?= e(fmt_date($m['created_at'])) ?>
          </p>
          <div class="media-card__actions">
            <button type="button" class="btn btn--sm" data-copy="<?= e('/media/' . (int) $m['id']) ?>">URL をコピー</button>
            <?php if (Auth::canDeleteMedia($m)): ?>
              <form class="inline-form" method="post" action="<?= e(url('/admin/media/' . (int) $m['id'] . '/delete')) ?>"
                    data-confirm="「<?= e($m['original_name']) ?>」を削除します。記事に挿入している場合は表示されなくなります。よろしいですか？">
                <?= csrf_field() ?>
                <button class="btn btn--sm btn--danger" type="submit">削除</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <?= render('partials/pagination', ['pager' => $pager, 'path' => '/admin/media', 'query' => $kind !== '' ? ['kind' => $kind] : []]) ?>
<?php endif; ?>

<?php
// 表示する項目と順番は「サイト設定 → サイドバー」で変更できる（Layout::sidebar()）
$pluginWidgets = Plugins::widgets();
?>
<aside class="sidebar" aria-label="サイドバー">
  <?php foreach (Layout::sidebar() as $block): ?>
    <?php if (!$block['visible']) continue; ?>
    <?php if ($block['core'] !== null): ?>
      <?= render('front/widgets/' . $block['core']) ?>
    <?php elseif (isset($pluginWidgets[$block['key']])): $w = $pluginWidgets[$block['key']]; ?>
      <section class="widget widget--plugin widget--<?= e($w['plugin']) ?>">
        <h2 class="widget__title"><?= e($w['title']) ?></h2>
        <?= $w['html'] ?>
      </section>
    <?php endif; ?>
  <?php endforeach; ?>
</aside>

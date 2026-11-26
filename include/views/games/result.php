<?php
/**
 * ゲーム終了後の結果
 * @var MiniGame $g
 * @var array $r score / stage / gametime
 * @var string|null $variant
 * @var int $rank 今月の順位（記録した場合）
 * @var int $rankAll これまでの順位（記録した場合）
 * @var bool $rankIn
 * @var array|null $user
 * @var string $guestName
 * @var array $rows
 */
$month = '今月の' . ($variant !== null ? e($g->variantLabel($variant)) . 'の' : '') . 'ランキング';
?>
<div class="layout">
  <div class="layout__main">
    <?= render('games/_tabs', ['g' => $g, 'current' => 'result']) ?>

    <section class="mg-result" aria-label="今回の結果">
      <p class="mg-result__label">今回の結果<?= $variant !== null ? '（' . e($g->variantLabel($variant)) . '）' : '' ?></p>
      <p class="mg-result__score"><?= number_format($r['score']) ?><span><?= e($g->cfg['unit']) ?></span></p>
      <p class="mg-result__meta">
        <?php if ($variant === null && $g->cfg['stageLabel'] !== null): ?>
          <span><?= e($g->cfg['stageLabel']) ?> <strong><?= number_format($r['stage']) ?></strong></span>
        <?php endif; ?>
        <span>プレイ時間 <strong><?= e(MiniGame::time($r['gametime'])) ?></strong></span>
      </p>
    </section>

    <?php if (!$rankIn): ?>
      <p class="alert alert--info"><?= $month ?>（上位 <?= MiniGame::RANK ?> 位）には届きませんでした。またチャレンジしてください！</p>
    <?php else: ?>
      <div class="alert alert--success">
        おめでとうございます！ 今月の <strong><?= $rank ?> 位</strong>（これまでの <?= $rankAll ?> 位）に入りました。
        <?= $user ? 'ひとことコメントを付けて記録できます。' : '名前を入れて記録できます。' ?>
      </div>
      <form class="mg-save" method="post" action="<?= e($g->url('/save')) ?>">
        <?= csrf_field() ?>
        <?php if (!$user): ?>
          <div class="mg-save__field mg-save__field--name">
            <label class="form-label" for="mg-name">名前 <span class="req">必須</span></label>
            <input class="form-control" id="mg-name" type="text" name="name" maxlength="<?= MiniGame::NAME_MAX ?>" required
                   value="<?= e(old('name', $guestName)) ?>" autocomplete="nickname">
          </div>
        <?php endif; ?>
        <div class="mg-save__field">
          <label class="form-label" for="mg-message">コメント</label>
          <input class="form-control" id="mg-message" type="text" name="message" maxlength="40"
                 value="<?= e(old('message')) ?>" placeholder="ひとこと（40 文字まで・省略可）">
        </div>
        <button class="btn btn--primary" type="submit">ランキングに記録する</button>
      </form>
      <p class="muted small">
        記録する前に他の人がもっと良い成績を出すと、ランキングから外れることがあります。
        <?php if (!$user): ?>ログインしていない人の記録には「ゲスト」と表示されます。<?php endif; ?>
      </p>
    <?php endif; ?>

    <p class="mg-again">
      <a class="btn btn--primary" href="<?= e($g->url()) ?>">もう一度遊ぶ</a>
      <a class="btn" href="<?= e($g->url('/ranking', $variant !== null ? ['v' => $variant] : [])) ?>">ランキングを見る</a>
    </p>

    <h2 class="mg-subtitle"><?= $month ?></h2>
    <?= render('games/_table', ['g' => $g, 'rows' => $rows]) ?>
  </div>
  <?= render('front/_sidebar') ?>
</div>

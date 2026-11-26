<?php
/**
 * @var int $code
 * @var string $message
 */
?>
<div class="error-page">
  <p class="error-page__code"><?= (int) $code ?></p>
  <p class="error-page__message"><?= e($message) ?></p>
  <p><a class="btn" href="<?= e(url('/')) ?>">トップページへ戻る</a></p>
</div>

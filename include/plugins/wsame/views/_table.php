<?php
/**
 * ランキング表
 * @var WsameGame $g
 * @var array $rows
 */
?>
<?php if ($rows): ?>
  <div class="mg-table-wrap">
    <table class="mg-table">
      <thead>
        <tr>
          <th class="mg-table__rank">順位</th>
          <th>名前</th>
          <th class="mg-table__num">スコア</th>
          <th class="mg-table__num"><?= e($g->cfg['stageLabel']) ?></th>
          <th class="mg-table__num">時間</th>
          <th>コメント</th>
          <th>日付</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $row): ?>
          <tr<?= $i < 3 ? ' class="mg-top' . ($i + 1) . '"' : '' ?>>
            <td class="mg-table__rank"><?= $i + 1 ?></td>
            <td class="mg-table__name"><?= e($row['name']) ?></td>
            <td class="mg-table__num"><strong><?= number_format((int) $row['score']) ?></strong></td>
            <td class="mg-table__num"><?= number_format((int) $row['stage']) ?></td>
            <td class="mg-table__num"><?= e(WsameGame::time((int) $row['gametime'])) ?></td>
            <td class="mg-table__msg"><?= e($row['message']) ?></td>
            <td class="nowrap"><?= e(fmt_date($row['created_at'], 'Y/n/j')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <p class="empty-state">まだ記録はありません。</p>
<?php endif; ?>

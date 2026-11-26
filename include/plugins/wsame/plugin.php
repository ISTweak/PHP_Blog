<?php
/**
 * プラグイン：さめがめ（ミニゲーム）
 *
 * - /games/wsame で遊べる。同じ色のコマが 2 つ以上つながっているところを消していくパズル
 * - ゲームが終わったら、ログインしている人は今月の上位 20 位に入るとコメント付きでランキングに記録できる
 * - /games/wsame/ranking で今月／これまでのランキング。サイドバーに今月の上位 3 人
 */
declare(strict_types=1);

return [
    'name'        => 'さめがめ',
    'version'     => '1.0.0',
    'description' => '同じ色のコマをまとめて消していくパズルゲームです。ランキングつき（記録できるのはログインした人）。',
    'author'      => 'PHP_Blog',

    'install' => static function (PluginApi $api): void {
        require_once __DIR__ . '/WsameGame.php';
        WsameGame::install($api);
    },

    'boot' => static function (PluginApi $api): void {
        require_once __DIR__ . '/WsameGame.php';
        (new WsameGame($api, [
            'title'      => 'さめがめ',
            'path'       => '/games/wsame',
            'stageLabel' => 'ステージ',
            'maxScore'   => 99_999_999,
            'maxStage'   => 9_999,
            'order'      => 91,
        ]))->register();
    },
];

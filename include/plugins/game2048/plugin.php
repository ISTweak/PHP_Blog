<?php
/**
 * プラグイン：2048（ミニゲーム）
 *
 * - /games/2048 で遊べる。4×4 のマスで同じ数字のタイルを合体させていくパズル
 * - ゲームが終わったら、ログインしている人は今月の上位 20 位に入るとコメント付きでランキングに記録できる
 * - /games/2048/ranking で今月／これまでのランキング。サイドバーに今月の上位 3 人
 */
declare(strict_types=1);

return [
    'name'        => '2048',
    'version'     => '1.0.0',
    'description' => '同じ数字のタイルを合体させて 2048 を目指すパズルゲームです。ランキングつき（記録できるのはログインした人）。',
    'author'      => 'PHP_Blog',

    'install' => static function (PluginApi $api): void {
        require_once __DIR__ . '/Game2048Game.php';
        Game2048Game::install($api);
    },

    'boot' => static function (PluginApi $api): void {
        require_once __DIR__ . '/Game2048Game.php';
        (new Game2048Game($api, [
            'title'      => '2048',
            'path'       => '/games/2048',
            'stageLabel' => '最大タイル',
            'maxScore'   => 99_999_999,
            'maxStage'   => 1 << 20,
            'order'      => 92,
        ]))->register();
    },
];

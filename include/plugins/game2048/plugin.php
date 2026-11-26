<?php
/**
 * プラグイン：2048（ミニゲーム）
 *
 * - /games/2048 で遊べる。4×4 のマスで同じ数字のタイルを合体させていくパズル
 * - ゲームが終わったら、今月の上位 20 位に入るとコメント付きでランキングに記録できる
 * - /games/2048/ranking で今月／これまでのランキング。サイドバーに今月の上位 3 人
 */
declare(strict_types=1);

return [
    'name'        => '2048',
    'version'     => '1.1.0',
    'description' => '同じ数字のタイルを合体させて 2048 を目指すパズルゲームです。今月／これまでのランキングつき。',
    'author'      => 'PHP_Blog',

    'install' => static fn(PluginApi $api) => MiniGame::install($api),

    'boot' => static function (PluginApi $api): void {
        (new MiniGame($api, [
            'title'      => '2048',
            'path'       => '/games/2048',
            'stageLabel' => '最大タイル',
            'maxScore'   => 99_999_999,
            'maxStage'   => 1 << 20,
            'order'      => 92,
        ]))->register();
    },
];

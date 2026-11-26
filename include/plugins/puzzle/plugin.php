<?php
/**
 * サンプルプラグイン：15 パズル（ミニゲーム）
 *
 * - /games/puzzle でスライドパズルが遊べる（3×3 / 4×4 / 5×5）
 * - 完成したら、今月の上位 20 位に入るとランキングに記録できる（サイズごと。手数が少ないほど上位）
 * - /games/puzzle/ranking で今月／これまでのランキング。サイドバーに 4×4 の今月の上位 3 人
 */
declare(strict_types=1);

return [
    'name'        => '15 パズル',
    'version'     => '1.1.0',
    'description' => 'ちょっとした息抜き用のスライドパズルです。サイズごとの手数ランキングつき。',
    'author'      => 'PHP_Blog',

    'install' => static fn(PluginApi $api) => MiniGame::install($api),

    'boot' => static function (PluginApi $api): void {
        (new MiniGame($api, [
            'title'          => '15 パズル',
            'path'           => '/games/puzzle',
            'scoreLabel'     => '手数',
            'unit'           => '手',
            'lowerIsBetter'  => true,
            'variants'       => ['3' => '3×3', '4' => '4×4', '5' => '5×5'],
            'defaultVariant' => '4',
            'maxScore'       => 999_999,
            'order'          => 90,
        ]))->register();
    },
];

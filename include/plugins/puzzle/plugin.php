<?php
/**
 * サンプルプラグイン：15 パズル（ミニゲーム）
 *
 * - /games/puzzle でスライドパズルが遊べる（3×3 / 4×4 / 5×5）
 * - 自己ベストはブラウザに保存
 * - サイドバーにゲームへのリンクを表示
 */
declare(strict_types=1);

return [
    'name'        => '15 パズル',
    'version'     => '1.0.0',
    'description' => 'ちょっとした息抜き用のスライドパズルです。プラグインで JavaScript を使ったページを追加する例でもあります。',
    'author'      => 'PHP_Blog',

    'boot' => static function (PluginApi $api): void {
        $api->navLink('ミニゲーム', '/games/puzzle');

        $api->get('/games/puzzle', static function () use ($api): void {
            $api->addStyle('puzzle.css');
            $api->addScript('puzzle.js');
            $api->view('game', [], '15 パズル');
        });

        $api->widget('ミニゲーム', static fn(): string => $api->render('widget'), 90);
    },
];

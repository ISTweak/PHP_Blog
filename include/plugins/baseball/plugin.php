<?php
/**
 * プラグイン：試合日程・結果（野球）
 *
 * - /baseball で試合予定と試合結果（年ごと・勝敗数と勝率）を表示
 * - サイドバーに次の試合・直近の結果・今年の成績
 * - 管理画面「試合日程・結果」で試合の登録と結果の入力（投稿者以上）
 */
declare(strict_types=1);

return [
    'name'        => '試合日程・結果',
    'version'     => '1.0.0',
    'description' => '野球の試合予定と試合結果（得点・勝敗）を管理します。年ごとの成績とサイドバーの「次の試合」表示つき。',
    'author'      => 'PHP_Blog',

    'install' => static function (PluginApi $api): void {
        $t = $api->table('games');
        $api->db()->exec("
            CREATE TABLE IF NOT EXISTS $t (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                game_date   TEXT    NOT NULL,
                start_time  TEXT    NOT NULL DEFAULT '',
                opponent    TEXT    NOT NULL,
                venue       TEXT    NOT NULL DEFAULT '',
                competition TEXT    NOT NULL DEFAULT '',
                status      TEXT    NOT NULL DEFAULT 'scheduled'
                            CHECK (status IN ('scheduled', 'final', 'cancelled', 'postponed')),
                our_score   INTEGER,
                opp_score   INTEGER,
                comment     TEXT    NOT NULL DEFAULT '',
                post_id     INTEGER,
                created_by  INTEGER,
                created_at  TEXT    NOT NULL,
                updated_at  TEXT    NOT NULL
            );
            CREATE INDEX IF NOT EXISTS {$t}_date ON $t (game_date, start_time);
        ");
    },

    'boot' => static function (PluginApi $api): void {
        require_once __DIR__ . '/BaseballPlugin.php';
        (new BaseballPlugin($api))->register();
    },
];

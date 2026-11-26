<?php
/**
 * サンプルプラグイン：イベント出欠
 *
 * - /events          イベント一覧
 * - /events/{id}     詳細と出欠の回答（参加・未定・不参加）
 * - 管理画面「イベント」で登録・編集（編集者以上）
 * - サイドバーに直近のイベントを表示
 */
declare(strict_types=1);

return [
    'name'        => 'イベント出欠',
    'version'     => '1.0.0',
    'description' => 'イベントを登録して、参加・未定・不参加の回答を集めます。サイドバーに直近のイベントを表示します。',
    'author'      => 'PHP_Blog',

    'install' => static function (PluginApi $api): void {
        $events = $api->table('events');
        $responses = $api->table('responses');
        $api->db()->exec("
            CREATE TABLE IF NOT EXISTS $events (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                title       TEXT    NOT NULL,
                starts_at   TEXT    NOT NULL,
                place       TEXT    NOT NULL DEFAULT '',
                description TEXT    NOT NULL DEFAULT '',
                deadline    TEXT,
                created_by  INTEGER,
                created_at  TEXT    NOT NULL,
                updated_at  TEXT    NOT NULL
            );
            CREATE INDEX IF NOT EXISTS {$events}_starts ON $events (starts_at);
            CREATE TABLE IF NOT EXISTS $responses (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id    INTEGER NOT NULL REFERENCES $events (id) ON DELETE CASCADE,
                user_id     INTEGER,
                guest_token TEXT,
                name        TEXT    NOT NULL,
                status      TEXT    NOT NULL CHECK (status IN ('yes', 'maybe', 'no')),
                comment     TEXT    NOT NULL DEFAULT '',
                created_at  TEXT    NOT NULL,
                updated_at  TEXT    NOT NULL,
                UNIQUE (event_id, user_id),
                UNIQUE (event_id, guest_token)
            );
        ");
    },

    'boot' => static function (PluginApi $api): void {
        require_once __DIR__ . '/EventsPlugin.php';
        (new EventsPlugin($api))->register();
    },
];

<?php
declare(strict_types=1);

/**
 * サイドバーの項目の並び順・表示と、ヘッダーに出すプラグインのリンクの設定
 * （管理画面「サイト設定」で変更し、settings テーブルに保存する）
 */
final class Layout
{
    /** 本体のサイドバー項目（キー => [テンプレート名, 表示名]）。この順が初期値 */
    private const CORE_BLOCKS = [
        'core:recent'     => ['recent', '最近の投稿'],
        'core:categories' => ['categories', 'カテゴリー'],
        'core:tags'       => ['tags', 'タグ'],
    ];

    /**
     * サイドバーの項目を設定どおりの順で返す。
     * 設定に無い項目（後から有効にしたプラグインなど）は末尾に表示ありで追加する。
     *
     * @return list<array{key:string,title:string,source:string,core:?string,visible:bool}>
     */
    public static function sidebar(): array
    {
        $defs = [];
        foreach (self::CORE_BLOCKS as $key => [$tpl, $title]) {
            $defs[$key] = ['key' => $key, 'title' => $title, 'source' => '本体', 'core' => $tpl];
        }
        foreach (Plugins::widgetDefs() as $w) {
            $defs[$w['key']] = ['key' => $w['key'], 'title' => $w['title'], 'source' => $w['pluginName'], 'core' => null];
        }

        $saved = json_decode(App::setting('layout.sidebar') ?: '[]', true);
        $out = [];
        foreach (is_array($saved) ? $saved : [] as $item) {
            $key = is_array($item) ? (string) ($item['key'] ?? '') : '';
            if (isset($defs[$key])) {
                $out[] = $defs[$key] + ['visible' => !empty($item['visible'])];
                unset($defs[$key]);
            }
        }
        foreach ($defs as $d) {
            $out[] = $d + ['visible' => true];
        }
        return $out;
    }

    /**
     * @param list<string> $order   表示順に並んだキー
     * @param list<string> $visible 表示するキー
     */
    public static function saveSidebar(array $order, array $visible): void
    {
        $known = array_column(self::sidebar(), 'key');
        $items = [];
        foreach ($order as $key) {
            if (in_array($key, $known, true) && !in_array($key, array_column($items, 'key'), true)) {
                $items[] = ['key' => $key, 'visible' => in_array($key, $visible, true)];
            }
        }
        // 送られてこなかった項目は順番を保ったまま後ろに
        foreach ($known as $key) {
            if (!in_array($key, array_column($items, 'key'), true)) {
                $items[] = ['key' => $key, 'visible' => in_array($key, $visible, true)];
            }
        }
        App::saveSetting('layout.sidebar', json_encode($items, JSON_UNESCAPED_UNICODE));
    }

    /** ヘッダーで非表示にしたリンクのキー */
    public static function hiddenNav(): array
    {
        $v = json_decode(App::setting('layout.nav_hidden') ?: '[]', true);
        return is_array($v) ? array_values(array_filter($v, 'is_string')) : [];
    }

    /** @param list<string> $shown ヘッダーに表示するリンクのキー */
    public static function saveNav(array $shown): void
    {
        $hidden = [];
        foreach (Plugins::navDefs() as $link) {
            if (!in_array($link['key'], $shown, true)) {
                $hidden[] = $link['key'];
            }
        }
        App::saveSetting('layout.nav_hidden', json_encode($hidden, JSON_UNESCAPED_UNICODE));
    }
}

# プラグインの作り方

`include/plugins/{ID}/plugin.php` を置くと、管理画面の「プラグイン」に表示されます。
「有効にする」を押すと動き始めます。無効にしても、プラグインが保存したデータは消えません。

- ID（フォルダ名）は半角英小文字・数字・`-`・`_` で 40 文字以内
- プラグインは PHP のコードなので、**信頼できるものだけ**を置いてください（管理画面からはアップロードできません）

## フォルダ構成

```
include/plugins/my-plugin/
├─ plugin.php        必須。設定と処理を return する
├─ views/            テンプレート（$api->view() / render() で使う）
└─ assets/           CSS・JS・画像（/plugin-assets/my-plugin/... で配信される）
```

## plugin.php の最小例

```php
<?php
declare(strict_types=1);

return [
    'name'        => 'こんにちは',
    'version'     => '1.0.0',
    'description' => 'サンプル',
    'author'      => 'あなたの名前',

    // 任意：有効にしたとき 1 回だけ呼ばれる（テーブル作成など。何度呼ばれても大丈夫な書き方に）
    'install' => static function (PluginApi $api): void {
        $api->db()->exec('CREATE TABLE IF NOT EXISTS ' . $api->table('items') . ' (id INTEGER PRIMARY KEY, name TEXT)');
    },

    // 必須：有効な間、毎リクエスト呼ばれる。ここでページやウィジェットを登録する
    'boot' => static function (PluginApi $api): void {
        $api->navLink('こんにちは', '/hello');
        $api->get('/hello', static function () use ($api): void {
            $api->view('hello', ['name' => 'World'], 'こんにちは');   // views/hello.php を表示
        });
    },
];
```

## PluginApi でできること

| メソッド | 内容 |
|---|---|
| `get($path, $fn, $access)` / `post(...)` | 閲覧側のページを追加。`/events/{id}` の `{id}` は `$fn(string $id)` に渡る。POST は CSRF トークンを自動で確認 |
| `adminPage($slug, $label, $fn, $role)` | 管理画面にページを追加（`/admin/plugin/{ID}/{slug}`）。メニューにも出る。GET と POST の両方で呼ばれる |
| `widget($title, $fn, $order, $access)` | サイドバーにウィジェットを追加。`$fn` は HTML を返す（空文字なら非表示） |
| `navLink($label, $path, $access)` | ヘッダーのメニューにリンクを追加 |
| `view($tpl, $vars, $title)` / `adminView(...)` | `views/{$tpl}.php` をサイト／管理画面のレイアウトで表示して終了 |
| `render($tpl, $vars)` | テンプレートを文字列で返す（ウィジェット用） |
| `addStyle($file)` / `addScript($file)` | そのページに `assets/` の CSS・JS を読み込む |
| `url($path)` / `adminUrl($slug)` / `asset($file)` | 設置パス付きの URL |
| `db()` / `table($name)` | PDO と、他とぶつからないテーブル名（`plg_{ID}_{name}`） |
| `setting($key)` / `saveSetting($key, $value)` | プラグイン専用の設定値 |
| `user()` / `can($access)` | ログイン中のユーザーと権限の確認 |

`$access` / `$role` には `'public'`（誰でも）・`'login'`（ログインした人）・`'author'`・`'editor'`・`'admin'` を指定します。
会員限定サイトでは、`'public'` でもログインしていない人は見られません。

テンプレートの中では `$api` が使えます。本体のヘルパー（`e()` でのエスケープ、`csrf_field()`、`flash()`、`redirect()`、`db_all()` など）もそのまま使えます。
**出力するときは必ず `e()` でエスケープしてください。**

## 注意

- `/admin`・`/post`・`/media`・`/login` など本体が使っているパスは登録できません。
- 本体のページの見た目に合わせたいときは、`page-heading`・`entry`・`btn`・`form-control`・`widget-list` などの本体の CSS クラスが使えます。
- インラインの `<script>` や `style` 属性は CSP で禁止されています。JS・CSS は `assets/` に置いて `addScript()` / `addStyle()` で読み込んでください。

## 付属のサンプル

- `events/` … イベント出欠（ページ・管理画面・ウィジェット・メニュー・独自テーブル）
- `puzzle/` … 15 パズル（手数が少ないほど上位・サイズごとのランキング）
- `baseball/` … 試合日程・結果（独自テーブル・管理画面での入力・プラグインの設定値・管理画面用の JS）
- `wsame/`・`game2048/` … さめがめ・2048（スコアが高いほど上位。さめがめは画像も assets/ から配信）

### ミニゲームのランキング（MiniGame）

`puzzle`・`wsame`・`game2048` は本体の `MiniGame` クラス（`include/lib/MiniGame.php`）を使っています。
ゲームの画面（`views/game.php`）と `assets/game.js` を用意し、ゲームが終わったら `score`・`stage`・`gametime`（秒）を
`{path}/end` に POST するだけで、結果画面・今月／これまでのランキング・サイドバーのウィジェットが使えます。
ランキングには、ログインしていない人も名前を入れて記録できます。設定の項目は `MiniGame` のコメントを参照してください。

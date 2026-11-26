# ブログシステム

PHP 8.3 + SQLite のシンプルなブログです。フレームワークは使っていません。JavaScript はバニラ、CSS も独自に書いています。
同じプログラムを 2 つのサーバーに置き、設定ファイルで次のどちらかとして動かします。

| site_mode | 用途 | 閲覧 | ログインできる人 |
|---|---|---|---|
| `public`  | 一般公開サイト | 誰でも | 管理者（ローカルアカウント）のみ |
| `private` | 会員限定サイト | ログインした人のみ | 管理者（ローカル）と AD ユーザー |

## ディレクトリ構成

```
blog/
├─ README.md            このファイル
├─ LICENSE              MIT ライセンス
├─ public/              ← ドキュメントルート（ここだけを Web に公開する）
│  ├─ index.php         すべてのリクエストを受けるフロントコントローラー
│  ├─ .htaccess
│  └─ assets/           CSS・JavaScript
└─ include/             ← Web から見えない場所（public 以外はすべてここ）
   ├─ config.php        設定（config.sample.php をコピーして作成）
   ├─ bootstrap.php
   ├─ schema.sql        DB スキーマ（初回アクセス時に自動作成・更新）
   ├─ lib/              共通クラス
   ├─ controllers/      画面ごとの処理
   ├─ views/            HTML テンプレート
   ├─ plugins/          プラグイン（作り方は plugins/README.md）
   └─ data/             ← 自動作成。サーバー移行時はこのフォルダごとコピー
      ├─ blog.sqlite    データベース
      ├─ backup/        DB のバックアップ
      ├─ uploads/       アップロードしたファイル（PHP 経由で配信）
      ├─ sessions/      ログインセッション
      └─ logs/          エラーログ
```

`include/` には念のため `Require all denied` の .htaccess を置いていますが（中の data/ も含めて保護されます）、
**Apache のドキュメントルートは必ず `public/` にしてください。**

## 必要な環境

- PHP 8.3 以上（拡張: `pdo_sqlite` `mbstring` `fileinfo`、会員限定サイトは `ldap`、サムネイル生成に `imagick`）
- Apache 2.4（`mod_rewrite` を有効にし、`AllowOverride All`）

### Apache の設定例

```apache
<VirtualHost *:80>
    ServerName blog.example.local
    DocumentRoot "E:/path/to/blog/public"
    <Directory "E:/path/to/blog/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### php.ini

アップロード上限が 10MB なので、次の値以上にしてください（mod_php の場合は public/.htaccess で設定済み）。

```ini
upload_max_filesize = 12M
post_max_size = 16M
```

## 初回セットアップ

1. `include/config.sample.php` を `include/config.php` にコピーします。
2. `site_mode`（`public` / `private`）と `setup_key`（ランダムな文字列）を設定します。
3. Apache を実行するユーザーが `include/data/` に書き込めるようにします。
4. ブラウザでサイトを開くと初期設定画面（`/setup`）が出るので、セットアップキーを入力して最初の管理者を作成します。
5. 作成が終わったら、`setup_key` は空にしておくことをおすすめします。

管理画面は `/admin` です。ダッシュボードの「動作環境チェック」で、PHP の拡張や書き込み権限に問題がないか確認できます。

## ユーザーとロール

| ロール | できること |
|---|---|
| 管理者 | すべて（ユーザー管理・サイト設定を含む）。ローカルアカウント |
| 編集者 | すべての記事の編集・削除、カテゴリー・タグ・コメントの管理 |
| 投稿者 | 自分の記事の作成・編集・削除、メディアのアップロード |

- AD ユーザーは初めてログインしたときに **投稿者** として自動で登録されます（ユーザー登録画面はありません）。
- 編集者にしたい場合は、管理者が「ユーザー」画面でロールを変更します。ログイン前に事前登録しておくこともできます。
- ユーザーを「無効」にすると、AD の認証に成功してもログインできなくなります。

### AD（LDAP）認証の設定

`config.php` の `ldap` で設定します。`ユーザー名@domain` で AD にバインドしてパスワードを確認し、
表示名とメールアドレスを AD から取得します。

- ログインできる人を特定のグループに限定したい場合は、`required_group` にグループの DN を設定します（入れ子のグループにも対応）。
- **389 番（`ldap://`）のままだと、パスワードが暗号化されずにネットワークを流れます。**
  本番では `use_ssl => true`（636 番）か `start_tls => true` を使ってください。
  DC 側で「LDAP 署名が必須」に設定されている場合も、暗号化しないとバインドが拒否されます。
- LDAPS で証明書エラーになる場合は、社内 CA の証明書を OpenLDAP に登録する必要があります
  （Windows 版 PHP は `C:\OpenLDAP\sysconf\ldap.conf` に `TLS_CACERT` を書きます）。

## 記事の書き方

WordPress のクラシックエディター（テキストモード）に近い書き方です。

- 空行で段落（`<p>`）が分かれ、段落の中の改行はそのまま `<br>` になります。
- ツールバーのボタンでタグを挿入できます。文字を選択してからボタンを押すと、選択した部分をタグで囲みます。
- 「メディアを追加」から、画像・動画・PDF をアップロードして本文に挿入できます（ドラッグ＆ドロップにも対応）。
- YouTube の URL を **1 行に単独で** 書くと、動画プレーヤーとして埋め込まれます。
- 「プレビュー」タブで、実際の表示を確認できます。Ctrl+S（Mac は ⌘+S）で保存できます。
- 安全のため、`<script>`・`style` 属性・イベント属性（`onclick` など）は表示時に取り除かれます。
  `<iframe>` は YouTube のみ使えます。

下書き・予約投稿はありません。保存した記事はすぐに公開されます。

## メディア

- 画像（JPEG / PNG / GIF / WebP）、動画（MP4 / WebM / MOV）、PDF に対応しています。1 ファイル 10MB までです。
- 画像は Imagick で向きを直し、位置情報などの EXIF を削除したうえで、サムネイル（最大 600px）と
  中サイズ（最大 1280px）を作成します。
- ファイルは `include/data/uploads/年/月/` に推測できない名前で保存し、`/media/{id}` から配信します。
  会員限定サイトでは、ログインしていない人はメディアも見られません。
- 本文には `/media/12/medium` の形で保存しています。設置パスが変わっても、表示するときに正しい URL に直します。

## バックアップ

記事を保存・削除するたびに、`include/data/backup/blog-YYYYMMDD-HHMMSS-xxxx.sqlite` を作成します
（SQLite の `VACUUM INTO` を使うので、書き込み中でも壊れていないコピーになります）。
保持する世代数は `backup_keep`（初期値 30）です。

復元するときは、Web サーバーを止めてから、バックアップを `include/data/blog.sqlite` に上書きコピーします。
アップロードしたファイル（`include/data/uploads/`）は別途バックアップしてください。

## プラグイン

`include/plugins/` にフォルダを置き、管理画面の「プラグイン」（管理者のみ）で有効にすると使えます。
プラグインはページ・管理画面・サイドバーのウィジェット・メニューのリンクを追加できます。
作り方は [include/plugins/README.md](include/plugins/README.md) を参照してください。

付属のサンプル:

| プラグイン | 内容 |
|---|---|
| イベント出欠（`events`） | 管理画面（編集者以上）でイベントを登録し、`/events` で参加・未定・不参加を回答。サイドバーに直近のイベントを表示 |
| 15 パズル（`puzzle`） | `/games/puzzle` で遊べるスライドパズル。自己ベストはブラウザに保存 |

## セキュリティ対策

- すべての POST に CSRF トークンを付けています。
- ログインに 15 分間で 5 回（同じ IP からは 20 回）失敗すると、一時的にロックします。
- パスワードは `password_hash()` で保存しています。
- Content-Security-Policy でインラインスクリプトを禁止しています。
- 会員限定サイトは `noindex` を出力し、検索エンジンに登録されないようにしています。

## ライセンス

[MIT License](LICENSE)

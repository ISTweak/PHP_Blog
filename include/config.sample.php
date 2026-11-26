<?php
/**
 * 設定ファイルのサンプル。
 * このファイルを config.php にコピーし、環境に合わせて書き換えてください。
 */
declare(strict_types=1);

return [
    // 'public'  : 誰でも閲覧できるサイト（ログインは管理者のローカルアカウントのみ）
    // 'private' : ログインした人だけが閲覧できるサイト（ADユーザーがログイン可能）
    'site_mode' => 'public',

    // サイト名・説明の初期値（管理画面の「設定」で変更可能）
    'site_name'        => 'ブログ',
    'site_description' => '',

    // 1ページに表示する記事数の初期値（管理画面の「設定」で変更可能）
    'posts_per_page' => 10,

    'timezone' => 'Asia/Tokyo',

    // true にするとエラー内容を画面に表示します（本番では必ず false）
    'debug' => false,

    // 初回セットアップ（最初の管理者作成）時に入力するキー。
    // 推測されにくいランダムな文字列にしてください。セットアップ後は空にしても構いません。
	// echo bin2hex(random_bytes(16));
    'setup_key' => 'change-me',

    // データベース・バックアップ
    'db_path'     => DATA . '/blog.sqlite',
    'backup_dir'  => DATA . '/backup',
    'backup_keep' => 30,          // 保持するバックアップの世代数

    // アップロード
    'upload_dir'       => DATA . '/uploads',
    'upload_max_bytes' => 10 * 1024 * 1024,   // 10MB

    // セッション
    'session_name'     => 'BLOGSESSID',
    'session_lifetime' => 60 * 60 * 8,         // 無操作で自動ログアウトするまでの秒数

    // AD（LDAP）認証。site_mode が 'private' のときだけ使われます。
    'ldap' => [
        'enabled'   => true,
        'host'      => '',
        'port'      => 389,          // LDAPS の場合は 636
        'use_ssl'   => false,        // true: ldaps:// で接続（推奨）
        'start_tls' => false,        // true: 389 番で StartTLS を使用
        'domain'    => '',          // ユーザー名@domain でバインドします
        'base_dn'   => '',
        // ログインを特定グループのメンバーに限定する場合はグループの DN を指定（入れ子グループも可）
        // 例: 'CN=Blog-Users,OU=Groups,DC=domain,DC=local'
        'required_group' => '',
        'timeout'   => 5,
    ],
];

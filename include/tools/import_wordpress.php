<?php
/**
 * WordPress のエクスポート（WXR 形式の XML）を取り込む
 *
 * 使い方（コマンドライン）:
 *   php include/tools/import_wordpress.php export.xml --uploads=wp-content/uploads [オプション]
 *
 * オプション:
 *   --uploads=DIR    WordPress の wp-content/uploads フォルダ（画像・動画も取り込む場合）
 *   --nextgen=DIR    NextGEN Gallery の写真フォルダ（uploads/ngg-originals や wp-content/gallery）。
 *                    [imagely id="N"] などをギャラリー N の写真一覧に変換する
 *   --attachment-map=FILE  メディアライブラリから消えた添付ファイルの対応表（1 行に「ID,uploads からの相対パス」）。
 *                    [gallery ids="..."] の画像が XML に無いときに、uploads に残ったファイルを使う
 *   --create-users   ブログに該当ユーザーがいない投稿者を、AD ユーザー（投稿者ロール）として事前登録する
 *                    （WordPress のログイン名＝AD のログオン名と想定）。付けない場合は --author のユーザーの記事になる
 *   --author=NAME    投稿者が見つからないときに使うブログのユーザー名（省略時は最初の管理者）
 *   --reconvert      取り込み済みの記事の本文を作り直す（ブログで編集していない記事のみ。
 *                    後からメディアを追加で取り込めたときに使う）
 *   --dry-run        内容の確認だけ行い、何も書き込まない
 *
 * 取り込むもの: 公開済みの投稿、カテゴリー（1 記事 1 カテゴリー。残りはタグ）、タグ、承認済みコメント、
 *              アイキャッチ、本文中の画像・動画・ファイル（記事から使われているものだけ）
 * 本文の変換: [caption] [gallery] [video] ショートコード、ブロックエディターのコメント、画像の URL
 *
 * 何度実行しても重複しません（取り込み済みの記事・メディアは飛ばします）。
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/* ------------------------------------------------------------------
 * 引数
 * ------------------------------------------------------------------ */

$xmlFile = null;
$opt = ['uploads' => null, 'nextgen' => null, 'attachment-map' => null, 'create-users' => false, 'author' => null, 'dry-run' => false, 'reconvert' => false];
foreach (array_slice($argv, 1) as $arg) {
    if (in_array($arg, ['--create-users', '--dry-run', '--reconvert'], true)) {
        $opt[substr($arg, 2)] = true;
    } elseif (preg_match('/^--(uploads|author|nextgen|attachment-map)=(.*)$/', $arg, $m)) {
        $opt[$m[1]] = $m[2];
    } elseif (str_starts_with($arg, '--')) {
        fwrite(STDERR, "不明なオプション: $arg\n");
        exit(1);
    } else {
        $xmlFile = $arg;
    }
}
if ($xmlFile === null || !is_file($xmlFile)) {
    fwrite(STDERR, "使い方: php " . basename(__FILE__) . " export.xml --uploads=DIR [--create-users] [--author=NAME] [--dry-run]\n");
    exit(1);
}
$uploadsDir = $opt['uploads'] !== null ? rtrim(str_replace('\\', '/', $opt['uploads']), '/') : null;
if ($uploadsDir !== null && !is_dir($uploadsDir)) {
    fwrite(STDERR, "uploads フォルダがありません: {$opt['uploads']}\n");
    exit(1);
}
$nextgenDir = $opt['nextgen'] !== null ? rtrim(str_replace('\\', '/', $opt['nextgen']), '/') : null;
if ($nextgenDir !== null && !is_dir($nextgenDir)) {
    fwrite(STDERR, "NextGEN のフォルダがありません: {$opt['nextgen']}\n");
    exit(1);
}

require dirname(__DIR__) . '/bootstrap.php';

/* ------------------------------------------------------------------
 * XML の読み込み
 * ------------------------------------------------------------------ */

$xml = simplexml_load_file($xmlFile, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_PARSEHUGE | LIBXML_NONET);
if ($xml === false) {
    fwrite(STDERR, "XML を読み込めません\n");
    exit(1);
}
$ns = $xml->getNamespaces(true);
$channel = $xml->channel;

$wpAuthors = [];            // login => display name
foreach ($channel->children($ns['wp'])->author as $a) {
    $wpAuthors[(string) $a->author_login] = (string) $a->author_display_name ?: (string) $a->author_login;
}

$attachments = [];          // WordPress の ID => 情報
$byRel = [];                // uploads からの相対パス => ID
$posts = [];
$skipped = [];
$catUse = [];               // カテゴリー名 => 使われている記事数

foreach ($channel->item as $it) {
    $w = $it->children($ns['wp']);
    $type = (string) $w->post_type;
    $id = (int) $w->post_id;

    if ($type === 'attachment') {
        $url = (string) $w->attachment_url;
        $rel = rel_from_url($url);
        $file = $rel !== null && $uploadsDir !== null && is_file("$uploadsDir/$rel") ? "$uploadsDir/$rel" : null;
        if ($file === null && $rel !== null && $uploadsDir !== null) {
            // 元ファイルが無ければ、残っている縮小版（name-1024x768.jpg など）のうち一番大きいものを使う
            $info = pathinfo($rel);
            $variants = glob("$uploadsDir/{$info['dirname']}/" . glob_escape($info['filename']) . '-*x*.' . ($info['extension'] ?? '*')) ?: [];
            $variants = array_filter($variants, static fn($f) => preg_match('/-\d+x\d+\.\w+$/', $f));
            usort($variants, static fn($a, $b) => filesize($b) <=> filesize($a));
            $file = $variants[0] ?? null;
        }
        $attachments[$id] = ['url' => $url, 'rel' => $rel, 'file' => $file, 'date' => (string) $w->post_date,
            'name' => $rel !== null ? basename($rel) : (string) $it->title];
        if ($rel !== null) {
            $byRel[$rel] = $id;
        }
        continue;
    }
    if ($type !== 'post' || (string) $w->status !== 'publish') {
        $k = $type . '/' . (string) $w->status;
        $skipped[$k] = ($skipped[$k] ?? 0) + 1;
        continue;
    }

    $cats = $tags = [];
    foreach ($it->category as $c) {
        $name = trim((string) $c);
        if ($name === '') {
            continue;
        }
        if ((string) $c['domain'] === 'category') {
            $cats[$name] = (string) $c['nicename'];
            $catUse[$name] = ($catUse[$name] ?? 0) + 1;
        } elseif ((string) $c['domain'] === 'post_tag') {
            $tags[] = $name;
        }
    }
    $thumb = null;
    foreach ($w->postmeta as $pm) {
        if ((string) $pm->meta_key === '_thumbnail_id') {
            $thumb = (int) $pm->meta_value;
        }
    }
    $comments = [];
    foreach ($w->comment as $c) {
        if ((string) $c->comment_approved === '1' && in_array((string) $c->comment_type, ['', 'comment'], true)) {
            $comments[] = [
                'name' => (string) $c->comment_author ?: '名無し',
                'body' => trim(html_entity_decode(strip_tags((string) $c->comment_content), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'date' => (string) $c->comment_date,
                'ip'   => (string) $c->comment_author_IP,
            ];
        }
    }
    $posts[] = [
        'wp_id'    => $id,
        'title'    => trim((string) $it->title) ?: '（無題）',
        'content'  => (string) $it->children($ns['content'])->encoded,
        'date'     => (string) $w->post_date,
        'modified' => (string) $w->post_modified ?: (string) $w->post_date,
        'login'    => (string) $it->children($ns['dc'])->creator,
        'cats'     => $cats,
        'tags'     => $tags,
        'thumb'    => $thumb,
        'comments' => $comments,
        'open'     => (string) $w->comment_status === 'open',
        'sticky'   => (int) $w->is_sticky === 1,
    ];
}

// メディアライブラリから消えた添付ファイルを、対応表（ID,uploads からの相対パス）で補う
if ($opt['attachment-map'] !== null) {
    $lines = @file($opt['attachment-map'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        fwrite(STDERR, "対応表を読み込めません: {$opt['attachment-map']}\n");
        exit(1);
    }
    $mapped = 0;
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !preg_match('/^\s*(\d+)\s*[,\t]\s*(.+?)\s*$/u', $line, $mm)) {
            continue;
        }
        [$aid, $rel] = [(int) $mm[1], str_replace('\\', '/', $mm[2])];
        if (isset($attachments[$aid])) {
            continue;   // XML にある添付ファイルはそちらを優先
        }
        $file = $uploadsDir !== null && is_file("$uploadsDir/$rel") ? "$uploadsDir/$rel" : null;
        $attachments[$aid] = ['url' => '', 'rel' => $rel, 'file' => $file, 'date' => '', 'name' => basename($rel)];
        $byRel[$rel] = $aid;
        $mapped++;
        if ($file === null) {
            echo "  対応表のファイルがありません: ID $aid → $rel\n";
        }
    }
    echo "対応表から補った添付ファイル: {$mapped} 件\n";
}

/** NextGEN の写真の取り込み記録用キー（NextGEN フォルダからの相対パス） */
function ngg_key(string $file, ?string $base): string
{
    return $base !== null && str_starts_with($file, $base . '/') ? substr($file, strlen($base) + 1) : basename($file);
}

/** glob のパターンで特別な意味を持つ文字をエスケープ */
function glob_escape(string $s): string
{
    return (string) preg_replace('/([*?\[\]])/', '[$1]', $s);
}

/** WordPress のメディア URL → uploads からの相対パス */
function rel_from_url(string $url): ?string
{
    if (!preg_match('#/wp-content/uploads/(.+)$#', parse_url($url, PHP_URL_PATH) ?: '', $m)) {
        return null;
    }
    return rawurldecode($m[1]);
}

/** 本文中の URL（縮小版 -1024x768 や -scaled を含む）から添付ファイルの ID を探す */
function find_attachment(string $url, array $byRel): ?int
{
    $rel = rel_from_url($url);
    if ($rel === null) {
        return null;
    }
    if (isset($byRel[$rel])) {
        return $byRel[$rel];
    }
    $base = preg_replace('/-(?:\d+x\d+|scaled)(\.\w+)$/', '$1', $rel);
    if (isset($byRel[$base])) {
        return $byRel[$base];
    }
    $base = preg_replace('/-scaled(\.\w+)$/', '$1', $base);
    return $byRel[$base] ?? null;
}

/** 記事が使っている添付ファイルの ID */
function referenced_attachments(array $post, array $byRel): array
{
    $ids = [];
    if ($post['thumb']) {
        $ids[] = $post['thumb'];
    }
    $c = $post['content'];
    preg_match_all('/wp-image-(\d+)/', $c, $m);
    $ids = array_merge($ids, array_map('intval', $m[1]));
    preg_match_all('/\[gallery[^\]]*\bids="([\d,\s]+)"/', $c, $m);
    foreach ($m[1] as $list) {
        $ids = array_merge($ids, array_map('intval', preg_split('/\s*,\s*/', trim($list))));
    }
    preg_match_all('/attachment_(\d+)/', $c, $m);
    $ids = array_merge($ids, array_map('intval', $m[1]));
    preg_match_all('#https?://[^\s"\'\]<>]+/wp-content/uploads/[^\s"\'\]<>]+#', $c, $m);
    foreach ($m[0] as $url) {
        $id = find_attachment($url, $byRel);
        if ($id !== null) {
            $ids[] = $id;
        }
    }
    return array_values(array_unique(array_filter($ids)));
}

/* ------------------------------------------------------------------
 * 取り込み計画（ここまでは読み込みのみ）
 * ------------------------------------------------------------------ */

$importLog = json_decode(App::setting('import.wordpress') ?: '{}', true) ?: [];
$importLog += ['media' => [], 'files' => [], 'posts' => []];

// ユーザーの対応付け
$fallback = $opt['author'] !== null
    ? db_one('SELECT * FROM users WHERE username = ?', [$opt['author']])
    : db_one("SELECT * FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1");
if (!$fallback) {
    fwrite(STDERR, "代わりに使うユーザーが見つかりません（--author を確認してください）\n");
    exit(1);
}
$userPlan = [];     // login => ['id' => ?int, 'action' => found|create|fallback, 'display' => ...]
foreach (array_unique(array_column($posts, 'login')) as $login) {
    $display = $wpAuthors[$login] ?? $login;
    $u = db_one('SELECT id FROM users WHERE username = ? OR display_name = ? ORDER BY id LIMIT 1', [$login, $display]);
    $uname = strtolower($login);
    if ($u) {
        $userPlan[$login] = ['id' => (int) $u['id'], 'action' => 'found', 'display' => $display];
    } elseif ($opt['create-users'] && preg_match('/^[a-z0-9._\-]{1,64}$/', $uname)) {
        $userPlan[$login] = ['id' => null, 'action' => 'create', 'display' => $display, 'username' => $uname];
    } else {
        $userPlan[$login] = ['id' => (int) $fallback['id'], 'action' => 'fallback', 'display' => $display];
    }
}

// 使われている添付ファイル
$needed = [];
foreach ($posts as $p) {
    foreach (referenced_attachments($p, $byRel) as $aid) {
        $needed[$aid] ??= $p['login'];
    }
}
$mediaStats = ['new' => 0, 'done' => 0, 'missing' => [], 'unknown' => 0, 'bytes' => 0];
foreach ($needed as $aid => $_) {
    if (isset($importLog['media'][$aid])) {
        $mediaStats['done']++;
    } elseif (!isset($attachments[$aid])) {
        $mediaStats['unknown']++;
    } elseif ($attachments[$aid]['file'] === null) {
        $mediaStats['missing'][] = $attachments[$aid]['rel'] ?? ('ID ' . $aid);
    } else {
        $mediaStats['new']++;
        $mediaStats['bytes'] += (int) filesize($attachments[$aid]['file']);
    }
}

// 元ファイルが無くても、本文が直接参照している縮小版（-300x225 など）が残っていればそれを使う
$extraFiles = [];   // uploads からの相対パス => 投稿者
if ($uploadsDir !== null) {
    foreach ($posts as $p) {
        preg_match_all('#https?://[^\s"\'\]<>]+/wp-content/uploads/[^\s"\'\]<>]+#', $p['content'], $m);
        foreach ($m[0] as $url) {
            $rel = rel_from_url($url);
            $aid = find_attachment($url, $byRel);
            $hasOriginal = $aid !== null && isset($attachments[$aid]) && $attachments[$aid]['file'] !== null;
            if ($rel !== null && !$hasOriginal && is_file("$uploadsDir/$rel")) {
                $extraFiles[$rel] ??= $p['login'];
            }
        }
    }
}
$extraNew = array_diff_key($extraFiles, $importLog['files']);

// NextGEN Gallery：[imagely id="N"] などの N をギャラリー番号とみなし、そのフォルダの写真を使う
// （wp-content/uploads/ngg-originals/<ハッシュ>/N/ や wp-content/gallery/<名前>/ の形に対応）
$importLog += ['ngg' => []];
$nggPlan = [];      // ギャラリー番号 => [ファイルの絶対パス, ...]（ファイル名順）
if ($nextgenDir !== null) {
    foreach ($posts as $p) {
        preg_match_all('#\[(?:imagely|ngg|ngg_images|nggallery)\b[^\]]*?\b(?:id|ids|gallery_ids)="?(\d+)#', $p['content'], $m);
        foreach ($m[1] as $gid) {
            if (isset($nggPlan[$gid])) {
                continue;
            }
            $found = [];
            foreach (["$nextgenDir/*/$gid", "$nextgenDir/$gid"] as $pattern) {
                foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $dir) {
                    foreach (scandir($dir) ?: [] as $f) {
                        if (preg_match('/\.(jpe?g|png|gif|webp)(_backup)?$/i', $f)) {
                            $found[] = "$dir/$f";
                        }
                    }
                }
            }
            natcasesort($found);
            $nggPlan[$gid] = array_values($found);
        }
    }
}

// 既に取り込み済みの記事
$newPosts =array_values(array_filter($posts, static fn($p) => !isset($importLog['posts'][$p['wp_id']])));

echo "== WordPress 取り込み" . ($opt['dry-run'] ? '（確認のみ）' : '') . " ==\n";
printf("記事: %d 件（新規 %d 件・取り込み済み %d 件）\n", count($posts), count($newPosts), count($posts) - count($newPosts));
foreach ($skipped as $k => $n) {
    echo "  対象外: $k $n 件\n";
}
printf("コメント: %d 件\n", array_sum(array_map(static fn($p) => count($p['comments']), $newPosts)));
echo "投稿者:\n";
foreach ($userPlan as $login => $u) {
    $label = ['found' => '既存ユーザー', 'create' => 'AD ユーザーとして事前登録', 'fallback' => '「' . $fallback['username'] . '」の記事にする'][$u['action']];
    printf("  %-18s %-10s → %s\n", $login, $u['display'], $label);
}
printf("カテゴリー: %d 種類\n", count($catUse));
printf("メディア: 使われている %d 件（新規 %d 件 %.1f MB・取り込み済み %d 件・ファイルなし %d 件）\n",
    count($needed), $mediaStats['new'], $mediaStats['bytes'] / 1048576, $mediaStats['done'], count($mediaStats['missing']) + $mediaStats['unknown']);
foreach (array_slice($mediaStats['missing'], 0, 30) as $miss) {
    echo "  ファイルなし: $miss\n";
}
if (count($mediaStats['missing']) > 30) {
    echo '  …ほか ' . (count($mediaStats['missing']) - 30) . " 件\n";
}
if ($extraFiles) {
    printf("  元ファイルの代わりに縮小版を使うもの: %d 件\n", count($extraFiles));
}
foreach ($nggPlan as $gid => $list) {
    $doneN = count(array_filter($list, static fn($f) => isset($importLog['ngg'][ngg_key($f, $nextgenDir)])));
    printf("NextGEN ギャラリー %s: 写真 %d 枚（取り込み済み %d 枚）\n", $gid, count($list), $doneN);
}

if ($opt['dry-run']) {
    // 本文変換の警告だけ確認する（メディア ID は仮）
    $fake = [];
    foreach (array_keys($needed) as $aid) {
        if (isset($importLog['media'][$aid]) || (isset($attachments[$aid]) && $attachments[$aid]['file'] !== null)) {
            $fake[$aid] = ['id' => 0, 'medium' => true, 'kind' => 'image'];
        }
    }
    $fakeFiles = array_map(static fn() => ['id' => 0, 'medium' => false, 'kind' => 'image'], $extraFiles);
    $warn = [];
    foreach ($newPosts as $p) {
        convert_content($p['content'], $fake, $byRel, $warn, $p['title'], $fakeFiles,
            array_map(static fn($list) => array_fill(0, count($list), 0), $nggPlan));
    }
    print_warnings($warn);
    echo "\n--dry-run のため、何も書き込んでいません。\n";
    exit(0);
}

/* ------------------------------------------------------------------
 * 書き込み
 * ------------------------------------------------------------------ */

$pdo = db();
$backupDir = (string) App::config('backup_dir');
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0770, true);
}
$backup = $backupDir . '/before-wordpress-import-' . date('Ymd-His') . '.sqlite';
$pdo->exec('VACUUM INTO ' . $pdo->quote($backup));
echo "\nバックアップ: $backup\n";

$saveLog = static function () use (&$importLog): void {
    App::saveSetting('import.wordpress', json_encode($importLog));
};

// 1. 投稿者
foreach ($userPlan as $login => &$u) {
    if ($u['action'] === 'create') {
        $exists = db_one("SELECT id FROM users WHERE auth_type = 'ldap' AND username = ?", [$u['username']]);
        if ($exists) {
            $u['id'] = (int) $exists['id'];
        } else {
            db_exec("INSERT INTO users (username, display_name, role, auth_type, is_active, created_at) VALUES (?, ?, 'author', 'ldap', 1, ?)",
                [$u['username'], $u['display'], now()]);
            $u['id'] = (int) $pdo->lastInsertId();
        }
    }
}
unset($u);

// 2. メディア（ファイルのコピーは取り消せないので、1 件ずつ記録しながら進める）
$media = [];        // WordPress ID => ['id' => 新 ID, 'medium' => 中サイズあり, 'kind' => ...]
foreach ($importLog['media'] as $wpId => $newId) {
    $row = db_one('SELECT id, kind, medium_path FROM media WHERE id = ?', [(int) $newId]);
    if ($row) {
        $media[(int) $wpId] = ['id' => (int) $row['id'], 'medium' => $row['medium_path'] !== null, 'kind' => $row['kind']];
    }
}
$done = 0;
$total = $mediaStats['new'];
foreach ($needed as $aid => $login) {
    if (isset($media[$aid]) || !isset($attachments[$aid]) || $attachments[$aid]['file'] === null) {
        continue;
    }
    $a = $attachments[$aid];
    try {
        $row = MediaService::importFile($a['file'], $a['name'], $userPlan[$login]['id'], $a['date'] ?: null);
    } catch (Throwable $e) {
        echo "  メディアを取り込めません: {$a['rel']}（{$e->getMessage()}）\n";
        continue;
    }
    $media[$aid] = ['id' => (int) $row['id'], 'medium' => $row['medium_path'] !== null, 'kind' => $row['kind']];
    $importLog['media'][$aid] = (int) $row['id'];
    $done++;
    if ($done % 25 === 0 || $done === $total) {
        $saveLog();
        printf("  メディア %d / %d\n", $done, $total);
    }
}
// 元ファイルが無いものは、本文が参照している縮小版のファイルを取り込む
$files = [];        // uploads からの相対パス => ['id', 'medium', 'kind']
foreach ($importLog['files'] as $rel => $newId) {
    $row = db_one('SELECT id, kind, medium_path FROM media WHERE id = ?', [(int) $newId]);
    if ($row) {
        $files[$rel] = ['id' => (int) $row['id'], 'medium' => $row['medium_path'] !== null, 'kind' => $row['kind']];
    }
}
foreach ($extraFiles as $rel => $login) {
    if (isset($files[$rel])) {
        continue;
    }
    try {
        $row = MediaService::importFile("$uploadsDir/$rel", basename($rel), $userPlan[$login]['id'], null);
    } catch (Throwable $e) {
        echo "  メディアを取り込めません: {$rel}（{$e->getMessage()}）\n";
        continue;
    }
    $files[$rel] = ['id' => (int) $row['id'], 'medium' => false, 'kind' => $row['kind']];
    $importLog['files'][$rel] = (int) $row['id'];
    $done++;
}
$saveLog();

// NextGEN ギャラリーの写真
$ngg = [];          // ギャラリー番号 => [新メディア ID, ...]
foreach ($nggPlan as $gid => $list) {
    $ngg[$gid] = [];
    $n = 0;
    foreach ($list as $file) {
        $key = ngg_key($file, $nextgenDir);
        $id = $importLog['ngg'][$key] ?? null;
        if ($id !== null && !db_val('SELECT 1 FROM media WHERE id = ?', [$id])) {
            $id = null;
        }
        if ($id === null) {
            try {
                $name = (string) preg_replace('/_backup$/i', '', basename($file));
                $row = MediaService::importFile($file, $name, (int) $fallback['id'], null);
            } catch (Throwable $e) {
                echo "  NextGEN の写真を取り込めません: {$key}（{$e->getMessage()}）\n";
                continue;
            }
            $id = (int) $row['id'];
            $importLog['ngg'][$key] = $id;
            $done++;
            if (++$n % 50 === 0) {
                $saveLog();
                printf("  NextGEN ギャラリー %s: %d / %d\n", $gid, $n, count($list));
            }
        }
        $ngg[$gid][] = (int) $id;
    }
    $saveLog();
}

// 3. カテゴリー
$catIds = [];
foreach (Repo::categories() as $c) {
    $catIds[mb_strtolower($c['name'])] = (int) $c['id'];
}
$slugN = 1;
foreach (array_keys($catUse) as $name) {
    if (isset($catIds[mb_strtolower($name)])) {
        continue;
    }
    $nice = '';
    foreach ($posts as $p) {
        if (isset($p['cats'][$name])) {
            $nice = strtolower(rawurldecode($p['cats'][$name]));
            break;
        }
    }
    $slug = preg_match('/^[a-z0-9][a-z0-9\-]{0,49}$/', $nice) ? $nice : '';
    while ($slug === '' || db_val('SELECT 1 FROM categories WHERE slug = ?', [$slug])) {
        $slug = 'cat-' . $slugN++;
    }
    db_exec('INSERT INTO categories (name, slug, description, sort_order, created_at) VALUES (?, ?, ?, 0, ?)', [mb_substr($name, 0, 50), $slug, '', now()]);
    $catIds[mb_strtolower($name)] = (int) $pdo->lastInsertId();
}

// 4. 記事・タグ・コメント
$warn = [];
$imported = 0;
$reconverted = 0;
if ($opt['reconvert']) {
    foreach ($posts as $p) {
        $postId = $importLog['posts'][$p['wp_id']] ?? null;
        if ($postId === null) {
            continue;
        }
        $content = convert_content($p['content'], $media, $byRel, $warn, $p['title'], $files, $ngg);
        $eyecatch = $p['thumb'] && isset($media[$p['thumb']]) && $media[$p['thumb']]['kind'] === 'image' ? $media[$p['thumb']]['id'] : null;
        // ブログで編集された記事（updated_at が変わっている）は上書きしない
        $reconverted += db_exec('UPDATE posts SET content = ?, eyecatch_media_id = COALESCE(?, eyecatch_media_id) WHERE id = ? AND updated_at = ?',
            [$content, $eyecatch, $postId, $p['modified']]);
    }
}
foreach ($newPosts as $p) {
    // カテゴリーは「使われている記事が最も少ない＝最も具体的」なものを 1 つ。残りはタグに
    $names = array_keys($p['cats']);
    usort($names, static fn($x, $y) => ($catUse[$x] <=> $catUse[$y]) ?: strcmp($x, $y));
    $primary = $names[0] ?? null;
    $tagNames = Repo::parseTags(implode(',', array_merge(array_slice($names, 1), $p['tags'])));

    $content = convert_content($p['content'], $media, $byRel, $warn, $p['title'], $files, $ngg);
    $eyecatch = $p['thumb'] && isset($media[$p['thumb']]) && $media[$p['thumb']]['kind'] === 'image' ? $media[$p['thumb']]['id'] : null;

    $pdo->beginTransaction();
    try {
        db_exec(
            'INSERT INTO posts (title, content, category_id, author_id, eyecatch_media_id, is_sticky, allow_comments, published_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [mb_substr($p['title'], 0, 200), $content, $primary !== null ? $catIds[mb_strtolower($primary)] : null,
             $userPlan[$p['login']]['id'], $eyecatch, $p['sticky'] ? 1 : 0, $p['open'] ? 1 : 0, $p['date'], $p['date'], $p['modified']]
        );
        $postId = (int) $pdo->lastInsertId();
        Repo::syncTags($postId, $tagNames);
        foreach ($p['comments'] as $c) {
            db_exec("INSERT INTO comments (post_id, user_id, author_name, body, status, ip, created_at) VALUES (?, NULL, ?, ?, 'approved', ?, ?)",
                [$postId, mb_substr($c['name'], 0, 50), mb_substr($c['body'], 0, 2000), $c['ip'], $c['date']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "  記事を取り込めません: {$p['title']}（{$e->getMessage()}）\n";
        continue;
    }
    $importLog['posts'][$p['wp_id']] = $postId;
    $imported++;
}
$saveLog();
Backup::run();

echo "\n取り込み完了: 記事 {$imported} 件 / メディア {$done} 件 / カテゴリー " . count($catUse) . ' 種類'
    . ($opt['reconvert'] ? " / 本文を作り直した記事 {$reconverted} 件" : '') . "\n";
print_warnings($warn);

/* ------------------------------------------------------------------
 * 本文の変換
 * ------------------------------------------------------------------ */

/**
 * @param array $media WordPress ID => ['id', 'medium', 'kind']
 */
function convert_content(string $html, array $media, array $byRel, array &$warn, string $title, array $files = [], array $ngg = []): string
{
    $html = str_replace(["\r\n", "\r"], "\n", $html);
    // ブロックエディターのコメント
    $html = (string) preg_replace('#<!--\s*/?wp:[^>]*?-->\n?#s', '', $html);

    $ref = static function (int $wpId, string $size = '') use ($media): ?string {
        if (!isset($media[$wpId])) {
            return null;
        }
        $m = $media[$wpId];
        if ($size === 'medium' && !$m['medium']) {
            $size = '';
        }
        return '/media/' . $m['id'] . ($size !== '' ? '/' . $size : '');
    };
    // 元ファイルの代わりに取り込んだ縮小版（URL のファイルそのもの）
    $byFile = static function (string $url) use ($files): ?string {
        $rel = rel_from_url($url);
        return $rel !== null && isset($files[$rel]) ? '/media/' . $files[$rel]['id'] : null;
    };

    // [caption]…[/caption] → <figure>
    $html = (string) preg_replace_callback('#\[caption([^\]]*)\](.*?)\[/caption\]#s', static function ($m) {
        preg_match('/align="(\w+)"/', $m[1], $al);
        $inner = trim($m[2]);
        $text = '';
        if (preg_match('#^(.*>)([^<>]*)$#s', $inner, $parts)) {
            [$inner, $text] = [trim($parts[1]), trim($parts[2])];
        }
        $class = isset($al[1]) && $al[1] !== 'alignnone' ? ' class="' . $al[1] . '"' : '';
        return "<figure$class>$inner" . ($text !== '' ? "<figcaption>$text</figcaption>" : '') . '</figure>';
    }, $html);

    // [gallery ids="1,2,3"] → 画像の一覧
    $html = (string) preg_replace_callback('#\[gallery([^\]]*)\]#', static function ($m) use ($ref, &$warn, $title) {
        if (!preg_match('/ids="([\d,\s]+)"/', $m[1], $ids)) {
            $warn[] = "$title: ids の無い [gallery] は変換できません";
            return '';
        }
        $items = '';
        foreach (preg_split('/\s*,\s*/', trim($ids[1])) as $id) {
            $orig = $ref((int) $id);
            if ($orig === null) {
                $warn[] = "$title: ギャラリーの画像（ID {$id}）が見つかりません";
                continue;
            }
            $items .= '<a href="' . $orig . '"><img src="' . $ref((int) $id, 'thumb') . '" alt=""></a>';
        }
        return $items !== '' ? "\n\n<div class=\"gallery\">$items</div>\n\n" : '';
    }, $html);

    // [video mp4="…"][/video] → <video>
    $html = (string) preg_replace_callback('#\[video([^\]]*)\](?:\s*\[/video\])?#', static function ($m) use ($ref, $byFile, $byRel, &$warn, $title) {
        if (!preg_match('/(?:mp4|m4v|mov|webm|src)="([^"]+)"/', $m[1], $src)) {
            $warn[] = "$title: 動画の URL が無い [video] を削除しました";
            return '';
        }
        $id = find_attachment($src[1], $byRel);
        $url = ($id !== null ? $ref($id) : null) ?? $byFile($src[1]);
        if ($url === null) {
            $warn[] = "$title: 動画ファイルが見つからないため削除しました（" . basename($src[1]) . '）';
            return '';
        }
        return "\n\n<video src=\"$url\" controls preload=\"metadata\" playsinline></video>\n\n";
    }, $html);

    // NextGEN Gallery（取り込んだ写真があればギャラリーに、無ければ削除）
    $html = (string) preg_replace_callback('#\[(imagely|ngg|ngg_images|nggallery|slideshow)\b[^\]]*\]#', static function ($m) use (&$warn, $title, $ngg) {
        if (preg_match('/\b(?:id|ids|gallery_ids)="?(\d+)/', $m[0], $g) && !empty($ngg[$g[1]])) {
            $items = '';
            foreach ($ngg[$g[1]] as $mid) {
                $items .= '<a href="/media/' . $mid . '"><img src="/media/' . $mid . '/thumb" alt=""></a>';
            }
            return "\n\n<div class=\"gallery\">$items</div>\n\n";
        }
        $warn[] = "$title: [{$m[1]}] のギャラリーが見つからないため削除しました（--nextgen でフォルダを指定してください）";
        return '';
    }, $html);

    // <img> の src を新しいメディアに
    $html = (string) preg_replace_callback('#<img\b[^>]*>#i', static function ($m) use ($ref, $byFile, $byRel, &$warn, $title) {
        $tag = $m[0];
        if (!preg_match('/\bsrc="([^"]+)"/', $tag, $src) || !str_contains($src[1], '/wp-content/uploads/')) {
            return $tag;
        }
        $id = preg_match('/wp-image-(\d+)/', $tag, $wid) ? (int) $wid[1] : null;
        if ($id === null || $ref($id) === null) {
            $id = find_attachment($src[1], $byRel);
        }
        $resized = (bool) preg_match('/-(?:\d+x\d+|scaled)\.\w+$/', parse_url($src[1], PHP_URL_PATH) ?: '');
        $url = ($id !== null ? $ref($id, $resized ? 'medium' : '') : null) ?? $byFile($src[1]);
        if ($url === null) {
            $warn[] = "$title: 画像が見つかりません（" . basename($src[1]) . '）';
            return $tag;
        }
        $tag = str_replace($src[0], 'src="' . $url . '"', $tag);
        return (string) preg_replace('/\s(?:srcset|sizes)="[^"]*"/', '', $tag);
    }, $html);

    // リンク先が WordPress のメディアなら新しいメディアに
    $html = (string) preg_replace_callback('#\bhref="([^"]*/wp-content/uploads/[^"]+)"#', static function ($m) use ($ref, $byFile, $byRel) {
        $id = find_attachment($m[1], $byRel);
        $url = ($id !== null ? $ref($id) : null) ?? $byFile($m[1]);
        return $url !== null ? 'href="' . $url . '"' : $m[0];
    }, $html);

    return trim($html);
}

function print_warnings(array $warn): void
{
    if (!$warn) {
        echo "本文の変換: 警告なし\n";
        return;
    }
    $warn = array_values(array_unique($warn));
    echo '本文の変換で確認が必要な点: ' . count($warn) . " 件\n";
    foreach ($warn as $w) {
        echo "  - $w\n";
    }
}

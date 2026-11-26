<?php
declare(strict_types=1);

/**
 * 閲覧側の画面
 */
final class FrontController
{
    public function home(): void
    {
        $this->listing('', [], ['isHome' => true, 'path' => '/']);
    }

    public function category(string $slug): void
    {
        $cat = db_one('SELECT * FROM categories WHERE slug = ?', [$slug]) ?? abort(404);
        $this->listing('p.category_id = ?', [$cat['id']], [
            'title'       => $cat['name'],
            'heading'     => $cat['name'],
            'label'       => 'カテゴリー',
            'description' => $cat['description'],
            'path'        => '/category/' . rawurlencode($cat['slug']),
        ]);
    }

    public function tag(string $name): void
    {
        $tag = db_one('SELECT * FROM tags WHERE name = ?', [$name]) ?? abort(404);
        $this->listing('p.id IN (SELECT post_id FROM post_tags WHERE tag_id = ?)', [$tag['id']], [
            'title'   => '#' . $tag['name'],
            'heading' => '#' . $tag['name'],
            'label'   => 'タグ',
            'path'    => '/tag/' . rawurlencode($tag['name']),
        ]);
    }

    public function search(): void
    {
        $q = mb_substr(query('q'), 0, 100);
        $words = array_slice(preg_split('/[\s\x{3000}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 5);
        $where = [];
        $params = [];
        foreach ($words as $w) {
            $like = '%' . like_escape($w) . '%';
            $where[] = "(p.title LIKE ? ESCAPE '\\' OR p.content LIKE ? ESCAPE '\\')";
            $params[] = $like;
            $params[] = $like;
        }
        $this->listing($where ? implode(' AND ', $where) : '0', $params, [
            'title'   => $q !== '' ? '「' . $q . '」の検索結果' : '検索',
            'heading' => $q !== '' ? '「' . $q . '」の検索結果' : '検索',
            'label'   => '検索',
            'path'    => '/search',
            'query'   => ['q' => $q],
            'search'  => $q,
            'emptyMessage' => $q === '' ? 'キーワードを入力してください。' : '該当する記事は見つかりませんでした。',
        ]);
    }

    private function listing(string $where, array $params, array $opt): void
    {
        $whereSql = $where !== '' ? 'WHERE ' . $where : '';
        $total = (int) db_val("SELECT COUNT(*) FROM posts p $whereSql", $params);
        $pager = paginate($total, (int) App::setting('posts_per_page'));
        $order = !empty($opt['isHome']) ? 'p.is_sticky DESC, ' : '';
        $posts = db_all(
            Repo::POST_SELECT . " $whereSql ORDER BY {$order}p.published_at DESC, p.id DESC LIMIT {$pager['per']} OFFSET {$pager['offset']}",
            $params
        );
        view('front/list', $opt + [
            'posts'   => $posts,
            'tagMap'  => Repo::tagsForPosts(array_column($posts, 'id')),
            'pager'   => $pager,
            'query'   => [],
            'isHome'  => false,
        ]);
    }

    public function post(string $id): void
    {
        $post = db_one(Repo::POST_SELECT . ' WHERE p.id = ?', [(int) $id]) ?? abort(404);
        $comments = db_all(
            "SELECT * FROM comments WHERE post_id = ? AND status = 'approved' ORDER BY created_at, id",
            [$post['id']]
        );
        // 行値の比較にするとインデックス (published_at, id) がそのまま使える
        $prev = db_one(
            'SELECT id, title FROM posts WHERE (published_at, id) < (?, ?) ORDER BY published_at DESC, id DESC LIMIT 1',
            [$post['published_at'], $post['id']]
        );
        $next = db_one(
            'SELECT id, title FROM posts WHERE (published_at, id) > (?, ?) ORDER BY published_at ASC, id ASC LIMIT 1',
            [$post['published_at'], $post['id']]
        );
        view('front/post', [
            'title'    => $post['title'],
            'post'     => $post,
            'html'     => Html::render((string) $post['content']),
            'tags'     => Repo::tagsForPosts([$post['id']])[(int) $post['id']] ?? [],
            'comments' => $comments,
            'prev'     => $prev,
            'next'     => $next,
        ]);
    }

    public function comment(string $id): void
    {
        $post = db_one('SELECT id, allow_comments FROM posts WHERE id = ?', [(int) $id]) ?? abort(404);
        if (!(int) $post['allow_comments']) {
            abort(403, 'この記事はコメントを受け付けていません。');
        }
        $back = '/post/' . (int) $post['id'];
        $user = current_user();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        // スパム対策（人間には見えない入力欄）
        if (input('website') !== '') {
            redirect($back);
        }

        $body = str_replace(["\r\n", "\r"], "\n", input('body'));
        $name = $user ? (string) $user['display_name'] : input('author_name');
        $errors = [];
        if ($body === '') {
            $errors[] = 'コメントを入力してください。';
        } elseif (mb_strlen($body) > 2000) {
            $errors[] = 'コメントは 2000 文字以内で入力してください。';
        }
        if (!$user) {
            if (App::isPrivate()) {
                abort(403);
            }
            if ($name === '' || mb_strlen($name) > 50) {
                $errors[] = 'お名前を 50 文字以内で入力してください。';
            }
            $recent = db_val(
                'SELECT COUNT(*) FROM comments WHERE ip = ? AND created_at >= ?',
                [$ip, date('Y-m-d H:i:s', time() - 30)]
            );
            if ((int) $recent > 0) {
                $errors[] = '連続して投稿できません。少し時間をおいてください。';
            }
        }
        if ($errors) {
            foreach ($errors as $err) {
                flash('error', $err);
            }
            with_old(['author_name' => $name, 'body' => $body]);
            header('Location: ' . post_url($post['id']) . '#comment-form', true, 303);
            exit;
        }

        $moderate = !$user && App::setting('comment_moderation') === '1';
        db_exec(
            'INSERT INTO comments (post_id, user_id, author_name, body, status, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$post['id'], $user['id'] ?? null, $name, $body, $moderate ? 'pending' : 'approved', $ip, now()]
        );
        flash('success', $moderate ? 'コメントを受け付けました。承認後に表示されます。' : 'コメントを投稿しました。');
        header('Location: ' . post_url($post['id']) . '#comments', true, 303);
        exit;
    }

    public function media(string $id, string $size = 'original'): void
    {
        $m = db_one('SELECT * FROM media WHERE id = ?', [(int) $id]) ?? abort(404);
        $rel = match ($size) {
            'original' => $m['path'],
            'thumb'    => $m['thumb_path'] ?: $m['path'],
            'medium'   => $m['medium_path'] ?: $m['path'],
            default    => abort(404),
        };
        $file = MediaService::absPath((string) $rel);
        if (!is_file($file)) {
            abort(404);
        }
        $mime = $rel === $m['path'] ? (string) $m['mime'] : MediaService::mimeForPath((string) $rel, (string) $m['mime']);
        $inline = $m['kind'] !== 'file' || $m['mime'] === 'application/pdf';
        MediaService::send($file, $mime, (string) $m['original_name'], $inline);
    }
}

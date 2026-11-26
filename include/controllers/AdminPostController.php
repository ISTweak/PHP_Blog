<?php
declare(strict_types=1);

final class AdminPostController
{
    public function index(): void
    {
        $user = require_login();
        $where = [];
        $params = [];
        $mine = !Auth::atLeast('editor') || query('mine') === '1';
        if ($mine) {
            $where[] = 'p.author_id = ?';
            $params[] = $user['id'];
        }
        $q = query('q');
        if ($q !== '') {
            $where[] = "p.title LIKE ? ESCAPE '\\'";
            $params[] = '%' . like_escape($q) . '%';
        }
        $cat = (int) query('category');
        if ($cat > 0) {
            $where[] = 'p.category_id = ?';
            $params[] = $cat;
        }
        $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $pager = paginate((int) db_val("SELECT COUNT(*) FROM posts p $ws", $params), 20);
        $posts = db_all(
            Repo::POST_SELECT . " $ws ORDER BY p.published_at DESC, p.id DESC LIMIT {$pager['per']} OFFSET {$pager['offset']}",
            $params
        );
        view('admin/posts', [
            'title'      => '投稿一覧',
            'posts'      => $posts,
            'pager'      => $pager,
            'q'          => $q,
            'category'   => $cat,
            'mine'       => $mine,
            'categories' => Repo::categories(),
            'query'      => array_filter(['q' => $q, 'category' => $cat ?: '', 'mine' => query('mine')], static fn($v) => $v !== ''),
        ], 'admin/layout');
    }

    public function create(): void
    {
        require_login();
        $this->form([
            'id' => null, 'title' => '', 'content' => '', 'category_id' => null, 'eyecatch_media_id' => null,
            'is_sticky' => 0, 'allow_comments' => 1,
        ], '');
    }

    public function edit(string $id): void
    {
        $post = $this->find($id);
        $tags = array_column(Repo::tagsForPosts([$post['id']])[(int) $post['id']] ?? [], 'name');
        $this->form($post, implode(', ', $tags));
    }

    public function store(): void
    {
        $user = require_login();
        [$data, $tags, $errors] = $this->validate();
        if ($errors) {
            $this->form(['id' => null] + $data, implode(', ', $tags), $errors);
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $now = now();
            db_exec(
                'INSERT INTO posts (title, content, category_id, author_id, eyecatch_media_id, is_sticky, allow_comments, published_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$data['title'], $data['content'], $data['category_id'], $user['id'], $data['eyecatch_media_id'],
                 $data['is_sticky'], $data['allow_comments'], $now, $now, $now]
            );
            $postId = (int) $pdo->lastInsertId();
            Repo::syncTags($postId, $tags);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Backup::run();
        flash('success', '記事を公開しました。');
        redirect('/admin/posts/' . $postId . '/edit');
    }

    public function update(string $id): void
    {
        $post = $this->find($id);
        [$data, $tags, $errors] = $this->validate();
        if ($errors) {
            $this->form(['id' => $post['id'], 'author_id' => $post['author_id']] + $data, implode(', ', $tags), $errors);
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            db_exec(
                'UPDATE posts SET title = ?, content = ?, category_id = ?, eyecatch_media_id = ?, is_sticky = ?, allow_comments = ?, updated_at = ?
                  WHERE id = ?',
                [$data['title'], $data['content'], $data['category_id'], $data['eyecatch_media_id'],
                 $data['is_sticky'], $data['allow_comments'], now(), $post['id']]
            );
            Repo::syncTags((int) $post['id'], $tags);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Backup::run();
        flash('success', '記事を更新しました。');
        redirect('/admin/posts/' . (int) $post['id'] . '/edit');
    }

    public function destroy(string $id): void
    {
        $post = $this->find($id);
        db_exec('DELETE FROM posts WHERE id = ?', [$post['id']]);
        Backup::run();
        flash('success', '「' . $post['title'] . '」を削除しました。');
        redirect('/admin/posts');
    }

    /** エディタのプレビュー（本文を表示用 HTML に変換して返す） */
    public function preview(): void
    {
        require_login();
        $content = is_string($_POST['content'] ?? null) ? $_POST['content'] : '';
        json_response(['ok' => true, 'html' => Html::render($content)]);
    }

    private function find(string $id): array
    {
        require_login();
        $post = db_one('SELECT * FROM posts WHERE id = ?', [(int) $id]) ?? abort(404);
        if (!Auth::canEditPost($post)) {
            abort(403, 'この記事を編集する権限がありません。');
        }
        return $post;
    }

    /** @return array{0: array, 1: list<string>, 2: list<string>} */
    private function validate(): array
    {
        $content = is_string($_POST['content'] ?? null) ? $_POST['content'] : '';
        $data = [
            'title'             => input('title'),
            'content'           => rtrim(str_replace(["\r\n", "\r"], "\n", $content)),
            'category_id'       => (int) input('category_id') ?: null,
            'eyecatch_media_id' => (int) input('eyecatch_media_id') ?: null,
            'is_sticky'         => checked('is_sticky') ? 1 : 0,
            'allow_comments'    => checked('allow_comments') ? 1 : 0,
        ];
        $tags = Repo::parseTags(input('tags'));
        $errors = [];
        if ($data['title'] === '') {
            $errors[] = 'タイトルを入力してください。';
        } elseif (mb_strlen($data['title']) > 200) {
            $errors[] = 'タイトルは 200 文字以内で入力してください。';
        }
        if (strlen($data['content']) > 1_000_000) {
            $errors[] = '本文が長すぎます。';
        }
        if ($data['category_id'] !== null && !db_val('SELECT 1 FROM categories WHERE id = ?', [$data['category_id']])) {
            $errors[] = '選択したカテゴリーが存在しません。';
        }
        if ($data['eyecatch_media_id'] !== null
            && !db_val("SELECT 1 FROM media WHERE id = ? AND kind = 'image'", [$data['eyecatch_media_id']])) {
            $errors[] = 'アイキャッチ画像が見つかりません。';
        }
        if (count($tags) > 20) {
            $errors[] = 'タグは 20 個までです。';
        }
        return [$data, $tags, $errors];
    }

    private function form(array $post, string $tags, array $errors = []): never
    {
        if ($errors) {
            http_response_code(422);
        }
        $eyecatch = $post['eyecatch_media_id']
            ? db_one('SELECT * FROM media WHERE id = ?', [$post['eyecatch_media_id']])
            : null;
        view('admin/post_form', [
            'title'      => $post['id'] ? '投稿を編集' : '新規投稿',
            'post'       => $post,
            'tags'       => $tags,
            'categories' => Repo::categories(),
            'allTags'    => db_all('SELECT name FROM tags ORDER BY name'),
            'eyecatch'   => $eyecatch,
            'errors'     => $errors,
        ], 'admin/layout');
    }
}

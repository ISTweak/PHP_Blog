<?php
declare(strict_types=1);

/**
 * カテゴリー・タグの管理（編集者以上）
 */
final class AdminTermController
{
    /* ---------------- カテゴリー ---------------- */

    public function categories(): void
    {
        require_role('editor');
        view('admin/categories', [
            'title'      => 'カテゴリー',
            'categories' => Repo::categories(),
        ], 'admin/layout');
    }

    public function storeCategory(): void
    {
        require_role('editor');
        $data = $this->categoryInput();
        $errors = $this->validateCategory($data, null);
        if ($errors) {
            $this->fail($errors, $data);
        }
        db_exec(
            'INSERT INTO categories (name, slug, description, sort_order, created_at) VALUES (?, ?, ?, ?, ?)',
            [$data['name'], $data['slug'], $data['description'], $data['sort_order'], now()]
        );
        flash('success', 'カテゴリー「' . $data['name'] . '」を追加しました。');
        redirect('/admin/categories');
    }

    public function updateCategory(string $id): void
    {
        require_role('editor');
        $cat = db_one('SELECT * FROM categories WHERE id = ?', [(int) $id]) ?? abort(404);
        $data = $this->categoryInput();
        $errors = $this->validateCategory($data, (int) $cat['id']);
        if ($errors) {
            foreach ($errors as $err) {
                flash('error', $err);
            }
            redirect('/admin/categories');
        }
        db_exec(
            'UPDATE categories SET name = ?, slug = ?, description = ?, sort_order = ? WHERE id = ?',
            [$data['name'], $data['slug'], $data['description'], $data['sort_order'], $cat['id']]
        );
        flash('success', 'カテゴリー「' . $data['name'] . '」を更新しました。');
        redirect('/admin/categories');
    }

    public function destroyCategory(string $id): void
    {
        require_role('editor');
        $cat = db_one('SELECT * FROM categories WHERE id = ?', [(int) $id]) ?? abort(404);
        db_exec('DELETE FROM categories WHERE id = ?', [$cat['id']]);
        flash('success', 'カテゴリー「' . $cat['name'] . '」を削除しました（記事は「未分類」になります）。');
        redirect('/admin/categories');
    }

    private function categoryInput(): array
    {
        return [
            'name'        => input('name'),
            'slug'        => strtolower(input('slug')),
            'description' => input('description'),
            'sort_order'  => (int) input('sort_order', '0'),
        ];
    }

    private function validateCategory(array $d, ?int $selfId): array
    {
        $errors = [];
        if ($d['name'] === '' || mb_strlen($d['name']) > 50) {
            $errors[] = 'カテゴリー名を 50 文字以内で入力してください。';
        } elseif (db_val('SELECT 1 FROM categories WHERE name = ? AND id != ?', [$d['name'], $selfId ?? 0])) {
            $errors[] = '同じ名前のカテゴリーがすでにあります。';
        }
        if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,49}$/', $d['slug'])) {
            $errors[] = 'スラッグ（URL 用の名前）は半角英小文字・数字・ハイフンで 50 文字以内にしてください。';
        } elseif (db_val('SELECT 1 FROM categories WHERE slug = ? AND id != ?', [$d['slug'], $selfId ?? 0])) {
            $errors[] = '同じスラッグのカテゴリーがすでにあります。';
        }
        if (mb_strlen($d['description']) > 300) {
            $errors[] = '説明は 300 文字以内で入力してください。';
        }
        return $errors;
    }

    /* ---------------- タグ ---------------- */

    public function tags(): void
    {
        require_role('editor');
        $pager = paginate((int) db_val('SELECT COUNT(*) FROM tags'), 100);
        $tags = db_all(
            "SELECT t.*, (SELECT COUNT(*) FROM post_tags pt WHERE pt.tag_id = t.id) AS cnt
               FROM tags t ORDER BY t.name LIMIT {$pager['per']} OFFSET {$pager['offset']}"
        );
        view('admin/tags', ['title' => 'タグ', 'tags' => $tags, 'pager' => $pager], 'admin/layout');
    }

    public function updateTag(string $id): void
    {
        require_role('editor');
        $tag = db_one('SELECT * FROM tags WHERE id = ?', [(int) $id]) ?? abort(404);
        $names = Repo::parseTags(input('name'));
        $name = $names[0] ?? '';
        if ($name === '' || count($names) > 1) {
            flash('error', 'タグ名を入力してください（カンマは使えません）。');
            redirect('/admin/tags');
        }
        $other = db_one('SELECT * FROM tags WHERE name = ? AND id != ?', [$name, $tag['id']]);
        if ($other) {
            // 既存のタグと同名にした場合は統合する
            db_exec('INSERT OR IGNORE INTO post_tags (post_id, tag_id) SELECT post_id, ? FROM post_tags WHERE tag_id = ?', [$other['id'], $tag['id']]);
            db_exec('DELETE FROM tags WHERE id = ?', [$tag['id']]);
            flash('success', 'タグ「' . $tag['name'] . '」を「' . $other['name'] . '」に統合しました。');
        } else {
            db_exec('UPDATE tags SET name = ? WHERE id = ?', [$name, $tag['id']]);
            flash('success', 'タグ名を「' . $name . '」に変更しました。');
        }
        redirect('/admin/tags');
    }

    public function destroyTag(string $id): void
    {
        require_role('editor');
        $tag = db_one('SELECT * FROM tags WHERE id = ?', [(int) $id]) ?? abort(404);
        db_exec('DELETE FROM tags WHERE id = ?', [$tag['id']]);
        flash('success', 'タグ「' . $tag['name'] . '」を削除しました。');
        redirect('/admin/tags');
    }

    public function pruneTags(): void
    {
        require_role('editor');
        $n = db_exec('DELETE FROM tags WHERE id NOT IN (SELECT DISTINCT tag_id FROM post_tags)');
        flash('success', "使われていないタグを {$n} 件削除しました。");
        redirect('/admin/tags');
    }

    private function fail(array $errors, array $old): never
    {
        foreach ($errors as $err) {
            flash('error', $err);
        }
        with_old($old);
        redirect('/admin/categories');
    }
}

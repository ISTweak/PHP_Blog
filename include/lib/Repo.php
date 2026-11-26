<?php
declare(strict_types=1);

/**
 * 複数の画面で使うデータ取得処理
 */
final class Repo
{
    public const POST_SELECT = <<<'SQL'
        SELECT p.*, c.name AS category_name, c.slug AS category_slug, u.display_name AS author_name,
               (SELECT COUNT(*) FROM comments cm WHERE cm.post_id = p.id AND cm.status = 'approved') AS comment_count
          FROM posts p
          LEFT JOIN categories c ON c.id = p.category_id
          LEFT JOIN users u ON u.id = p.author_id
        SQL;

    private static ?array $categories = null;

    public static function categories(): array
    {
        if (self::$categories === null) {
            self::$categories = db_all(
                'SELECT c.*, (SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id) AS post_count
                   FROM categories c ORDER BY c.sort_order, c.name'
            );
        }
        return self::$categories;
    }

    public static function tagCloud(int $limit = 30): array
    {
        // 件数の集計は post_tags のインデックスだけで行い、上位だけ tags と結合する
        return db_all(
            'SELECT t.id, t.name, x.cnt
               FROM (SELECT tag_id, COUNT(*) AS cnt FROM post_tags GROUP BY tag_id ORDER BY cnt DESC LIMIT ' . max(1, $limit) . ') x
               JOIN tags t ON t.id = x.tag_id
              ORDER BY x.cnt DESC, t.name'
        );
    }

    public static function recentPosts(int $limit = 5): array
    {
        return db_all('SELECT id, title, published_at FROM posts ORDER BY published_at DESC, id DESC LIMIT ' . max(1, $limit));
    }

    /** @return array<int, list<array{id:int,name:string}>> post_id => tags */
    public static function tagsForPosts(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $map = [];
        $rows = db_all(
            "SELECT pt.post_id, t.id, t.name FROM post_tags pt JOIN tags t ON t.id = pt.tag_id
              WHERE pt.post_id IN ($in) ORDER BY t.name",
            $ids
        );
        foreach ($rows as $r) {
            $map[(int) $r['post_id']][] = $r;
        }
        return $map;
    }

    /** カンマ（全角・読点も可）区切りの文字列をタグ名の配列に */
    public static function parseTags(string $input): array
    {
        $tags = [];
        foreach (preg_split('/[,、，]+/u', $input) ?: [] as $part) {
            $part = str_replace(['/', '\\'], '-', $part);
            $part = (string) preg_replace('/[\s\x{3000}]+/u', ' ', $part);
            $part = (string) preg_replace('/^[\s#＃]+|\s+$/u', '', $part);
            if ($part === '') {
                continue;
            }
            $part = mb_substr($part, 0, 50);
            $tags[mb_strtolower($part)] = $part;
        }
        return array_values($tags);
    }

    public static function syncTags(int $postId, array $names): void
    {
        db_exec('DELETE FROM post_tags WHERE post_id = ?', [$postId]);
        foreach ($names as $name) {
            $tagId = db_val('SELECT id FROM tags WHERE name = ?', [$name]);
            if ($tagId === false) {
                db_exec('INSERT INTO tags (name, created_at) VALUES (?, ?)', [$name, now()]);
                $tagId = db()->lastInsertId();
            }
            db_exec('INSERT OR IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)', [$postId, (int) $tagId]);
        }
    }

    public static function pendingCommentCount(): int
    {
        return (int) db_val("SELECT COUNT(*) FROM comments WHERE status = 'pending'");
    }
}

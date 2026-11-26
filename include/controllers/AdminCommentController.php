<?php
declare(strict_types=1);

/**
 * コメントの管理（編集者以上）
 */
final class AdminCommentController
{
    public function index(): void
    {
        require_role('editor');
        $status = in_array(query('status'), ['pending', 'approved'], true) ? query('status') : '';
        $where = $status !== '' ? 'WHERE c.status = ?' : '';
        $params = $status !== '' ? [$status] : [];
        $pager = paginate((int) db_val("SELECT COUNT(*) FROM comments c $where", $params), 30);
        $comments = db_all(
            "SELECT c.*, p.title AS post_title FROM comments c JOIN posts p ON p.id = c.post_id
             $where ORDER BY c.id DESC LIMIT {$pager['per']} OFFSET {$pager['offset']}",
            $params
        );
        view('admin/comments', [
            'title'    => 'コメント',
            'comments' => $comments,
            'pager'    => $pager,
            'status'   => $status,
            'pending'  => Repo::pendingCommentCount(),
        ], 'admin/layout');
    }

    public function approve(string $id): void
    {
        require_role('editor');
        $c = db_one('SELECT * FROM comments WHERE id = ?', [(int) $id]) ?? abort(404);
        $new = $c['status'] === 'approved' ? 'pending' : 'approved';
        db_exec('UPDATE comments SET status = ? WHERE id = ?', [$new, $c['id']]);
        flash('success', $new === 'approved' ? 'コメントを承認しました。' : 'コメントを非公開（承認待ち）に戻しました。');
        redirect('/admin/comments', array_filter(['status' => query('status'), 'page' => query('page')]));
    }

    public function destroy(string $id): void
    {
        require_role('editor');
        $c = db_one('SELECT * FROM comments WHERE id = ?', [(int) $id]) ?? abort(404);
        db_exec('DELETE FROM comments WHERE id = ?', [$c['id']]);
        flash('success', 'コメントを削除しました。');
        redirect('/admin/comments', array_filter(['status' => query('status'), 'page' => query('page')]));
    }
}

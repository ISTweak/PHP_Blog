<?php
declare(strict_types=1);

final class AdminMediaController
{
    private const KINDS = ['image', 'video', 'file'];

    public function index(): void
    {
        require_login();
        $kind = in_array(query('kind'), self::KINDS, true) ? query('kind') : '';
        $where = $kind !== '' ? 'WHERE m.kind = ?' : '';
        $params = $kind !== '' ? [$kind] : [];
        $pager = paginate((int) db_val("SELECT COUNT(*) FROM media m $where", $params), 48);
        $items = db_all(
            "SELECT m.*, u.display_name AS uploader FROM media m LEFT JOIN users u ON u.id = m.uploaded_by
             $where ORDER BY m.id DESC LIMIT {$pager['per']} OFFSET {$pager['offset']}",
            $params
        );
        view('admin/media', [
            'title' => 'メディア',
            'items' => $items,
            'pager' => $pager,
            'kind'  => $kind,
        ], 'admin/layout');
    }

    /** メディア選択ダイアログ用の一覧（JSON） */
    public function listJson(): void
    {
        require_login();
        $kind = in_array(query('kind'), self::KINDS, true) ? query('kind') : '';
        $per = 40;
        $page = max(1, (int) query('page', '1'));
        $where = $kind !== '' ? 'WHERE kind = ?' : '';
        $params = $kind !== '' ? [$kind] : [];
        $rows = db_all(
            "SELECT * FROM media $where ORDER BY id DESC LIMIT " . ($per + 1) . ' OFFSET ' . (($page - 1) * $per),
            $params
        );
        $hasMore = count($rows) > $per;
        json_response([
            'ok'      => true,
            'items'   => array_map([MediaService::class, 'toArray'], array_slice($rows, 0, $per)),
            'hasMore' => $hasMore,
        ]);
    }

    public function upload(): void
    {
        $user = require_login();
        try {
            if (empty($_FILES['file'])) {
                throw new RuntimeException('ファイルが送信されていません。');
            }
            $media = MediaService::upload($_FILES['file'], (int) $user['id']);
        } catch (RuntimeException $e) {
            if (wants_json()) {
                json_response(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            flash('error', $e->getMessage());
            redirect('/admin/media');
        }
        if (wants_json()) {
            json_response(['ok' => true, 'media' => MediaService::toArray($media)]);
        }
        flash('success', '「' . $media['original_name'] . '」をアップロードしました。');
        redirect('/admin/media');
    }

    public function destroy(string $id): void
    {
        require_login();
        $media = db_one('SELECT * FROM media WHERE id = ?', [(int) $id]) ?? abort(404);
        if (!Auth::canDeleteMedia($media)) {
            abort(403, 'このファイルを削除する権限がありません。');
        }
        MediaService::delete($media);
        flash('success', '「' . $media['original_name'] . '」を削除しました。記事本文に挿入している場合は表示されなくなります。');
        redirect('/admin/media');
    }
}

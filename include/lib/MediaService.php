<?php
declare(strict_types=1);

/**
 * メディア（画像・動画・PDF）のアップロード、サムネイル生成、配信
 *
 * ファイルは公開ディレクトリの外（include/data/uploads）に保存し、/media/{id} 経由で配信します。
 * 会員限定サイトではログインしていないとメディアも取得できません。
 */
final class MediaService
{
    public const TYPES = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/gif'       => 'gif',
        'image/webp'      => 'webp',
        'video/mp4'       => 'mp4',
        'video/webm'      => 'webm',
        'video/quicktime' => 'mov',
        'application/pdf' => 'pdf',
    ];

    public const THUMB_SIZE = 600;
    public const MEDIUM_SIZE = 1280;

    public static function maxBytes(): int
    {
        return (int) App::config('upload_max_bytes', 10 * 1024 * 1024);
    }

    public static function dir(): string
    {
        return rtrim((string) App::config('upload_dir', DATA . '/uploads'), '/\\');
    }

    public static function absPath(string $rel): string
    {
        return self::dir() . '/' . $rel;
    }

    /** @throws RuntimeException 利用者に表示できるエラーメッセージ付き */
    public static function upload(array $file, int $userId): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (is_array($error)) {
            throw new RuntimeException('ファイルは 1 つずつ送信してください。');
        }
        $max = self::maxBytes();
        $maxLabel = format_bytes($max);
        switch ((int) $error) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException("ファイルサイズが上限（{$maxLabel}）を超えています。");
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('ファイルが選択されていません。');
            default:
                throw new RuntimeException('アップロードに失敗しました（エラーコード: ' . (int) $error . '）。');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('不正なアップロードです。');
        }
        $size = (int) filesize($tmp);
        if ($size <= 0) {
            throw new RuntimeException('空のファイルはアップロードできません。');
        }
        if ($size > $max) {
            throw new RuntimeException("ファイルサイズが上限（{$maxLabel}）を超えています。");
        }

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset(self::TYPES[$mime])) {
            throw new RuntimeException('このファイル形式には対応していません（画像: JPEG/PNG/GIF/WebP、動画: MP4/WebM/MOV、PDF）。');
        }
        $ext = self::TYPES[$mime];
        $kind = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : 'file');

        $sub = date('Y/m');
        $dir = self::dir() . '/' . $sub;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('保存先フォルダを作成できません。');
        }
        $name = bin2hex(random_bytes(12));
        $rel = "$sub/$name.$ext";
        $abs = self::absPath($rel);
        if (!move_uploaded_file($tmp, $abs)) {
            throw new RuntimeException('ファイルを保存できませんでした。');
        }

        $width = $height = null;
        $thumb = $medium = null;
        if ($kind === 'image') {
            try {
                [$width, $height, $thumb, $medium] = self::processImage($abs, $sub, $name, $ext);
            } catch (Throwable $e) {
                error_log('Image processing failed: ' . $e->getMessage());
                $dims = @getimagesize($abs);
                if ($dims) {
                    [$width, $height] = $dims;
                }
            }
        }
        clearstatcache(true, $abs);

        db_exec(
            'INSERT INTO media (path, thumb_path, medium_path, original_name, mime, kind, size, width, height, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$rel, $thumb, $medium, self::cleanName((string) ($file['name'] ?? ''), $ext), $mime, $kind,
             (int) filesize($abs), $width, $height, $userId, now()]
        );
        return db_one('SELECT * FROM media WHERE id = ?', [(int) db()->lastInsertId()])
            ?? throw new RuntimeException('保存に失敗しました。');
    }

    /** 向きの補正・位置情報などの削除・サムネイル生成 */
    private static function processImage(string $abs, string $sub, string $name, string $ext): array
    {
        if (!extension_loaded('imagick')) {
            $d = @getimagesize($abs);
            return [$d[0] ?? null, $d[1] ?? null, null, null];
        }
        $isGif = $ext === 'gif';
        $img = new Imagick($abs . ($isGif ? '[0]' : ''));
        if (!$isGif) {
            self::autoOrient($img);
            // EXIF（撮影位置など）を削除。色味が変わらないよう ICC プロファイルは残す
            $profiles = $img->getImageProfiles('icc', true);
            $img->stripImage();
            if (!empty($profiles['icc'])) {
                $img->profileImage('icc', $profiles['icc']);
            }
            $img->writeImage($abs);
        }
        $w = $img->getImageWidth();
        $h = $img->getImageHeight();
        $outExt = $isGif ? 'png' : $ext;
        $thumb = self::resized($img, self::THUMB_SIZE, "$sub/{$name}_thumb.$outExt");
        $medium = self::resized($img, self::MEDIUM_SIZE, "$sub/{$name}_medium.$outExt");
        $img->clear();
        return [$w, $h, $thumb, $medium];
    }

    private static function resized(Imagick $src, int $max, string $rel): ?string
    {
        if ($src->getImageWidth() <= $max && $src->getImageHeight() <= $max) {
            return null; // 元画像が小さい場合は元画像をそのまま使う
        }
        $img = clone $src;
        $img->thumbnailImage($max, $max, true);
        $fmt = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        $img->setImageFormat($fmt === 'jpg' ? 'jpeg' : $fmt);
        if ($fmt === 'jpg' || $fmt === 'webp') {
            $img->setImageCompressionQuality(82);
        }
        $img->writeImage(self::absPath($rel));
        $img->clear();
        return $rel;
    }

    private static function autoOrient(Imagick $img): void
    {
        switch ($img->getImageOrientation()) {
            case Imagick::ORIENTATION_TOPRIGHT:
                $img->flopImage();
                break;
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $img->rotateImage('#000', 180);
                break;
            case Imagick::ORIENTATION_BOTTOMLEFT:
                $img->flipImage();
                break;
            case Imagick::ORIENTATION_LEFTTOP:
                $img->rotateImage('#000', 90);
                $img->flopImage();
                break;
            case Imagick::ORIENTATION_RIGHTTOP:
                $img->rotateImage('#000', 90);
                break;
            case Imagick::ORIENTATION_RIGHTBOTTOM:
                $img->rotateImage('#000', -90);
                $img->flopImage();
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $img->rotateImage('#000', -90);
                break;
            default:
                return;
        }
        $img->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }

    private static function cleanName(string $name, string $ext): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F"<>|:*?]/u', '', $name);
        $name = mb_substr(trim($name), 0, 100);
        return $name !== '' ? $name : 'file.' . $ext;
    }

    public static function delete(array $m): void
    {
        foreach ([$m['path'], $m['thumb_path'], $m['medium_path']] as $rel) {
            if ($rel) {
                $abs = self::absPath((string) $rel);
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
        }
        db_exec('DELETE FROM media WHERE id = ?', [$m['id']]);
    }

    /** JavaScript に渡す形式 */
    public static function toArray(array $m): array
    {
        $id = (int) $m['id'];
        return [
            'id'          => $id,
            'kind'        => $m['kind'],
            'mime'        => $m['mime'],
            'name'        => $m['original_name'],
            'size'        => (int) $m['size'],
            'sizeLabel'   => format_bytes((int) $m['size']),
            'width'       => $m['width'] !== null ? (int) $m['width'] : null,
            'height'      => $m['height'] !== null ? (int) $m['height'] : null,
            'ref'         => '/media/' . $id,          // 本文に挿入する URL（設置パスは表示時に補完）
            'url'         => media_url($id),
            'thumb'       => media_url($id, 'thumb'),
            'medium'      => media_url($id, 'medium'),
            'created_at'  => $m['created_at'],
            'uploaded_by' => $m['uploaded_by'] !== null ? (int) $m['uploaded_by'] : null,
        ];
    }

    public static function mimeForPath(string $path, string $fallback): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = array_flip(self::TYPES);
        return $map[$ext] ?? $fallback;
    }

    /** Range リクエスト対応のファイル送信（動画のシーク再生に必要） */
    public static function send(string $file, string $mime, string $downloadName, bool $inline): never
    {
        session_write_close(); // 同時リクエストがセッションロックで待たされないように
        // session_start() が送るキャッシュ無効化ヘッダーを取り消す
        header_remove('Pragma');
        header_remove('Expires');
        $size = (int) filesize($file);
        $mtime = (int) filemtime($file);
        $etag = '"' . dechex($mtime) . '-' . dechex($size) . '"';

        header('Cache-Control: ' . (App::isPrivate() ? 'private, max-age=86400' : 'public, max-age=604800'));
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }

        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($downloadName));

        $start = 0;
        $end = $size - 1;
        $range = $_SERVER['HTTP_RANGE'] ?? '';
        if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m)) {
            if ($m[1] === '' && $m[2] !== '') {
                $start = max(0, $size - (int) $m[2]);
            } elseif ($m[1] !== '') {
                $start = (int) $m[1];
                if ($m[2] !== '') {
                    $end = min((int) $m[2], $size - 1);
                }
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header("Content-Range: bytes */$size");
                exit;
            }
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
        $length = $end - $start + 1;
        header('Content-Length: ' . $length);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            exit;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $fp = fopen($file, 'rb');
        if ($fp === false) {
            exit;
        }
        fseek($fp, $start);
        $remain = $length;
        while ($remain > 0 && !feof($fp)) {
            $chunk = fread($fp, min(65536, $remain));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            flush();
            $remain -= strlen($chunk);
        }
        fclose($fp);
        exit;
    }
}

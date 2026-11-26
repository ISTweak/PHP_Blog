<?php
declare(strict_types=1);

/**
 * 記事本文の整形とサニタイズ
 *
 * 本文は入力されたまま保存し、表示時に以下を行います。
 *   1. 単独行の YouTube URL を埋め込みプレーヤーに変換
 *   2. 空行で段落 <p> を、改行で <br> を補完（WordPress の wpautop 相当の簡易版）
 *   3. 許可したタグ・属性以外を除去（XSS 対策）
 */
final class Html
{
    private const ALLOWED = [
        'a'          => ['href', 'title', 'target', 'rel', 'class'],
        'abbr'       => ['title'],
        'audio'      => ['src', 'controls', 'preload'],
        'b'          => [],
        'blockquote' => ['cite', 'class'],
        'br'         => [],
        'caption'    => [],
        'cite'       => [],
        'code'       => ['class'],
        'dd'         => [],
        'del'        => [],
        'details'    => [],
        'div'        => ['class'],
        'dl'         => [],
        'dt'         => [],
        'em'         => [],
        'figcaption' => [],
        'figure'     => ['class'],
        'h1'         => ['id'], 'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'h5' => ['id'], 'h6' => ['id'],
        'hr'         => [],
        'i'          => [],
        'iframe'     => ['src', 'title', 'allow', 'allowfullscreen', 'loading', 'width', 'height', 'frameborder', 'referrerpolicy'],
        'img'        => ['src', 'alt', 'title', 'width', 'height', 'loading', 'class'],
        'ins'        => [],
        'kbd'        => [],
        'li'         => [],
        'mark'       => [],
        'ol'         => ['start', 'type'],
        'p'          => ['class'],
        'pre'        => ['class'],
        'q'          => [],
        's'          => [],
        'small'      => [],
        'source'     => ['src', 'type'],
        'span'       => ['class'],
        'strong'     => [],
        'sub'        => [],
        'summary'    => [],
        'sup'        => [],
        'table'      => ['class'],
        'tbody'      => [],
        'td'         => ['colspan', 'rowspan'],
        'tfoot'      => [],
        'th'         => ['colspan', 'rowspan', 'scope'],
        'thead'      => [],
        'tr'         => [],
        'u'          => [],
        'ul'         => [],
        'video'      => ['src', 'controls', 'poster', 'width', 'height', 'preload', 'muted', 'loop', 'playsinline', 'class'],
    ];

    /** 中身ごと削除する要素 */
    private const DROP = [
        'script', 'style', 'object', 'embed', 'applet', 'form', 'input', 'button', 'textarea', 'select',
        'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript', 'frame', 'frameset', 'title', 'head',
    ];

    private const URL_ATTRS = ['href', 'src', 'cite', 'poster'];

    private const BLOCK_TAGS = 'address|article|aside|audio|blockquote|details|div|dl|dd|dt|fieldset|figcaption|figure'
        . '|footer|form|h[1-6]|header|hr|iframe|li|main|nav|ol|p|section|summary|table|thead|tbody|tfoot|tr|td|th'
        . '|caption|ul|video';

    private const YOUTUBE_EMBED = '#^https://www\.youtube(?:-nocookie)?\.com/embed/[A-Za-z0-9_-]{11}(?:\?[A-Za-z0-9_=&;%.\-]*)?$#';

    public static function render(string $source): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $source);
        $text = self::embedYoutube($text);
        $text = self::autop($text);
        return self::sanitize($text);
    }

    /** 1 行に YouTube の URL だけが書かれている場合、埋め込みプレーヤーに変換 */
    public static function embedYoutube(string $text): string
    {
        $pattern = '#^[ \t]*https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:[^\s&<]*&)*v=|shorts/|live/)|youtu\.be/)'
            . '([A-Za-z0-9_-]{11})[^\s<]*[ \t]*$#m';
        return (string) preg_replace_callback($pattern, static fn(array $m): string => self::youtubeIframe($m[1]), $text);
    }

    public static function youtubeIframe(string $id): string
    {
        return "\n\n" . '<div class="video-embed"><iframe src="https://www.youtube-nocookie.com/embed/' . $id
            . '" title="YouTube" loading="lazy" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"'
            . ' referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>' . "\n\n";
    }

    /** 空行区切りを段落に、段落内の改行を <br> に変換 */
    public static function autop(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        // <pre> の中は改行をそのまま残す
        $pres = [];
        $text = (string) preg_replace_callback('#<pre[\s>].*?</pre>#is', static function (array $m) use (&$pres): string {
            $key = '<!--PRE' . count($pres) . '-->';
            $pres[$key] = $m[0];
            return "\n\n" . $key . "\n\n";
        }, $text);

        // ブロック要素の前後を段落の区切りにする
        $text = (string) preg_replace('#(<(?:' . self::BLOCK_TAGS . ')[\s/>])#i', "\n\n$1", $text);
        $text = (string) preg_replace('#(</(?:' . self::BLOCK_TAGS . ')>)#i', "$1\n\n", $text);

        $out = [];
        foreach (preg_split('/\n\s*\n/', $text) ?: [] as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            if (isset($pres[$block]) || preg_match('#</?(?:' . self::BLOCK_TAGS . ')[\s/>]#i', $block)) {
                $out[] = $block;
                continue;
            }
            $out[] = '<p>' . nl2br($block, false) . '</p>';
        }
        return strtr(implode("\n", $out), $pres);
    }

    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><html><body><div>' . $html . '</div></body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }
        $root = $body->firstChild instanceof DOMElement && strtolower($body->firstChild->nodeName) === 'div'
            ? $body->firstChild
            : $body;

        self::cleanChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    private static function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes, false) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }
            if (!$child instanceof DOMElement) {
                $node->removeChild($child);
                continue;
            }
            $tag = strtolower($child->nodeName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }
            if (!isset(self::ALLOWED[$tag])) {
                // 許可していないタグは外して中身だけ残す
                self::cleanChildren($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            if (!self::cleanAttributes($child, $tag)) {
                $node->removeChild($child);
                continue;
            }
            self::cleanChildren($child);
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): bool
    {
        $allowed = self::ALLOWED[$tag];
        $attrs = [];
        foreach ($el->attributes as $attr) {
            $attrs[] = [$attr->nodeName, (string) $attr->nodeValue];
        }
        foreach ($attrs as [$rawName, $value]) {
            $name = strtolower($rawName);
            if (!in_array($name, $allowed, true)) {
                $el->removeAttribute($rawName);
                continue;
            }
            if (in_array($name, self::URL_ATTRS, true)) {
                $safe = self::safeUrl($value, $name === 'href');
                if ($safe === null) {
                    $el->removeAttribute($rawName);
                } else {
                    $el->setAttribute($name, $safe);
                }
            } elseif ($name === 'class') {
                $el->setAttribute('class', trim((string) preg_replace('/[^A-Za-z0-9_\- ]/', '', $value)));
            } elseif ($name === 'target' && $value !== '_blank') {
                $el->removeAttribute($rawName);
            }
        }

        if ($tag === 'iframe' && !preg_match(self::YOUTUBE_EMBED, $el->getAttribute('src'))) {
            return false;
        }
        if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
        if ($tag === 'img' && !$el->hasAttribute('loading')) {
            $el->setAttribute('loading', 'lazy');
        }
        if ($tag === 'video' && !$el->hasAttribute('controls')) {
            $el->setAttribute('controls', '');
        }
        return true;
    }

    private static function safeUrl(string $url, bool $isHref): ?string
    {
        $url = trim($url);
        // ブラウザは URL 中のタブや改行を無視するため、判定用に除去する
        $check = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $url);
        if ($check === '') {
            return null;
        }
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $check, $m)) {
            $allowed = $isHref ? ['http', 'https', 'mailto', 'tel'] : ['http', 'https'];
            return in_array(strtolower($m[1]), $allowed, true) ? $url : null;
        }
        // エディタで挿入したメディア（/media/...）や public/assets/ 内のファイル（/assets/...）は
        // 設置パスに関係なく書けるよう、表示時に設置パスを補う
        if (str_starts_with($url, '/media/') || str_starts_with($url, '/assets/')) {
            return base_path() . $url;
        }
        // "assets/..." のような相対パスは、記事ページなど階層の深い URL で壊れるので同じく補う
        if (str_starts_with($url, 'assets/') || str_starts_with($url, 'media/')) {
            return base_path() . '/' . $url;
        }
        return $url;
    }

    /** 一覧表示用の抜粋 */
    public static function excerpt(string $source, int $length = 110): string
    {
        $text = (string) preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $source);
        $text = (string) preg_replace('#<(br|/p|/li|/h[1-6]|/div|/blockquote)[^>]*>#i', ' ', $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('#https?://\S+#u', '', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
    }
}

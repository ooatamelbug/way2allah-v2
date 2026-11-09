<?php

namespace App\Domain\Content\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Renders stored legacy HTML under the owner's strict safe-rendering policy
 * (2026-10-15): public promotional files (`nuke_islamic_advfile.code` +
 * `audio_url`) and public poll comments (`nuke_pollcomments.comment`). OLD
 * echoed both raw (Pass 3A A-5, stored XSS by design); this never does.
 *
 * The stored value is parsed and **rebuilt** from an allowlist — nothing is
 * passed through, and the stored value is never modified:
 *
 * - **links** `<a>`: only an http(s) or scheme-less `href`; opened in a new tab
 *   with `rel="nofollow noopener noreferrer"`. An unsafe link keeps its text.
 * - **images** `<img>`: only an http(s) or scheme-less `src`, with `alt`,
 *   `title` and numeric `width` / `height`.
 * - **inert formatting** the historical content is written in (paragraphs,
 *   line breaks, bold/italic/underline, lists, headings, quotes, tables),
 *   rebuilt **without attributes** except a validated `align` (as a class) and
 *   `dir`. `font` and `center` become `span` / a centred `div`.
 * - **audio / video**: OLD's `[audio]URL[/audio]` shortcode, the `audio_url`
 *   column, and a media URL recoverable from a legacy `<embed>` / `<object>`
 *   (its `src`, `data`, or a Flash player's `soundFile`) render as native
 *   `<audio>` / `<video>` — but only for a browser-playable extension
 *   (mp3, m4a, aac, ogg, oga, opus, wav / mp4, webm, ogv). Anything else
 *   (RealMedia `.rm`, `mms://`, Flash streams) shows «الملف غير متوفر حالياً».
 *   Flash is never restored.
 *
 * Removed with their content: `script`, `style`, `iframe`, `frame`, `object`
 * internals, `form` controls, `svg`, `math`, document `head` parts, and the
 * like. Every other element is unwrapped (its safe content kept). Every
 * attribute not listed above — event handlers, `style`, `class`, `id` — is
 * dropped. All text and attribute values are escaped on output.
 */
final class SafeLegacyHtmlRenderer
{
    private const URL_SCHEMES = ['http', 'https'];

    private const AUDIO_EXTENSIONS = ['mp3', 'm4a', 'aac', 'ogg', 'oga', 'opus', 'wav'];

    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'ogv'];

    private const KEEP = [
        'p' => 'p', 'div' => 'div', 'span' => 'span', 'b' => 'b', 'strong' => 'strong', 'i' => 'i', 'em' => 'em',
        'u' => 'u', 'sup' => 'sup', 'sub' => 'sub', 'blockquote' => 'blockquote', 'ol' => 'ol', 'ul' => 'ul', 'li' => 'li',
        'h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h6',
        'table' => 'table', 'thead' => 'thead', 'tbody' => 'tbody', 'tfoot' => 'tfoot', 'tr' => 'tr', 'td' => 'td', 'th' => 'th',
        'font' => 'span', 'center' => 'div',
    ];

    private const VOID = ['br' => true, 'hr' => true];

    private const DROP = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'noscript', 'template', 'svg', 'math', 'head', 'title', 'meta',
        'link', 'base', 'form', 'input', 'button', 'textarea', 'select', 'option', 'applet', 'canvas', 'param', 'audio',
        'video', 'source', 'track', 'picture', 'map', 'area', 'xml',
    ];

    private const ALIGN = ['center' => 'text-center', 'right' => 'text-right', 'left' => 'text-left', 'justify' => 'text-justify'];

    public const UNAVAILABLE = 'الملف غير متوفر حالياً';

    private string $placeholder = '';

    /** @var array<int, string> */
    private array $shortcodes = [];

    public function render(?string $code, ?string $audioUrl = null): string
    {
        $html = $this->body((string) $code);

        if (trim((string) $audioUrl) !== '') {
            $html .= '<div class="text-center">'.$this->media((string) $audioUrl).'</div>';
        }

        return $html;
    }

    private function body(string $code): string
    {
        if (trim($code) === '') {
            return '';
        }

        // OLD's [audio]URL[/audio] shortcode becomes an unguessable placeholder
        // element before parsing, so stored markup cannot forge one.
        $this->placeholder = 'w2aaudio'.bin2hex(random_bytes(6));
        $this->shortcodes = [];
        $code = (string) preg_replace_callback('#\[audio\](.*?)\[/audio\]#is', function (array $m): string {
            $this->shortcodes[] = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return '<'.$this->placeholder.' n="'.(count($this->shortcodes) - 1).'"></'.$this->placeholder.'>';
        }, $code);

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body><div id="w2a-root">'.$code.'</div></body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('w2a-root');

        return $root === null ? '' : $this->children($root);
    }

    private function children(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $this->node($child);
        }

        return $out;
    }

    private function node(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return e($node->data);
        }

        if (! $node instanceof DOMElement) {
            return ''; // comments, processing instructions, CDATA
        }

        $tag = strtolower($node->tagName);

        if ($tag === $this->placeholder) {
            return $this->media($this->shortcodes[(int) $node->getAttribute('n')] ?? '');
        }

        if ($tag === 'embed' || $tag === 'object') {
            return $this->legacyPlayer($node);
        }

        if (in_array($tag, self::DROP, true)) {
            return '';
        }

        if ($tag === 'a') {
            $href = $this->safeUrl($node->getAttribute('href'));

            return $href === null
                ? $this->children($node)
                : '<a href="'.e($href).'" target="_blank" rel="nofollow noopener noreferrer">'.$this->children($node).'</a>';
        }

        if ($tag === 'img') {
            $src = $this->safeUrl($node->getAttribute('src'));
            if ($src === null) {
                return '';
            }
            $attributes = ' src="'.e($src).'" loading="lazy"';
            foreach (['alt', 'title'] as $name) {
                if ($node->hasAttribute($name)) {
                    $attributes .= ' '.$name.'="'.e($node->getAttribute($name)).'"';
                }
            }
            foreach (['width', 'height'] as $name) {
                $value = trim($node->getAttribute($name));
                if (ctype_digit($value) && (int) $value > 0 && (int) $value <= 2000) {
                    $attributes .= ' '.$name.'="'.(int) $value.'"';
                }
            }

            return '<img'.$attributes.' style="max-width:100%;height:auto">';
        }

        if (isset(self::VOID[$tag])) {
            return '<'.$tag.'>';
        }

        if (isset(self::KEEP[$tag])) {
            $out = self::KEEP[$tag];
            $attributes = '';
            $align = $tag === 'center' ? 'center' : strtolower(trim($node->getAttribute('align')));
            if (isset(self::ALIGN[$align])) {
                $attributes .= ' class="'.self::ALIGN[$align].'"';
            }
            $dir = strtolower(trim($node->getAttribute('dir')));
            if ($dir === 'rtl' || $dir === 'ltr') {
                $attributes .= ' dir="'.$dir.'"';
            }

            return '<'.$out.$attributes.'>'.$this->children($node).'</'.$out.'>';
        }

        return $this->children($node); // unknown element: keep its safe content only
    }

    /** A legacy `<embed>` / `<object>`: a playable media URL inside it, or the unavailable notice. */
    private function legacyPlayer(DOMElement $node): string
    {
        $candidates = [$node->getAttribute('src'), $node->getAttribute('data')];

        foreach ($node->getElementsByTagName('param') as $param) {
            $value = $param->getAttribute('value');
            $candidates[] = $value;
            parse_str(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $vars);
            foreach (['soundFile', 'file', 'src', 'url'] as $key) {
                if (isset($vars[$key]) && is_string($vars[$key])) {
                    $candidates[] = $vars[$key];
                }
            }
        }
        foreach ($node->getElementsByTagName('embed') as $embed) {
            $candidates[] = $embed->getAttribute('src');
        }

        foreach ($candidates as $candidate) {
            if ($this->mediaKind($candidate) !== null) {
                return $this->media($candidate);
            }
        }

        return $this->unavailable();
    }

    private function media(string $url): string
    {
        $kind = $this->mediaKind($url);
        $safe = $this->safeUrl($url);

        if ($kind === null || $safe === null) {
            return $this->unavailable();
        }

        return $kind === 'audio'
            ? '<audio controls preload="none" src="'.e($safe).'" style="max-width:100%"></audio>'
            : '<video controls preload="none" src="'.e($safe).'" style="max-width:100%"></video>';
    }

    private function mediaKind(string $url): ?string
    {
        $safe = $this->safeUrl($url);
        if ($safe === null || ! preg_match('#^https?://#i', $safe)) {
            return null;
        }

        $extension = strtolower(pathinfo((string) parse_url($safe, PHP_URL_PATH), PATHINFO_EXTENSION));

        return match (true) {
            in_array($extension, self::AUDIO_EXTENSIONS, true) => 'audio',
            in_array($extension, self::VIDEO_EXTENSIONS, true) => 'video',
            default => null,
        };
    }

    /** A stored link for an `href`: http(s) only, or null. For callers outside the markup walk. */
    public function httpUrl(?string $url): ?string
    {
        $safe = $this->safeUrl((string) $url);

        return $safe !== null && preg_match('#^https?://#i', $safe) === 1 ? $safe : null;
    }

    /** Native player markup for a browser-playable audio/video URL, or null. */
    public function player(?string $url): ?string
    {
        return $this->mediaKind((string) $url) === null ? null : $this->media((string) $url);
    }

    private function unavailable(): string
    {
        return '<span class="text-muted">'.e(self::UNAVAILABLE).'</span>';
    }

    /** An http(s) or scheme-less URL, or null. Browsers ignore control characters and whitespace inside a scheme, so they are removed before checking. */
    private function safeUrl(string $raw): ?string
    {
        $url = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $url = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $url);

        if ($url === '' || str_contains($url, '\\')) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m) === 1) {
            return in_array(strtolower($m[1]), self::URL_SCHEMES, true) ? $url : null;
        }

        // Scheme-less: a relative path. Refuse anything that is not plainly one.
        return preg_match('#^[a-z0-9_./?=&%\#\-~+]+$#i', $url) === 1 && ! str_contains($url, ':') ? $url : null;
    }
}

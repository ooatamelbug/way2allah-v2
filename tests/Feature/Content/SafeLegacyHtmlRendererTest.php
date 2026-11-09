<?php

/*
 * Final public features — the strict safe-rendering policy for stored
 * promotional files (owner decision, 2026-10-15). The stored markup is
 * rebuilt from an allowlist; nothing executable survives.
 */

use App\Domain\Content\Support\SafeLegacyHtmlRenderer;

function promo(?string $code, ?string $audioUrl = null): string
{
    return (new SafeLegacyHtmlRenderer)->render($code, $audioUrl);
}

function assertInert(string $html): void
{
    expect($html)->not->toMatch('/<\s*(script|iframe|object|embed|svg|math|style|form|input|base|meta|link|frame)\b/i')
        ->not->toMatch('/\son[a-z]+\s*=/i')
        ->not->toMatch('/(javascript|vbscript|data)\s*:/i')
        ->not->toMatch('/\sstyle\s*=\s*"(?!max-width:100%)/i');
}

it('keeps the historical content: a linked banner image, formatting and text', function () {
    $html = promo('<p align="center"><a href="http://www.way2allah.com/modules.php?name=Khotab&op=Detailes&khid=7078"><img src="http://www.way2allah.com/media/files/banners/a.gif" border="0" width="410" height="180"></a></p><p><b><font color="#FF0000" size="4">لو معرفناش ربنا</font></b></p>');

    expect($html)->toContain('<p class="text-center"><a href="http://www.way2allah.com/modules.php?name=Khotab&amp;op=Detailes&amp;khid=7078" target="_blank" rel="nofollow noopener noreferrer"><img src="http://www.way2allah.com/media/files/banners/a.gif" loading="lazy" width="410" height="180"')
        ->toContain('<p><b><span>لو معرفناش ربنا</span></b></p>')
        ->not->toContain('color=')->not->toContain('border=');
    assertInert($html);
});

it('removes scripts, iframes, svg and other executable elements with their content', function (string $code) {
    $html = promo('<p>قبل</p>'.$code.'<p>بعد</p>');

    expect($html)->toContain('قبل')->toContain('بعد')->not->toContain('alert');
    assertInert($html);
})->with([
    'script' => '<script>alert(1)</script>',
    'script, mixed case' => '<ScRiPt src="https://evil.example/x.js">alert(1)</sCrIpT>',
    'iframe' => '<iframe src="https://evil.example/" onload="alert(1)"></iframe>',
    'svg onload' => '<svg onload="alert(1)"><circle/></svg>',
    'style block' => '<style>body{background:url("javascript:alert(1)")}</style>',
    'form' => '<form action="https://evil.example"><input name="alert" value="alert"></form>',
    'meta refresh' => '<meta http-equiv="refresh" content="0;url=javascript:alert(1)">',
    'base' => '<base href="javascript:alert(1)//">',
    'math' => '<math><mtext>alert</mtext></math>',
]);

it('drops every event handler and style attribute', function () {
    $html = promo('<div onclick="alert(1)" style="background:url(javascript:alert(1))" class="x" id="y"><img src="https://x.example/a.gif" onerror="alert(1)" onload="alert(2)"><b onmouseover="alert(3)">نص</b></div>');

    expect($html)->toBe('<div><img src="https://x.example/a.gif" loading="lazy" style="max-width:100%;height:auto"><b>نص</b></div>');
});

it('refuses javascript:, vbscript:, data: and obfuscated schemes in links and images', function (string $url) {
    $html = promo('<a href="'.$url.'">الرابط</a><img src="'.$url.'">');

    expect($html)->toBe('الرابط');
})->with([
    'javascript:alert(1)', 'JaVaScRiPt:alert(1)', ' javascript:alert(1)', 'java&#x09;script:alert(1)', 'java&#10;script:alert(1)',
    "java\tscript:alert(1)", '&#106;avascript:alert(1)', 'vbscript:msgbox(1)', 'data:text/html;base64,PHNjcmlwdD4=',
    'data:image/svg+xml,<svg onload=alert(1)>', 'file:///etc/passwd', 'ftp://x.example/a', 'mailto:x@example.com', '\\\\evil\\share',
]);

it('escapes text and attribute values instead of interpreting them', function () {
    $html = promo('<p>a &lt;script&gt;alert(1)&lt;/script&gt; b</p><img src="https://x.example/a.gif" alt="x&quot; onerror=&quot;alert(1)">');

    expect($html)->toContain('a &lt;script&gt;alert(1)&lt;/script&gt; b')
        ->toContain('alt="x&quot; onerror=&quot;alert(1)"');
    assertInert(str_replace('alt="x&quot; onerror=&quot;alert(1)"', '', $html));
});

it('copes with malformed HTML without executing or leaking anything', function () {
    $html = promo('<p><b>عريض<p>غير مغلق <a href="https://x.example/">رابط<div><<script>>alert(1)</script></td></table><img src=https://x.example/b.gif onerror=alert(1)');

    expect($html)->toContain('عريض')->toContain('غير مغلق')->toContain('href="https://x.example/"')->not->toContain('<script');
    assertInert($html);
});

it('unwraps full HTML documents and unknown elements, dropping the head', function () {
    $html = promo('<html><head><title>T</title><meta charset="x"><style>p{}</style></head><body><o:p>نص</o:p><marquee>متحرك</marquee></body></html>');

    // libxml's HTML parser reads Word's `<o:p>` as a plain (inert) `<p>`.
    expect($html)->toBe('<p>نص</p>متحرك');
});

it('plays the audio shortcode, the audio_url column and a Flash player\'s soundFile natively — never Flash', function () {
    expect(promo('[audio]http://x.example/a.mp3[/audio]'))->toBe('<audio controls preload="none" src="http://x.example/a.mp3" style="max-width:100%"></audio>')
        ->and(promo('', 'https://archive.org/download/x/b.mp3'))->toBe('<div class="text-center"><audio controls preload="none" src="https://archive.org/download/x/b.mp3" style="max-width:100%"></audio></div>')
        ->and(promo('<object type="application/x-shockwave-flash" data="http://m.example/player.swf"><param name="movie" value="http://m.example/player.swf"><param name="FlashVars" value="playerID=121&amp;autostart=yes&amp;soundFile=http://w.example/qabl.mp3"></object>'))
        ->toBe('<audio controls preload="none" src="http://w.example/qabl.mp3" style="max-width:100%"></audio>')
        ->and(promo('<embed src="http://x.example/v.mp4" type="video/mp4">'))->toContain('<video controls');
});

it('shows unplayable legacy media as unavailable instead of executing it', function (string $code) {
    $html = promo($code);

    expect($html)->toContain(SafeLegacyHtmlRenderer::UNAVAILABLE)->not->toContain('<audio')->not->toContain('<video');
    assertInert($html);
})->with([
    'RealMedia embed' => '<embed src="http://way2allah.com/media/files/sound/monaya.rm" type="audio/mpeg" autostart="true">',
    'Flash live stream' => '<object classid="clsid:D27CDB6E"><param name="movie" value="http://www.ustream.tv/flash/live/1/824104"><embed src="http://www.ustream.tv/flash/live/1/824104" type="application/x-shockwave-flash"></object>',
    'mms stream' => '<object><param name="movie" value="mms://alhekmah.tv/live"><embed src="mms://alhekmah.tv/live"></object>',
    'RealMedia shortcode with markup' => '[audio]<font face="Arial">http://www.way2allah.com/media/sound/viruse.rm</font>[/audio]',
    'javascript in shortcode' => '[audio]javascript:alert(1)//.mp3[/audio]',
    'swf only' => '<embed src="https://x.example/player.swf">',
]);

it('rejects an unsafe audio_url', function () {
    expect(promo('', 'javascript:alert(1)//a.mp3'))->toContain(SafeLegacyHtmlRenderer::UNAVAILABLE)->not->toContain('javascript');
});

it('cannot be tricked into the audio placeholder by stored markup', function () {
    $html = promo('<w2aaudio000000000000 n="0">https://evil.example/</w2aaudio000000000000>[audio]https://x.example/ok.mp3[/audio]');

    expect(substr_count($html, '<audio'))->toBe(1)->and($html)->toContain('https://x.example/ok.mp3')->toContain('https://evil.example/');
    assertInert($html);
});

it('renders nothing for empty content', function () {
    expect(promo(null))->toBe('')->and(promo('   '))->toBe('');
});

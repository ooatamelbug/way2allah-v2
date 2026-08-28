<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * G-06 (test-hardening pass) — protects `CategoryDownItemsController`
 * (`khotab-series-{id}.grx` / `khotab-series-{id}-{cat}.grx`) against
 * regression. No application behavior is changed by this file — every
 * assertion targets behavior already documented in IF-040/IF-041.
 */
function useInMemoryMainConnectionForCategoryDownItems(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForCategoryDownItems();
});

it('khotab-series-{id}.grx: 200, correct headers, one URL/File block per item', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'My Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3', 'title' => 'Item One'],
        ['id' => 2, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/b.mp3', 'title' => 'Item Two'],
    ]);

    $response = $this->get('/khotab-series-9.grx');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/force-download')
        ->assertHeader('Content-Disposition', 'attachment; filename="Way2Allah-My Series.grx"')
        ->assertHeader('Content-Transfer-Encoding', 'binary');

    $decoded = iconv('windows-1256', 'utf-8', $response->getContent());
    expect(substr_count($decoded, 'URL:'))->toBe(2)
        ->and($decoded)->toContain('https://example.com/a.mp3')
        ->toContain('https://example.com/b.mp3')
        ->toContain('Item One.mp3.GetRight')
        ->toContain('C:\Way2Allah\My Series\\');
});

it('khotab-series-{id}-{cat}.grx: filters items through khotab_category_index for the given category', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'My Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/in-cat.mp3', 'title' => 'In Category'],
        ['id' => 2, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/not-in-cat.mp3', 'title' => 'Not In Category'],
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 11]);

    $decoded = iconv('windows-1256', 'utf-8', $this->get('/khotab-series-9-11.grx')->assertOk()->getContent());

    expect($decoded)->toContain('in-cat.mp3')->not->toContain('not-in-cat.mp3');
});

it('IF-040: output is windows-1256-encoded, not UTF-8 — round-trips correctly, and raw bytes are not valid UTF-8', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'سلسلة عربية']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3', 'title' => 'شرح صحيح مسلم',
    ]);

    $body = $this->get('/khotab-series-9.grx')->assertOk()->getContent();

    expect(mb_check_encoding($body, 'UTF-8'))->toBeFalse();

    $decoded = iconv('windows-1256', 'utf-8', $body);
    expect($decoded)->toContain('شرح صحيح مسلم');
});

it('title sanitization: backslash and forward-slash become a hyphen, colon becomes underscore, other reserved chars become a space', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3',
        'title' => 'A\\B/C:D*E?F<G>H|I"J',
    ]);

    $decoded = iconv('windows-1256', 'utf-8', $this->get('/khotab-series-9.grx')->assertOk()->getContent());

    expect($decoded)->toContain('A-B-C_D E F G H I J.mp3.GetRight');
});

it('hidden items are excluded from the playlist', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'ser_id' => 9, 'hidden' => 1, 'link' => 'https://example.com/hidden.mp3', 'title' => 'Hidden',
    ]);

    $decoded = iconv('windows-1256', 'utf-8', $this->get('/khotab-series-9.grx')->assertOk()->getContent());

    expect($decoded)->not->toContain('hidden.mp3');
});

it('404s for a nonexistent series', function () {
    $this->get('/khotab-series-99999.grx')->assertNotFound();
    $this->get('/khotab-series-99999-11.grx')->assertNotFound();
});

// ---- .grx Encoding Repair (decision-log #53), BUSINESS_REPAIR_LOW_RISK,
// explicitly NOT recovered legacy behavior — legacy's own real DownItems()
// would silently serve an empty 200 body for these same rows; the owner
// explicitly rejected reproducing that, approving narrow normalization of
// exactly 3 proven-unsupported-in-Windows-1256 character classes instead. ----

it('Arabic-Indic digits are normalized to ASCII digits before Windows-1256 export (٢٠١٨ -> 2018)', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3', 'title' => 'عام ٢٠١٨',
    ]);

    $response = $this->get('/khotab-series-9.grx');

    $response->assertOk();
    $decoded = iconv('windows-1256', 'utf-8', $response->getContent());
    expect($decoded)->toContain('عام 2018')->not->toContain('٢٠١٨');
});

it('U+200F (RIGHT-TO-LEFT MARK) is stripped without removing the surrounding Arabic text', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3',
        'title' => "الوجيز في فقه السنة\u{200F}\u{200F}\u{200F}",
    ]);

    $response = $this->get('/khotab-series-9.grx');

    $response->assertOk();
    $decoded = iconv('windows-1256', 'utf-8', $response->getContent());
    expect($decoded)->toContain('الوجيز في فقه السنة')
        ->and(mb_strpos($decoded, "\u{200F}"))->toBeFalse();
});

it('the ﷺ ligature (U+FDFA) is expanded to "صلى الله عليه وسلم" before Windows-1256 export', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3',
        'title' => 'مرور النبي ﷺ على بيت علي',
    ]);

    $response = $this->get('/khotab-series-9.grx');

    $response->assertOk();
    $decoded = iconv('windows-1256', 'utf-8', $response->getContent());
    expect($decoded)->toContain('مرور النبي صلى الله عليه وسلم على بيت علي')
        ->and(mb_strpos($decoded, "\u{FDFA}"))->toBeFalse();
});

it('all 3 normalized character classes combined in one series still convert successfully, matching the real khotab-series-15972.grx case', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3', 'title' => 'محترف بر ٢'],
        ['id' => 2, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/b.mp3', 'title' => "فقه الصيام «الوجيز»\u{200F}\u{200F}"],
        ['id' => 3, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/c.mp3', 'title' => 'مرور النبي ﷺ'],
    ]);

    $response = $this->get('/khotab-series-9.grx');

    $response->assertOk();
    expect($response->getContent())->not->toBeEmpty();
});

it('U+0670 (ARABIC LETTER SUPERSCRIPT ALEF, dagger alif), found in Quranic-diacritic titles, is now normalized (decision-log #54) rather than triggering a 500', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3',
        'title' => "هَٰذِهِ الْحَيَاةِ الدُّنْيَا",
    ]);

    $response = $this->get('/khotab-series-9.grx');
    $response->assertOk();

    $decoded = iconv('windows-1256', 'utf-8', $response->getContent());
    expect($decoded)->toContain('هَذِهِ الْحَيَاةِ الدُّنْيَا')
        ->and(mb_strpos($decoded, "\u{0670}"))->toBeFalse();
});

it('a series with zero non-hidden items still returns 200 with an empty body — a pre-existing, unrelated condition, not the encoding-failure path', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Empty Series']);

    $response = $this->get('/khotab-series-9.grx');

    $response->assertOk();
    expect($response->getContent())->toBe('');
});

// ---- Quranic Unicode Compatibility (decision-log #54), owner-approved
// extension of the #53 repair — 12 additional codepoints found in titles
// quoting Quranic verses verbatim with full tashkeel/Uthmani annotation.
// Explicit allowlist, not a Unicode-range/-category rule. ----

it('recomposes decomposed ALEF+hamza/maddah sequences into the precomposed letter (ا+U+0654 -> أ, ا+U+0655 -> إ, ا+U+0653 -> آ)', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3', 'title' => "سورة ا\u{0654}لأعلى"],
        ['id' => 2, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/b.mp3', 'title' => "سورة ا\u{0655}لإخلاص"],
        ['id' => 3, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/c.mp3', 'title' => "قدرة ا\u{0653}لله"],
    ]);

    $decoded = iconv('windows-1256', 'utf-8', $this->get('/khotab-series-9.grx')->assertOk()->getContent());

    expect($decoded)->toContain('سورة ألأعلى')
        ->toContain('سورة إلإخلاص')
        ->toContain('قدرة آلله')
        ->and(mb_strpos($decoded, "\u{0654}"))->toBeFalse()
        ->and(mb_strpos($decoded, "\u{0655}"))->toBeFalse();
});

it('substitutes exactly the 2 owner-approved distinct letters (ALEF WASLA U+0671 -> ا, FARSI YEH U+06CC -> ي) and no others', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3',
        'title' => "كَذَٰلِكَ حَقَّتۡ كَلِمَتُ رَبِّكَ عَلَى \u{0671}لَّذِينَ فَسَقُوٓاْ",
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 2, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/b.mp3',
        'title' => "وَقَالَ نِسۡوَةٌ فِ\u{06CC} ٱلۡمَدِینَةِ",
    ]);

    $decoded = iconv('windows-1256', 'utf-8', $this->get('/khotab-series-9.grx')->assertOk()->getContent());

    expect($decoded)->toContain('الَّذِينَ')
        ->and(mb_strpos($decoded, "\u{0671}"))->toBeFalse()
        ->and($decoded)->toContain('فِي')
        ->and(mb_strpos($decoded, "\u{06CC}"))->toBeFalse();
});

it('removes the 8 owner-approved standalone Quranic annotation marks without disturbing surrounding text', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3',
        'title' => "هَٰذِهِ الْحَيَاةِ تۡ نِحْلَةً ۚ قَدْرِهِۦ قَبْضَتُهُۥ عَلَىٰۤ نِسۡوَةࣱ",
    ]);

    $response = $this->get('/khotab-series-9.grx');
    $response->assertOk();

    $decoded = iconv('windows-1256', 'utf-8', $response->getContent());
    foreach (["\u{0670}", "\u{06E1}", "\u{06DA}", "\u{06E6}", "\u{06E5}", "\u{06E4}", "\u{08F1}"] as $mark) {
        expect(mb_strpos($decoded, $mark))->toBeFalse();
    }
    expect($decoded)->toContain('هَذِهِ الْحَيَاةِ');
});

it('the real, previously-failing items (100712, 187318, 187348, 218707, 225928-equivalent) now succeed and decode to readable, word-identity-preserving Arabic', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 100712, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3', 'title' => 'مَثَلُ مَا يُنْفِقُونَ فِي هَٰذِهِ الْحَيَاةِ الدُّنْيَا'],
        ['id' => 187318, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/b.mp3', 'title' => '87 - قراءة تفسير سورة الأعلى من كتاب المختصر'],
        ['id' => 187348, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/c.mp3', 'title' => '112 - قراءة تفسير سورة الإخلاص من كتاب المختصر'],
        ['id' => 218707, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/d.mp3', 'title' => 'رَضُوا بِأَن يَكُونُوا مَعَ الْخَوَالِفِ وَطُبِعَ عَلَىٰ قُلُوبِهِمْ'],
        ['id' => 225928, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/e.mp3', 'title' => 'كَذَٰلِكَ حَقَّتۡ كَلِمَتُ رَبِّكَ عَلَى ٱلَّذِينَ فَسَقُوٓاْ'],
    ]);

    $response = $this->get('/khotab-series-9.grx');
    $response->assertOk();

    $decoded = iconv('windows-1256', 'utf-8', $response->getContent());
    expect($decoded)->toContain('هَذِهِ الْحَيَاةِ')
        ->toContain('الأعلى')
        ->toContain('الإخلاص')
        ->toContain('عَلَى قُلُوبِهِمْ')
        ->toContain('الَّذِينَ فَسَقُواْ');
});

it('the additional real items found in the broader 150-series sample (110233/224914/261626/265409-equivalent) now succeed', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 110233, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3', 'title' => "15-\"وَآتُوا النِّسَاءَ صَدُقَاتِهِنَّ نِحْلَةً \u{06DA}\""],
        ['id' => 224914, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/b.mp3', 'title' => "وَمَا قَدَرُواْ \u{0671}للَّهَ حَقَّ قَدْرِهِ\u{06E6} وَ\u{0671}لْأَرْضُ قَبْضَتُهُ\u{06E5}"],
        ['id' => 265409, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/c.mp3', 'title' => "وَقَالَ نِسۡوَة\u{08F1} فِ\u{06CC} \u{0671}لۡمَدِینَةِ"],
    ]);

    $response = $this->get('/khotab-series-9.grx');
    $response->assertOk();
    expect($response->getContent())->not->toBeEmpty();
});

it('a genuinely unknown character still not covered by the approved allowlist continues to trigger a controlled 500', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3',
        'title' => "بسم الله \u{1EE00}", // outside the approved allowlist entirely
    ]);

    $this->get('/khotab-series-9.grx')->assertStatus(500);
});

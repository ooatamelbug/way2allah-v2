<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MobileHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 7 — the public Mobile Downloads section at its
 * legacy URLs (OLD `modules/Mobile/index.php`, plan §26).
 */
beforeEach(function () {
    H::setUp();
});

it('main page lists top-level categories by title with up to three subcategories (OLD index)', function () {
    $b = H::category('ب أناشيد');
    $a = H::category('أ القرآن');
    foreach (['د', 'ج', 'ه', 'و'] as $t) {
        H::category("فرعي {$t}", $a);
    }
    H::material($a);

    $content = $this->get('/mobiles.htm')->assertOk()->getContent();

    expect(mb_strpos($content, 'أ القرآن'))->toBeLessThan(mb_strpos($content, 'ب أناشيد'))
        ->and($content)->toContain('فرعي ج')->toContain('فرعي د')->toContain('فرعي ه')->not->toContain('فرعي و')
        ->and($content)->toContain('/mobiles-cat'.$b.'.htm')->toContain('يوجد حاليا 1 مادة في 6 تصنيف');
});

it('category page: path, subcategories, materials sorted by title by default, paged 10', function () {
    $parent = H::category('القرآن');
    $cid = H::category('تلاوات', $parent);
    foreach (range(1, 12) as $i) {
        H::material($cid, ['title' => sprintf('مادة %02d', $i)]);
    }

    $first = $this->get("/mobiles-cat{$cid}.htm")->assertOk()->getContent();
    expect($first)->toContain('القرآن/تلاوات')->toContain('مادة 01')->toContain('مادة 10')->not->toContain('مادة 11')
        ->and($first)->toContain("/mobile{$cid}-10-titleA-10.htm");

    $this->get("/mobile{$cid}-10-titleA-10.htm")->assertOk()->assertSee('مادة 11')->assertSee('مادة 12')->assertDontSee('مادة 01');
});

it('honours the eight OLD sort keys and sends an unknown one home (convertorderbyin)', function () {
    $cid = H::category();
    H::material($cid, ['title' => 'قليل', 'hits' => 1]);
    H::material($cid, ['title' => 'كثير', 'hits' => 99]);

    $content = $this->get("/mobile{$cid}-orderbyhitsD.htm")->assertOk()->getContent();
    expect(mb_strpos($content, 'كثير'))->toBeLessThan(mb_strpos($content, 'قليل'));

    $this->get("/mobile{$cid}-orderbyevil.htm")->assertRedirect('/');
});

it('new materials: per-day counts and a date page (NewDownloads / NewDownloadsDate)', function () {
    $cid = H::category();
    H::material($cid, ['title' => 'اليوم', 'date' => date('Y-m-d H:i:s')]);
    H::material($cid, ['title' => 'قديمة', 'date' => '2010-01-01 00:00:00']);

    $this->get('/mobiles-newmobiles.htm')->assertOk()->assertSee('آخر أسبوع 1')->assertSee(date('Y-m-d'));
    $this->get('/mobile-shownew-30.htm')->assertOk()->assertSee('آخر 30 يوم 1');
    $this->get('/mobile-seldate-'.time().'.htm')->assertOk()->assertSee('اليوم')->assertDontSee('قديمة');
});

it('most popular and top rated follow OLD rules (top rated needs a rating and at least 5 votes)', function () {
    $cid = H::category();
    H::material($cid, ['title' => 'شعبية', 'hits' => 500]);
    H::material($cid, ['title' => 'مقيمة', 'downloadratingsummary' => 9, 'totalvotes' => 5]);
    H::material($cid, ['title' => 'أصوات قليلة', 'downloadratingsummary' => 10, 'totalvotes' => 4]);

    $popular = $this->get('/mobiles-mostpopular.htm')->assertOk()->getContent();
    expect(mb_strpos($popular, 'شعبية'))->toBeLessThan(mb_strpos($popular, 'مقيمة'));
    $this->get('/mobiles-toprated.htm')->assertOk()->assertSee('مقيمة')->assertDontSee('أصوات قليلة');
});

it('search matches title or description, lists matching categories, and serves the legacy URL forms', function () {
    $cid = H::category('أدعية');
    H::material($cid, ['title' => 'دعاء الصباح']);
    H::material($cid, ['title' => 'شيء آخر', 'description' => 'فيه دعاء']);
    H::material($cid, ['title' => 'لا علاقة']);

    $this->get('/mobile/search?query=دعاء')->assertOk()->assertSee('دعاء الصباح')->assertSee('شيء آخر')->assertDontSee('لا علاقة');
    $this->get('/mobile-search-أدعية.htm')->assertOk()->assertSee('تصنيفات مطابقة');
    $this->get('/mobile-search-دعاء-titleD.htm')->assertOk()->assertSee('دعاء الصباح');
    $this->get('/mobile-search-دعاء-0-titleA-10.htm')->assertOk()->assertSee('شيء آخر');
    $this->get('/mobile/search?query=100%25')->assertOk()->assertSee('لا يوجد نتائج للبحث');
});

it('details shows metadata and rating split, renders legacy HTML descriptions as safe text, and hides the email', function () {
    $cid = H::category();
    $lid = H::material($cid, [
        'title' => 'تطبيق', 'description' => '<strong>مهم</strong><br>سطر ثان<script>alert(1)</script>',
        'email' => 'secret@example.com', 'name' => 'المؤلف', 'version' => '1.2', 'totalvotes' => 2, 'downloadratingsummary' => 7,
    ]);
    H::vote($lid, ['ratinguser' => 'عضو', 'rating' => 9]);
    H::vote($lid, ['rating' => 5]);

    $content = $this->get("/mobileview-details-{$lid}.htm")->assertOk()->getContent();
    expect($content)->toContain('مهم')->toContain('سطر ثان')->not->toContain('<strong>مهم')->not->toContain('<script>alert(1)')
        ->and($content)->not->toContain('secret@example.com')
        ->and($content)->toContain('المؤلف')->toContain('1.2')->toContain('أعضاء مسجلين: 1')->toContain('زوار: 1');
    $this->get("/mobileview-details-{$lid}-تطبيق_جديد.htm")->assertOk();
});

it('comments page lists comments newest first; editorial page shows the review', function () {
    $cid = H::category();
    $lid = H::material($cid);
    H::vote($lid, ['ratinguser' => 'أ', 'ratingcomments' => 'قديم', 'ratingtimestamp' => '2010-01-01 00:00:00']);
    H::vote($lid, ['ratinguser' => 'ب', 'ratingcomments' => 'حديث', 'ratingtimestamp' => '2010-06-01 00:00:00']);
    H::vote($lid, ['ratinguser' => 'ج', 'ratingcomments' => '']);
    DB::connection('main')->table('nuke_downloads_editorials')->insert(['downloadid' => $lid, 'adminid' => 'x', 'editorialtitle' => 'رأينا', 'editorialtext' => 'ممتازة']);

    $comments = $this->get("/mobileview-comments-{$lid}.htm")->assertOk()->getContent();
    expect(mb_strpos($comments, 'حديث'))->toBeLessThan(mb_strpos($comments, 'قديم'));
    $this->get("/mobileview-editorial-{$lid}.htm")->assertOk()->assertSee('رأينا')->assertSee('ممتازة');
});

it('download redirects to an available URL and counts the hit (OLD getit)', function () {
    $lid = H::material(H::category(), ['url' => 'https://archive.org/download/a/b.mp3', 'hits' => 4]);

    $this->get("/mobile-file-{$lid}.htm")->assertRedirect('https://archive.org/download/a/b.mp3');

    expect((int) H::row('nuke_downloads_downloads', 'lid', $lid)->hits)->toBe(5);
});

it('reports a historical dead-host or truncated URL as unavailable — no redirect, no hit', function (string $url) {
    $lid = H::material(H::category(), ['url' => $url, 'hits' => 4]);

    $this->get("/mobile-file-{$lid}.htm")->assertStatus(410)->assertSee('غير متوفر حالياً');
    $this->get("/mobileview-details-{$lid}.htm")->assertOk()->assertSee('الملف غير متوفر حالياً')->assertDontSee("/mobile-file-{$lid}.htm", false);

    expect((int) H::row('nuke_downloads_downloads', 'lid', $lid)->hits)->toBe(4)
        ->and(H::row('nuke_downloads_downloads', 'lid', $lid)->url)->toBe($url);   // stored URL untouched
})->with([
    'dead host' => ['http://way2allah.knoz4arab.com/media/mobile/do3a/Do3a2.wav'],
    'truncated at 100' => ['http://way2allah.knoz4arab.com/media/mobile/quran/koran%20Meshary%20Rashed%20Al%20afasy%20AMR/001.am'],
    'not http' => ['javascript:alert(1)'],
]);

it('returns 404 for missing categories and materials', function () {
    $this->get('/mobiles-cat999.htm')->assertNotFound();
    $this->get('/mobileview-details-999.htm')->assertNotFound();
    $this->get('/mobile-file-999.htm')->assertNotFound();
    $this->get('/ratemobile-999.htm')->assertNotFound();
});

it('serves the other legacy rating URL form', function () {
    $lid = H::material(H::category(), ['title' => 'للتقييم']);

    $this->get("/ratemobile-{$lid}.htm")->assertOk()->assertSee('للتقييم');
    $this->get("/ratemobile-{$lid}-للتقييم.htm")->assertOk();
});

<?php

use App\Domain\Content\Services\Mobile\MobileRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MobileHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 7 — visitor contributions: member submissions,
 * correction requests, broken-link reports, and ratings / comments
 * (members and anonymous visitors), per OLD `modules/Mobile/index.php`.
 * "Member" = a vBulletin session (OLD `is_user()`).
 */
beforeEach(function () {
    H::setUp();
    $this->cid = H::category('برامج');
});

function mobileFields(int $cid, array $override = []): array
{
    return array_merge([
        'cid' => $cid, 'title' => 'برنامج جديد', 'url' => 'https://archive.org/download/p/app.sis',
        'description' => 'وصف البرنامج', 'name' => 'صاحب', 'email' => 'a@b.c', 'filesize' => '1.234', 'version' => '1.0', 'homepage' => 'http://x',
    ], $override);
}

// ---- submission (OLD AddDownload / Add) ----

it('lets a member submit a material for review, recorded under their username', function () {
    $this->actingAs(H::member('أبو يحيى'), 'vbulletin');

    $this->post(route('mobile.submit'), mobileFields($this->cid))->assertRedirect('/mobiles.htm')->assertSessionHas('success');

    $row = DB::connection('main')->table('nuke_downloads_newdownload')->first();
    expect([$row->title, $row->submitter, (int) $row->filesize, (int) $row->cid])->toBe(['برنامج جديد', 'أبو يحيى', 1234, $this->cid])
        ->and(DB::connection('main')->table('nuke_downloads_downloads')->count())->toBe(0);   // not published until approved
});

it('keeps submission members-only (downloads_anonadddownloadlock = 1)', function () {
    $this->get('/mobiles-admobile.htm')->assertOk()->assertSee('للأعضاء المسجلين فقط');
    $this->post(route('mobile.submit'), mobileFields($this->cid))->assertOk()->assertSee('للأعضاء المسجلين فقط');

    expect(DB::connection('main')->table('nuke_downloads_newdownload')->count())->toBe(0);
});

it('refuses a URL already published, and silently de-duplicates a pending submission (OLD)', function () {
    $this->actingAs(H::member(), 'vbulletin');
    H::material($this->cid, ['url' => 'https://archive.org/download/p/taken.sis']);

    $this->post(route('mobile.submit'), mobileFields($this->cid, ['url' => 'https://archive.org/download/p/taken.sis']))->assertSessionHasErrors('url');
    $this->post(route('mobile.submit'), mobileFields($this->cid))->assertSessionHas('success');
    $this->post(route('mobile.submit'), mobileFields($this->cid, ['url' => 'https://archive.org/download/p/other.sis']))->assertSessionHas('success');   // same title

    expect(DB::connection('main')->table('nuke_downloads_newdownload')->count())->toBe(1);
});

it('validates submissions — required fields, http(s) URL within the 100-character column, real category', function (array $override, string $field) {
    $this->actingAs(H::member(), 'vbulletin');

    $this->post(route('mobile.submit'), mobileFields($this->cid, $override))->assertSessionHasErrors($field);

    expect(DB::connection('main')->table('nuke_downloads_newdownload')->count())->toBe(0);
})->with([
    'no title' => [['title' => ''], 'title'],
    'no url' => [['url' => ''], 'url'],
    'no description' => [['description' => ''], 'description'],
    'url over 100 (would be truncated)' => [['url' => 'https://archive.org/'.str_repeat('a', 90)], 'url'],
    'not a web url' => [['url' => 'javascript:alert(1)'], 'url'],
    'unknown category' => [['cid' => 999], 'cid'],
]);

// ---- correction request (OLD modifydownloadrequestS) ----

it('stores a member correction request with every proposed field (brokendownload = 0)', function () {
    $lid = H::material($this->cid, ['title' => 'قديم']);
    $this->actingAs(H::member('مصحح'), 'vbulletin');

    $this->get("/mobile-mod-{$lid}.htm")->assertOk()->assertSee('value="قديم"', false);
    $this->post(route('mobile.correction', $lid), mobileFields($this->cid, ['title' => 'مصحح']))->assertRedirect("/mobileview-details-{$lid}.htm");

    $row = DB::connection('main')->table('nuke_downloads_modrequest')->first();
    expect([(int) $row->lid, (int) $row->brokendownload, $row->title, $row->modifysubmitter])->toBe([$lid, 0, 'مصحح', 'مصحح'])
        ->and(H::row('nuke_downloads_downloads', 'lid', $lid)->title)->toBe('قديم');   // unchanged until staff accept
});

it('keeps correction requests and broken-link reports members-only', function () {
    $lid = H::material($this->cid);

    $this->get("/mobile-mod-{$lid}.htm")->assertSee('للأعضاء المسجلين فقط');
    $this->post(route('mobile.correction', $lid), mobileFields($this->cid))->assertSee('للأعضاء المسجلين فقط');
    $this->get("/mobile-broken-{$lid}.htm")->assertSee('للأعضاء المسجلين فقط');
    $this->post(route('mobile.broken', $lid))->assertSee('للأعضاء المسجلين فقط');

    expect(DB::connection('main')->table('nuke_downloads_modrequest')->count())->toBe(0);
});

// ---- broken link (OLD brokendownloadS) ----

it('stores a member broken-link report (brokendownload = 1); repeated reports are kept, as OLD', function () {
    $lid = H::material($this->cid);
    $this->actingAs(H::member('مبلغ'), 'vbulletin');

    $this->post(route('mobile.broken', $lid))->assertRedirect("/mobileview-details-{$lid}.htm");
    $this->post(route('mobile.broken', $lid));

    $rows = DB::connection('main')->table('nuke_downloads_modrequest')->get();
    expect($rows)->toHaveCount(2)->and([(int) $rows[0]->lid, (int) $rows[0]->brokendownload, $rows[0]->modifysubmitter])->toBe([$lid, 1, 'مبلغ']);
});

// ---- rating (OLD ratedownload / addrating) ----

it('records an anonymous rating under the site anonymous name, without a comment, and recomputes the material', function () {
    $lid = H::material($this->cid);

    $this->post(route('mobile.rate', $lid), ['rating' => 7, 'comment' => 'تجاهل'])->assertSessionHas('success');

    $vote = DB::connection('main')->table('nuke_downloads_votedata')->first();
    $m = H::row('nuke_downloads_downloads', 'lid', $lid);
    expect([$vote->ratinguser, (int) $vote->rating, $vote->ratingcomments, $vote->ratinghostname])->toBe([H::ANONYMOUS, 7, '', '127.0.0.1'])
        ->and([(int) $m->downloadratingsummary, (int) $m->totalvotes, (int) $m->totalcomments])->toBe([7, 1, 0]);
});

it('lets a member rate with a comment, counted in totalcomments', function () {
    $lid = H::material($this->cid);
    $this->actingAs(H::member('معلق'), 'vbulletin');

    $this->get("/ratemobile-{$lid}.htm")->assertSee('name="comment"', false);
    $this->post(route('mobile.rate', $lid), ['rating' => 9, 'comment' => 'جيد جدا']);

    expect(DB::connection('main')->table('nuke_downloads_votedata')->value('ratingcomments'))->toBe('جيد جدا')
        ->and((int) H::row('nuke_downloads_downloads', 'lid', $lid)->totalcomments)->toBe(1);
});

it('refuses a member rating their own submission, and a second rating by the same member', function () {
    $own = H::material($this->cid, ['submitter' => 'صاحبها']);
    $other = H::material($this->cid);
    $this->actingAs(H::member('صاحبها'), 'vbulletin');

    $this->post(route('mobile.rate', $own), ['rating' => 10])->assertSessionHas('error', 'لا يمكنك تقييم مادة قمت بإرسالها');
    $this->post(route('mobile.rate', $other), ['rating' => 10])->assertSessionHas('success');
    $this->post(route('mobile.rate', $other), ['rating' => 1])->assertSessionHas('error', 'لقد قمت بتقييم هذه المادة من قبل');

    expect(DB::connection('main')->table('nuke_downloads_votedata')->count())->toBe(1);
});

it('allows an anonymous visitor one rating per material per day per IP', function () {
    $lid = H::material($this->cid);
    H::vote($lid, ['ratinghostname' => '127.0.0.1', 'ratingtimestamp' => date('Y-m-d H:i:s', strtotime('-2 days'))]);   // older vote: allowed again

    $this->post(route('mobile.rate', $lid), ['rating' => 6])->assertSessionHas('success');
    $this->post(route('mobile.rate', $lid), ['rating' => 6])->assertSessionHas('error', 'يمكن للزائر تقييم المادة مرة واحدة في اليوم');
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])->post(route('mobile.rate', $lid), ['rating' => 6])->assertSessionHas('success');

    expect(DB::connection('main')->table('nuke_downloads_votedata')->count())->toBe(3);
});

it('validates the rating 1–10 ("--" refused)', function ($rating) {
    $lid = H::material($this->cid);

    $this->post(route('mobile.rate', $lid), ['rating' => $rating])->assertSessionHasErrors('rating');

    expect(DB::connection('main')->table('nuke_downloads_votedata')->count())->toBe(0);
})->with(['--', 0, 11, '']);

it('replaces stale historical counters on the first new vote — OLD recomputes from vote rows', function () {
    $lid = H::material($this->cid, ['downloadratingsummary' => 9, 'totalvotes' => 37, 'totalcomments' => 3]);

    $this->post(route('mobile.rate', $lid), ['rating' => 4]);

    $m = H::row('nuke_downloads_downloads', 'lid', $lid);
    expect([(int) $m->downloadratingsummary, (int) $m->totalvotes, (int) $m->totalcomments])->toBe([4, 1, 0]);
});

it('computes the OLD weighted summary, truncated to a whole number', function () {
    $v = fn ($user, $rating, $comment = '') => (object) ['ratinguser' => $user, 'rating' => $rating, 'ratingcomments' => $comment];
    $anon = H::ANONYMOUS;

    expect(MobileRating::summarize([], $anon))->toBe(['downloadratingsummary' => 0, 'totalvotes' => 0, 'totalcomments' => 0])
        ->and(MobileRating::summarize([$v('a', 7), $v('b', 8, 'x')], $anon))->toBe(['downloadratingsummary' => 7, 'totalvotes' => 2, 'totalcomments' => 1])
        ->and(MobileRating::summarize([$v($anon, 3), $v($anon, 4)], $anon)['downloadratingsummary'])->toBe(3)
        // registered 10 (weight 1) + anonymous 1 (weight 1/10): (10*1 + 1*0.1) / 1.1 = 9.18 → 9
        ->and(MobileRating::summarize([$v('a', 10), $v($anon, 1)], $anon)['downloadratingsummary'])->toBe(9);
});

it('does not change votes or counters of other materials', function () {
    $lid = H::material($this->cid);
    $other = H::material($this->cid, ['downloadratingsummary' => 5, 'totalvotes' => 9]);
    $before = H::row('nuke_downloads_downloads', 'lid', $other);

    $this->post(route('mobile.rate', $lid), ['rating' => 8]);

    expect(H::row('nuke_downloads_downloads', 'lid', $other))->toEqual($before);
});

it('never writes the admin audit log for visitor actions (public activity is not admin audit)', function () {
    $lid = H::material($this->cid);
    $this->actingAs(H::member(), 'vbulletin');

    $this->post(route('mobile.rate', $lid), ['rating' => 8]);
    $this->post(route('mobile.broken', $lid));
    $this->post(route('mobile.submit'), mobileFields($this->cid));

    expect(H::auditRows())->toBe([]);
});

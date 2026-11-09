<?php

/*
 * Contextual surface C4 — polls (P1 edit per poll on the list, P2 add / P3
 * edit on the results page), CDs (W1 edit, W2 delete on the CD page) and
 * Mobile (M1 edit beside each listed material, on every listing page OLD
 * showed it on, and on the comments page). The CD create control is
 * intentionally absent (owner, 2026-10-15: no public group page).
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $this->poll = H::insert('nuke_poll_desc', ['pollTitle' => 'ما رأيك؟', 'timeStamp' => 1700000000, 'voters' => 0, 'planguage' => 'arabic', 'artid' => 0, 'comments' => 0], 'pollID');
    DB::connection('main')->table('nuke_poll_data')->insert([
        ['pollID' => $this->poll, 'optionText' => 'نعم', 'optionCount' => 3, 'voteID' => 1],
        ['pollID' => $this->poll, 'optionText' => 'لا', 'optionCount' => 1, 'voteID' => 2],
    ]);
    $this->cd = H::insert('nuke_w2acd_w2acd', ['group_id' => 0, 'title' => 'إسطوانة']);
    $this->cid = H::insert('nuke_downloads_categories', ['title' => 'القرآن', 'cdescription' => '', 'parentid' => 0], 'cid');
    $this->lid = H::insert('nuke_downloads_downloads', [
        'cid' => $this->cid, 'sid' => 0, 'title' => 'برنامج القرآن', 'url' => 'https://archive.org/download/x/a.zip', 'description' => 'وصف',
        'date' => '2010-12-15 06:40:47', 'name' => '', 'email' => '', 'hits' => 9, 'submitter' => '', 'downloadratingsummary' => 8,
        'totalvotes' => 5, 'totalcomments' => 1, 'filesize' => 0, 'version' => '', 'homepage' => '',
    ], 'lid');
    H::insert('nuke_downloads_votedata', ['ratinglid' => $this->lid, 'ratinguser' => 'عضو', 'rating' => 8, 'ratinghostname' => '10.0.0.1',
        'ratingcomments' => 'جميل', 'ratingtimestamp' => '2010-12-16 10:00:00'], 'ratingdbid');
});

function contextualPage($test, string $url, $admin = null): string
{
    if ($admin === null) {
        H::visitor();
    }

    return ($admin ? $test->withSession(H::session($admin)) : $test)->get($url)->assertOk()->getContent();
}

// ---- Polls ----

dataset('poll lists', ['/surveys.htm', '/polls']);
dataset('poll results', [fn () => "/survey-results-{$this->poll}.htm", fn () => "/polls/{$this->poll}/results"]);

it('poll list: visitor and admins without polls.managepolls see nothing; the route refuses them', function (string $url) {
    expect(contextualPage($this, $url))->not->toContain('data-contextual-admin')
        ->and(contextualPage($this, $url, H::admin(['w2acd.managecds'])))->not->toContain('data-contextual-admin');
    $this->get(route('admin.polls.edit', $this->poll))->assertForbidden();
})->with('poll lists');

it('poll list: «Edit» beside each poll for a poll admin', function (string $url) {
    $html = contextualPage($this, $url, H::admin(['polls.managepolls']));

    expect($html)->toContain('href="'.route('admin.polls.edit', $this->poll).'?return_to='.urlencode($url).'"');
})->with('poll lists');

it('poll results: «Add | Edit» for a poll admin and a super-admin, nothing for others', function (string $url) {
    foreach ([H::admin(['polls.managepolls']), H::superAdmin()] as $admin) {
        $html = contextualPage($this, $url, $admin);
        expect($html)->toContain('href="'.route('admin.polls.create').'?return_to='.urlencode($url).'"')
            ->toContain('href="'.route('admin.polls.edit', $this->poll).'?return_to='.urlencode($url).'"');
        H::visitor();
    }

    expect(contextualPage($this, $url))->not->toContain('data-contextual-admin')
        ->and(contextualPage($this, $url, H::admin(['mobile.managedownloads'], 'ctx-other')))->not->toContain('data-contextual-admin');
})->with('poll results');

it('poll edit entered from the results page returns there after saving', function () {
    $url = "/survey-results-{$this->poll}.htm";
    $this->withSession(H::session(H::admin(['polls.managepolls'])));

    expect($this->get(route('admin.polls.edit', $this->poll).'?return_to='.urlencode($url))->assertOk()->getContent())
        ->toContain('<input type="hidden" name="return_to" value="'.$url.'">');

    $this->put(route('admin.polls.update', $this->poll), ['pollTitle' => 'عنوان جديد', 'optionText' => [1 => 'نعم', 2 => 'لا'], 'return_to' => $url])
        ->assertRedirect($url);
    expect(DB::connection('main')->table('nuke_poll_desc')->where('pollID', $this->poll)->value('pollTitle'))->toBe('عنوان جديد');
});

// ---- CDs ----

it('CD page: visitor and admins without w2acd.managecds see nothing; the routes refuse them', function () {
    $url = "/cds-item-{$this->cd}.htm";

    expect(contextualPage($this, $url))->not->toContain('data-contextual-admin')
        ->and(contextualPage($this, $url, H::admin(['polls.managepolls'])))->not->toContain('data-contextual-admin');
    $this->get(route('admin.cds.edit', $this->cd))->assertForbidden();
    $this->delete(route('admin.cds.destroy', $this->cd))->assertForbidden();
});

it('CD page: edit, and delete as a confirmed DELETE form returning to the CD listing', function () {
    $url = "/cds-item-{$this->cd}.htm";

    foreach ([H::admin(['w2acd.managecds']), H::superAdmin()] as $admin) {
        $html = contextualPage($this, $url, $admin);
        expect($html)->toContain('href="'.route('admin.cds.edit', $this->cd).'?return_to='.urlencode($url).'"')
            ->toContain('action="'.route('admin.cds.destroy', $this->cd).'"')
            ->toContain('name="_method" value="DELETE"')
            ->toContain('name="return_to" value="/cds-main.htm"')
            ->toContain('return confirm(')
            ->not->toContain(route('admin.cds.create'));
        H::visitor();
    }

    expect(contextualPage($this, '/cds-main.htm', H::superAdmin('ctx-boss2')))->not->toContain(route('admin.cds.create'));
});

it('CD delete from the CD page lands on the CD listing', function () {
    $this->withSession(H::session(H::admin(['w2acd.managecds'])))
        ->delete(route('admin.cds.destroy', $this->cd), ['return_to' => '/cds-main.htm'])
        ->assertRedirect('/cds-main.htm');

    expect(DB::connection('main')->table('nuke_w2acd_w2acd')->where('id', $this->cd)->exists())->toBeFalse();
});

// ---- Mobile ----

dataset('mobile pages', [
    'category' => fn () => "/mobiles-cat{$this->cid}.htm",
    'by date' => fn () => '/mobile-seldate-'.strtotime('2010-12-15 00:00:00').'.htm',
    'most popular' => '/mobiles-mostpopular.htm',
    'top rated' => '/mobiles-toprated.htm',
    'search' => '/mobile-search-'.rawurlencode('القرآن').'.htm',
    'comments' => fn () => "/mobileview-comments-{$this->lid}.htm",
]);

it('Mobile: visitor and admins without mobile.managedownloads see nothing', function (string $url) {
    expect(contextualPage($this, $url))->toContain('برنامج القرآن')->not->toContain('data-contextual-admin')
        ->and(contextualPage($this, $url, H::admin(['w2acd.managecds'])))->not->toContain('data-contextual-admin');
})->with('mobile pages');

it('Mobile: «edit material» for a Mobile admin on every page OLD showed it on', function (string $url) {
    $html = contextualPage($this, $url, H::admin(['mobile.managedownloads']));

    expect($html)->toContain('href="'.route('admin.mobile.materials.edit', $this->lid).'?return_to='.urlencode($url).'"');
})->with('mobile pages');

it('Mobile: the edit route refuses an admin without the permission, and returns a Mobile admin to the listing after saving', function () {
    $this->withSession(H::session(H::admin(['polls.managepolls'], 'ctx-nope')))->get(route('admin.mobile.materials.edit', $this->lid))->assertForbidden();
    H::visitor();

    $url = "/mobiles-cat{$this->cid}.htm";
    $this->withSession(H::session(H::admin(['mobile.managedownloads'])))
        ->put(route('admin.mobile.materials.update', $this->lid), [
            'cid' => $this->cid, 'title' => 'برنامج القرآن ٢', 'url' => 'https://archive.org/download/x/a.zip', 'description' => 'وصف',
            'name' => '', 'email' => '', 'filesize' => 0, 'version' => '', 'homepage' => '', 'hits' => 9, 'return_to' => $url,
        ])->assertRedirect($url);

    expect(DB::connection('main')->table('nuke_downloads_downloads')->where('lid', $this->lid)->value('title'))->toBe('برنامج القرآن ٢');
});

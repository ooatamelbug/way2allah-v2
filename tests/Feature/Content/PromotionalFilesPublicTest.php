<?php

/*
 * Final public features — public promotional files (OLD modules/advfiles),
 * owner decision 2026-10-15: the item and full file pages, the library and
 * its author / group / series pages, the item page's «الملف الدعائي» button,
 * and the contextual admin controls OLD showed there (Phase 5 routes).
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'محمد', 'prename' => 'الشيخ', 'advfile' => 3, 'hidden' => 0]);
    H::insert('nuke_islamic_authors', ['id' => 8, 'name' => 'زيدون التجريبي', 'prename' => '', 'advfile' => 0, 'hidden' => 0]);
    H::insert('nuke_islamic_authors', ['id' => 9, 'name' => 'مخفي التجريبي', 'prename' => '', 'advfile' => 2, 'hidden' => 1]);
    $this->group = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'المجموعة', 'advfile' => 2, 'hidden' => 0]);
    H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'فارغة', 'advfile' => 0, 'hidden' => 0]);
    $this->series = H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $this->group, 'title' => 'السلسلة', 'advfile' => 1, 'count' => 4, 'hidden' => 0]);
    H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $this->group, 'title' => 'بلا مواد', 'advfile' => 1, 'count' => 0, 'hidden' => 0]);
    $base = ['author' => 7, 'vedio' => 1, 'hidden' => 0];
    $this->loose = H::insert('nuke_islamic_khotab', $base + ['title' => 'مادة مفردة', 'ser_id' => 0, 'group_id' => 0, 'advfile' => 1, 'weight' => 1]);
    $this->heavy = H::insert('nuke_islamic_khotab', $base + ['title' => 'مادة ثقيلة', 'ser_id' => 0, 'group_id' => 0, 'advfile' => 1, 'weight' => 9]);
    $this->inGroup = H::insert('nuke_islamic_khotab', $base + ['title' => 'مادة في المجموعة', 'ser_id' => 0, 'group_id' => $this->group, 'advfile' => 1]);
    $this->inSeries = H::insert('nuke_islamic_khotab', $base + ['title' => 'مادة في السلسلة', 'ser_id' => $this->series, 'group_id' => $this->group, 'advfile' => 1]);
    $this->pending = H::insert('nuke_islamic_khotab', $base + ['title' => 'طلب معلق', 'ser_id' => 0, 'group_id' => 0, 'advfile' => -1]);
    $this->hiddenItem = H::insert('nuke_islamic_khotab', ['title' => 'مادة مخفية', 'ser_id' => 0, 'group_id' => 0, 'advfile' => 1, 'hidden' => 1] + $base);
    $this->code = '<p align="center"><a href="http://www.way2allah.com/x"><img src="http://www.way2allah.com/b.gif"></a></p><script>alert(1)</script><img src="x.gif" onerror="alert(2)">';
    foreach ([$this->loose, $this->heavy, $this->inGroup, $this->inSeries] as $id) {
        DB::connection('main')->table('nuke_islamic_advfile')->insert(['id' => $id, 'code' => $this->code, 'credit' => 22, 'audio_url' => 'https://archive.org/download/x/a.mp3']);
    }
});

it('serves the item file page (popup) and the full file page at their OLD URLs, rendered safely', function () {
    $fragment = $this->get("/khotab-advfile-{$this->loose}.htm")->assertOk()->getContent();
    $full = $this->get("/khotab-advfilefull-{$this->loose}.htm")->assertOk()->getContent();

    foreach ([$fragment, $full] as $html) {
        expect($html)->toContain('<img src="http://www.way2allah.com/b.gif"')
            ->toContain('<audio controls preload="none" src="https://archive.org/download/x/a.mp3"')
            ->not->toContain('<script>alert')->not->toContain('onerror')->not->toContain('data-contextual-admin');
    }

    expect($fragment)->not->toContain('class="main"')                    // bare page, for the popup
        ->and($full)->toContain('id="main-content"')->toContain('/khotab-item-'.$this->loose.'.htm'); // inside the site layout
});

it('never mutates the stored markup to render it', function () {
    $this->get("/khotab-advfile-{$this->loose}.htm")->assertOk();
    $this->get("/khotab-advfilefull-{$this->loose}.htm")->assertOk();

    expect(DB::connection('main')->table('nuke_islamic_advfile')->where('id', $this->loose)->value('code'))->toBe($this->code);
});

it('404s for an id with no stored file', function () {
    $this->get("/khotab-advfile-{$this->pending}.htm")->assertNotFound();
    $this->get('/khotab-advfilefull-999999.htm')->assertNotFound();
    $this->get('/khotab-advfile-abc.htm')->assertNotFound();
});

it('library: preachers with promotional files, by name, with their counts', function () {
    $html = $this->get('/khotab-advfiles.htm')->assertOk()->getContent();

    expect($html)->toContain('<a href="/khotab-advfiles-author-7.htm"><b>محمد</b></a>')->toContain('1 طلب')
        ->not->toContain('/khotab-advfiles-author-8.htm')->not->toContain('/khotab-advfiles-author-9.htm')->not->toContain('زيدون التجريبي')->not->toContain('مخفي التجريبي');
});

it('author page: groups, loose series and loose items (weight DESC), linking to the normal item pages', function () {
    $html = $this->get('/khotab-advfiles-author-7.htm')->assertOk()->getContent();

    expect($html)->toContain('/khotab-advfiles-group-'.$this->group.'.htm')->not->toContain('فارغة')
        ->toContain('<a href="/khotab-item-'.$this->heavy.'.htm"><b>مادة ثقيلة</b></a>')
        ->toContain('/khotab-item-'.$this->loose.'.htm')
        ->not->toContain('/khotab-item-'.$this->inGroup.'.htm')->not->toContain('/khotab-item-'.$this->pending.'.htm')->not->toContain('/khotab-item-'.$this->hiddenItem.'.htm')
        ->and(strpos($html, 'مادة ثقيلة'))->toBeLessThan(strpos($html, 'مادة مفردة'));
});

it('group and series pages list their own series and items', function () {
    $group = $this->get('/khotab-advfiles-group-'.$this->group.'.htm')->assertOk()->getContent();
    expect($group)->toContain('/khotab-advfiles-series-'.$this->series.'.htm')->not->toContain('بلا مواد')
        ->toContain('/khotab-item-'.$this->inGroup.'.htm')->not->toContain('/khotab-item-'.$this->inSeries.'.htm');

    $series = $this->get('/khotab-advfiles-series-'.$this->series.'.htm')->assertOk()->getContent();
    expect($series)->toContain('/khotab-item-'.$this->inSeries.'.htm')->not->toContain('/khotab-item-'.$this->inGroup.'.htm')
        ->toContain('/khotab-advfiles-author-7.htm');

    $this->get('/khotab-advfiles-author-9.htm')->assertNotFound();
});

it('shows the «الملف الدعائي» popup button on the item page only when a file is published', function () {
    $published = $this->get("/khotab-item-{$this->loose}.htm")->assertOk()->getContent();
    expect($published)->toContain('href="/khotab-advfile-'.$this->loose.'.htm"')->toContain('data-fancybox-type="iframe"')->toContain("$('.various').fancybox");

    expect($this->get("/khotab-item-{$this->pending}.htm")->assertOk()->getContent())->not->toContain('/khotab-advfile-');
});

it('gives a promotional-file admin OLD\'s credit, edit and delete on the file pages, and nobody else', function () {
    $url = "/khotab-advfilefull-{$this->loose}.htm";

    expect($this->withSession(H::session(H::admin(['khotab.addmirror'])))->get($url)->getContent())->not->toContain('data-contextual-admin');

    $html = $this->withSession(H::session(H::admin(['khotab.addadv'], 'ctx-adv')))->get($url)->getContent();
    expect($html)->toContain('مرات العرض المتبقية لهذا الملف = 22')
        ->toContain('href="'.route('admin.advfiles.edit', $this->loose).'?return_to='.urlencode($url).'"')
        ->toContain('action="'.route('admin.advfiles.unpublish', $this->loose).'"')
        ->toContain('name="return_to" value="/khotab-item-'.$this->loose.'.htm"');

    $frame = $this->withSession(H::session(H::superAdmin()))->get("/khotab-advfile-{$this->loose}.htm")->getContent();
    expect($frame)->toContain('target="_top"')->toContain(route('admin.advfiles.unpublish', $this->loose));
});

it('puts edit / delete / view beside each listed item for a promotional-file admin', function () {
    $html = $this->withSession(H::session(H::admin(['khotab.addadv'])))->get('/khotab-advfiles-author-7.htm')->getContent();

    expect($html)->toContain('href="'.route('admin.advfiles.edit', $this->heavy).'?return_to='.urlencode('/khotab-advfiles-author-7.htm').'"')
        ->toContain('action="'.route('admin.advfiles.unpublish', $this->loose).'"')
        ->toContain('href="/khotab-advfile-'.$this->heavy.'.htm" class="various"');

    expect($this->get('/khotab-advfiles-author-7.htm')->getContent())->toContain('data-contextual-admin'); // still the same admin session
    H::visitor();
    expect($this->get('/khotab-advfiles-author-7.htm')->getContent())->not->toContain('data-contextual-admin')->not->toContain('/khotab-advfile-'.$this->heavy);
});

it('unpublishing from the file page runs Phase 5 and lands on the item page; the route still refuses others', function () {
    $this->withSession(H::session(H::admin(['khotab.addmirror'], 'ctx-no')))
        ->delete(route('admin.advfiles.unpublish', $this->loose), ['return_to' => "/khotab-item-{$this->loose}.htm"])->assertForbidden();

    $this->withSession(H::session(H::admin(['khotab.addadv'])))
        ->delete(route('admin.advfiles.unpublish', $this->loose), ['return_to' => "/khotab-item-{$this->loose}.htm"])
        ->assertRedirect("/khotab-item-{$this->loose}.htm");

    expect((int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->loose)->value('advfile'))->toBe(0)
        ->and(DB::connection('main')->table('nuke_islamic_advfile')->where('id', $this->loose)->exists())->toBeFalse();
    $this->get("/khotab-advfile-{$this->loose}.htm")->assertNotFound();
});

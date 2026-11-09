<?php

/*
 * Contextual surface C5 — the Telawah item page's admin-only link status
 * (OLD `AdvancedStats()`), now a read-only Laravel page that offers the
 * EXISTING Telawah recheck / fix-size routes. No Telawah CRUD, no mirrors.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $this->item = H::insert('nuke_telawah_telawah', ['title' => 'سورة الكهف', 'link' => 'https://x/kahf.mp3', 'linksize' => 1000, 'online' => 900, 'percent' => 90, 'down' => 1, 'trial' => 0, 'checktime' => 1700000000, 'group_id' => 0]);
    $this->url = "/recite-item-{$this->item}.htm";
});

it('shows a visitor nothing, and an admin without telawah.repair nothing', function () {
    expect($this->get($this->url)->assertOk()->getContent())->not->toContain('data-contextual-admin')
        ->and($this->withSession(H::session(H::admin(['khotab.repair'])))->get($this->url)->assertOk()->getContent())->not->toContain('data-contextual-admin');
});

it('links the link-health page for a Telawah repair admin and a super-admin, without return_to', function () {
    foreach ([H::admin(['telawah.repair']), H::superAdmin()] as $admin) {
        $html = $this->withSession(H::session($admin))->get($this->url)->assertOk()->getContent();
        expect($html)->toContain('href="'.route('admin.link-quality.telawah.item', $this->item).'"')->not->toContain('return_to');
    }
});

it('refuses the link-health page to an admin without telawah.repair, and to a visitor', function () {
    $this->get(route('admin.link-quality.telawah.item', $this->item))->assertStatus(401);
    $this->withSession(H::session(H::admin(['khotab.repair'])))->get(route('admin.link-quality.telawah.item', $this->item))->assertForbidden();
});

it('shows the stored link values and offers only the existing recheck / fix-size routes, writing nothing', function () {
    Queue::fake();
    $before = DB::connection('main')->table('nuke_telawah_telawah')->where('id', $this->item)->first();

    $html = $this->withSession(H::session(H::admin(['telawah.repair'])))->get(route('admin.link-quality.telawah.item', $this->item))->assertOk()->getContent();

    expect($html)->toContain('https://x/kahf.mp3')->toContain('<td>900</td>')->toContain('<td>1000</td>')
        ->toContain('action="'.route('admin.link-quality.telawah.recheck', $this->item).'"')
        ->toContain('action="'.route('admin.link-quality.telawah.fix-size', $this->item).'"')
        ->toContain('href="/recite-item-'.$this->item.'.htm"')
        ->and(DB::connection('main')->table('nuke_telawah_telawah')->where('id', $this->item)->first())->toEqual($before);
    Queue::assertNothingPushed();
});

it('does not offer fix-size for a never-measured link', function () {
    DB::connection('main')->table('nuke_telawah_telawah')->where('id', $this->item)->update(['online' => 0]);

    $html = $this->withSession(H::session(H::superAdmin()))->get(route('admin.link-quality.telawah.item', $this->item))->assertOk()->getContent();

    expect($html)->toContain(route('admin.link-quality.telawah.recheck', $this->item))->not->toContain(route('admin.link-quality.telawah.fix-size', $this->item));
});

it('the existing fix-size route still behaves as before, returning to the page it came from', function () {
    $page = route('admin.link-quality.telawah.item', $this->item);

    $this->withSession(H::session(H::admin(['telawah.repair'])))->from($page)
        ->post(route('admin.link-quality.telawah.fix-size', $this->item))->assertRedirect($page);

    expect((int) DB::connection('main')->table('nuke_telawah_telawah')->where('id', $this->item)->value('linksize'))->toBe(900);
});

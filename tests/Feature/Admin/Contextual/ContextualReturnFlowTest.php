<?php

/*
 * OLD Admin Migration, contextual surface — the approved same-site return_to
 * (owner decision 3, 2026-10-15), exercised against the REAL Phase 1/4/5
 * routes: success returns to the public page; anything else keeps the
 * operation's own AdminCP redirect; authorization and the operation itself
 * are untouched.
 */

use App\Domain\Admin\Contextual\ContextualReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => '']);
    $this->item = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'درس', 'vedio' => 1, 'hidden' => 0, 'mirror' => 0, 'advfile' => 0]);
    $this->editor = H::admin(['khotab.addmirror', 'khotab.editmirror', 'khotab.delmirror']);
});

function storeMirror($test, array $extra = [])
{
    return $test->withSession(H::session($test->editor))
        ->post(route('admin.mirrors.khotab.store', $test->item), array_merge(['link' => 'https://archive.org/download/x/a.mp4', 'vedio' => 1], $extra));
}

it('returns to the originating public page after a successful operation, and shows the message there', function () {
    $response = storeMirror($this, [ContextualReturn::PARAM => "/khotab-item-{$this->item}.htm"]);

    $response->assertRedirect("/khotab-item-{$this->item}.htm");
    expect(DB::connection('main')->table('nuke_islamic_mirror')->where('khid', $this->item)->count())->toBe(1)
        ->and(session(ContextualReturn::FLASH))->toStartWith('تمت إضافة الوصلة البديلة')
        ->and(session('success'))->toBeNull();

    $this->get("/khotab-item-{$this->item}.htm")->assertOk()->assertSee('data-contextual-admin-flash', false)->assertSee('تمت إضافة الوصلة البديلة');
});

it('keeps the AdminCP redirect when return_to is absent', function () {
    storeMirror($this)->assertRedirect(route('admin.mirrors.khotab.show', $this->item));
});

it('never redirects off-site or into the AdminCP, whatever return_to says', function (string $returnTo) {
    storeMirror($this, [ContextualReturn::PARAM => $returnTo])->assertRedirect(route('admin.mirrors.khotab.show', $this->item));
})->with([
    'external' => 'https://evil.example/khotab-item-1.htm',
    'protocol-relative' => '//evil.example/x',
    'backslash' => '/\\evil.example',
    'javascript' => 'javascript:alert(1)',
    'admin path' => '/admincp/permissions',
    'header injection' => "/x\r\nLocation: https://evil.example",
]);

it('keeps the operation\'s own redirect on a validation failure', function () {
    $from = route('admin.mirrors.khotab.show', $this->item).'?return_to=%2Fkhotab-item-'.$this->item.'.htm';

    $this->withSession(H::session($this->editor))->from($from)
        ->post(route('admin.mirrors.khotab.store', $this->item), ['vedio' => 1, ContextualReturn::PARAM => "/khotab-item-{$this->item}.htm"])
        ->assertRedirect($from)->assertSessionHasErrors('link');

    expect(DB::connection('main')->table('nuke_islamic_mirror')->count())->toBe(0);
});

it('keeps the operation\'s own redirect when the operation refuses (error flash)', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->update(['advfile' => 1]);

    $this->withSession(H::session($this->editor))
        ->post(route('admin.advfiles.request', $this->item), [ContextualReturn::PARAM => "/khotab-item-{$this->item}.htm"])
        ->assertRedirect(route('admin.advfiles.item', $this->item))->assertSessionHas('error');
});

it('ignores return_to on routes outside the contextual set', function () {
    $boss = H::superAdmin();
    $poll = H::insert('nuke_poll_desc', ['pollTitle' => 'س', 'timeStamp' => 1, 'voters' => 0, 'planguage' => 'arabic', 'artid' => 0, 'comments' => 0], 'pollID');

    $this->withSession(H::session($boss))
        ->delete(route('admin.polls.destroy', $poll), [ContextualReturn::PARAM => '/surveys.htm'])
        ->assertRedirect(route('admin.polls.index'));
});

it('does not change authorization: a denied admin is still refused', function () {
    $this->withSession(H::session(H::admin([], 'ctx-nobody')))
        ->post(route('admin.mirrors.khotab.store', $this->item), ['link' => 'https://x/a.mp4', 'vedio' => 1, ContextualReturn::PARAM => "/khotab-item-{$this->item}.htm"])
        ->assertForbidden();

    expect(DB::connection('main')->table('nuke_islamic_mirror')->count())->toBe(0);
});

it('carries a valid return_to into the AdminCP form, and drops an invalid one', function () {
    $page = $this->withSession(H::session($this->editor))
        ->get(route('admin.mirrors.khotab.show', $this->item).'?return_to=%2Fkhotab-item-'.$this->item.'.htm')
        ->assertOk()->getContent();

    expect($page)->toContain('<input type="hidden" name="return_to" value="/khotab-item-'.$this->item.'.htm">')
        ->toContain('data-contextual-admin-back');

    $bad = $this->withSession(H::session($this->editor))
        ->get(route('admin.mirrors.khotab.show', $this->item).'?return_to='.urlencode('https://evil.example/'))
        ->assertOk()->getContent();

    expect($bad)->not->toContain('name="return_to"')->not->toContain('data-contextual-admin-back');
});

// ─── the return_to contract, as read through input() (Anasheed T1 fix, 2026-10-23) ───

it('honours a query-only return_to on a mutation', function () {
    $this->withSession(H::session($this->editor))
        ->post(route('admin.mirrors.khotab.store', $this->item).'?return_to='.urlencode("/khotab-item-{$this->item}.htm"), ['link' => 'https://x/a.mp4', 'vedio' => 1])
        ->assertRedirect("/khotab-item-{$this->item}.htm");
});

it('lets the form body win over the query when both carry return_to', function () {
    $this->withSession(H::session($this->editor))
        ->post(route('admin.mirrors.khotab.store', $this->item).'?return_to='.urlencode('/surveys.htm'), ['link' => 'https://x/a.mp4', 'vedio' => 1, ContextualReturn::PARAM => "/khotab-item-{$this->item}.htm"])
        ->assertRedirect("/khotab-item-{$this->item}.htm");
});

it('still honours a JSON-body return_to', function () {
    $this->withSession(H::session($this->editor))
        ->postJson(route('admin.mirrors.khotab.store', $this->item), ['link' => 'https://x/a.mp4', 'vedio' => 1, ContextualReturn::PARAM => "/khotab-item-{$this->item}.htm"])
        ->assertRedirect("/khotab-item-{$this->item}.htm");
});

it('never takes an uploaded file named return_to as the target', function () {
    $this->withSession(H::session($this->editor))
        ->post(route('admin.mirrors.khotab.store', $this->item), ['link' => 'https://x/a.mp4', 'vedio' => 1, ContextualReturn::PARAM => UploadedFile::fake()->create('target.txt', 1)])
        ->assertRedirect(route('admin.mirrors.khotab.show', $this->item));

    expect(DB::connection('main')->table('nuke_islamic_mirror')->where('khid', $this->item)->count())->toBe(1);
});

it('returns a GET-then-submit edit flow to the public page', function () {
    $mirror = H::insert('nuke_islamic_mirror', ['khid' => $this->item, 'link' => 'https://x/a.mp4', 'comment' => 'قديم', 'vedio' => 1, 'backupme' => 0]);

    $this->withSession(H::session($this->editor))
        ->put(route('admin.mirrors.khotab.update', $mirror), ['link' => 'https://x/b.mp4', 'comment' => 'جديد', 'vedio' => 1, ContextualReturn::PARAM => "/khotab-item-{$this->item}.htm"])
        ->assertRedirect("/khotab-item-{$this->item}.htm");

    expect(DB::connection('main')->table('nuke_islamic_mirror')->where('id', $mirror)->value('comment'))->toBe('جديد');
});

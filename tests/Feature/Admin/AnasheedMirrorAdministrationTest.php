<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\MirrorAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 1 — Anasheed mirror insert / edit / delete,
 * through the real routes, against the 3E.1 data contract and the edit
 * semantics OLD actually executed.
 */
beforeEach(function () {
    H::setUp();
    $this->actingAs(H::superAdmin(), 'admin');
});

function anasheedMirrorRow(int $id): ?object
{
    return DB::connection('main')->table('nuke_anasheed_mirror')->where('id', $id)->first();
}

it('inserts with title as the label, converting megabytes, without touching quality columns', function () {
    $item = H::anasheedItem();

    $statements = [];
    DB::connection('main')->listen(function ($q) use (&$statements) {
        if (str_starts_with(strtolower($q->sql), 'insert into "nuke_anasheed_mirror"')) {
            $statements[] = $q->sql;
        }
    });

    $this->post(route('admin.mirrors.anasheed.store', $item), [
        'link' => 'https://cdn.example/n.mp3', 'title' => 'جودة عالية', 'linksize_mb' => '2', 'vedio' => '0',
    ])->assertRedirect(route('admin.mirrors.anasheed.show', $item));

    $mirror = DB::connection('main')->table('nuke_anasheed_mirror')->where('khid', $item)->first();
    expect($mirror->title)->toBe('جودة عالية')
        ->and((int) $mirror->linksize)->toBe(2097152)
        ->and((int) $mirror->time)->toBeGreaterThan(1700000000);

    expect($statements)->toHaveCount(1);
    foreach (['down', 'trial', 'booking', 'online', 'checktime', 'downloader', 'hits'] as $column) {
        expect($statements[0])->not->toContain("\"{$column}\"");
    }
});

it('increments the counter and sets lastmirror on insert', function () {
    $item = H::anasheedItem(['mirror' => 3, 'lastmirror' => 0]);

    $before = time();
    $this->post(route('admin.mirrors.anasheed.store', $item), ['link' => 'https://cdn.example/x.mp3', 'vedio' => '0']);

    $parent = DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $item)->first();
    expect((int) $parent->mirror)->toBe(4)
        ->and((int) $parent->lastmirror)->toBeGreaterThanOrEqual($before);
});

it('allows duplicate links on the same item — OLD Anasheed never had a guard', function () {
    $item = H::anasheedItem();

    foreach ([1, 2] as $_) {
        $this->post(route('admin.mirrors.anasheed.store', $item), ['link' => 'https://cdn.example/same.mp3', 'vedio' => '0'])
            ->assertSessionHasNoErrors();
    }

    expect(DB::connection('main')->table('nuke_anasheed_mirror')->where('khid', $item)->count())->toBe(2)
        ->and(H::anasheedCounter($item))->toBe(2);
});

it('refuses a size that would overflow the signed INT linksize column', function () {
    $item = H::anasheedItem();

    $this->post(route('admin.mirrors.anasheed.store', $item), ['link' => 'https://cdn.example/big.mp3', 'linksize_mb' => '2048', 'vedio' => '0'])
        ->assertSessionHasErrors('linksize_mb');

    $this->post(route('admin.mirrors.anasheed.store', $item), ['link' => 'https://cdn.example/big.mp3', 'linksize_mb' => '2047', 'vedio' => '0'])
        ->assertSessionHasNoErrors();
});

it('edits title, link, vedio, backupme and resets trial', function () {
    $item = H::anasheedItem(['mirror' => 1]);
    $mirror = H::anasheedMirror($item, ['trial' => 4, 'backupme' => 0, 'down' => 2]);

    $this->put(route('admin.mirrors.anasheed.update', $mirror), [
        'title' => 'معدل', 'link' => 'https://cdn.example/fixed.mp3', 'vedio' => '1', 'backupme' => '1',
    ])->assertRedirect(route('admin.mirrors.anasheed.show', $item));

    $row = anasheedMirrorRow($mirror);
    expect($row->title)->toBe('معدل')
        ->and($row->link)->toBe('https://cdn.example/fixed.mp3')
        ->and((int) $row->vedio)->toBe(1)
        ->and((int) $row->backupme)->toBe(1)
        ->and((int) $row->trial)->toBe(0)
        ->and(H::anasheedCounter($item))->toBe(1);
});

it('applies an edited size in BYTES, unconverted, when down is 1 — as OLD executed', function () {
    $item = H::anasheedItem();
    $mirror = H::anasheedMirror($item, ['linksize' => 500, 'down' => 1]);

    $this->put(route('admin.mirrors.anasheed.update', $mirror), ['linksize' => '123456', 'vedio' => '0'])
        ->assertSessionHasNoErrors();

    expect((int) anasheedMirrorRow($mirror)->linksize)->toBe(123456);
});

it('ignores a submitted size when down is not 1 — the code-faithful gate', function (int $down) {
    $item = H::anasheedItem();
    $mirror = H::anasheedMirror($item, ['linksize' => 500, 'down' => $down]);

    $this->put(route('admin.mirrors.anasheed.update', $mirror), ['linksize' => '123456', 'vedio' => '0'])
        ->assertSessionHasNoErrors();

    expect((int) anasheedMirrorRow($mirror)->linksize)->toBe(500);
})->with([0, 2]);

it('renders the size field only for mirrors whose size OLD would accept, in bytes', function () {
    $item = H::anasheedItem();
    $editable = H::anasheedMirror($item, ['down' => 1, 'linksize' => 777]);
    $locked = H::anasheedMirror($item, ['down' => 2, 'linksize' => 888]);

    $this->get(route('admin.mirrors.anasheed.edit', $editable))->assertOk()->assertSee('الحجم (بايت)')->assertSee('name="linksize"', false);
    $this->get(route('admin.mirrors.anasheed.edit', $locked))->assertOk()->assertSee('لا يمكن تعديل الحجم')->assertDontSee('name="linksize"', false);
});

it('deletes physically and decrements, leaving lastmirror alone', function () {
    $item = H::anasheedItem(['mirror' => 2, 'lastmirror' => 1600000000]);
    $mirror = H::anasheedMirror($item);

    $this->delete(route('admin.mirrors.anasheed.destroy', $mirror))->assertRedirect(route('admin.mirrors.anasheed.show', $item));

    $parent = DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $item)->first();
    expect(anasheedMirrorRow($mirror))->toBeNull()
        ->and((int) $parent->mirror)->toBe(1)
        ->and((int) $parent->lastmirror)->toBe(1600000000);
});

it('deletes an orphan safely and floors a zero counter', function () {
    $orphan = H::anasheedMirror(888888);
    $this->delete(route('admin.mirrors.anasheed.destroy', $orphan))->assertRedirect(route('admin.mirrors.anasheed.index'));
    expect(anasheedMirrorRow($orphan))->toBeNull();

    $item = H::anasheedItem(['mirror' => 0]);
    $mirror = H::anasheedMirror($item);
    $this->delete(route('admin.mirrors.anasheed.destroy', $mirror))->assertRedirect();
    expect(H::anasheedCounter($item))->toBe(0);
});

it('edits an orphan Anasheed mirror', function () {
    $orphan = H::anasheedMirror(919191);

    $this->put(route('admin.mirrors.anasheed.update', $orphan), ['title' => 'rescued', 'vedio' => '0'])
        ->assertRedirect(route('admin.mirrors.anasheed.edit', $orphan));

    expect(anasheedMirrorRow($orphan)->title)->toBe('rescued');
});

it('has no re-parent operation — Anasheed never had one', function () {
    expect(Route::has('admin.mirrors.anasheed.move'))->toBeFalse()
        ->and(Route::has('admin.mirrors.anasheed.pick'))->toBeFalse();
});

it('renders the item page with mirrors and the read-only legacy counter', function () {
    $item = H::anasheedItem(['title' => 'نشيد الصباح', 'mirror' => 6]);
    H::anasheedMirror($item, ['title' => 'جودة منخفضة']);

    $this->get(route('admin.mirrors.anasheed.show', $item))
        ->assertOk()->assertSee('نشيد الصباح')->assertSee('جودة منخفضة')->assertSee('العداد القديم: 6');

    expect(H::anasheedCounter($item))->toBe(6);
});

<?php

use App\Domain\Admin\Http\Controllers\KhotabMirrorController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MirrorAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 1 — Khotab mirror insert / edit / delete /
 * re-parent, exercised through the real routes, against the 3E.1 data
 * contract.
 */
beforeEach(function () {
    H::setUp();
    $this->actingAs(H::superAdmin(), 'admin');
});

function khotabMirrorRow(int $id): ?object
{
    return DB::connection('main')->table('nuke_islamic_mirror')->where('id', $id)->first();
}

// ---------------------------------------------------------------- insert

it('inserts a mirror with exactly the columns OLD wrote, converting megabytes to bytes', function () {
    $item = H::khotabItem(['mirror' => 0]);

    $statements = [];
    DB::connection('main')->listen(function ($q) use (&$statements) {
        if (str_starts_with(strtolower($q->sql), 'insert into "nuke_islamic_mirror"')) {
            $statements[] = $q->sql;
        }
    });

    $this->post(route('admin.mirrors.khotab.store', $item), [
        'link' => 'https://cdn.example/a.mp3',
        'comment' => 'جودة متوسطة',
        'linksize_mb' => '1.5',
        'vedio' => '0',
        'backupme' => '1',
    ])->assertRedirect(route('admin.mirrors.khotab.show', $item));

    $mirror = DB::connection('main')->table('nuke_islamic_mirror')->where('khid', $item)->first();

    expect($mirror->link)->toBe('https://cdn.example/a.mp3')
        ->and($mirror->comment)->toBe('جودة متوسطة')
        ->and((int) $mirror->linksize)->toBe(1572864)   // (int) (1024*1024*1.5)
        ->and((int) $mirror->vedio)->toBe(0)
        ->and((int) $mirror->backupme)->toBe(1)
        ->and((int) $mirror->time)->toBeGreaterThan(1700000000);

    // I-6/I-7: no quality or backup column appears in the INSERT at all —
    // they are left to the table default, as OLD left them.
    expect($statements)->toHaveCount(1);
    foreach (['down', 'trial', 'booking', 'online', 'percent', 'checktime', 'downloader', 'hits'] as $column) {
        expect($statements[0])->not->toContain("\"{$column}\"");
    }
});

it('truncates the megabyte conversion rather than rounding, as OLD did', function () {
    $item = H::khotabItem();

    $this->post(route('admin.mirrors.khotab.store', $item), [
        'link' => 'https://cdn.example/b.mp3', 'linksize_mb' => '0.0000004', 'vedio' => '1',
    ])->assertRedirect();

    expect((int) DB::connection('main')->table('nuke_islamic_mirror')->where('khid', $item)->value('linksize'))->toBe(0);
});

it('increments the legacy counter and sets lastmirror on insert', function () {
    $item = H::khotabItem(['mirror' => 0, 'lastmirror' => null]);

    $before = time();
    $this->post(route('admin.mirrors.khotab.store', $item), ['link' => 'https://cdn.example/c.mp3', 'vedio' => '1']);

    $parent = DB::connection('main')->table('nuke_islamic_khotab')->where('id', $item)->first();
    expect((int) $parent->mirror)->toBe(1)
        ->and((int) $parent->lastmirror)->toBeGreaterThanOrEqual($before);
});

it('keeps several mirrors per item', function () {
    $item = H::khotabItem();

    foreach (['a', 'b', 'c'] as $suffix) {
        $this->post(route('admin.mirrors.khotab.store', $item), ['link' => "https://cdn.example/{$suffix}.mp3", 'vedio' => '1']);
    }

    expect(DB::connection('main')->table('nuke_islamic_mirror')->where('khid', $item)->count())->toBe(3)
        ->and(H::khotabCounter($item))->toBe(3);
});

it('moves an already-drifted counter by one rather than repairing it', function () {
    // Production drift is real (3.92%). Laravel must not recompute it.
    $item = H::khotabItem(['mirror' => 7]);
    H::khotabMirror($item);
    H::khotabMirror($item);

    $this->post(route('admin.mirrors.khotab.store', $item), ['link' => 'https://cdn.example/d.mp3', 'vedio' => '1']);

    expect(H::khotabCounter($item))->toBe(8);   // 7 + 1, not the true 3
});

it('requires a link on insert', function () {
    $item = H::khotabItem();

    $this->post(route('admin.mirrors.khotab.store', $item), ['vedio' => '1'])->assertSessionHasErrors('link');

    expect(DB::connection('main')->table('nuke_islamic_mirror')->count())->toBe(0);
});

it('404s an insert against an item that does not exist', function () {
    $this->post(route('admin.mirrors.khotab.store', 999999), ['link' => 'https://x.example/a.mp3', 'vedio' => '1'])
        ->assertNotFound();
});

// ---------------------------------------------- author-scoped duplicate guard

it('rejects a link already used by any mirror of the same author, on insert', function () {
    $first = H::khotabItem(['author' => 42]);
    $second = H::khotabItem(['author' => 42]);
    H::khotabMirror($first, ['link' => 'https://cdn.example/shared.mp3']);

    $this->post(route('admin.mirrors.khotab.store', $second), ['link' => 'https://cdn.example/shared.mp3', 'vedio' => '1'])
        ->assertSessionHasErrors('link');

    expect(DB::connection('main')->table('nuke_islamic_mirror')->where('khid', $second)->count())->toBe(0)
        ->and(H::khotabCounter($second))->toBe(0)
        ->and(H::auditRows())->toBe([]);
});

it('allows the same link under a different author', function () {
    $mine = H::khotabItem(['author' => 1]);
    $theirs = H::khotabItem(['author' => 2]);
    H::khotabMirror($theirs, ['link' => 'https://cdn.example/shared.mp3']);

    $this->post(route('admin.mirrors.khotab.store', $mine), ['link' => 'https://cdn.example/shared.mp3', 'vedio' => '1'])
        ->assertSessionHasNoErrors();

    expect(DB::connection('main')->table('nuke_islamic_mirror')->where('khid', $mine)->count())->toBe(1);
});

it('reproduces OLD\'s NULL-author coercion exactly: NULL becomes 0 before comparing', function () {
    $nullAuthorA = H::khotabItem(['author' => null]);
    $nullAuthorB = H::khotabItem(['author' => null]);
    H::khotabMirror($nullAuthorA, ['link' => 'https://cdn.example/n.mp3']);

    // Two NULL-author items never match each other (author = 0 is not NULL)…
    $this->post(route('admin.mirrors.khotab.store', $nullAuthorB), ['link' => 'https://cdn.example/n.mp3', 'vedio' => '1'])
        ->assertSessionHasNoErrors();

    // …but a NULL-author item IS compared against literal-0 authors.
    $zeroAuthor = H::khotabItem(['author' => 0]);
    H::khotabMirror($zeroAuthor, ['link' => 'https://cdn.example/z.mp3']);

    $this->post(route('admin.mirrors.khotab.store', $nullAuthorA), ['link' => 'https://cdn.example/z.mp3', 'vedio' => '1'])
        ->assertSessionHasErrors('link');
});

it('does not apply the duplicate guard on edit, matching OLD', function () {
    $item = H::khotabItem(['author' => 9]);
    H::khotabMirror($item, ['link' => 'https://cdn.example/taken.mp3']);
    $other = H::khotabMirror($item, ['link' => 'https://cdn.example/other.mp3']);

    $this->put(route('admin.mirrors.khotab.update', $other), ['link' => 'https://cdn.example/taken.mp3', 'vedio' => '0'])
        ->assertSessionHasNoErrors();

    expect(khotabMirrorRow($other)->link)->toBe('https://cdn.example/taken.mp3');
});

// ---------------------------------------------------------------- edit

it('edits comment, link, vedio and backupme, resets trial, and never writes linksize', function () {
    $item = H::khotabItem(['mirror' => 1]);
    $mirror = H::khotabMirror($item, ['linksize' => 999, 'trial' => 7, 'backupme' => 1, 'vedio' => 0]);

    $this->put(route('admin.mirrors.khotab.update', $mirror), [
        'comment' => 'معدل', 'link' => 'https://cdn.example/fixed.mp3', 'vedio' => '1',
        // Even if someone posts a size, Khotab's edit has no size field.
        'linksize' => '123456',
    ])->assertRedirect(route('admin.mirrors.khotab.show', $item));

    $row = khotabMirrorRow($mirror);
    expect($row->comment)->toBe('معدل')
        ->and($row->link)->toBe('https://cdn.example/fixed.mp3')
        ->and((int) $row->vedio)->toBe(1)
        ->and((int) $row->backupme)->toBe(0)
        ->and((int) $row->trial)->toBe(0)
        ->and((int) $row->linksize)->toBe(999)
        ->and(H::khotabCounter($item))->toBe(1);
});

it('lets an empty existing link stay editable', function () {
    $item = H::khotabItem();
    $mirror = H::khotabMirror($item, ['link' => '']);

    $this->put(route('admin.mirrors.khotab.update', $mirror), ['comment' => 'x', 'vedio' => '0'])
        ->assertSessionHasNoErrors();
});

it('edits an orphan mirror, which OLD refused to', function () {
    $orphan = H::khotabMirror(424242);

    $this->put(route('admin.mirrors.khotab.update', $orphan), ['comment' => 'rescued', 'link' => 'https://x.example/o.mp3', 'vedio' => '0'])
        ->assertRedirect(route('admin.mirrors.khotab.edit', $orphan));

    expect(khotabMirrorRow($orphan)->comment)->toBe('rescued');
});

// ---------------------------------------------------------------- delete

it('deletes physically, decrements the counter and leaves lastmirror alone', function () {
    $item = H::khotabItem(['mirror' => 2, 'lastmirror' => 1600000000]);
    $mirror = H::khotabMirror($item);
    H::khotabMirror($item);

    $this->delete(route('admin.mirrors.khotab.destroy', $mirror))->assertRedirect(route('admin.mirrors.khotab.show', $item));

    $parent = DB::connection('main')->table('nuke_islamic_khotab')->where('id', $item)->first();
    expect(khotabMirrorRow($mirror))->toBeNull()
        ->and((int) $parent->mirror)->toBe(1)
        ->and((int) $parent->lastmirror)->toBe(1600000000);
});

it('deletes an orphan mirror safely — no parent update, no error', function () {
    $bystander = H::khotabItem(['mirror' => 5]);
    $orphan = H::khotabMirror(777777);

    $this->delete(route('admin.mirrors.khotab.destroy', $orphan))->assertRedirect(route('admin.mirrors.khotab.index'));

    expect(khotabMirrorRow($orphan))->toBeNull()
        ->and(H::khotabCounter($bystander))->toBe(5);
});

it('floors the counter at zero instead of failing when it is already zero', function () {
    // Production has exactly this state (Check D1: counter_zero_but_has_mirrors = 2).
    $item = H::khotabItem(['mirror' => 0]);
    $mirror = H::khotabMirror($item);

    $this->delete(route('admin.mirrors.khotab.destroy', $mirror))->assertRedirect();

    expect(khotabMirrorRow($mirror))->toBeNull()
        ->and(H::khotabCounter($item))->toBe(0);
});

// ---------------------------------------------------------------- re-parent

it('re-parents a mirror, moving both counters and leaving both lastmirrors alone', function () {
    $from = H::khotabItem(['mirror' => 2, 'lastmirror' => 1500000000]);
    $to = H::khotabItem(['mirror' => 4, 'lastmirror' => 1600000000]);
    $mirror = H::khotabMirror($from);

    $this->post(route('admin.mirrors.khotab.move', $mirror), ['target_item_id' => $to])
        ->assertRedirect(route('admin.mirrors.khotab.show', $to));

    $fromRow = DB::connection('main')->table('nuke_islamic_khotab')->where('id', $from)->first();
    $toRow = DB::connection('main')->table('nuke_islamic_khotab')->where('id', $to)->first();

    expect((int) khotabMirrorRow($mirror)->khid)->toBe($to)
        ->and((int) $fromRow->mirror)->toBe(1)
        ->and((int) $toRow->mirror)->toBe(5)
        ->and((int) $fromRow->lastmirror)->toBe(1500000000)
        ->and((int) $toRow->lastmirror)->toBe(1600000000);
});

it('re-parents an orphan onto a real item — the only way OLD could rescue one', function () {
    $to = H::khotabItem(['mirror' => 0]);
    $orphan = H::khotabMirror(555555);

    $this->post(route('admin.mirrors.khotab.move', $orphan), ['target_item_id' => $to])->assertRedirect();

    expect((int) khotabMirrorRow($orphan)->khid)->toBe($to)
        ->and(H::khotabCounter($to))->toBe(1);
});

it('refuses to re-parent onto the current parent or onto a missing item', function () {
    $item = H::khotabItem(['mirror' => 1]);
    $mirror = H::khotabMirror($item);

    $this->post(route('admin.mirrors.khotab.move', $mirror), ['target_item_id' => $item])->assertSessionHasErrors('target_item_id');
    $this->post(route('admin.mirrors.khotab.move', $mirror), ['target_item_id' => 9999999])->assertSessionHasErrors('target_item_id');

    expect((int) khotabMirrorRow($mirror)->khid)->toBe($item)
        ->and(H::khotabCounter($item))->toBe(1);
});

it('keeps the two-step pick-then-place interaction in the session, not a cookie', function () {
    $from = H::khotabItem();
    $to = H::khotabItem();
    $mirror = H::khotabMirror($from);

    $this->post(route('admin.mirrors.khotab.pick', $mirror))->assertSessionHas(KhotabMirrorController::CLIPBOARD_KEY, $mirror);

    $this->get(route('admin.mirrors.khotab.show', $to))->assertOk()->assertSee('نقل الوصلة هنا');

    $response = $this->post(route('admin.mirrors.khotab.move', $mirror), ['target_item_id' => $to]);
    $response->assertSessionMissing(KhotabMirrorController::CLIPBOARD_KEY);

    // OLD used a `.way2allah.com` cookie holding a serialized row; no such cookie here.
    expect(collect($response->headers->getCookies())->map->getName()->all())->not->toContain('MirrorMove');
});

// ---------------------------------------------------------------- pages

it('renders the item page with its mirrors and the read-only legacy counter', function () {
    $item = H::khotabItem(['title' => 'خطبة الجمعة', 'mirror' => 9]);
    H::khotabMirror($item, ['comment' => 'جودة منخفضة', 'link' => 'https://cdn.example/low.mp3']);

    $this->get(route('admin.mirrors.khotab.show', $item))
        ->assertOk()
        ->assertSee('خطبة الجمعة')
        ->assertSee('https://cdn.example/low.mp3')
        ->assertSee('العداد القديم: 9');

    expect(H::khotabCounter($item))->toBe(9);   // viewing never repairs
});

it('looks up an item or a mirror by id, including an orphan mirror', function () {
    $item = H::khotabItem();
    $orphan = H::khotabMirror(313131);

    $this->get(route('admin.mirrors.khotab.index', ['item' => $item]))->assertRedirect(route('admin.mirrors.khotab.show', $item));
    $this->get(route('admin.mirrors.khotab.index', ['mirror' => $orphan]))->assertRedirect(route('admin.mirrors.khotab.edit', $orphan));
    $this->get(route('admin.mirrors.khotab.edit', $orphan))->assertOk()->assertSee('غير موجودة');
});

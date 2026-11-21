<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MirrorAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * F09 — restore OLD's post-backup link lock on Khotab mirror edit
 * (OLD `EditMirror`, `modules/Khotab/admin/index.php:578-581`, and
 * `admin_edit_mirror.htm:5`). Once the volunteer backup archived the file
 * (`down = 1`), only a super-admin may change the mirror's link.
 *
 * OLD enforced this with a `disabled` input only, which made the browser
 * omit the field so `ConfirmEditMirror` blanked the link. Laravel enforces
 * the lock server-side and keeps the stored link.
 *
 * Telawah mirrors have no `down` column (OLD's Telawah check never fired) and
 * Anasheed never locked the link, so the lock is Khotab-only.
 */
beforeEach(function () {
    H::setUp();
});

function f09MirrorRow(int $id): object
{
    return DB::connection('main')->table('nuke_islamic_mirror')->where('id', $id)->first();
}

it('keeps the stored link of a backed-up mirror when an ordinary editor submits a different one, and still saves the other fields', function () {
    $this->actingAs(H::admin(['khotab.editmirror']), 'admin');
    $item = H::khotabItem(['mirror' => 1]);
    $mirror = H::khotabMirror($item, ['link' => 'https://archive.org/download/a/kept.mp3', 'down' => 1, 'trial' => 4, 'backupme' => 1, 'vedio' => 0]);

    $this->put(route('admin.mirrors.khotab.update', $mirror), [
        'comment' => 'تعليق جديد', 'link' => 'https://evil.example/replaced.mp3', 'vedio' => '1',
    ])->assertRedirect(route('admin.mirrors.khotab.show', $item));

    $row = f09MirrorRow($mirror);
    expect($row->link)->toBe('https://archive.org/download/a/kept.mp3')
        ->and($row->comment)->toBe('تعليق جديد')
        ->and((int) $row->vedio)->toBe(1)
        ->and((int) $row->backupme)->toBe(0)
        ->and((int) $row->trial)->toBe(0)
        ->and((int) $row->down)->toBe(1);

    $audit = collect(H::auditRows())->last();
    $changes = json_decode((string) $audit->metadata, true)['changes'] ?? [];
    expect($changes)->not->toHaveKey('link');
});

it('never blanks the link of a backed-up mirror when the field is omitted (OLD\'s disabled-input bug)', function () {
    $this->actingAs(H::admin(['khotab.editmirror']), 'admin');
    $item = H::khotabItem(['mirror' => 1]);
    $mirror = H::khotabMirror($item, ['link' => 'https://archive.org/download/a/kept.mp3', 'down' => 1]);

    $this->put(route('admin.mirrors.khotab.update', $mirror), ['comment' => 'x', 'vedio' => '0'])->assertRedirect();

    expect(f09MirrorRow($mirror)->link)->toBe('https://archive.org/download/a/kept.mp3');
});

it('lets a super-admin change the link of a backed-up mirror', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $item = H::khotabItem(['mirror' => 1]);
    $mirror = H::khotabMirror($item, ['link' => 'https://archive.org/download/a/old.mp3', 'down' => 1]);

    $this->put(route('admin.mirrors.khotab.update', $mirror), ['link' => 'https://archive.org/download/a/new.mp3', 'vedio' => '0'])->assertRedirect();

    expect(f09MirrorRow($mirror)->link)->toBe('https://archive.org/download/a/new.mp3');
});

it('does not lock a mirror that has not been backed up (down = 2 or 0)', function (int $down) {
    $this->actingAs(H::admin(['khotab.editmirror']), 'admin');
    $item = H::khotabItem(['mirror' => 1]);
    $mirror = H::khotabMirror($item, ['link' => 'https://cdn.example/old.mp3', 'down' => $down]);

    $this->put(route('admin.mirrors.khotab.update', $mirror), ['link' => 'https://cdn.example/new.mp3', 'vedio' => '0'])->assertRedirect();

    expect(f09MirrorRow($mirror)->link)->toBe('https://cdn.example/new.mp3');
})->with([2, 0]);

it('decides the lock on the current row, not on the state the form was opened with', function () {
    $this->actingAs(H::admin(['khotab.editmirror']), 'admin');
    $item = H::khotabItem(['mirror' => 1]);
    $mirror = H::khotabMirror($item, ['link' => 'https://cdn.example/a.mp3', 'down' => 2]);

    $this->get(route('admin.mirrors.khotab.edit', $mirror))->assertOk()->assertDontSee('readonly', false);

    // The volunteer backup completes while the form is open.
    DB::connection('main')->table('nuke_islamic_mirror')->where('id', $mirror)->update(['down' => 1]);

    $this->put(route('admin.mirrors.khotab.update', $mirror), ['link' => 'https://cdn.example/b.mp3', 'vedio' => '0'])->assertRedirect();

    expect(f09MirrorRow($mirror)->link)->toBe('https://cdn.example/a.mp3');
});

it('renders the link field read-only with an explanation for an ordinary editor, and editable for a super-admin', function () {
    $item = H::khotabItem(['mirror' => 1]);
    $mirror = H::khotabMirror($item, ['link' => 'https://archive.org/download/a/kept.mp3', 'down' => 1]);

    $this->actingAs(H::admin(['khotab.editmirror']), 'admin');
    $html = $this->get(route('admin.mirrors.khotab.edit', $mirror))->assertOk()->getContent();
    expect($html)->toMatch('/name="link"[^>]*readonly/')
        ->toContain('لا يمكن تعديل رابطها إلا بواسطة المشرف العام')
        ->not->toMatch('/name="link"[^>]*disabled/');

    $this->actingAs(H::superAdmin(), 'admin');
    $html = $this->get(route('admin.mirrors.khotab.edit', $mirror))->assertOk()->getContent();
    expect($html)->not->toMatch('/name="link"[^>]*readonly/')
        ->not->toContain('لا يمكن تعديل رابطها إلا بواسطة المشرف العام');
});

it('leaves the move action untouched: a backed-up mirror keeps its link and the counts move by one', function () {
    $this->actingAs(H::admin(['khotab.editmirror']), 'admin');
    $source = H::khotabItem(['mirror' => 1]);
    $target = H::khotabItem(['mirror' => 0]);
    $mirror = H::khotabMirror($source, ['link' => 'https://archive.org/download/a/kept.mp3', 'down' => 1]);

    $this->post(route('admin.mirrors.khotab.move', $mirror), ['target_item_id' => $target])->assertRedirect();

    $row = f09MirrorRow($mirror);
    expect((int) $row->khid)->toBe($target)
        ->and($row->link)->toBe('https://archive.org/download/a/kept.mp3')
        ->and(H::khotabCounter($source))->toBe(0)
        ->and(H::khotabCounter($target))->toBe(1);
});

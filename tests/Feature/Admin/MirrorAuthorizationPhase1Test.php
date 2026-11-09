<?php

use App\Domain\Admin\Actions\Mirrors\DeleteKhotabMirrorAction;
use App\Domain\Admin\Actions\Mirrors\InsertAnasheedMirrorAction;
use App\Domain\Admin\Actions\Mirrors\InsertKhotabMirrorAction;
use App\Domain\Admin\Actions\Mirrors\MoveKhotabMirrorAction;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MirrorAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 1 — the flat-permission authorization model
 * (D1b' closed), enforced at both the route and the action boundary.
 */
beforeEach(function () {
    H::setUp();
});

/**
 * Each of the seven operations as [permission it needs, request it makes].
 *
 * @return array<string, array{0: string, 1: Closure}>
 */
function mirrorOperations(): array
{
    return [
        'khotab insert' => ['khotab.addmirror', function ($t) {
            $item = H::khotabItem();

            return $t->post(route('admin.mirrors.khotab.store', $item), ['link' => 'https://x.example/'.uniqid().'.mp3', 'vedio' => '1']);
        }],
        'khotab edit' => ['khotab.editmirror', function ($t) {
            $mirror = H::khotabMirror(H::khotabItem());

            return $t->put(route('admin.mirrors.khotab.update', $mirror), ['link' => 'https://x.example/e.mp3', 'vedio' => '1']);
        }],
        'khotab delete' => ['khotab.delmirror', function ($t) {
            $mirror = H::khotabMirror(H::khotabItem(['mirror' => 1]));

            return $t->delete(route('admin.mirrors.khotab.destroy', $mirror));
        }],
        'khotab re-parent' => ['khotab.editmirror', function ($t) {
            $mirror = H::khotabMirror(H::khotabItem(['mirror' => 1]));

            return $t->post(route('admin.mirrors.khotab.move', $mirror), ['target_item_id' => H::khotabItem()]);
        }],
        'anasheed insert' => ['anasheed.addmirror', function ($t) {
            return $t->post(route('admin.mirrors.anasheed.store', H::anasheedItem()), ['link' => 'https://x.example/a.mp3', 'vedio' => '0']);
        }],
        'anasheed edit' => ['anasheed.editmirror', function ($t) {
            $mirror = H::anasheedMirror(H::anasheedItem());

            return $t->put(route('admin.mirrors.anasheed.update', $mirror), ['title' => 'x', 'vedio' => '0']);
        }],
        'anasheed delete' => ['anasheed.delmirror', function ($t) {
            $mirror = H::anasheedMirror(H::anasheedItem(['mirror' => 1]));

            return $t->delete(route('admin.mirrors.anasheed.destroy', $mirror));
        }],
    ];
}

it('allows each operation to an admin holding exactly its permission', function (string $permission, Closure $request) {
    $this->actingAs(H::admin([$permission]), 'admin');

    $request($this)->assertRedirect()->assertSessionHasNoErrors();
})->with(mirrorOperations());

it('denies each operation to an admin holding every OTHER mirror permission but not its own', function (string $permission, Closure $request) {
    $others = array_values(array_diff([...H::KHOTAB_PERMISSIONS, ...H::ANASHEED_PERMISSIONS], [$permission]));

    $this->actingAs(H::admin($others), 'admin');

    $request($this)->assertForbidden();
})->with(mirrorOperations());

it('denies every operation to an authenticated admin with no mirror permission', function (string $permission, Closure $request) {
    $this->actingAs(H::admin([]), 'admin');

    $request($this)->assertForbidden();
})->with(mirrorOperations());

it('returns 401 for every operation when nobody is logged in', function (string $permission, Closure $request) {
    $request($this)->assertUnauthorized();
})->with(mirrorOperations());

it('lets a super-admin perform every operation with no explicit grant', function (string $permission, Closure $request) {
    $this->actingAs(H::superAdmin(), 'admin');

    $request($this)->assertRedirect()->assertSessionHasNoErrors();
})->with(mirrorOperations());

it('gates Khotab re-parent with editmirror and nothing else', function () {
    $from = H::khotabItem(['mirror' => 1]);
    $to = H::khotabItem();

    $this->actingAs(H::admin(['khotab.addmirror', 'khotab.delmirror'], 'no-edit'), 'admin');
    $this->post(route('admin.mirrors.khotab.move', H::khotabMirror($from)), ['target_item_id' => $to])->assertForbidden();

    $this->actingAs(H::admin(['khotab.editmirror'], 'edit-only'), 'admin');
    $this->post(route('admin.mirrors.khotab.move', H::khotabMirror($from)), ['target_item_id' => $to])->assertRedirect();

    expect(DB::table('permissions')->where('name', 'like', '%movemirror%')->exists())->toBeFalse();
});

it('applies no audio/vedio constraint: editmirror covers audio and video items alike', function () {
    $this->actingAs(H::admin(['khotab.editmirror']), 'admin');

    $audio = H::khotabMirror(H::khotabItem(['vedio' => 0]));
    $video = H::khotabMirror(H::khotabItem(['vedio' => 1]));

    $this->put(route('admin.mirrors.khotab.update', $audio), ['comment' => 'a', 'vedio' => '0'])->assertRedirect();
    $this->put(route('admin.mirrors.khotab.update', $video), ['comment' => 'v', 'vedio' => '1'])->assertRedirect();

    expect(app(AdminRecordAuthorizer::class)->constraints())->toBe([]);
});

it('keeps Khotab and Anasheed permissions independent', function () {
    $this->actingAs(H::admin(H::KHOTAB_PERMISSIONS), 'admin');

    $this->post(route('admin.mirrors.anasheed.store', H::anasheedItem()), ['link' => 'https://x.example/a.mp3', 'vedio' => '0'])
        ->assertForbidden();
    $this->get(route('admin.mirrors.anasheed.index'))->assertForbidden();
});

it('lets an admin holding only delmirror reach the item page to find something to delete', function () {
    $this->actingAs(H::admin(['khotab.delmirror']), 'admin');
    $item = H::khotabItem();
    H::khotabMirror($item);

    $this->get(route('admin.mirrors.khotab.show', $item))->assertOk()->assertSee('حذف')->assertDontSee('أضف الآن');
});

it('re-checks authorization at the action boundary, not only in route middleware', function () {
    $unprivileged = H::admin([]);
    $item = KhotabItem::query()->find(H::khotabItem());
    $mirror = Mirror::query()->find(H::khotabMirror($item->id));

    expect(fn () => app(InsertKhotabMirrorAction::class)->execute($unprivileged, $item, ['link' => 'https://x.example/a.mp3', 'vedio' => 1]))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(DeleteKhotabMirrorAction::class)->execute($unprivileged, $mirror))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(MoveKhotabMirrorAction::class)->execute($unprivileged, $mirror, KhotabItem::query()->find(H::khotabItem())))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(InsertAnasheedMirrorAction::class)->execute($unprivileged, AnasheedItem::query()->find(H::anasheedItem()), ['link' => 'https://x.example/a.mp3', 'vedio' => 0]))
        ->toThrow(AuthorizationException::class);

    // Nothing was written by any refused call.
    expect(DB::connection('main')->table('nuke_islamic_mirror')->count())->toBe(1)
        ->and(H::auditRows())->toBe([]);
});

it('never touches a legacy moderator grant table at runtime', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $sql = [];
    DB::connection('main')->listen(function ($q) use (&$sql) {
        $sql[] = $q->sql;
    });

    foreach (mirrorOperations() as [$permission, $request]) {
        $request($this);
    }

    $joined = implode(' | ', $sql);
    foreach (['nuke_islamic_mod', 'nuke_anasheed_mod', 'nuke_telawah_mod', 'nuke_w2acd_mod'] as $table) {
        expect($joined)->not->toContain($table);
    }
    // And nothing reads the legacy permissions blob for authorization.
    expect($joined)->not->toContain('"permissions"');
});

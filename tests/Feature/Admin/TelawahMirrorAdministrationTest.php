<?php

/*
 * Closure correction (2026-10-15) — Telawah mirror administration: the
 * historically working half of OLD's Telawah mirror CRUD (edit, delete),
 * with Laravel authorization, audit inside the transaction, AdminCP and
 * contextual reachability, and the validated return flow. OLD's insert was
 * disabled by OLD itself and is not recreated.
 */

use App\Support\Permission\Permission;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $this->item = H::insert('nuke_telawah_telawah', ['title' => 'سورة يس', 'link' => 'https://x/yasin.mp3', 'group_id' => 0]);
    $this->other = H::insert('nuke_telawah_telawah', ['title' => 'سورة الملك', 'link' => 'https://x/mulk.mp3', 'group_id' => 0]);
    $this->mirror = H::insert('nuke_telawah_mirror', ['telawa_id' => $this->item, 'comment' => 'sound cloud', 'link' => 'https://soundcloud.com/a/b',
        'extension' => 'mp3', 'size' => 9437184, 'video' => '0', 'backupme' => '1', 'creation_date' => '2018-12-24 18:48:50']);
    $this->url = "/recite-item-{$this->item}.htm";
});

function telawahMirrorRow(int $id): ?array
{
    $row = DB::connection('main')->table('nuke_telawah_mirror')->where('id', $id)->first();

    return $row === null ? null : (array) $row;
}

function telawahAuditRows(): array
{
    return DB::connection('main')->table('admin_audit_log')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
}

// ---------------------------------------------------------------- definitions

it('defines telawah.editmirror and telawah.delmirror, grants them to nobody, and defines no add', function () {
    expect(Permission::query()->where('guard_name', 'admin')->whereIn('name', ['telawah.editmirror', 'telawah.delmirror'])->count())->toBe(2)
        ->and(Permission::query()->where('name', 'telawah.addmirror')->exists())->toBeFalse()
        ->and(DB::table('model_has_permissions')->count())->toBe(0)
        ->and(Route::has('admin.mirrors.telawah.store'))->toBeFalse()
        ->and(Route::has('admin.mirrors.telawah.create'))->toBeFalse();
});

// ---------------------------------------------------------------- authorization

it('refuses visitors and admins without the Telawah mirror permissions', function () {
    $routes = fn () => [
        ['get', route('admin.mirrors.telawah.index')], ['get', route('admin.mirrors.telawah.show', $this->item)],
        ['get', route('admin.mirrors.telawah.edit', $this->mirror)], ['put', route('admin.mirrors.telawah.update', $this->mirror)],
        ['delete', route('admin.mirrors.telawah.destroy', $this->mirror)],
    ];

    foreach ($routes() as [$method, $url]) {
        H::visitor();
        $this->{$method}($url, ['vedio' => 0])->assertStatus(401);
    }

    $this->withSession(H::session(H::admin(['anasheed.editmirror', 'anasheed.delmirror', 'khotab.editmirror', 'telawah.repair'])));
    foreach ($routes() as [$method, $url]) {
        $this->{$method}($url, ['vedio' => 0])->assertForbidden();
    }

    expect(telawahMirrorRow($this->mirror))->not->toBeNull()->and(telawahAuditRows())->toBe([]);
});

it('separates edit from delete', function () {
    $this->withSession(H::session(H::admin(['telawah.editmirror'], 'ctx-edit')));
    // Update and destroy share one URL (PUT vs DELETE), so check for the delete form itself.
    $this->get(route('admin.mirrors.telawah.edit', $this->mirror))->assertOk()->assertDontSee('value="DELETE"', false)->assertSee('value="PUT"', false);
    $this->delete(route('admin.mirrors.telawah.destroy', $this->mirror))->assertForbidden();

    $this->withSession(H::session(H::admin(['telawah.delmirror'], 'ctx-del')));
    $this->get(route('admin.mirrors.telawah.edit', $this->mirror))->assertForbidden();
    $this->put(route('admin.mirrors.telawah.update', $this->mirror), ['vedio' => 1])->assertForbidden();
    $this->get(route('admin.mirrors.telawah.show', $this->item))->assertOk()
        ->assertSee(route('admin.mirrors.telawah.destroy', $this->mirror))->assertDontSee(route('admin.mirrors.telawah.edit', $this->mirror));
});

// ---------------------------------------------------------------- edit

it('edits exactly OLD\'s columns — comment, link, video, backupme — and nothing else', function () {
    $before = telawahMirrorRow($this->mirror);
    $parent = (array) DB::connection('main')->table('nuke_telawah_telawah')->where('id', $this->item)->first();

    $this->withSession(H::session(H::admin(['telawah.editmirror'])))
        ->put(route('admin.mirrors.telawah.update', $this->mirror), ['comment' => 'يوتيوب', 'link' => 'https://youtu.be/x', 'vedio' => '1'])
        ->assertRedirect(route('admin.mirrors.telawah.show', $this->item))->assertSessionHas('success');

    $after = telawahMirrorRow($this->mirror);
    expect($after)->toBe(array_merge($before, ['comment' => 'يوتيوب', 'link' => 'https://youtu.be/x', 'video' => '1', 'backupme' => '0']))
        ->and((array) DB::connection('main')->table('nuke_telawah_telawah')->where('id', $this->item)->first())->toBe($parent);

    $audit = telawahAuditRows();
    expect($audit)->toHaveCount(1)
        ->and($audit[0])->toMatchArray(['action' => 'edit_mirror', 'module' => 'telawah', 'entity_type' => 'mirror', 'entity_id' => $this->mirror, 'actor_aid' => 'ctx-admin'])
        ->and(json_decode($audit[0]['metadata'], true))->toBe(['item_id' => $this->item, 'changes' => [
            'comment' => ['from' => 'sound cloud', 'to' => 'يوتيوب'],
            'link' => ['from' => 'https://soundcloud.com/a/b', 'to' => 'https://youtu.be/x'],
            'video' => ['from' => '0', 'to' => '1'],
            'backupme' => ['from' => '1', 'to' => '0'],
        ]]);
});

it('validates edit input against the production column sizes', function () {
    $this->withSession(H::session(H::superAdmin()));

    $this->put(route('admin.mirrors.telawah.update', $this->mirror), ['link' => 'https://x/'.str_repeat('a', 260)])->assertSessionHasErrors(['vedio', 'link']);
    expect(telawahMirrorRow($this->mirror)['link'])->toBe('https://soundcloud.com/a/b')->and(telawahAuditRows())->toBe([]);
});

// ---------------------------------------------------------------- delete

it('deletes the mirror only — no parent counter exists to touch — and audits it', function () {
    $parent = (array) DB::connection('main')->table('nuke_telawah_telawah')->where('id', $this->item)->first();

    $this->withSession(H::session(H::admin(['telawah.delmirror'])))
        ->delete(route('admin.mirrors.telawah.destroy', $this->mirror))
        ->assertRedirect(route('admin.mirrors.telawah.show', $this->item))->assertSessionHas('success');

    $audit = telawahAuditRows();
    expect(telawahMirrorRow($this->mirror))->toBeNull()
        ->and((array) DB::connection('main')->table('nuke_telawah_telawah')->where('id', $this->item)->first())->toBe($parent)
        ->and($audit)->toHaveCount(1)
        ->and($audit[0])->toMatchArray(['action' => 'del_mirror', 'module' => 'telawah', 'entity_type' => 'mirror', 'entity_id' => $this->mirror])
        ->and(json_decode($audit[0]['metadata'], true)['deleted'])->toBe(['comment' => 'sound cloud', 'link' => 'https://soundcloud.com/a/b', 'size' => 9437184, 'video' => '0', 'backupme' => '1']);
});

it('rolls the mutation back when the authoritative audit write fails', function (string $method) {
    $this->withSession(H::session(H::superAdmin()));
    $before = telawahMirrorRow($this->mirror);
    Schema::connection('main')->drop('admin_audit_log');
    $this->withoutExceptionHandling();

    expect(fn () => $method === 'put'
        ? $this->put(route('admin.mirrors.telawah.update', $this->mirror), ['comment' => 'x', 'vedio' => 1])
        : $this->delete(route('admin.mirrors.telawah.destroy', $this->mirror)))->toThrow(QueryException::class)
        ->and(telawahMirrorRow($this->mirror))->toBe($before);
})->with(['edit' => 'put', 'delete' => 'delete']);

it('writes nothing to nuke_islamic_logs by default', function () {
    $this->withSession(H::session(H::superAdmin()));
    $this->put(route('admin.mirrors.telawah.update', $this->mirror), ['comment' => 'x', 'vedio' => 0]);
    $this->delete(route('admin.mirrors.telawah.destroy', $this->mirror));

    expect(DB::connection('main')->table('nuke_islamic_logs')->count())->toBe(0);
});

// ---------------------------------------------------------------- AdminCP reachability

it('is reachable from the AdminCP: sidebar entry, lookup by item and by mirror, item list', function () {
    $this->withSession(H::session(H::admin(['telawah.editmirror'])));

    $this->get(route('admin.mirrors.telawah.index'))->assertOk();
    $this->get('/admincp/link-quality/telawah/items/'.$this->item)->assertForbidden(); // a different permission, untouched
    expect($this->get(route('admin.mirrors.telawah.index'))->getContent())->toContain('href="'.route('admin.mirrors.telawah.index').'"');

    $this->get(route('admin.mirrors.telawah.index', ['item' => $this->item]))->assertRedirect(route('admin.mirrors.telawah.show', $this->item));
    $this->get(route('admin.mirrors.telawah.index', ['mirror' => $this->mirror]))->assertRedirect(route('admin.mirrors.telawah.edit', $this->mirror));
    $this->get(route('admin.mirrors.telawah.show', $this->item))->assertOk()->assertSee('sound cloud')->assertSee(route('admin.mirrors.telawah.edit', $this->mirror))
        ->assertDontSee('إضافة وصلة');
});

// ---------------------------------------------------------------- contextual (public Telawah item page)

it('shows visitors OLD\'s public mirror list — description linked, type, size — with no admin controls', function () {
    H::insert('nuke_telawah_mirror', ['telawa_id' => $this->item, 'comment' => 'ملف صوتي', 'link' => 'https://x.example/yasin.mp3', 'size' => 1048576, 'video' => '0', 'backupme' => '0']);
    H::insert('nuke_telawah_mirror', ['telawa_id' => $this->item, 'comment' => 'خبيث', 'link' => 'javascript:alert(1)', 'size' => 0, 'video' => '0', 'backupme' => '0']);

    $html = $this->get($this->url)->assertOk()->getContent();

    expect($html)->toContain('قائمة الروابط البديلة للمادة')
        ->toContain('<a href="https://soundcloud.com/a/b" target="_blank" rel="nofollow noopener noreferrer">sound cloud</a>')
        ->toContain('<audio controls preload="none" src="https://x.example/yasin.mp3"')
        ->toContain('خبيث')->not->toContain('javascript:alert')
        ->not->toContain('data-contextual-admin');

    expect($this->get("/recite-item-{$this->other}.htm")->getContent())->not->toContain('قائمة الروابط البديلة للمادة');
});

it('puts edit and delete beside the mirror for admins who hold them, and nothing for others', function () {
    $other = $this->withSession(H::session(H::admin(['anasheed.editmirror'], 'ctx-other')))->get($this->url)->getContent();
    expect($other)->not->toContain(route('admin.mirrors.telawah.edit', $this->mirror));

    $edit = $this->withSession(H::session(H::admin(['telawah.editmirror'], 'ctx-e')))->get($this->url)->getContent();
    expect($edit)->toContain('href="'.route('admin.mirrors.telawah.edit', $this->mirror).'?return_to='.urlencode($this->url).'"')
        ->not->toContain('action="'.route('admin.mirrors.telawah.destroy', $this->mirror).'"');

    $boss = $this->withSession(H::session(H::superAdmin()))->get($this->url)->getContent();
    $label = strpos($boss, '>sound cloud</a>');
    expect($boss)->toContain('action="'.route('admin.mirrors.telawah.destroy', $this->mirror).'"')->toContain('name="_method" value="DELETE"')
        ->and(strpos($boss, route('admin.mirrors.telawah.edit', $this->mirror)))->toBeGreaterThan($label);
});

it('returns to the public Telawah page after a contextual edit or delete', function () {
    $this->withSession(H::session(H::superAdmin()));

    expect($this->get(route('admin.mirrors.telawah.edit', $this->mirror).'?return_to='.urlencode($this->url))->getContent())
        ->toContain('<input type="hidden" name="return_to" value="'.$this->url.'">');

    $this->put(route('admin.mirrors.telawah.update', $this->mirror), ['comment' => 'معدل', 'vedio' => 0, 'backupme' => 1, 'return_to' => $this->url])->assertRedirect($this->url);
    $this->delete(route('admin.mirrors.telawah.destroy', $this->mirror), ['return_to' => 'https://evil.example/'])->assertRedirect(route('admin.mirrors.telawah.show', $this->item));
});

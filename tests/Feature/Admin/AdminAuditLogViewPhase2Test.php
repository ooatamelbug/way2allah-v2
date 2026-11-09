<?php

use App\Domain\Admin\Support\AdminDashboardModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MirrorAdminHarness as H;
use Tests\Support\ReportingHarness as R;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 2 — the authoritative audit trail view
 * (`admin_audit_log`), super-admin only.
 */
beforeEach(function () {
    R::setUp();
});

// --------------------------------------------------------------- access

it('returns 401 to a guest', function () {
    $this->get(route('admin.audit.index'))->assertUnauthorized();
});

it('refuses a normal admin even with every mirror and repair permission', function () {
    $this->actingAs(H::admin([...H::KHOTAB_PERMISSIONS, ...H::ANASHEED_PERMISSIONS, 'khotab.repair']), 'admin');

    $this->get(route('admin.audit.index'))->assertForbidden();
});

it('shows the trail to a super-admin', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $this->get(route('admin.audit.index'))->assertOk()->assertSee('سجل الإدارة — السجل المعتمد');
});

it('lists the audit entry in the sidebar for super-admins only, with no invented permission', function () {
    $modules = app(AdminDashboardModules::class);
    $labels = fn ($admin) => collect($modules->visibleFor($admin))->pluck('label')->all();

    expect($labels(H::superAdmin()))->toContain('سجل الإدارة')
        ->and($labels(H::admin(['khotab.repair', ...H::KHOTAB_PERMISSIONS], 'plain')))->not->toContain('سجل الإدارة');
});

// ------------------------------------------------------------ rendering

it('renders time, actor, action, module, entity, ip and metadata', function () {
    $boss = H::superAdmin('auditor-boss');
    $this->actingAs($boss, 'admin');
    $item = H::khotabItem();
    $mirror = H::khotabMirror($item);

    R::auditEntry([
        'actor_admin_id' => $boss->id, 'actor_aid' => $boss->aid,
        'action' => 'edit_mirror', 'module' => 'khotab', 'entity_type' => 'mirror', 'entity_id' => $mirror,
        'metadata' => ['item_id' => $item, 'changes' => ['comment' => ['from' => 'قديم', 'to' => 'جديد']]],
        'ip' => '203.0.113.9', 'created_at' => 1760000000,
    ]);

    $this->get(route('admin.audit.index'))->assertOk()
        ->assertSee(date('Y-m-d H:i:s', 1760000000))
        ->assertSee('auditor-boss')
        ->assertSee('edit_mirror')
        ->assertSee('203.0.113.9')
        ->assertSee('قديم')
        ->assertSee('جديد')
        ->assertSee(route('admin.mirrors.khotab.edit', $mirror), false);
});

it('links an item entity to its Phase 1 page and marks a deleted entity without a link', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $item = H::khotabItem();

    R::auditEntry(['entity_type' => 'item', 'entity_id' => $item]);
    R::auditEntry(['entity_type' => 'mirror', 'entity_id' => 987654]);   // never existed / deleted

    $this->get(route('admin.audit.index'))->assertOk()
        ->assertSee(route('admin.mirrors.khotab.show', $item), false)
        ->assertSee('mirror #987654')
        ->assertSee('(محذوف)')
        ->assertDontSee(route('admin.mirrors.khotab.edit', 987654), false);
});

it('renders an unknown module/entity pair as plain text, claiming nothing', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    R::auditEntry(['module' => 'future', 'entity_type' => 'widget', 'entity_id' => 5]);

    $this->get(route('admin.audit.index'))->assertOk()->assertSee('widget #5')->assertDontSee('(محذوف)');
});

it('falls back to the stored aid when the actor account no longer exists', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    R::auditEntry(['actor_admin_id' => 424242, 'actor_aid' => 'departed-admin']);

    $this->get(route('admin.audit.index'))->assertOk()
        ->assertSee('departed-admin')
        ->assertSee('(حساب محذوف)')
        ->assertSee('#424242');
});

it('escapes stored values instead of rendering them as HTML', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    // Written past the recorder, which would refuse it, to prove the view
    // is safe on its own.
    R::auditEntry([
        'actor_aid' => '<img src=x onerror=alert(1)>',
        'actor_admin_id' => 777,
        'metadata' => ['note' => '<script>alert("xss")</script>', '<b>key</b>' => 'v'],
    ]);

    $html = $this->get(route('admin.audit.index'))->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert("xss")</script>')
        ->and($html)->not->toContain('<img src=x onerror=alert(1)>')
        ->and($html)->not->toContain('<b>key</b>')
        ->and($html)->toContain('&lt;script&gt;')
        ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('redacts sensitive keys by name but keeps ordinary values like links', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    R::auditEntry(['metadata' => [
        'password' => 'hunter2', 'TOKEN' => 'abc123', 'nested' => ['api_key' => 'k-999'],
        'link' => 'https://cdn.example/visible.mp3',
    ]]);

    $this->get(route('admin.audit.index'))->assertOk()
        ->assertDontSee('hunter2')->assertDontSee('abc123')->assertDontSee('k-999')
        ->assertSee('[محجوب]')
        ->assertSee('https://cdn.example/visible.mp3');
});

it('shows an empty state', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $this->get(route('admin.audit.index'))->assertOk()->assertSee('لا توجد سجلات مطابقة.');
});

// ------------------------------------------------- filters / pagination

it('filters by module, action, entity type, entity id, actor and date range', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    // Actor ids that match no admin row, so the view shows the STORED aid —
    // otherwise it shows the live actor's aid and these markers never render.
    // Markers are long enough not to occur anywhere else in the page.
    R::auditEntry(['module' => 'khotab', 'action' => 'add_mirror', 'entity_type' => 'mirror', 'entity_id' => 11, 'actor_admin_id' => 501, 'actor_aid' => 'marker-alpha-khotab', 'created_at' => strtotime('2026-01-10 12:00:00')]);
    R::auditEntry(['module' => 'anasheed', 'action' => 'del_mirror', 'entity_type' => 'item', 'entity_id' => 22, 'actor_admin_id' => 502, 'actor_aid' => 'marker-beta-anasheed', 'created_at' => strtotime('2026-03-10 12:00:00')]);

    $a = 'marker-alpha-khotab';
    $b = 'marker-beta-anasheed';
    $only = fn (array $q, string $yes, string $no) => $this->get(route('admin.audit.index', $q))->assertOk()->assertSee($yes)->assertDontSee($no);

    $only(['module' => 'anasheed'], $b, $a);
    $only(['action' => 'add_mirror'], $a, $b);
    $only(['entity_type' => 'item'], $b, $a);
    $only(['entity_id' => 11], $a, $b);
    $only(['actor' => 502], $b, $a);
    $only(['from' => '2026-02-01'], $b, $a);
    $only(['to' => '2026-02-01'], $a, $b);
});

it('validates filters', function (array $query, string $field) {
    $this->actingAs(H::superAdmin(), 'admin');

    $this->get(route('admin.audit.index', $query))->assertSessionHasErrors($field);
})->with([
    [['entity_id' => 'abc'], 'entity_id'],
    [['actor' => '-3'], 'actor'],
    [['from' => '10/01/2026'], 'from'],
    [['from' => '2026-05-01', 'to' => '2026-04-01'], 'to'],
    [['module' => 'kho tab'], 'module'],
]);

it('paginates server-side at 50 with a stable newest-first order, ties broken by id', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    // 60 entries sharing ONE timestamp: only the id tie-break can order them.
    // An unknown module/type pair renders as plain "rowmarker #N", which
    // gives each row a unique, link-free marker to read the order from.
    for ($i = 1; $i <= 60; $i++) {
        R::auditEntry(['module' => 'ordertest', 'entity_type' => 'rowmarker', 'entity_id' => $i, 'created_at' => 1760000000]);
    }

    $page1 = $this->get(route('admin.audit.index'))->assertOk()->getContent();
    $page2 = $this->get(route('admin.audit.index', ['page' => 2]))->assertOk()->getContent();

    preg_match_all('/rowmarker #(\d+)/', $page1, $m1);
    preg_match_all('/rowmarker #(\d+)/', $page2, $m2);
    $ids1 = array_map('intval', array_unique($m1[1]));
    $ids2 = array_map('intval', array_unique($m2[1]));

    expect($ids1)->toHaveCount(50)->and($ids2)->toHaveCount(10)
        ->and(array_values($ids1))->toBe(range(60, 11))
        ->and(array_values($ids2))->toBe(range(10, 1));
});

it('keeps the query count flat as the page fills — no N+1 on actors or entities', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $item = H::khotabItem();

    $count = function () {
        $n = 0;
        // Connection::listen() registers on the shared event dispatcher, so
        // this sees every connection's queries; scoping by name keeps the
        // count to `main`, where all the page's data lives.
        DB::connection('main')->listen(function ($q) use (&$n) {
            if ($q->connectionName === 'main') {
                $n++;
            }
        });
        $this->get(route('admin.audit.index'))->assertOk();

        return $n;
    };

    // Warm-up: the first request also pays one-off costs (e.g. the cached
    // role lookup), which would otherwise distort the comparison.
    R::auditEntry();
    $count();

    // Same mix of entity types at both sizes, so the number of batched
    // entity queries is identical and only per-row queries could differ.
    $add = function (int $pairs, int $actorOffset) use ($item) {
        for ($i = 0; $i < $pairs; $i++) {
            R::auditEntry(['entity_type' => 'mirror', 'entity_id' => H::khotabMirror($item), 'actor_admin_id' => $actorOffset + $i]);
            R::auditEntry(['entity_type' => 'item', 'entity_id' => $item, 'actor_admin_id' => $actorOffset + $i]);
        }
    };

    $add(1, 100);
    $few = $count();

    $add(23, 200);   // page now holds 49 rows, 25 distinct actors
    $many = $count();

    expect($many)->toBe($few);
});

it('is read-only — rendering issues no write on any connection', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    R::auditEntry();

    expect(R::writesDuring(fn () => $this->get(route('admin.audit.index'))->assertOk()))->toBe([]);
});

it('consults no legacy permission store while rendering', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    R::auditEntry();

    $sql = [];
    DB::connection('main')->listen(function ($q) use (&$sql) {
        $sql[] = $q->sql;
    });
    $this->get(route('admin.audit.index'))->assertOk();

    foreach (['nuke_islamic_mod', 'nuke_anasheed_mod', 'nuke_telawah_mod', 'nuke_w2acd_mod', 'nuke_islamic_logs'] as $table) {
        expect(implode(' | ', $sql))->not->toContain($table);
    }
});

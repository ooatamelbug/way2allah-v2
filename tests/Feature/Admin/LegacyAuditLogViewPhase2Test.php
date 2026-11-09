<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MirrorAdminHarness as H;
use Tests\Support\ReportingHarness as R;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 2 — the read-only view of OLD's
 * `nuke_islamic_logs`, reproducing OLD's own `listlogs()`
 * (`includes/logs.php:103`).
 */
beforeEach(function () {
    R::setUp();
});

it('is super-admin only, like OLD listlogs()', function () {
    $this->get(route('admin.audit.legacy'))->assertUnauthorized();

    $this->actingAs(H::admin([...H::KHOTAB_PERMISSIONS, 'khotab.repair']), 'admin');
    $this->get(route('admin.audit.legacy'))->assertForbidden();

    $this->actingAs(H::superAdmin(), 'admin');
    $this->get(route('admin.audit.legacy'))->assertOk();
});

it('labels itself as the legacy, non-authoritative log and links to the authoritative one', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $this->get(route('admin.audit.legacy'))->assertOk()
        ->assertSee('سجل النظام القديم — للاطلاع فقط')
        ->assertSee('وليس السجل المعتمد للإدارة')
        ->assertSee(route('admin.audit.index'), false);
});

it('scopes to one module, defaulting to Khotab', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    R::legacyLog(['module' => 'Khotab', 'extrainfo' => 'legacy-marker-khotab']);
    R::legacyLog(['module' => 'Anasheed', 'extrainfo' => 'legacy-marker-anasheed']);

    $this->get(route('admin.audit.legacy'))->assertOk()
        ->assertSee('legacy-marker-khotab')->assertDontSee('legacy-marker-anasheed');

    $this->get(route('admin.audit.legacy', ['module' => 'Anasheed']))->assertOk()
        ->assertSee('legacy-marker-anasheed')->assertDontSee('legacy-marker-khotab');
});

it('never shows hidden rows — OLD listlogs() always filtered hidden = 0', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    R::legacyLog(['hidden' => '0', 'extrainfo' => 'legacy-visible-row']);
    R::legacyLog(['hidden' => '1', 'extrainfo' => 'legacy-hidden-row']);

    $this->get(route('admin.audit.legacy'))->assertOk()
        ->assertSee('legacy-visible-row')->assertDontSee('legacy-hidden-row');
});

it('filters by moderator, action, target id and type — OLD\'s own filter set', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    R::legacyLog(['aid' => 'mod-one', 'action' => 'add_mirror', 'target_id' => 10, 'type' => 'mirror', 'extrainfo' => 'legacy-row-a']);
    R::legacyLog(['aid' => 'mod-two', 'action' => 'del_mirror', 'target_id' => 20, 'type' => 'item', 'extrainfo' => 'legacy-row-b']);

    $only = fn (array $q, string $yes, string $no) => $this->get(route('admin.audit.legacy', $q))->assertOk()->assertSee($yes)->assertDontSee($no);

    $only(['aid' => 'mod-two'], 'legacy-row-b', 'legacy-row-a');
    $only(['action' => 'add_mirror'], 'legacy-row-a', 'legacy-row-b');
    $only(['target_id' => 20], 'legacy-row-b', 'legacy-row-a');
    $only(['type' => 'mirror'], 'legacy-row-a', 'legacy-row-b');
});

it('rejects a module outside the three OLD log views', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $this->get(route('admin.audit.legacy', ['module' => 'Telawah']))->assertSessionHasErrors('module');
    $this->get(route('admin.audit.legacy', ['target_id' => 'x']))->assertSessionHasErrors('target_id');
});

it('strips and escapes legacy extrainfo markup instead of rendering it', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    R::legacyLog(['extrainfo' => '<a href=khotab-item-5.htm class="legacy-anchor">عنوان المادة</a><script>legacyEvil()</script>']);

    $html = $this->get(route('admin.audit.legacy'))->assertOk()->getContent();

    expect($html)->not->toContain('class="legacy-anchor"')
        ->and($html)->not->toContain('<script>legacyEvil()')
        ->and($html)->toContain('عنوان المادة');
});

it('escapes legacy aid and type values', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    R::legacyLog(['aid' => '<i>bold-aid</i>']);

    $html = $this->get(route('admin.audit.legacy'))->assertOk()->getContent();

    expect($html)->toContain('&lt;i&gt;bold-aid&lt;/i&gt;')->and($html)->not->toContain('<i>bold-aid</i>');
});

it('cursor-paginates at OLD\'s 200 per page, newest first, with no overlap', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    for ($i = 1; $i <= 250; $i++) {
        R::legacyLog(['extrainfo' => sprintf('lrow-%03d', $i)]);
    }

    $page1 = $this->get(route('admin.audit.legacy'));
    $page1->assertOk();
    preg_match_all('/lrow-(\d{3})/', $page1->getContent(), $m1);
    $ids1 = array_map('intval', $m1[1]);

    $next = $page1->viewData('rows')->nextPageUrl();
    expect($next)->not->toBeNull();

    preg_match_all('/lrow-(\d{3})/', $this->get($next)->assertOk()->getContent(), $m2);
    $ids2 = array_map('intval', $m2[1]);

    expect($ids1)->toBe(range(250, 51))
        ->and($ids2)->toBe(range(50, 1))
        ->and(array_intersect($ids1, $ids2))->toBe([]);
});

it('shows an empty state', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $this->get(route('admin.audit.legacy'))->assertOk()->assertSee('لا توجد سجلات مطابقة.');
});

it('is read-only and never touches the authoritative trail or a legacy permission store', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    R::legacyLog();

    $sql = [];
    DB::connection('main')->listen(function ($q) use (&$sql) {
        if ($q->connectionName === 'main') {
            $sql[] = $q->sql;
        }
    });

    expect(R::writesDuring(fn () => $this->get(route('admin.audit.legacy'))->assertOk()))->toBe([]);

    $joined = implode(' | ', $sql);
    foreach (['admin_audit_log', 'nuke_islamic_mod', 'nuke_anasheed_mod', 'nuke_telawah_mod', 'nuke_w2acd_mod'] as $table) {
        expect($joined)->not->toContain($table);
    }
});

<?php

use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 0 — the authoritative Laravel audit trail.
 *
 * Covers the four legacy defects the recorder refuses (3E.2 §8.2) as well
 * as the fields production readers depend on (§8.1).
 */
beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_authors' => MainSchema::nukeAuthors(),
        'admin_audit_log' => MainSchema::adminAuditLog(),
        'nuke_islamic_logs' => MainSchema::nukeIslamicLogs(),
    ]);

    config(['admin_audit.legacy_compatibility_writes' => false]);
});

function auditActor(string $aid = 'auditor'): AdminUser
{
    $admin = new AdminUser;
    $admin->setConnection('main');
    $admin->forceFill(['aid' => $aid, 'password' => 'x'])->save();

    return $admin->fresh();
}

it('records an entry with actor, action, module, entity and timestamp', function () {
    $actor = auditActor('maintainer');

    $entry = app(AdminAuditRecorder::class)->record(
        actor: $actor,
        action: 'edit_mirror',
        module: 'khotab',
        entityType: 'mirror',
        entityId: 4242,
        metadata: ['link' => ['from' => 'https://a.example/x.mp3', 'to' => 'https://b.example/x.mp3']],
        ip: '203.0.113.7',
    );

    $stored = AdminAuditEntry::on('main')->find($entry->id);

    expect($stored->actor_admin_id)->toBe((int) $actor->id);
    expect($stored->actor_aid)->toBe('maintainer');
    expect($stored->action)->toBe('edit_mirror');
    expect($stored->module)->toBe('khotab');
    expect($stored->entity_type)->toBe('mirror');
    expect($stored->entity_id)->toBe(4242);
    expect($stored->ip)->toBe('203.0.113.7');
    expect($stored->created_at)->toBeGreaterThan(0);
});

it('stores structured before/after metadata, not a rendered string', function () {
    $entry = app(AdminAuditRecorder::class)->record(
        actor: auditActor(),
        action: 'edit_mirror',
        module: 'khotab',
        entityType: 'mirror',
        entityId: 7,
        metadata: ['link' => ['from' => 'https://old.example/a.mp3', 'to' => 'https://new.example/a.mp3']],
    );

    $stored = AdminAuditEntry::on('main')->find($entry->id);

    expect($stored->metadata)->toBeArray();
    expect($stored->metadata['link']['from'])->toBe('https://old.example/a.mp3');
    expect($stored->metadata['link']['to'])->toBe('https://new.example/a.mp3');
});

it('has no free-text description column at all, so a display string cannot leak into one', function () {
    $columns = Schema::connection('main')->getColumnListing('admin_audit_log');

    foreach (['extrainfo', 'description', 'message', 'notes'] as $forbidden) {
        expect($columns)->not->toContain($forbidden);
    }
});

it('refuses metadata containing HTML markup — the legacy extrainfo defect', function () {
    app(AdminAuditRecorder::class)->record(
        actor: auditActor(),
        action: 'add_mirror',
        module: 'khotab',
        entityType: 'mirror',
        entityId: 1,
        metadata: ['note' => '<a target=_blank href=https://x.example/a.mp3>https://x.example/a.mp3</a>'],
    );
})->throws(InvalidArgumentException::class, 'contains markup');

it('allows a bare URL in structured metadata, since that is legitimate before/after data', function () {
    $entry = app(AdminAuditRecorder::class)->record(
        actor: auditActor(),
        action: 'add_mirror',
        module: 'khotab',
        entityType: 'mirror',
        entityId: 2,
        metadata: ['link' => 'https://x.example/a.mp3'],
    );

    expect($entry->metadata['link'])->toBe('https://x.example/a.mp3');
});

it('refuses a non-positive entity id rather than recording an unattributable row', function () {
    app(AdminAuditRecorder::class)->record(
        actor: auditActor(),
        action: 'add_mirror',
        module: 'khotab',
        entityType: 'mirror',
        entityId: 0,
    );
})->throws(InvalidArgumentException::class, 'positive integer');

it('refuses an empty action, module or entity type', function (string $field) {
    $args = [
        'actor' => auditActor(),
        'action' => 'add_mirror',
        'module' => 'khotab',
        'entityType' => 'mirror',
        'entityId' => 3,
    ];
    $args[$field] = '   ';

    app(AdminAuditRecorder::class)->record(...$args);
})->with(['action', 'module', 'entityType'])->throws(InvalidArgumentException::class);

it('attributes the mirror entity, not its parent — the legacy mis-attribution defect', function () {
    // Legacy logged type='mirror' with the khotab id because the variable
    // had been reassigned. Here the two ids are explicitly distinct.
    $recorder = app(AdminAuditRecorder::class);
    $actor = auditActor();

    $mirrorEntry = $recorder->record($actor, 'add_mirror', 'khotab', 'mirror', 9001);
    $itemEntry = $recorder->record($actor, 'add_mirror', 'khotab', 'item', 500);

    expect($mirrorEntry->entity_type)->toBe('mirror');
    expect($mirrorEntry->entity_id)->toBe(9001);
    expect($itemEntry->entity_type)->toBe('item');
    expect($itemEntry->entity_id)->toBe(500);
});

it('participates in the caller transaction and rolls back with it', function () {
    $actor = auditActor();

    try {
        DB::connection('main')->transaction(function () use ($actor) {
            app(AdminAuditRecorder::class)->record($actor, 'del_mirror', 'khotab', 'mirror', 55);

            expect(AdminAuditEntry::on('main')->count())->toBe(1);

            throw new RuntimeException('caller failed after the audit write');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(AdminAuditEntry::on('main')->count())->toBe(0);
});

it('fails loudly rather than silently when persistence is impossible', function () {
    Schema::connection('main')->drop('admin_audit_log');

    app(AdminAuditRecorder::class)->record(auditActor(), 'add_mirror', 'khotab', 'mirror', 1);
})->throws(QueryException::class);

it('writes no legacy compatibility row while the flag is off', function () {
    app(AdminAuditRecorder::class)->record(auditActor(), 'add_mirror', 'khotab', 'mirror', 11);

    expect(DB::connection('main')->table('nuke_islamic_logs')->count())->toBe(0);
});

it('writes a markup-free legacy compatibility row when the flag is on', function () {
    config(['admin_audit.legacy_compatibility_writes' => true]);

    app(AdminAuditRecorder::class)->record(
        actor: auditActor('compat'),
        action: 'edit_mirror',
        module: 'khotab',
        entityType: 'mirror',
        entityId: 77,
        metadata: ['link' => ['from' => 'https://a.example/x.mp3', 'to' => 'https://b.example/x.mp3']],
        ip: '198.51.100.9',
    );

    $row = DB::connection('main')->table('nuke_islamic_logs')->first();

    expect($row->aid)->toBe('compat');
    // Phase 1: legacy rows use OLD's capitalised `$module_name` (the module
    // directory name) so OLD's moderator-log views can match them. The
    // authoritative entry keeps the lowercase permission namespace.
    expect($row->module)->toBe('Khotab');
    expect($row->type)->toBe('mirror');
    expect((int) $row->target_id)->toBe(77);
    expect($row->action)->toBe('edit_mirror');
    expect($row->hidden)->toBe('0');

    // The compatibility row must carry neither markup nor any metadata
    // *value* — only its keys — so a URL cannot reach this shared column.
    expect($row->extrainfo)->not->toContain('<');
    expect($row->extrainfo)->not->toContain('http');
    expect($row->extrainfo)->toContain('link');
});

it('keeps the authoritative trail independent of the compatibility flag', function () {
    config(['admin_audit.legacy_compatibility_writes' => true]);

    app(AdminAuditRecorder::class)->record(auditActor(), 'move_mirror', 'khotab', 'mirror', 3);

    expect(AdminAuditEntry::on('main')->count())->toBe(1);
    expect(DB::connection('main')->table('nuke_islamic_logs')->count())->toBe(1);
});

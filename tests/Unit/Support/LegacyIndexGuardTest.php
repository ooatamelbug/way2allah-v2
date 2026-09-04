<?php

use App\Support\Database\LegacyIndexGuard;

/**
 * E-15 — the performance-index migrations used to guard themselves on index
 * *name* alone. Production carried structurally equivalent indexes under
 * different names, so a `migrate` run there would have created four
 * redundant duplicates. `LegacyIndexGuard` replaces that with left-prefix
 * coverage detection.
 *
 * The decision is tested as a pure function against explicit index maps
 * rather than through a live MySQL server, so every branch — including the
 * ones production cannot easily reproduce — is exercised deterministically
 * in CI.
 */

// ---- the four cases the guard must get right ----

it('skips creation when an index of the same name already exists', function () {
    $existing = ['idx_khotab_pdf' => ['pdf']];

    expect(LegacyIndexGuard::isCovered($existing, 'idx_khotab_pdf', ['pdf']))->toBeTrue();
});

it('skips creation when an equivalent index exists under a different name', function () {
    // Production's real shape: our (vedio, time) already exists as idx_vedio_time.
    $existing = ['idx_vedio_time' => ['vedio', 'time']];

    expect(LegacyIndexGuard::isCovered($existing, 'idx_khotab_day_listing', ['vedio', 'time']))->toBeTrue();
});

it('skips creation when the proposed columns are a left-prefix of a longer index', function () {
    // Production: idx_khid (khid, id) covers our (khid);
    // idx_location_author_hidden covers our (location_id).
    expect(LegacyIndexGuard::isCovered(['idx_khid' => ['khid', 'id']], 'idx_mirror_khid', ['khid']))->toBeTrue();

    expect(LegacyIndexGuard::isCovered(
        ['idx_location_author_hidden' => ['location_id', 'author', 'hidden']],
        'idx_khotab_location',
        ['location_id'],
    ))->toBeTrue();
});

it('does NOT treat a different column order as equivalent', function () {
    // (vedio, time) is not covered by (time, vedio) — order is the whole point
    // of a B-tree prefix, and treating these as equal would skip a real index.
    $existing = ['idx_time_vedio' => ['time', 'vedio']];

    expect(LegacyIndexGuard::isCovered($existing, 'idx_khotab_day_listing', ['vedio', 'time']))->toBeFalse();
});

it('still creates the index when nothing covers it', function () {
    // Production's real shape for the news index: weight appears in no index.
    $existing = [
        'PRIMARY' => ['id'],
        'idx_vedio_hits' => ['vedio', 'hits'],
        'idx_vedio_time' => ['vedio', 'time'],
        'idx_home_lastmirror' => ['vedio', 'newslist', 'lastmirror'],
    ];

    expect(LegacyIndexGuard::isCovered($existing, 'idx_khotab_news_listing', ['vedio', 'weight', 'time']))->toBeFalse();
});

// ---- edges that would be silent failures if wrong ----

it('does not treat a shorter existing index as covering a longer proposal', function () {
    // (vedio) does not cover (vedio, weight, time) — the reverse direction
    // of the prefix rule, which must not hold.
    expect(LegacyIndexGuard::isCovered(['idx_vedio' => ['vedio']], 'idx_new', ['vedio', 'weight', 'time']))->toBeFalse();
});

it('compares index and column names case-insensitively', function () {
    expect(LegacyIndexGuard::isCovered(['IDX_VEDIO_TIME' => ['Vedio', 'TIME']], 'idx_khotab_day_listing', ['vedio', 'time']))->toBeTrue();
});

it('never reports an empty column list as covered', function () {
    // An empty list is a left-prefix of everything; treating it as covered
    // would silently skip a real index.
    expect(LegacyIndexGuard::isCovered(['idx_any' => ['a', 'b']], 'idx_empty', []))->toBeFalse();
});

it('treats an unrelated index set as not covering anything', function () {
    $existing = ['idx_pdf_time' => ['pdf', 'pdf_time'], 'idx_group_id' => ['group_id', 'vedio']];

    expect(LegacyIndexGuard::isCovered($existing, 'idx_khotab_author_listing', ['author', 'vedio', 'ser_id', 'group_id']))->toBeFalse();
});

// ---- SHOW INDEX row parsing ----

it('builds ordered column lists from SHOW INDEX rows regardless of row order', function () {
    $rows = [
        (object) ['Key_name' => 'idx_a', 'Seq_in_index' => 2, 'Column_name' => 'weight', 'Sub_part' => null],
        (object) ['Key_name' => 'idx_a', 'Seq_in_index' => 1, 'Column_name' => 'vedio', 'Sub_part' => null],
        (object) ['Key_name' => 'idx_a', 'Seq_in_index' => 3, 'Column_name' => 'time', 'Sub_part' => null],
    ];

    expect(LegacyIndexGuard::mapShowIndexRows($rows))->toBe(['idx_a' => ['vedio', 'weight', 'time']]);
});

it('truncates an index at a partial-prefix column rather than dropping that column', function () {
    // (a, b(10), c) can fully serve (a) but not (a, b). Dropping the partial
    // entry instead would collapse it to [a, c] and falsely report (a, c) as
    // covered — a silent wrong answer.
    $rows = [
        (object) ['Key_name' => 'idx_p', 'Seq_in_index' => 1, 'Column_name' => 'a', 'Sub_part' => null],
        (object) ['Key_name' => 'idx_p', 'Seq_in_index' => 2, 'Column_name' => 'b', 'Sub_part' => 10],
        (object) ['Key_name' => 'idx_p', 'Seq_in_index' => 3, 'Column_name' => 'c', 'Sub_part' => null],
    ];

    $map = LegacyIndexGuard::mapShowIndexRows($rows);

    expect($map)->toBe(['idx_p' => ['a']])
        ->and(LegacyIndexGuard::isCovered($map, 'x', ['a', 'c']))->toBeFalse()
        ->and(LegacyIndexGuard::isCovered($map, 'x', ['a']))->toBeTrue();
});

// ---- the decisive test: the real production inventory ----

it('performs zero duplicate index creations against the current production schema', function () {
    // Exactly the index inventory verified on production `w2acp_laravel`,
    // including the three added by hand during the E-15 deployment.
    $khotab = [
        'PRIMARY' => ['id'],
        'idx_home_lastmirror' => ['vedio', 'newslist', 'lastmirror'],
        'idx_vedio_hits' => ['vedio', 'hits'],
        'idx_vedio_time' => ['vedio', 'time'],
        'idx_author_vedio_hits' => ['author', 'vedio', 'hits'],
        'idx_author_vedio_time' => ['author', 'vedio', 'time'],
        'idx_channel_vedio_hits' => ['channel_id', 'vedio', 'hits'],
        'idx_channel_vedio_time' => ['channel_id', 'vedio', 'time'],
        'idx_ser_id_hidden' => ['ser_id', 'hidden'],
        'idx_pdf_time' => ['pdf', 'pdf_time'],
        'idx_group_id' => ['group_id', 'vedio'],
        'idx_location_author_hidden' => ['location_id', 'author', 'hidden'],
        // deployed by hand, E-15
        'idx_khotab_news_listing' => ['vedio', 'weight', 'time'],
        'idx_khotab_author_listing' => ['author', 'vedio', 'ser_id', 'group_id'],
        'idx_khotab_channel_listing' => ['channel_id', 'vedio', 'ser_id', 'group_id'],
    ];
    $mirror = [
        'PRIMARY' => ['id'],
        'idx_khid' => ['khid', 'id'],
    ];

    $proposals = [
        // [existing set, proposed name, proposed columns, why it must be skipped]
        [$mirror, 'idx_mirror_khid', ['khid'], 'covered by idx_khid (khid, id)'],
        [$khotab, 'idx_khotab_author_listing', ['author', 'vedio', 'ser_id', 'group_id'], 'same name, deployed by hand'],
        [$khotab, 'idx_khotab_channel_listing', ['channel_id', 'vedio', 'ser_id', 'group_id'], 'same name, deployed by hand'],
        [$khotab, 'idx_khotab_pdf', ['pdf'], 'covered by idx_pdf_time (pdf, pdf_time)'],
        [$khotab, 'idx_khotab_news_listing', ['vedio', 'weight', 'time'], 'same name, deployed by hand'],
        [$khotab, 'idx_khotab_day_listing', ['vedio', 'time'], 'covered by idx_vedio_time (vedio, time)'],
        [$khotab, 'idx_khotab_location', ['location_id'], 'covered by idx_location_author_hidden'],
    ];

    foreach ($proposals as [$existing, $name, $columns, $why]) {
        expect(LegacyIndexGuard::isCovered($existing, $name, $columns))
            ->toBeTrue("{$name} should be skipped — {$why}");
    }

    expect($proposals)->toHaveCount(7);
});

it('would still create all seven indexes against a schema that has none of them', function () {
    // The original local baseline: proves the guard has not become a blanket
    // "skip everything", which would be the dangerous failure mode.
    $bare = ['PRIMARY' => ['id'], 'idx_home_lastmirror' => ['vedio', 'newslist', 'lastmirror']];

    foreach ([
        ['idx_khotab_author_listing', ['author', 'vedio', 'ser_id', 'group_id']],
        ['idx_khotab_channel_listing', ['channel_id', 'vedio', 'ser_id', 'group_id']],
        ['idx_khotab_pdf', ['pdf']],
        ['idx_khotab_news_listing', ['vedio', 'weight', 'time']],
        ['idx_khotab_day_listing', ['vedio', 'time']],
        ['idx_khotab_location', ['location_id']],
    ] as [$name, $columns]) {
        expect(LegacyIndexGuard::isCovered($bare, $name, $columns))->toBeFalse("{$name} should still be created");
    }

    expect(LegacyIndexGuard::isCovered(['PRIMARY' => ['id']], 'idx_mirror_khid', ['khid']))->toBeFalse();
});

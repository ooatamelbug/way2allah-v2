<?php

/*
 * Phase 3D origin tracking — `download_rejections.resource_title`: the material's title at refusal time, taken from
 * the record the controller already loaded (primary: the item; mirror: the parent item, via a subselect in the SAME
 * query) — no extra query on the refusal path. A deployment that runs ahead of the additive migration still records
 * the refusal (without the title).
 */

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\DownloadRejectionRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    Schema::connection('main')->create(DownloadRejectionRecorder::TABLE, MainSchema::downloadRejections());
    $this->slotDir = sys_get_temp_dir().'/rot-slots-'.uniqid();
    config(['downloads.guard' => true, 'downloads.max_concurrent' => 1, 'downloads.rejection_log' => true, 'downloads.slot_directory' => $this->slotDir]);
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.']);
    $this->item = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'فضل العلم وأهله', 'vedio' => 1, 'link' => 'https://archive.invalid/a.mp4', 'linksize' => 5, 'downcount' => 0]);
    $this->mirror = H::insert('nuke_islamic_mirror', ['khid' => $this->item, 'link' => 'https://archive.invalid/m.mp4', 'linksize' => 5, 'hits' => 0]);
    $this->held = app(DownloadCapacityGuard::class)->tryAcquire();   // pool full
});

afterEach(function () {
    $this->held?->release();
    array_map('unlink', glob($this->slotDir.'/*') ?: []);
    @rmdir($this->slotDir);
});

function rotQueries(Closure $request): array
{
    $selects = [];
    DB::listen(function ($q) use (&$selects) {
        if (str_starts_with(strtolower(ltrim($q->sql)), 'select') && ! str_contains($q->sql, 'download_rejections')) {
            $selects[] = $q->sql;
        }
    });
    $request();

    return $selects;
}

it('a refused primary download records the item title — with no extra query', function () {
    $selects = rotQueries(fn () => $this->get("/khotab-download-{$this->item}.htm")->assertStatus(503));

    expect(DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->value('resource_title'))->toBe('فضل العلم وأهله')
        ->and(array_filter($selects, fn ($s) => str_contains($s, 'nuke_islamic_khotab')))->toHaveCount(1);   // the item lookup it always did
});

it('a refused mirror download records the PARENT item title from the same single query', function () {
    $selects = rotQueries(fn () => $this->get("/khotab-mirror-{$this->item}-{$this->mirror}.htm")->assertStatus(503));

    $row = DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->first();
    expect($row->resource_title)->toBe('فضل العلم وأهله')->and($row->variant)->toBe('mirror')->and((int) $row->variant_id)->toBe($this->mirror)
        ->and(array_filter($selects, fn ($s) => str_contains($s, 'nuke_islamic_mirror') || str_contains($s, 'nuke_islamic_khotab')))->toHaveCount(1);
});

it('a long or missing title is stored safely (cut to 255 characters, or NULL)', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->update(['title' => str_repeat('ع', 300)]);
    $this->get("/khotab-download-{$this->item}.htm")->assertStatus(503);
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->update(['title' => null]);
    $this->get("/khotab-download-{$this->item}.htm")->assertStatus(503);

    $titles = DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->orderBy('id')->pluck('resource_title')->all();
    expect(mb_strlen($titles[0]))->toBe(255)->and($titles[1])->toBeNull();
});

it('deployed before the migration (no resource_title column): the refusal row is still written, without the title', function () {
    Schema::connection('main')->drop(DownloadRejectionRecorder::TABLE);
    Schema::connection('main')->create(DownloadRejectionRecorder::TABLE, function ($table) {
        MainSchema::downloadRejections()($table);
        $table->dropColumn('resource_title');
    });
    if (Schema::connection('main')->hasColumn(DownloadRejectionRecorder::TABLE, 'resource_title')) {
        Schema::connection('main')->table(DownloadRejectionRecorder::TABLE, fn ($t) => $t->dropColumn('resource_title'));
    }
    expect(Schema::connection('main')->hasColumn(DownloadRejectionRecorder::TABLE, 'resource_title'))->toBeFalse();

    $this->get("/khotab-download-{$this->item}.htm")->assertStatus(503)->assertHeader('Retry-After');

    expect(DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->count())->toBe(1)
        ->and((int) DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->value('resource_id'))->toBe($this->item);
});

it('mirror downloads that are NOT refused are unaffected by the subselect (stream as before)', function () {
    $this->held->release();
    $this->held = null;
    $file = tempnam(sys_get_temp_dir(), 'rot');
    file_put_contents($file, 'mirror-bytes');
    DB::connection('main')->table('nuke_islamic_mirror')->where('id', $this->mirror)->update(['link' => $file]);

    $updates = [];
    DB::listen(function ($q) use (&$updates) {
        if (str_starts_with(strtolower(ltrim($q->sql)), 'update') && str_contains($q->sql, 'hits')) {
            $updates[] = $q->sql;
        }
    });
    // (A streamed response releases the in-memory test database, so the counter is checked by its UPDATE.)
    expect($this->get("/khotab-mirror-{$this->item}-{$this->mirror}.htm")->assertOk()->streamedContent())->toBe('mirror-bytes')
        ->and($updates)->toHaveCount(1);
    @unlink($file);
});

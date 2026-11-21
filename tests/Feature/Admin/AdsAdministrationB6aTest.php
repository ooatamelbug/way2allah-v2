<?php

/*
 * F01 / B6a — OLD advertising administration on `nuke_ads`: list + filters, create, edit, delete,
 * show/hide, reports, and OLD's field semantics on the production table shape.
 */

use App\Domain\Admin\Support\AdsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $this->actingAs(H::admin(), 'admin');
});

afterEach(fn () => Carbon::setTestNow());

// ─── create ─────────────────────────────────────────────────────────────────

it('creates a URL image ad with OLD\'s column values (percentage 0, path_type 2, period dates only)', function () {
    $this->post(route('admin.ads.store'), H::imageInput())->assertRedirect(route('admin.ads.index'))->assertSessionHas('success');

    $row = DB::connection('main')->table('nuke_ads')->sole();
    expect($row->name)->toBe('إعلان جديد')
        ->and((int) $row->type)->toBe(1)
        ->and($row->image_path)->toBe('https://example.org/banner.jpg')
        ->and((int) $row->path_type)->toBe(2)
        ->and((int) $row->position)->toBe(4)
        ->and((int) $row->percentage)->toBe(0)
        ->and((int) $row->show)->toBe(1)
        ->and($row->link)->toBe('https://example.org/')
        ->and($row->ads_show_type)->toBe('period')
        ->and($row->startdate)->toBe('2026-11-01')
        ->and($row->enddate)->toBe('2026-11-30')
        ->and((int) $row->required_num_view)->toBe(0)   // table default, untouched as in OLD
        ->and((int) $row->num_view)->toBe(0)
        ->and((int) $row->num_click)->toBe(0);
});

it('creates a numview ad writing required_num_view only; an unticked «اظهار» stores show = 0', function () {
    $this->post(route('admin.ads.store'), H::imageInput([
        'ads_show_type' => 'numview', 'required_num_view' => '5000', 'startdate' => '', 'enddate' => '', 'show' => null,
    ]))->assertRedirect();

    $row = DB::connection('main')->table('nuke_ads')->sole();
    expect($row->ads_show_type)->toBe('numview')->and((int) $row->required_num_view)->toBe(5000)
        ->and($row->startdate)->toBeNull()->and($row->enddate)->toBeNull()->and((int) $row->show)->toBe(0);
});

it('validates every field server-side and writes nothing on failure', function (array $input, string $field) {
    $this->post(route('admin.ads.store'), H::imageInput($input))->assertSessionHasErrors($field);

    expect(H::snapshot())->toBe([])->and(H::auditRows())->toBe([]);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'bad type' => [['type' => '5'], 'type'],
    'position 0' => [['position' => '0'], 'position'],
    'position 13' => [['position' => '13'], 'position'],
    'missing show type' => [['ads_show_type' => ''], 'ads_show_type'],
    'unknown show type' => [['ads_show_type' => 'forever'], 'ads_show_type'],
    'period without start' => [['startdate' => ''], 'startdate'],
    'end before start' => [['startdate' => '2026-11-30', 'enddate' => '2026-11-01'], 'enddate'],
    'bad date format' => [['startdate' => '01/11/2026'], 'startdate'],
    'numview without count' => [['ads_show_type' => 'numview', 'required_num_view' => ''], 'required_num_view'],
    'numview zero' => [['ads_show_type' => 'numview', 'required_num_view' => '0'], 'required_num_view'],
    'image type without path type' => [['path_type' => ''], 'path_type'],
    'URL image without URL' => [['image_url' => ''], 'image_url'],
    'javascript: image URL' => [['image_url' => 'javascript:alert(1)'], 'image_url'],
    'javascript: link' => [['link' => 'javascript:alert(1)'], 'link'],
    'data: link' => [['link' => 'data:text/html,<script>1</script>'], 'link'],
    'upload without a file' => [['path_type' => '1'], 'image_file'],
]);

// ─── edit (OLD edit_ads semantics) ───────────────────────────────────────────

it('edits the production legacy row without rewriting what the admin did not change', function () {
    $id = H::legacyRow(['percentage' => 50]);

    // Same values back, except the name: the malformed legacy link `ttps://…` is accepted unchanged.
    $this->put(route('admin.ads.update', $id), [
        'name' => 'اسم معدل', 'type' => '1', 'path_type' => '1', 'image_url' => '', 'link' => 'ttps://www.youtube.com/watch?v=rxg2d7ZUN',
        'position' => '4', 'ads_show_type' => 'numview', 'required_num_view' => '10000000',
    ])->assertRedirect(route('admin.ads.edit', $id))->assertSessionHas('success');

    $row = H::row($id);
    expect($row->name)->toBe('اسم معدل')
        ->and($row->link)->toBe('ttps://www.youtube.com/watch?v=rxg2d7ZUN')
        ->and($row->image_path)->toBe('https://way2allah.com/media/bann/1742505317.jpg')   // upload kept (no new file)
        ->and((int) $row->path_type)->toBe(1)
        ->and((int) $row->percentage)->toBe(50)       // not reset to 0 (OLD's side effect is not reproduced)
        ->and((int) $row->num_view)->toBe(76)         // counters never written by administration
        ->and((int) $row->num_click)->toBe(0);

    $audit = json_decode(H::auditRows()[0]->metadata, true);
    expect(array_keys($audit['after']))->toBe(['name']);
});

it('a changed link must be a real http(s) URL, even on a legacy row', function () {
    $id = H::legacyRow();

    $this->put(route('admin.ads.update', $id), [
        'name' => 'x', 'type' => '1', 'path_type' => '1', 'link' => 'ttps://changed.example', 'position' => '4',
        'ads_show_type' => 'numview', 'required_num_view' => '1',
    ])->assertSessionHasErrors('link');

    expect(H::row($id)->name)->toBe('إعلان قديم');
});

it('switching the counting method keeps the other side, as OLD did', function () {
    $id = H::legacyRow(['ads_show_type' => 'period', 'startdate' => '2026-01-01', 'enddate' => '2026-02-01', 'required_num_view' => 777]);

    $this->put(route('admin.ads.update', $id), [
        'name' => 'إعلان قديم', 'type' => '1', 'path_type' => '1', 'link' => 'ttps://www.youtube.com/watch?v=rxg2d7ZUN',
        'position' => '4', 'ads_show_type' => 'numview', 'required_num_view' => '900',
    ])->assertSessionHas('success');

    $row = H::row($id);
    expect($row->ads_show_type)->toBe('numview')->and((int) $row->required_num_view)->toBe(900)
        ->and($row->startdate)->toBe('2026-01-01')->and($row->enddate)->toBe('2026-02-01');
});

it('an unchanged submission writes nothing and records nothing', function () {
    $id = H::legacyRow();
    $before = H::snapshot();

    $this->put(route('admin.ads.update', $id), [
        'name' => 'إعلان قديم', 'type' => '1', 'path_type' => '1', 'link' => 'ttps://www.youtube.com/watch?v=rxg2d7ZUN',
        'position' => '4', 'ads_show_type' => 'numview', 'required_num_view' => '10000000',
    ])->assertSessionHas('error', 'لم يتغير شيء');

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('edit and update of a missing ad are 404', function () {
    $this->get(route('admin.ads.edit', 999))->assertNotFound();
    $this->put(route('admin.ads.update', 999), H::imageInput())->assertNotFound();
});

// ─── delete, show / hide ─────────────────────────────────────────────────────

it('deletes (hard DELETE, as OLD) and keeps the full row in the audit log', function () {
    $id = H::legacyRow();
    $keep = H::legacyRow(['name' => 'باق']);

    $this->delete(route('admin.ads.destroy', $id))->assertRedirect(route('admin.ads.index'))->assertSessionHas('success');

    expect(H::row($id))->toBeNull()->and(H::row($keep))->not->toBeNull();
    $audit = H::auditRows()[0];
    $deleted = json_decode($audit->metadata, true)['deleted'];
    expect($audit->action)->toBe('del_ad')->and((int) $audit->entity_id)->toBe($id)
        ->and($deleted['name'])->toBe('إعلان قديم')->and($deleted['link'])->toBe('ttps://www.youtube.com/watch?v=rxg2d7ZUN')
        ->and((int) $deleted['num_view'])->toBe(76);
});

it('show / hide sets the explicit target state; a repeated click is a no-op', function () {
    $id = H::legacyRow(['show' => 0]);

    $this->post(route('admin.ads.toggle', $id), ['show' => '1'])->assertSessionHas('success');
    expect((int) H::row($id)->show)->toBe(1);

    $this->post(route('admin.ads.toggle', $id), ['show' => '1'])->assertSessionHas('error', 'لم يتغير شيء');
    expect((int) H::row($id)->show)->toBe(1)->and(H::auditRows())->toHaveCount(1);

    $this->post(route('admin.ads.toggle', $id), ['show' => '0'])->assertSessionHas('success');
    expect((int) H::row($id)->show)->toBe(0);

    $this->post(route('admin.ads.toggle', $id), ['show' => '7'])->assertSessionHasErrors('show');
    expect(H::auditRows())->toHaveCount(2);
});

// ─── list ────────────────────────────────────────────────────────────────────

it('lists ads in OLD\'s order (show DESC, id DESC) with OLD\'s status, position label and counters', function () {
    Carbon::setTestNow('2026-11-14 12:00:00');
    $old = H::legacyRow();                                                     // numview 76 ≤ 10M → ساري, hidden
    $running = H::legacyRow(['name' => 'جار', 'show' => 1, 'ads_show_type' => 'period', 'startdate' => '2026-11-01', 'enddate' => '2026-11-30', 'position' => 1]);
    $future = H::legacyRow(['name' => 'قادم', 'show' => 1, 'ads_show_type' => 'period', 'startdate' => '2026-12-01', 'enddate' => '2026-12-31']);
    $ended = H::legacyRow(['name' => 'منته', 'show' => 0, 'ads_show_type' => 'numview', 'required_num_view' => 10, 'num_view' => 11]);

    $html = $this->get(route('admin.ads.index'))->assertOk()->getContent();

    $at = fn (string $name) => strpos($html, '<td>'.$name.' <small');
    expect($at('قادم'))->toBeInt()
        ->and($at('قادم'))->toBeLessThan($at('جار'))
        ->and($at('جار'))->toBeLessThan($at('منته'))
        ->and($at('منته'))->toBeLessThan($at('إعلان قديم'))
        ->and($html)->toContain('يسار الصفحة الرئيسية - 120 * 600')
        ->toContain('تفاصيل المادة علوي - 728 * 90')
        ->toContain('لم يبدأ بعد')->toContain('منتهي')->toContain('ساري')
        ->toContain('<td>76</td>');
});

it('filters by name, show, type, position and date range as OLD did', function () {
    H::legacyRow(['name' => 'ألف', 'show' => 1, 'position' => 1, 'ads_show_type' => 'period', 'startdate' => '2026-01-01', 'enddate' => '2026-01-31']);
    H::legacyRow(['name' => 'باء', 'show' => 0, 'position' => 2, 'ads_show_type' => 'period', 'startdate' => '2026-06-01', 'enddate' => '2026-06-30']);
    H::codeRow(['name' => 'جيم', 'position' => 3]);

    $names = function (array $query) {
        $html = $this->get(route('admin.ads.index', $query))->assertOk()->getContent();

        return collect(['ألف', 'باء', 'جيم'])->filter(fn ($n) => str_contains($html, '<td>'.$n.' '))->values()->all();
    };

    expect($names([]))->toBe(['ألف', 'باء', 'جيم'])
        ->and($names(['name' => 'باء']))->toBe(['باء'])
        ->and($names(['show' => '0']))->toBe(['باء'])
        ->and($names(['show' => '1']))->toBe(['ألف', 'جيم'])
        ->and($names(['type' => '0']))->toBe(['جيم'])
        ->and($names(['position' => '1']))->toBe(['ألف'])
        ->and($names(['from' => '2026-05-01', 'to' => '2026-07-01']))->toBe(['باء'])
        ->and($names(['show' => 'x', 'position' => '99']))->toBe(['ألف', 'باء', 'جيم']);   // invalid filters ignored
});

it('viewing the list, the forms and the report writes nothing (no counters, no auto-hide)', function () {
    $id = H::legacyRow(['show' => 1, 'ads_show_type' => 'period', 'startdate' => '2020-01-01', 'enddate' => '2020-01-02']);
    $before = H::snapshot();

    $this->get(route('admin.ads.index'))->assertOk();
    $this->get(route('admin.ads.create'))->assertOk();
    $this->get(route('admin.ads.edit', $id))->assertOk();
    $this->get(route('admin.ads.reports', ['search' => 1, 'from' => '2020-01-01', 'to' => '2020-12-31', 'position' => 4]))->assertOk();

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

// ─── reports ─────────────────────────────────────────────────────────────────

it('reports enabled ads overlapping the period for one position, in non-overlapping groups, with count and share sum', function () {
    $a = H::legacyRow(['name' => 'أ', 'show' => 1, 'position' => 4, 'percentage' => 50, 'ads_show_type' => 'period', 'startdate' => '2026-01-01', 'enddate' => '2026-01-10']);
    $b = H::legacyRow(['name' => 'ب', 'show' => 1, 'position' => 4, 'percentage' => 25, 'ads_show_type' => 'period', 'startdate' => '2026-01-05', 'enddate' => '2026-01-20']);
    $c = H::legacyRow(['name' => 'ج', 'show' => 1, 'position' => 4, 'percentage' => 25, 'ads_show_type' => 'period', 'startdate' => '2026-01-15', 'enddate' => '2026-02-15']);
    $spanning = H::legacyRow(['name' => 'د', 'show' => 1, 'position' => 4, 'percentage' => 0, 'ads_show_type' => 'period', 'startdate' => '2025-12-01', 'enddate' => '2026-03-01']);
    H::legacyRow(['name' => 'مخفي', 'show' => 0, 'position' => 4, 'ads_show_type' => 'period', 'startdate' => '2026-01-01', 'enddate' => '2026-01-31']);
    H::legacyRow(['name' => 'مكان آخر', 'show' => 1, 'position' => 5, 'ads_show_type' => 'period', 'startdate' => '2026-01-01', 'enddate' => '2026-01-31']);
    H::legacyRow(['name' => 'خارج', 'show' => 1, 'position' => 4, 'ads_show_type' => 'period', 'startdate' => '2026-05-01', 'enddate' => '2026-05-31']);

    $report = AdsReport::build(4, '2026-01-01', '2026-01-31');

    expect(collect($report['rows'])->pluck('name')->all())->toBe(['د', 'أ', 'ب', 'ج'])
        ->and(collect($report['rows'])->mapWithKeys(fn ($r) => [$r['name'] => $r['lane']])->all())->toBe(['د' => 1, 'أ' => 2, 'ب' => 3, 'ج' => 2])
        ->and(collect($report['rows'])->firstWhere('name', 'أ')['days_in_range'])->toBe(10)
        ->and(collect($report['rows'])->firstWhere('name', 'د')['days_in_range'])->toBe(31)
        ->and($report['count'])->toBe(4)->and($report['percentage_sum'])->toBe(100)->and($report['lanes'])->toBe(3);

    $html = $this->get(route('admin.ads.reports', ['search' => 1, 'from' => '2026-01-01', 'to' => '2026-01-31', 'position' => 4, 'type' => 2]))
        ->assertOk()->getContent();
    expect($html)->toContain('المجموعة 3')->toContain('عدد الإعلانات: 4')->toContain('مجموع النسب: 100%')
        ->not->toContain('مخفي')->not->toContain('مكان آخر')->not->toContain('خارج');
});

it('the report filters by type and requires a valid period', function () {
    H::legacyRow(['name' => 'مصور', 'show' => 1, 'position' => 4, 'ads_show_type' => 'period', 'startdate' => '2026-01-01', 'enddate' => '2026-01-31']);
    H::codeRow(['name' => 'كود', 'position' => 4]);

    expect(collect(AdsReport::build(4, '2026-01-01', '2026-01-31', 0)['rows'])->pluck('name')->all())->toBe(['كود'])
        ->and(collect(AdsReport::build(4, '2026-01-01', '2026-01-31', 1)['rows'])->pluck('name')->all())->toBe(['مصور']);

    $this->get(route('admin.ads.reports', ['search' => 1, 'from' => '', 'to' => '2026-01-31', 'position' => 4]))->assertSessionHasErrors('from');
    $this->get(route('admin.ads.reports', ['search' => 1, 'from' => '2026-02-01', 'to' => '2026-01-01', 'position' => 4]))->assertSessionHasErrors('to');
    $this->get(route('admin.ads.reports'))->assertOk()->assertDontSee('كل الإعلانات السارية والمعروضة');
});

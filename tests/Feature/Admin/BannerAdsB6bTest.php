<?php

/*
 * F01 / B6b — «إعلانات بنرات المواد» (OLD modules/banners_ads/admin): the set of Khotab banner items
 * (`nuke_banners_ads.media_id`), saved non-destructively.
 */

use App\Domain\Admin\Actions\Ads\UpdateBannerAdsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BannerAdsHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $main = DB::connection('main');
    $main->table('nuke_islamic_authors')->insert([['id' => 7, 'name' => 'الشيخ أ'], ['id' => 8, 'name' => 'الشيخ ب']]);
    $main->table('nuke_w2a_cat')->insert([['id' => 1, 'title' => 'خطب', 'main_cat' => 0], ['id' => 2, 'title' => 'رمضان', 'main_cat' => 1]]);

    $this->a = H::item('بنر ألف', 1, ['author' => 7]);
    $this->b = H::item('بنر باء', 1, ['author' => 8]);
    $this->c = H::item('بنر جيم', 1, ['author' => 7]);
    $this->noBanner = H::item('بلا بنر', 0, ['author' => 7]);
    $main->table('khotab_category_index')->insert(['khotab_id' => $this->c, 'category_id' => 2]);

    $this->memberA = H::member($this->a, 968);
    $this->memberB = H::member($this->b, 969);

    $this->actingAs(H::admin(['banners_ads.managebanners']), 'admin');
});

it('shows the current set and the banner items (only banner = 1), with OLD\'s category paths and authors', function () {
    $html = $this->get(route('admin.banners_ads.index'))->assertOk()->getContent();

    expect($html)->toContain('المواد المختارة (2)')
        ->toContain('name="keep[]" value="'.$this->a.'" checked')->toContain('name="keep[]" value="'.$this->b.'" checked')
        ->toContain('name="add[]" value="'.$this->c.'"')
        ->not->toContain('name="add[]" value="'.$this->a.'"')     // already a member: no duplicate offer
        ->not->toContain('بلا بنر')
        ->toContain('خطب=&gt;رمضان')->toContain('الشيخ أ')
        ->toContain('2019-12-01 06:43:29');
});

it('filters suggestions by category (khotab_category_index), author and title', function () {
    $offered = function (array $query) {
        $html = $this->get(route('admin.banners_ads.index', $query))->assertOk()->getContent();
        preg_match_all('/name="add\[\]" value="(\d+)"/', $html, $m);

        return array_map('intval', $m[1]);
    };

    expect($offered(['category' => 2]))->toBe([$this->c])
        ->and($offered(['category' => 1]))->toBe([])
        ->and($offered(['author' => 8]))->toBe([])                 // b is a member already
        ->and($offered(['author' => 7]))->toBe([$this->c])
        ->and($offered(['q' => 'جيم']))->toBe([$this->c])
        ->and($offered(['category' => 'x', 'author' => '-3']))->toBe([$this->c]);   // invalid filters ignored
});

it('saving adds and removes only what changed; unchanged rows keep their id and creation_date', function () {
    $this->put(route('admin.banners_ads.update'), ['keep' => [$this->a], 'add' => [$this->c], 'set_version' => H::version()])
        ->assertRedirect(route('admin.banners_ads.index'))->assertSessionHas('success');

    $set = H::set();
    expect(array_column($set, 'media_id'))->toBe([$this->a, $this->c])
        ->and($set[0]['id'])->toBe(968)->and($set[0]['creation_date'])->toBe('2019-12-01 06:43:29');

    $audit = H::auditRows();
    expect($audit)->toHaveCount(1)->and($audit[0]->action)->toBe('update_banners')->and($audit[0]->module)->toBe('banners_ads')
        ->and(json_decode($audit[0]->metadata, true))->toBe(['added' => [$this->c], 'removed' => [$this->b], 'count_after' => 2]);
});

it('an unchanged save writes nothing and records nothing', function () {
    $before = H::set();

    $this->put(route('admin.banners_ads.update'), ['keep' => [$this->a, $this->b], 'set_version' => H::version()])->assertSessionHas('error', 'لم يتغير شيء');

    expect(H::set())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('saving with nothing ticked empties the set (OLD\'s replacement semantics), audited', function () {
    $this->put(route('admin.banners_ads.update'), ['set_version' => H::version()])->assertSessionHas('success');

    expect(H::set())->toBe([])
        ->and(json_decode(H::auditRows()[0]->metadata, true)['removed'])->toBe([$this->a, $this->b]);
});

it('refuses to add an item without a banner, a missing item, or a tampered keep list — and writes nothing', function (string $field, string $which) {
    $before = H::set();
    $value = match ($which) {
        'no-banner' => $this->noBanner,
        'missing' => 99999,
        'non-member' => $this->c,
    };
    $payload = ['keep' => [$this->a, $this->b], 'set_version' => H::version()];
    $payload[$field][] = $value;

    $this->put(route('admin.banners_ads.update'), $payload)->assertSessionHasErrors($field);

    expect(H::set())->toBe($before)->and(H::auditRows())->toBe([]);
})->with([
    'item without banner' => ['add', 'no-banner'],
    'missing item' => ['add', 'missing'],
    'keep a non-member' => ['keep', 'non-member'],
]);

it('validates the submitted ids', function (array $payload, string $field) {
    $this->put(route('admin.banners_ads.update'), $payload + ['set_version' => H::version()])->assertSessionHasErrors($field);
    expect(H::auditRows())->toBe([]);
})->with([
    'non-integer add' => [['add' => ['1 OR 1=1']], 'add.0'],
    'zero' => [['add' => [0]], 'add.0'],
    'duplicate add' => [['add' => [3, 3]], 'add.0'],
    'scalar keep' => [['keep' => '968'], 'keep'],
]);

it('keeps a legacy member whose item lost its banner or no longer exists (stored data is not silently dropped)', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->b)->update(['banner' => 0]);
    $ghost = H::member(424242);

    $html = $this->get(route('admin.banners_ads.index'))->assertOk()->getContent();
    expect($html)->toContain('لم تعد تحتوي على بنر')->toContain('المادة غير موجودة');

    $this->put(route('admin.banners_ads.update'), ['keep' => [$this->a, $this->b, 424242], 'add' => [$this->c], 'set_version' => H::version()])->assertSessionHas('success');
    expect(array_column(H::set(), 'media_id'))->toBe([$this->a, $this->b, 424242, $this->c])
        ->and(collect(H::set())->firstWhere('id', $ghost)['creation_date'])->toBe('2019-12-01 06:43:29');
});

it('escapes item titles and author names (OLD printed them raw into <option>)', function () {
    $xss = H::item('<img src=x onerror=alert(1)>', 1, ['author' => 7]);
    DB::connection('main')->table('nuke_islamic_authors')->where('id', 7)->update(['name' => '<script>alert(2)</script>']);
    H::member($xss);

    $html = $this->get(route('admin.banners_ads.index'))->assertOk()->getContent();
    expect($html)->not->toContain('<img src=x onerror=alert(1)>')->not->toContain('<script>alert(2)</script>')
        ->toContain(e('<img src=x onerror=alert(1)>'))->toContain(e('<script>alert(2)</script>'));
});

it('viewing the page writes nothing', function () {
    $before = H::set();
    $this->get(route('admin.banners_ads.index', ['category' => 2]))->assertOk();
    expect(H::set())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('the form carries the fingerprint of the set it was rendered from', function () {
    expect($this->get(route('admin.banners_ads.index'))->getContent())
        ->toContain('name="set_version" value="'.H::version().'"');
});

it('a stale form cannot silently undo another admin\'s concurrent change — rejected, nothing written', function (string $change) {
    $staleVersion = H::version();                      // admin A renders {a, b}
    $other = H::superAdmin();
    match ($change) {                                  // admin B saves first
        'added' => app(UpdateBannerAdsAction::class)->execute($other, [$this->a, $this->b], [$this->c], $staleVersion),
        'removed' => app(UpdateBannerAdsAction::class)->execute($other, [$this->a], [], $staleVersion),
    };
    $before = H::set();
    $auditBefore = count(H::auditRows());

    // A submits the stale form: changed nothing, or added c too.
    $this->put(route('admin.banners_ads.update'), ['keep' => [$this->a], 'set_version' => $staleVersion])->assertSessionHasErrors('keep');
    $this->put(route('admin.banners_ads.update'), ['keep' => [$this->a], 'add' => [$this->c], 'set_version' => $staleVersion])->assertSessionHasErrors('keep');

    expect(H::set())->toBe($before)->and(H::auditRows())->toHaveCount($auditBefore);
})->with(['another admin added an item' => 'added', 'another admin removed an item' => 'removed']);

it('a save without the set fingerprint is refused', function (mixed $version) {
    $before = H::set();
    $payload = ['keep' => [], 'add' => [$this->c]];
    if ($version !== null) {
        $payload['set_version'] = $version;
    }

    $this->put(route('admin.banners_ads.update'), $payload)->assertSessionHasErrors();

    expect(H::set())->toBe($before)->and(H::auditRows())->toBe([]);
})->with(['missing' => null, 'malformed' => 'abc', 'forged' => str_repeat('0', 64)]);

it('the fingerprint ignores order and legacy duplicate rows', function () {
    expect(UpdateBannerAdsAction::fingerprint([3, 1, 2]))->toBe(UpdateBannerAdsAction::fingerprint(['2', 3, 1, 1]))
        ->not->toBe(UpdateBannerAdsAction::fingerprint([1, 2]));
});

it('legacy duplicate rows for one item are tolerated: no new duplicate is inserted, and removal clears them all', function () {
    H::member($this->a);                               // legacy duplicate of a
    $this->put(route('admin.banners_ads.update'), ['keep' => [$this->a, $this->b], 'add' => [$this->a, $this->b], 'set_version' => H::version()])
        ->assertSessionHas('error', 'لم يتغير شيء');
    expect(collect(H::set())->where('media_id', $this->a))->toHaveCount(2);

    $this->put(route('admin.banners_ads.update'), ['keep' => [$this->b], 'set_version' => H::version()])->assertSessionHas('success');
    expect(array_column(H::set(), 'media_id'))->toBe([$this->b]);
});

<?php

/*
 * OLD public admin parity — the Khotab series page (owner, 2026-10-21):
 * «لوحة تحكم السلسلة» (OLD series_cp.htm), the icon beside each item, and
 * admin visibility of hidden content (owner decision: admins see what OLD
 * showed them, visitors are unchanged). Reproduced from production OLD
 * (= the old-project working copy).
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    foreach ([
        'nuke_islamic_locations' => MainSchema::nukeIslamicLocations(),
        'nuke_islamic_authors_location' => MainSchema::nukeIslamicAuthorsLocation(),
        'nuke_islamic_groups_location' => MainSchema::nukeIslamicGroupsLocation(),
        'nuke_islamic_series_location' => MainSchema::nukeIslamicSeriesLocation(),
    ] as $table => $schema) {
        Schema::connection('main')->create($table, $schema);
    }

    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.', 'vedio' => 10, 'audio' => 4]);
    H::insert('nuke_sat_channels', ['id' => 3, 'title' => 'قناة']);
    H::insert('nuke_sat_channels', ['id' => 4, 'title' => 'أخرى']);
    H::insert('nuke_islamic_locations', ['id' => 5, 'title' => 'مسجد']);
    H::category(1, 'العقيدة');
    H::category(2, 'الفقه');
    H::category(9, 'غير تابع');

    $this->group = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'مجموعة', 'hidden' => 0, 'vedio' => 1, 'cat' => '|1|2|', 'channel_id' => 3, 'count' => 4]);
    $this->group2 = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'مجموعة ثانية', 'hidden' => 0, 'vedio' => 1, 'channel_id' => 4, 'count' => 0]);
    $this->series = H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $this->group, 'channel_id' => 3, 'title' => 'سلسلة', 'hidden' => 0, 'vedio' => 1, 'count' => 3, 'cat' => '|1|2|']);
    $this->a = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'أ', 'vedio' => 1, 'group_id' => $this->group, 'ser_id' => $this->series, 'weight' => 3, 'cat' => '|1|9|', 'location_id' => 5, 'channel_id' => 3]);
    $this->b = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'ب', 'vedio' => 1, 'group_id' => $this->group, 'ser_id' => $this->series, 'weight' => 1, 'cat' => '|2|', 'channel_id' => 3]);
    $this->hidden = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مخفي', 'vedio' => 1, 'group_id' => $this->group, 'ser_id' => $this->series, 'weight' => 2, 'hidden' => 1, 'channel_id' => 3]);
    // In the series but not in its group: outside OLD's `ser_id AND group_id` set, inside `ser_id` alone.
    $this->stray = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'شارد', 'vedio' => 1, 'group_id' => 0, 'ser_id' => $this->series, 'weight' => 9]);
    $this->groupOnly = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'في المجموعة فقط', 'vedio' => 1, 'group_id' => $this->group, 'ser_id' => 0]);

    $this->loose = H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => 0, 'channel_id' => 0, 'title' => 'سلسلة حرة', 'hidden' => 0, 'vedio' => 1, 'count' => 0]);

    $this->url = "/khotab-series-{$this->series}.htm";
    $this->boss = H::superAdmin();
});

function srow(string $table, int $id, string $column)
{
    return DB::connection('main')->table($table)->where('id', $id)->value($column);
}

function seriesAudit(): array
{
    return DB::connection('main')->table('admin_audit_log')->orderBy('id')->pluck('action')->all();
}

// ─── the public page ────────────────────────────────────────────────────────

it('shows a visitor nothing at all, and no hidden item', function () {
    $html = $this->get($this->url)->assertOk()->getContent();

    expect($html)->not->toContain('data-contextual-admin')->not->toContain('لوحة تحكم السلسلة')->not->toContain('/admincp/')
        ->toContain('>أ</a>')->not->toContain('>مخفي</a>');
});

it('shows a super-admin OLD\'s full panel, in OLD\'s order, plus the existing add / remove-all and a detach per item', function () {
    $html = $this->withSession(H::session($this->boss))->get($this->url)->assertOk()->getContent();

    expect($html)->toContain('لوحة تحكم السلسلة');

    $positions = [];
    foreach (['إعادة ترتيب', 'التصنيف السريع', 'تعديل السلسلة', 'إضافة مادة', 'حذف السلسلة', 'مواد لم تصنف'] as $label) {
        $positions[] = strpos($html, '</i> '.$label.'</a>');
    }
    expect($positions)->not->toContain(false);
    $sorted = $positions;
    sort($sorted);
    expect($positions)->toBe($sorted);

    expect($html)
        ->toContain(route('admin.khotab.series.rearrange', $this->series))
        ->toContain(route('admin.khotab.series.fastcopy', $this->series))
        ->toContain(route('admin.khotab.series.edit', $this->series))
        ->toContain(route('admin.khotab.series.items.create', $this->series))
        ->toContain(route('admin.khotab.series.delete', $this->series))
        ->toContain(route('admin.khotab.series.items', $this->series))
        ->toContain('action="'.route('admin.categories.khotab.series.add-all', $this->series).'"')
        ->toContain('action="'.route('admin.categories.khotab.series.remove-all', $this->series).'"')
        ->toContain('action="'.route('admin.khotab.items.detach', $this->a).'"')
        ->toContain('action="'.route('admin.khotab.items.detach', $this->b).'"')
        ->toContain('إخراج المادة من السلسلة');
});

it('shows each admin only the controls their permissions reach; «مواد لم تصنف» is open to every admin, as in OLD', function () {
    $plain = $this->withSession(H::session(H::admin([])))->get($this->url)->assertOk()->getContent();
    expect($plain)->toContain(route('admin.khotab.series.items', $this->series))
        ->not->toContain(route('admin.khotab.series.edit', $this->series))
        ->not->toContain(route('admin.khotab.series.delete', $this->series))
        ->not->toContain('إخراج المادة من السلسلة');

    $editor = $this->withSession(H::session(H::admin(['khotab.editseries', 'khotab.moveitem', ...H::BOTH_SECTIONS], 'ed')))->get($this->url)->getContent();
    expect($editor)->toContain(route('admin.khotab.series.edit', $this->series))
        ->toContain('action="'.route('admin.khotab.items.detach', $this->a).'"')
        ->not->toContain(route('admin.khotab.series.delete', $this->series))
        ->not->toContain(route('admin.khotab.series.rearrange', $this->series));
});

it('refuses every mutation without its permission (403) and without a session (401)', function () {
    $routes = [
        ['post', route('admin.khotab.series.rearrange.save', $this->series)],
        ['post', route('admin.khotab.series.fastcopy.save', $this->series)],
        ['put', route('admin.khotab.series.update', $this->series)],
        ['delete', route('admin.khotab.series.destroy', $this->series)],
        ['post', route('admin.khotab.series.items.store', $this->series)],
        ['post', route('admin.khotab.items.detach', $this->a)],
    ];

    foreach ($routes as [$method, $url]) {
        $this->{$method}($url)->assertUnauthorized();
    }

    $session = H::session(H::admin(['khotab.editgroup', 'khotab.delgroup']));
    foreach ($routes as [$method, $url]) {
        $this->withSession($session)->{$method}($url)->assertForbidden();
    }

    expect(DB::connection('main')->table('nuke_islamic_series')->where('id', $this->series)->exists())->toBeTrue()
        ->and(seriesAudit())->toBe([]);
});

// ─── hidden content (owner decision: admin parity) ───────────────────────────

it('renders a hidden series for an admin (with its panel and hidden items) and 404s it for a visitor', function () {
    DB::connection('main')->table('nuke_islamic_series')->where('id', $this->series)->update(['hidden' => 1]);

    $this->get($this->url)->assertNotFound();

    $response = $this->withSession(H::session($this->boss))->get($this->url)->assertOk();
    expect($response->getContent())->toContain('لوحة تحكم السلسلة')->toContain('>مخفي</a>')
        ->toContain('action="'.route('admin.khotab.items.detach', $this->hidden).'"')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('lists a visible series\' hidden items for any admin, privately, and never for a visitor', function () {
    $visitor = $this->get($this->url)->assertOk();
    expect($visitor->getContent())->not->toContain('>مخفي</a>')
        ->and((string) $visitor->headers->get('Cache-Control'))->not->toContain('no-store');

    $response = $this->withSession(H::session(H::admin([])))->get($this->url)->assertOk();

    expect($response->getContent())->toContain('>مخفي</a>')->toContain('>أ</a>')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

// ─── 1. إعادة ترتيب ──────────────────────────────────────────────────────────

it('re-arrange lists the series\' items in its group (hidden included) by weight DESC, numbered N..1', function () {
    $html = $this->withSession(H::session($this->boss))->get(route('admin.khotab.series.rearrange', $this->series))->assertOk()->getContent();

    expect(strpos($html, '>&nbsp;أ<'))->toBeLessThan(strpos($html, '>&nbsp;مخفي<'))
        ->and(strpos($html, '>&nbsp;مخفي<'))->toBeLessThan(strpos($html, '>&nbsp;ب<'))
        ->and($html)->not->toContain('شارد')->not->toContain('في المجموعة فقط')
        ->toContain('name="Khotab['.$this->a.']" size="3" dir="ltr" value="3"')
        ->toContain('name="Khotab['.$this->b.']" size="3" dir="ltr" value="1"');
});

it('re-arrange saves weights 1..N in asort order, only where ser_id and group_id both match, and returns to the series', function () {
    $this->withSession(H::session($this->boss))->post(route('admin.khotab.series.rearrange.save', $this->series), [
        'Khotab' => [$this->a => '5', $this->b => '9', $this->hidden => '1', $this->stray => '0'],
    ])->assertRedirect($this->url);

    // asort: stray(0) → 1 (group_id 0 ≠ the series' group: untouched), hidden(1) → 2, a(5) → 3, b(9) → 4
    expect((int) srow('nuke_islamic_khotab', $this->hidden, 'weight'))->toBe(2)
        ->and((int) srow('nuke_islamic_khotab', $this->a, 'weight'))->toBe(3)
        ->and((int) srow('nuke_islamic_khotab', $this->b, 'weight'))->toBe(4)
        ->and((int) srow('nuke_islamic_khotab', $this->stray, 'weight'))->toBe(9)
        ->and(seriesAudit())->toBe(['rearrange_item']);
});

// ─── 2. التصنيف السريع ───────────────────────────────────────────────────────

it('fast-copy shows the series\' categories as columns, linking its group and itself', function () {
    $html = $this->withSession(H::session($this->boss))->get(route('admin.khotab.series.fastcopy', $this->series))->assertOk()->getContent();

    expect($html)->toContain('<p class="css-vertical-text">العقيدة</p>')->toContain('<p class="css-vertical-text">الفقه</p>')
        ->not->toContain('<p class="css-vertical-text">غير تابع</p>')
        ->toContain('name="Khotab['.$this->a.'][1]" value="1" checked')
        ->toContain('href="/khotab-group-'.$this->group.'.htm">مجموعة</a>')
        ->toContain('href="/khotab-series-'.$this->series.'.htm">سلسلة</a>')
        ->not->toContain('شارد');
});

it('fast-copy save replaces each list with the ticked series categories, then repairs the group and the series', function () {
    $counters = DB::connection('main')->table('nuke_w2a_cat')->orderBy('id')->pluck('video_count', 'id')->all();

    $this->withSession(H::session($this->boss))->post(route('admin.khotab.series.fastcopy.save', $this->series), [
        'Khotab' => [$this->a => ['1' => '1'], $this->b => ['1' => '1'], $this->hidden => ['9' => '9'], $this->stray => ['2' => '2']],
    ])->assertRedirect(route('admin.khotab.series.fastcopy', $this->series));

    expect(srow('nuke_islamic_khotab', $this->a, 'cat'))->toBe('|1|')       // 9 is not a series column: dropped
        ->and(srow('nuke_islamic_khotab', $this->b, 'cat'))->toBe('|1|')
        ->and(srow('nuke_islamic_khotab', $this->hidden, 'cat'))->toBeNull()  // ticked only a non-column, unchanged set (empty)
        ->and(srow('nuke_islamic_khotab', $this->stray, 'cat'))->toBeNull()   // outside the matrix
        ->and(DB::connection('main')->table('nuke_w2a_cat')->orderBy('id')->pluck('video_count', 'id')->all())->toBe($counters)
        ->and(srow('nuke_islamic_groups', $this->group, 'cat'))->toBe('|1|')  // OLD cat_repair_group: union of all group items
        ->and(srow('nuke_islamic_series', $this->series, 'cat'))->toBe('|1|') // OLD cat_repair_series
        ->and(seriesAudit())->toBe(['fast_copy', 'fast_copy', 'repair_cat_group', 'repair_cat_series']);
});

// ─── 3. تعديل السلسلة ────────────────────────────────────────────────────────

it('edit shows OLD\'s fields: the author\'s groups, and the channel as text for a grouped series', function () {
    $html = $this->withSession(H::session($this->boss))->get(route('admin.khotab.series.edit', $this->series))->assertOk()->getContent();

    expect($html)->toContain('name="ramadan"')->toContain('name="C1"')->toContain('<option value="0">بدون مجموعة</option>')
        ->toContain('<option value="'.$this->group.'" selected>مجموعة</option>')->toContain('>مجموعة ثانية</option>')
        ->not->toContain('name="Channel"')->toContain('قناة');

    $loose = $this->withSession(H::session($this->boss))->get(route('admin.khotab.series.edit', $this->loose))->getContent();
    expect($loose)->toContain('name="Channel"');
});

it('edit with the same group writes the series\' own fields only, and returns to the group page as OLD', function () {
    $this->withSession(H::session($this->boss))->put(route('admin.khotab.series.update', $this->series), [
        'title' => 'جديد', 'group' => (string) $this->group, 'Channel' => '4', 'C1' => '1', 'ramadan' => '1',
        'description' => '<b>وصف</b>', 'meta_keywords' => 'k', 'meta_description' => 'd', 'meta_index' => '1', 'Des' => 'تعليق',
    ])->assertRedirect("/khotab-group-{$this->group}.htm");

    $row = DB::connection('main')->table('nuke_islamic_series')->find($this->series);
    expect($row->title)->toBe('جديد')->and((int) $row->hidden)->toBe(1)->and((int) $row->ramadan)->toBe(1)
        ->and((int) $row->meta_index)->toBe(1)->and((int) $row->meta_follow)->toBe(0)->and($row->des)->toBe('تعليق')
        ->and((int) $row->channel_id)->toBe(3)->and((int) $row->group_id)->toBe($this->group)
        ->and((int) srow('nuke_islamic_khotab', $this->a, 'channel_id'))->toBe(3)
        ->and(seriesAudit())->toBe(['edit_series'])
        ->and(DB::connection('main')->table('admin_audit_log')->value('metadata'))->not->toContain('<b>');
});

it('edit moving to another group of the author moves the items too and takes that group\'s channel', function () {
    $this->withSession(H::session($this->boss))->put(route('admin.khotab.series.update', $this->series), [
        'title' => 'سلسلة', 'group' => (string) $this->group2,
    ])->assertRedirect("/khotab-group-{$this->group2}.htm");

    expect((int) srow('nuke_islamic_series', $this->series, 'group_id'))->toBe($this->group2)
        ->and((int) srow('nuke_islamic_series', $this->series, 'channel_id'))->toBe(4)
        ->and((int) srow('nuke_islamic_khotab', $this->a, 'group_id'))->toBe($this->group2)
        ->and((int) srow('nuke_islamic_khotab', $this->stray, 'group_id'))->toBe($this->group2)
        ->and((int) srow('nuke_islamic_khotab', $this->hidden, 'channel_id'))->toBe(4)
        ->and((int) srow('nuke_islamic_khotab', $this->groupOnly, 'group_id'))->toBe($this->group)
        ->and((int) srow('nuke_islamic_groups', $this->group, 'count'))->toBe(4);   // OLD moved no counter
});

it('edit to «بدون مجموعة» (or a group that is not the author\'s) takes series and items out, channels untouched', function () {
    $foreign = H::insert('nuke_islamic_groups', ['author_id' => 99, 'title' => 'غريبة', 'vedio' => 1, 'channel_id' => 4]);

    $this->withSession(H::session($this->boss))->put(route('admin.khotab.series.update', $this->series), [
        'title' => 'سلسلة', 'group' => (string) $foreign,
    ])->assertRedirect("/khotab-group-{$foreign}.htm");   // OLD redirected to the posted group regardless

    expect((int) srow('nuke_islamic_series', $this->series, 'group_id'))->toBe(0)
        ->and((int) srow('nuke_islamic_series', $this->series, 'channel_id'))->toBe(3)
        ->and((int) srow('nuke_islamic_khotab', $this->a, 'group_id'))->toBe(0)
        ->and((int) srow('nuke_islamic_khotab', $this->a, 'channel_id'))->toBe(3);

    $this->withSession(H::session($this->boss))->put(route('admin.khotab.series.update', $this->loose), ['title' => 'x', 'group' => '0'])
        ->assertRedirect("/khotab-series-{$this->loose}.htm");
});

it('edit of a series with no group writes the chosen channel to it and its items, and refuses an unknown one', function () {
    $item = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'حر', 'vedio' => 1, 'ser_id' => $this->loose, 'channel_id' => 0]);

    $this->withSession(H::session($this->boss))->put(route('admin.khotab.series.update', $this->loose), ['title' => 'حرة', 'group' => '0', 'Channel' => '4'])
        ->assertRedirect("/khotab-series-{$this->loose}.htm");

    expect((int) srow('nuke_islamic_series', $this->loose, 'channel_id'))->toBe(4)
        ->and((int) srow('nuke_islamic_khotab', $item, 'channel_id'))->toBe(4);

    $this->withSession(H::session($this->boss))->from(route('admin.khotab.series.edit', $this->loose))
        ->put(route('admin.khotab.series.update', $this->loose), ['title' => 'y', 'group' => '0', 'Channel' => '99'])
        ->assertSessionHasErrors('Channel');

    expect(srow('nuke_islamic_series', $this->loose, 'title'))->toBe('حرة');
});

// ─── 4. إضافة مادة ───────────────────────────────────────────────────────────

it('add item form: a disabled channel for a series with a channel, a real choice for a free series', function () {
    $html = $this->withSession(H::session($this->boss))->get(route('admin.khotab.series.items.create', $this->series))->assertOk()->getContent();
    expect($html)->toContain('<select size="1" disabled="disabled"><option value="3" selected>قناة</option></select>')
        ->not->toContain('name="Channel"')->toContain('مجموعة')->toContain('سلسلة');

    $free = $this->withSession(H::session($this->boss))->get(route('admin.khotab.series.items.create', $this->loose))->getContent();
    expect($free)->toContain('<select name="Channel" size="1">')->toContain('بدون مجموعة');
});

it('add item to a grouped series: OLD\'s row, series and group counters, three location counts, the series\' channel', function () {
    $this->withSession(H::session($this->boss))->post(route('admin.khotab.series.items.store', $this->series), [
        'link' => ' https://example.test/s.mp4 ', 'title' => 'درس جديد', 'size' => '2.5', 'MoreInfo' => 'n',
        'Location' => '5', 'newslist' => '1', 'meta_index' => '1', 'meta_follow' => '1', 'Channel' => '4',
    ])->assertRedirect($this->url);

    $item = DB::connection('main')->table('nuke_islamic_khotab')->orderByDesc('id')->first();
    expect($item->title)->toBe('درس جديد')->and($item->link)->toBe('https://example.test/s.mp4')
        ->and((int) $item->ser_id)->toBe($this->series)->and((int) $item->group_id)->toBe($this->group)
        ->and((int) $item->channel_id)->toBe(3)          // the series' channel (OLD: disabled select, then the on-view cascade)
        ->and((int) $item->linksize)->toBe((int) (1024 * 1024 * 2.5))->and((int) $item->down)->toBe(2)
        ->and((int) $item->weight)->toBe((int) $item->id)->and((int) $item->fixed)->toBe(0)
        ->and((int) srow('nuke_islamic_authors', 7, 'vedio'))->toBe(11)
        ->and((int) srow('nuke_islamic_series', $this->series, 'count'))->toBe(4)
        ->and((int) srow('nuke_islamic_series', $this->series, 'lastupdate'))->toBe((int) $item->time)
        ->and((int) srow('nuke_islamic_groups', $this->group, 'count'))->toBe(5)
        ->and(DB::connection('main')->table('nuke_islamic_authors_location')->where('author_id', 7)->where('location_id', 5)->value('count'))->toBe(2)
        ->and(DB::connection('main')->table('nuke_islamic_series_location')->where('series_id', $this->series)->where('location_id', 5)->value('count'))->toBe(2)
        ->and(DB::connection('main')->table('nuke_islamic_groups_location')->where('group_id', $this->group)->where('location_id', 5)->value('count'))->toBe(2)
        ->and(seriesAudit())->toBe(['add_item']);
});

it('add item to a free series keeps the chosen channel and touches no group', function () {
    $this->withSession(H::session($this->boss))->post(route('admin.khotab.series.items.store', $this->loose), [
        'link' => 'https://example.test/f.mp4', 'title' => 'درس حر', 'Channel' => '4',
    ])->assertRedirect("/khotab-series-{$this->loose}.htm");

    $item = DB::connection('main')->table('nuke_islamic_khotab')->orderByDesc('id')->first();
    expect((int) $item->ser_id)->toBe($this->loose)->and((int) $item->group_id)->toBe(0)->and((int) $item->channel_id)->toBe(4)
        ->and((int) srow('nuke_islamic_series', $this->loose, 'count'))->toBe(1)
        ->and((int) srow('nuke_islamic_groups', $this->group, 'count'))->toBe(4);
});

it('add item applies OLD\'s refusals and downloads only archive.org images', function () {
    H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'قديم', 'link' => 'https://dup.test/x', 'vedio' => 1]);
    $session = H::session($this->boss);
    $before = DB::connection('main')->table('nuke_islamic_khotab')->count();

    $this->withSession($session)->post(route('admin.khotab.series.items.store', $this->series), ['link' => 'l', 'title' => 'ab'])->assertSessionHasErrors('title');
    $this->withSession($session)->post(route('admin.khotab.series.items.store', $this->series), ['link' => 'https://dup.test/x', 'title' => 'عنوان'])->assertSessionHasErrors('link');
    expect(DB::connection('main')->table('nuke_islamic_khotab')->count())->toBe($before)->and(seriesAudit())->toBe([]);

    $root = sys_get_temp_dir().'/w2a-media-'.uniqid();
    config(['admin_khotab.media_root' => $root]);
    Http::fake(['ia800.us.archive.org/*' => Http::response('IMG', 200), '*' => Http::response('NO', 200)]);

    $this->withSession($session)->post(route('admin.khotab.series.items.store', $this->series), [
        'link' => 'https://archive.org/download/x/s.mp4', 'title' => 'درس', 'original_size' => '123456',
        'archive_gif' => 'https://evil.test/a.gif', 'archive_frame' => 'https://ia800.us.archive.org/x/a.jpg',
    ])->assertRedirect($this->url);

    $item = DB::connection('main')->table('nuke_islamic_khotab')->orderByDesc('id')->first();
    $bucket = intdiv((int) $item->id, 1000);
    expect((int) $item->linksize)->toBe(123456)->and((int) $item->gif)->toBe(0)->and((int) $item->frame)->toBe(1)
        ->and(file_get_contents("{$root}/khotab_frames/{$bucket}/{$item->id}.jpg"))->toBe('IMG');
});

// ─── 5. حذف السلسلة ──────────────────────────────────────────────────────────

it('delete shows OLD\'s confirmation, and refuses — writing nothing — while any item (hidden included) is in the series', function () {
    $this->withSession(H::session($this->boss))->get(route('admin.khotab.series.delete', $this->series))->assertOk()
        ->assertSee("هل ترغب في مسح سلسلة د. الشيخ بعنوان<br>''سلسلة''????", false);

    DB::connection('main')->table('nuke_islamic_khotab')->where('ser_id', $this->series)->where('id', '!=', $this->hidden)->update(['ser_id' => 0]);

    $this->withSession(H::session($this->boss))->delete(route('admin.khotab.series.destroy', $this->series))
        ->assertOk()->assertSee('عفواً .... لن تتمكن من مسح هذه السلسلة');

    expect(DB::connection('main')->table('nuke_islamic_series')->where('id', $this->series)->exists())->toBeTrue()
        ->and(seriesAudit())->toBe([]);
});

it('delete removes an empty series only, landing on its group page, or on the author page without a group', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->where('ser_id', $this->series)->update(['ser_id' => 0]);

    $this->withSession(H::session($this->boss))->delete(route('admin.khotab.series.destroy', $this->series))
        ->assertRedirect("/khotab-group-{$this->group}.htm");
    $this->withSession(H::session($this->boss))->delete(route('admin.khotab.series.destroy', $this->loose))
        ->assertRedirect('/khotab-video-7.htm');

    expect(DB::connection('main')->table('nuke_islamic_series')->whereIn('id', [$this->series, $this->loose])->exists())->toBeFalse()
        ->and((int) srow('nuke_islamic_groups', $this->group, 'count'))->toBe(4)
        ->and((int) srow('nuke_islamic_khotab', $this->a, 'group_id'))->toBe($this->group)
        ->and(seriesAudit())->toBe(['del_series', 'del_series']);
});

// ─── 6. مواد لم تصنف ─────────────────────────────────────────────────────────

it('«مواد لم تصنف» lists every item WHERE ser_id = X (hidden and off-group included), by id, for any admin', function () {
    $html = $this->withSession(H::session(H::admin([])))->get(route('admin.khotab.series.items', $this->series))->assertOk()->getContent();

    expect($html)->toContain('قائمة المواد ( 4 مادة )')
        ->and(strpos($html, '>أ</a>'))->toBeLessThan(strpos($html, '>ب</a>'))
        ->and($html)->toContain('>مخفي</a>')->toContain('>شارد</a>')->not->toContain('>في المجموعة فقط</a>');
});

// ─── 7. the detach icon ──────────────────────────────────────────────────────

it('per-item detach takes the item out of the series only: series location recount, count - 1 (OLD UpKhotab)', function () {
    H::insert('nuke_islamic_series_location', ['series_id' => $this->series, 'location_id' => 5, 'count' => 0]);

    $this->withSession(H::session(H::admin(['khotab.moveitem', ...H::BOTH_SECTIONS])))
        ->post(route('admin.khotab.items.detach', $this->a), ['return_to' => $this->url])
        ->assertRedirect($this->url)->assertSessionHas('contextual_admin_success', "تم نقل المادة '' أ '' خارج السلسلة الحالية");

    expect((int) srow('nuke_islamic_khotab', $this->a, 'ser_id'))->toBe(0)
        ->and((int) srow('nuke_islamic_khotab', $this->a, 'group_id'))->toBe($this->group)
        ->and((int) srow('nuke_islamic_series', $this->series, 'count'))->toBe(2)
        ->and((int) srow('nuke_islamic_groups', $this->group, 'count'))->toBe(4)
        // OLD recounted before moving the item, so the item is still counted.
        ->and(DB::connection('main')->table('nuke_islamic_series_location')->where('series_id', $this->series)->value('count'))->toBe(1)
        ->and(seriesAudit())->toBe(['detach_item']);
});

// ─── the existing controls ───────────────────────────────────────────────────

it('keeps the migrated «اضف للكل» / «امسح من الكل» working from the same page', function () {
    $session = H::session(H::admin(['khotab.addcat']));

    $this->withSession($session)->post(route('admin.categories.khotab.series.add-all', $this->series), ['category_id' => 2, 'return_to' => $this->url])
        ->assertRedirect($this->url);
    expect(srow('nuke_islamic_khotab', $this->a, 'cat'))->toBe('|1|9|2|');

    $this->withSession($session)->post(route('admin.categories.khotab.series.remove-all', $this->series), ['category_id' => 2, 'return_to' => $this->url])
        ->assertRedirect($this->url);
    expect(srow('nuke_islamic_khotab', $this->a, 'cat'))->toBe('|1|9|');
});

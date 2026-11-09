<?php

/*
 * OLD public admin parity — the Khotab group page (owner, 2026-10-21):
 * «لوحة تحكم المجموعة» (OLD group_cp.htm) and the icons beside each series
 * and item, reproduced from production OLD (= the old-project working copy).
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
    H::insert('nuke_islamic_locations', ['id' => 5, 'title' => 'مسجد']);
    H::category(1, 'العقيدة');
    H::category(2, 'الفقه');
    H::category(9, 'غير تابع');

    $this->group = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'مجموعة', 'hidden' => 0, 'vedio' => 1, 'cat' => '|1|2|', 'channel_id' => 3, 'count' => 2]);
    $this->series = H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $this->group, 'title' => 'سلسلة', 'hidden' => 0, 'vedio' => 1, 'count' => 1]);
    $this->a = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'أ', 'vedio' => 1, 'group_id' => $this->group, 'ser_id' => 0, 'weight' => 3, 'cat' => '|1|9|', 'location_id' => 5, 'channel_id' => 3]);
    $this->b = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'ب', 'vedio' => 1, 'group_id' => $this->group, 'ser_id' => 0, 'weight' => 1, 'cat' => '|2|', 'channel_id' => 3]);
    $this->hidden = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مخفي', 'vedio' => 1, 'group_id' => $this->group, 'ser_id' => 0, 'weight' => 2, 'hidden' => 1, 'channel_id' => 3]);
    $this->inSeries = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'ضمن سلسلة', 'vedio' => 1, 'group_id' => $this->group, 'ser_id' => $this->series, 'channel_id' => 3]);
    $this->other = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'خارج', 'vedio' => 1, 'group_id' => 0, 'ser_id' => 0]);

    $this->url = "/khotab-group-{$this->group}.htm";
    $this->boss = H::superAdmin();
});

function grp(string $table, int $id, string $column)
{
    return DB::connection('main')->table($table)->where('id', $id)->value($column);
}

function auditActions(): array
{
    return DB::connection('main')->table('admin_audit_log')->orderBy('id')->pluck('action')->all();
}

// ─── the public page ────────────────────────────────────────────────────────

it('shows a visitor nothing at all', function () {
    $html = $this->get($this->url)->assertOk()->getContent();

    expect($html)->not->toContain('data-contextual-admin')->not->toContain('لوحة تحكم المجموعة')->not->toContain('/admincp/');
});

it('shows a super-admin OLD\'s full panel, in OLD\'s order, plus the existing add / remove-all', function () {
    $html = $this->withSession(H::session($this->boss))->get($this->url)->assertOk()->getContent();

    expect($html)->toContain('لوحة تحكم المجموعة');

    $positions = [];
    foreach (['إعادة ترتيب', 'التصنيف السريع', 'تعديل المجموعة', 'إضافة مادة', 'إضافة سلسلة', 'حذف المجموعة', 'مواد لم تصنف'] as $label) {
        $positions[] = strpos($html, '</i> '.$label.'</a>');
    }
    expect($positions)->not->toContain(false);
    $sorted = $positions;
    sort($sorted);
    expect($positions)->toBe($sorted);

    expect($html)
        ->toContain(route('admin.khotab.groups.rearrange', $this->group))
        ->toContain(route('admin.khotab.groups.fastcopy', $this->group))
        ->toContain(route('admin.khotab.groups.edit', $this->group))
        ->toContain(route('admin.khotab.groups.items.create', $this->group))
        ->toContain(route('admin.khotab.groups.series.create', $this->group))
        ->toContain(route('admin.khotab.groups.delete', $this->group))
        ->toContain(route('admin.khotab.groups.items', $this->group))
        ->toContain('action="'.route('admin.categories.khotab.group.add-all', $this->group).'"')
        ->toContain('action="'.route('admin.categories.khotab.group.remove-all', $this->group).'"')
        ->toContain('action="'.route('admin.khotab.series.detach', $this->series).'"')
        ->toContain('action="'.route('admin.khotab.items.detach', $this->a).'"')
        ->toContain('action="'.route('admin.khotab.items.detach', $this->b).'"');
});

it('shows each admin only the controls their permissions reach; «مواد لم تصنف» is open to every admin, as in OLD', function () {
    $plain = $this->withSession(H::session(H::admin([])))->get($this->url)->assertOk()->getContent();
    expect($plain)->toContain(route('admin.khotab.groups.items', $this->group))
        ->not->toContain(route('admin.khotab.groups.edit', $this->group))
        ->not->toContain(route('admin.khotab.groups.delete', $this->group))
        ->not->toContain('admin.khotab.items.detach');

    $editor = $this->withSession(H::session(H::admin(['khotab.editgroup', 'khotab.moveitem'], 'ed')))->get($this->url)->getContent();
    expect($editor)->toContain(route('admin.khotab.groups.edit', $this->group))
        ->toContain('action="'.route('admin.khotab.items.detach', $this->a).'"')
        ->toContain('action="'.route('admin.khotab.series.detach', $this->series).'"')
        ->not->toContain(route('admin.khotab.groups.delete', $this->group))
        ->not->toContain(route('admin.khotab.groups.rearrange', $this->group));
});

it('refuses every mutation without its permission (403) and without a session (401)', function () {
    $routes = [
        ['post', route('admin.khotab.groups.rearrange.save', $this->group)],
        ['post', route('admin.khotab.groups.fastcopy.save', $this->group)],
        ['put', route('admin.khotab.groups.update', $this->group)],
        ['delete', route('admin.khotab.groups.destroy', $this->group)],
        ['post', route('admin.khotab.groups.series.store', $this->group)],
        ['post', route('admin.khotab.groups.items.store', $this->group)],
        ['post', route('admin.khotab.series.detach', $this->series)],
        ['post', route('admin.khotab.items.detach', $this->a)],
    ];

    foreach ($routes as [$method, $url]) {
        $this->{$method}($url)->assertUnauthorized();
    }

    $session = H::session(H::admin(['khotab.addmirror']));
    foreach ($routes as [$method, $url]) {
        $this->withSession($session)->{$method}($url)->assertForbidden();
    }

    expect(DB::connection('main')->table('nuke_islamic_groups')->where('id', $this->group)->exists())->toBeTrue()
        ->and(auditActions())->toBe([]);
});

// ─── 1. إعادة ترتيب ──────────────────────────────────────────────────────────

it('re-arrange lists the direct items (hidden included) by weight DESC, numbered N..1', function () {
    $html = $this->withSession(H::session($this->boss))->get(route('admin.khotab.groups.rearrange', $this->group))->assertOk()->getContent();

    expect(strpos($html, '>&nbsp;أ<'))->toBeLessThan(strpos($html, '>&nbsp;مخفي<'))
        ->and(strpos($html, '>&nbsp;مخفي<'))->toBeLessThan(strpos($html, '>&nbsp;ب<'))
        ->and($html)->not->toContain('ضمن سلسلة')->not->toContain('خارج')
        ->toContain('name="Khotab['.$this->a.']" size="3" dir="ltr" value="3"')
        ->toContain('name="Khotab['.$this->b.']" size="3" dir="ltr" value="1"');
});

it('re-arrange saves weights 1..N in asort order of the submitted numbers, only on the group\'s direct items', function () {
    $this->withSession(H::session($this->boss))->post(route('admin.khotab.groups.rearrange.save', $this->group), [
        'Khotab' => [$this->a => '5', $this->b => '9', $this->hidden => '1', $this->inSeries => '0'],
    ])->assertRedirect($this->url);

    // asort: inSeries(0) → 1 (but not a direct item: untouched), hidden(1) → 2, a(5) → 3, b(9) → 4
    expect((int) grp('nuke_islamic_khotab', $this->hidden, 'weight'))->toBe(2)
        ->and((int) grp('nuke_islamic_khotab', $this->a, 'weight'))->toBe(3)
        ->and((int) grp('nuke_islamic_khotab', $this->b, 'weight'))->toBe(4)
        ->and((int) grp('nuke_islamic_khotab', $this->inSeries, 'weight'))->toBe(0)
        ->and(auditActions())->toBe(['rearrange_item']);
});

// ─── 2. التصنيف السريع ───────────────────────────────────────────────────────

it('fast-copy shows the group\'s categories as columns and the direct items as rows', function () {
    $html = $this->withSession(H::session($this->boss))->get(route('admin.khotab.groups.fastcopy', $this->group))->assertOk()->getContent();

    expect($html)->toContain('<p class="css-vertical-text">العقيدة</p>')->toContain('<p class="css-vertical-text">الفقه</p>')
        ->not->toContain('<p class="css-vertical-text">غير تابع</p>')
        ->toContain('name="Khotab['.$this->a.'][1]" value="1" checked')
        ->toContain('name="Khotab['.$this->a.'][2]" value="2" >')
        ->not->toContain('ضمن سلسلة');
});

it('fast-copy save replaces each list with the ticked group categories (dropping the rest), touches no counter, repairs the group', function () {
    $counters = DB::connection('main')->table('nuke_w2a_cat')->orderBy('id')->pluck('video_count', 'id')->all();

    $this->withSession(H::session($this->boss))->post(route('admin.khotab.groups.fastcopy.save', $this->group), [
        'Khotab' => [$this->a => ['2' => '2'], $this->hidden => ['1' => '1', '9' => '9']],
    ])->assertRedirect(route('admin.khotab.groups.fastcopy', $this->group));

    expect(grp('nuke_islamic_khotab', $this->a, 'cat'))->toBe('|2|')          // 1 un-ticked, 9 (not a group column) dropped
        ->and(grp('nuke_islamic_khotab', $this->b, 'cat'))->toBe('')          // nothing ticked: emptied (OLD wrote '|')
        ->and(grp('nuke_islamic_khotab', $this->hidden, 'cat'))->toBe('|1|')  // 9 is not one of the group's categories
        ->and(grp('nuke_islamic_khotab', $this->inSeries, 'cat'))->toBeNull()
        ->and(DB::connection('main')->table('nuke_w2a_cat')->orderBy('id')->pluck('video_count', 'id')->all())->toBe($counters)
        // The group still holds |1|2| through its items (a: 2, hidden: 1): the set is unchanged, so no repair write.
        ->and(grp('nuke_islamic_groups', $this->group, 'cat'))->toBe('|1|2|')
        ->and(auditActions())->toBe(['fast_copy', 'fast_copy', 'fast_copy']);
});

it('fast-copy repairs the group when its items no longer carry one of its categories (OLD cat_repair_group)', function () {
    $this->withSession(H::session($this->boss))->post(route('admin.khotab.groups.fastcopy.save', $this->group), [
        'Khotab' => [$this->a => ['1' => '1'], $this->b => ['1' => '1']],
    ]);

    expect(grp('nuke_islamic_groups', $this->group, 'cat'))->toBe('|1|')
        ->and(auditActions())->toBe(['fast_copy', 'fast_copy', 'repair_cat_group']);
});

// ─── 3. تعديل المجموعة ───────────────────────────────────────────────────────

it('edit group writes OLD\'s columns and cascades the channel to the group\'s series and items', function () {
    H::insert('nuke_sat_channels', ['id' => 4, 'title' => 'أخرى']);

    $this->withSession(H::session($this->boss))->put(route('admin.khotab.groups.update', $this->group), [
        'title' => 'جديد', 'description' => '<b>وصف</b>', 'meta_keywords' => 'k', 'meta_description' => 'd',
        'meta_index' => '1', 'C1' => '1', 'Channel' => '4', 'Des' => 'تعليق',
    ])->assertRedirect($this->url);

    $row = DB::connection('main')->table('nuke_islamic_groups')->find($this->group);
    expect($row->title)->toBe('جديد')->and($row->description)->toBe('<b>وصف</b>')->and((int) $row->meta_index)->toBe(1)
        ->and((int) $row->meta_follow)->toBe(0)->and((int) $row->hidden)->toBe(1)->and((int) $row->channel_id)->toBe(4)->and($row->des)->toBe('تعليق')
        ->and((int) grp('nuke_islamic_series', $this->series, 'channel_id'))->toBe(4)
        ->and((int) grp('nuke_islamic_khotab', $this->inSeries, 'channel_id'))->toBe(4)
        ->and((int) grp('nuke_islamic_khotab', $this->a, 'channel_id'))->toBe(4)
        ->and(grp('nuke_islamic_khotab', $this->other, 'channel_id'))->toBeNull()
        ->and(auditActions())->toBe(['edit_group']);

    // Markup never reaches the audit metadata (digests only).
    expect(DB::connection('main')->table('admin_audit_log')->value('metadata'))->not->toContain('<b>');
});

it('edit group refuses an unknown channel, as OLD did', function () {
    $this->withSession(H::session($this->boss))->from(route('admin.khotab.groups.edit', $this->group))
        ->put(route('admin.khotab.groups.update', $this->group), ['title' => 'x', 'Channel' => '99'])
        ->assertSessionHasErrors('Channel');

    expect(grp('nuke_islamic_groups', $this->group, 'title'))->toBe('مجموعة');
});

// ─── 4. إضافة مادة ───────────────────────────────────────────────────────────

it('add item inserts OLD\'s row, counters, weight and locations, with channel 0 as OLD stored it', function () {
    $this->withSession(H::session($this->boss))->post(route('admin.khotab.groups.items.store', $this->group), [
        'link' => ' https://example.test/a.mp4 ', 'title' => 'درس جديد', 'size' => '2.5', 'MoreInfo' => 'n',
        'Location' => '5', 'newslist' => '1', 'fixed' => 'on', 'meta_index' => '1', 'meta_follow' => '1',
    ])->assertRedirect($this->url);

    $item = DB::connection('main')->table('nuke_islamic_khotab')->orderByDesc('id')->first();
    expect($item->title)->toBe('درس جديد')->and($item->link)->toBe('https://example.test/a.mp4')
        ->and((int) $item->linksize)->toBe((int) (1024 * 1024 * 2.5))->and((int) $item->group_id)->toBe($this->group)
        ->and((int) $item->ser_id)->toBe(0)->and((int) $item->vedio)->toBe(1)->and((int) $item->down)->toBe(2)
        ->and((int) $item->channel_id)->toBe(0)->and((int) $item->newslist)->toBe(1)->and((int) $item->fixed)->toBe(1)
        ->and((int) $item->weight)->toBe((int) $item->id)->and((int) $item->lastmirror)->toBe((int) $item->time)
        ->and((int) $item->location_id)->toBe(5)
        ->and((int) grp('nuke_islamic_authors', 7, 'vedio'))->toBe(11)->and((int) grp('nuke_islamic_authors', 7, 'audio'))->toBe(4)
        ->and((int) grp('nuke_islamic_groups', $this->group, 'count'))->toBe(3)
        ->and((int) grp('nuke_islamic_groups', $this->group, 'lastupdate'))->toBe((int) $item->time)
        ->and(DB::connection('main')->table('nuke_islamic_authors_location')->where('author_id', 7)->where('location_id', 5)->value('count'))->toBe(2)
        ->and(DB::connection('main')->table('nuke_islamic_groups_location')->where('group_id', $this->group)->where('location_id', 5)->value('count'))->toBe(2)
        ->and(auditActions())->toBe(['add_item']);
});

it('add item uses archive.org\'s exact size, downloads the GIF and frame, and ignores a non-archive.org image URL', function () {
    $root = sys_get_temp_dir().'/w2a-media-'.uniqid();
    config(['admin_khotab.media_root' => $root]);
    Http::fake([
        'ia800.us.archive.org/*' => Http::response('IMG', 200),
        '*' => Http::response('NO', 200),
    ]);

    $this->withSession(H::session($this->boss))->post(route('admin.khotab.groups.items.store', $this->group), [
        'link' => 'https://archive.org/download/x/a.mp4', 'title' => 'درس', 'original_size' => '123456',
        'archive_gif' => 'https://ia800.us.archive.org/x/a.gif', 'archive_frame' => 'https://evil.test/a.jpg',
    ])->assertRedirect($this->url);

    $item = DB::connection('main')->table('nuke_islamic_khotab')->orderByDesc('id')->first();
    $bucket = intdiv((int) $item->id, 1000);
    expect((int) $item->linksize)->toBe(123456)->and((int) $item->gif)->toBe(1)->and((int) $item->frame)->toBe(0)
        ->and(file_get_contents("{$root}/khotab_gifs/{$bucket}/{$item->id}.gif"))->toBe('IMG')
        ->and(file_exists("{$root}/khotab_frames/{$bucket}/{$item->id}.jpg"))->toBeFalse();
});

it('add item applies OLD\'s refusals: byte length of the title, and a link the author already uses', function () {
    H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'قديم', 'link' => 'https://dup.test/x', 'vedio' => 1]);
    $session = H::session($this->boss);
    $before = DB::connection('main')->table('nuke_islamic_khotab')->count();

    $this->withSession($session)->post(route('admin.khotab.groups.items.store', $this->group), ['link' => 'l', 'title' => 'ab'])->assertSessionHasErrors('title');
    $this->withSession($session)->post(route('admin.khotab.groups.items.store', $this->group), ['link' => 'l', 'title' => str_repeat('م', 126)])->assertSessionHasErrors('title'); // 252 bytes
    $this->withSession($session)->post(route('admin.khotab.groups.items.store', $this->group), ['link' => 'https://dup.test/x', 'title' => 'عنوان'])->assertSessionHasErrors('link');

    expect(DB::connection('main')->table('nuke_islamic_khotab')->count())->toBe($before)
        ->and((int) grp('nuke_islamic_authors', 7, 'vedio'))->toBe(10)->and(auditActions())->toBe([]);
});

it('the «التالي» step reads archive.org exactly as OLD LeechImages did', function () {
    Http::fake(['www.archive.org/download/myitem/myitem_files.xml' => Http::response(
        '<files><file name="a.mp4" source="original"><format>MPEG4</format><size>2097152</size><length>75</length></file>'.
        '<file name="a.thumbs/a_000001.jpg"><format>Thumbnail</format><original>a.mp4</original></file>'.
        '<file name="a.thumbs/a_000002.jpg"><format>Thumbnail</format><original>a.mp4</original></file>'.
        '<file name="a.gif"><format>Animated GIF</format><original>a.mp4</original></file></files>', 200)]);

    $html = $this->withSession(H::session($this->boss))
        ->get(route('admin.khotab.archive', ['url' => 'https://archive.org/download/myitem/a.mp4']))->assertOk()->getContent();

    expect($html)->toContain('value="2.00 ميجا بايت"')->toContain('name="original_size"')->toContain('value="2097152"')
        ->toContain('value="01:15"')->toContain('value="MPEG4"')
        ->toContain('src="http://archive.org/download/myitem/a.thumbs/a_000002.jpg"')   // the middle thumbnail, preselected
        ->toContain('name="archive_gif" id="archive_gif"')->toContain('http://archive.org/download/myitem/a.gif');
});

// ─── 5. إضافة سلسلة ──────────────────────────────────────────────────────────

it('add series takes author, group, media type and channel from the group, as OLD\'s locked form did', function () {
    $this->withSession(H::session($this->boss))->post(route('admin.khotab.groups.series.store', $this->group), [
        'title' => 'سلسلة جديدة', 'MoreInfo' => 'تعليق', 'meta_index' => '1',
    ])->assertRedirect($this->url);

    $s = DB::connection('main')->table('nuke_islamic_series')->orderByDesc('id')->first();
    expect($s->title)->toBe('سلسلة جديدة')->and((int) $s->author_id)->toBe(7)->and((int) $s->group_id)->toBe($this->group)
        ->and((int) $s->vedio)->toBe(1)->and((int) $s->channel_id)->toBe(3)->and($s->des)->toBe('تعليق')
        ->and((int) $s->meta_index)->toBe(1)->and((int) $s->meta_follow)->toBe(0)
        ->and((int) grp('nuke_islamic_groups', $this->group, 'count'))->toBe(2)
        ->and(auditActions())->toBe(['add_series']);
});

// ─── 6. حذف المجموعة ─────────────────────────────────────────────────────────

it('delete group detaches its items and series, deletes it, and lands on the author page like OLD', function () {
    $this->withSession(H::session($this->boss))->delete(route('admin.khotab.groups.destroy', $this->group))
        ->assertRedirect('/khotab-video-7.htm');

    expect(DB::connection('main')->table('nuke_islamic_groups')->where('id', $this->group)->exists())->toBeFalse()
        ->and((int) grp('nuke_islamic_khotab', $this->a, 'group_id'))->toBe(0)
        ->and((int) grp('nuke_islamic_khotab', $this->inSeries, 'group_id'))->toBe(0)
        ->and((int) grp('nuke_islamic_khotab', $this->inSeries, 'ser_id'))->toBe($this->series)
        ->and((int) grp('nuke_islamic_series', $this->series, 'group_id'))->toBe(0)
        ->and((int) grp('nuke_islamic_authors', 7, 'vedio'))->toBe(10)
        ->and(auditActions())->toBe(['del_group']);
});

// ─── 7. مواد لم تصنف ─────────────────────────────────────────────────────────

it('«مواد لم تصنف» lists every item of the group (OLD Lists with no mode adds no filter), by id', function () {
    $html = $this->withSession(H::session(H::admin([])))->get(route('admin.khotab.groups.items', $this->group))->assertOk()->getContent();

    expect($html)->toContain('قائمة المواد ( 4 مادة )')->toContain('4 مادة')
        ->and(strpos($html, '>أ</a>'))->toBeLessThan(strpos($html, '>ب</a>'))
        ->and($html)->toContain('>مخفي</a>')->toContain('>ضمن سلسلة</a>')->not->toContain('>خارج</a>');
});

it('«مواد لم تصنف» pages at 50, as OLD', function () {
    foreach (range(1, 50) as $i) {
        H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => "مادة {$i}", 'vedio' => 1, 'group_id' => $this->group]);
    }

    $page2 = $this->withSession(H::session($this->boss))->get(route('admin.khotab.groups.items', ['group' => $this->group, 'page' => 2]))->assertOk()->getContent();

    expect($page2)->toContain('قائمة المواد ( 54 مادة )')->toContain('>مادة 50</a>')->not->toContain('>مادة 1</a>');
});

// ─── 8. / 9. the detach icons ────────────────────────────────────────────────

it('per-series detach takes the series and its items out of the group, with no counter change (OLD UpSeries)', function () {
    $this->withSession(H::session(H::admin(['khotab.moveitem'])))->post(route('admin.khotab.series.detach', $this->series))
        ->assertRedirect($this->url);

    expect((int) grp('nuke_islamic_series', $this->series, 'group_id'))->toBe(0)
        ->and((int) grp('nuke_islamic_khotab', $this->inSeries, 'group_id'))->toBe(0)
        ->and((int) grp('nuke_islamic_khotab', $this->a, 'group_id'))->toBe($this->group)
        ->and((int) grp('nuke_islamic_groups', $this->group, 'count'))->toBe(2)
        ->and(auditActions())->toBe(['detach_series']);
});

it('per-item detach takes a group item out of the group: count - 1, then the group location recount (OLD UpKhotab)', function () {
    H::insert('nuke_islamic_groups_location', ['group_id' => $this->group, 'location_id' => 5, 'count' => 1]);

    $this->withSession(H::session(H::admin(['khotab.moveitem'])))
        ->post(route('admin.khotab.items.detach', $this->a), ['return_to' => $this->url])
        ->assertRedirect($this->url)->assertSessionHas('contextual_admin_success', "تم نقل المادة '' أ '' خارج المجموعة الحالية");

    expect((int) grp('nuke_islamic_khotab', $this->a, 'group_id'))->toBe(0)
        ->and((int) grp('nuke_islamic_groups', $this->group, 'count'))->toBe(1)
        ->and(DB::connection('main')->table('nuke_islamic_groups_location')->where('group_id', $this->group)->value('count'))->toBe(0)
        ->and(auditActions())->toBe(['detach_item']);
});

it('per-item detach on a series item leaves the series first (OLD branch order)', function () {
    $this->withSession(H::session($this->boss))->post(route('admin.khotab.items.detach', $this->inSeries))->assertRedirect();

    expect((int) grp('nuke_islamic_khotab', $this->inSeries, 'ser_id'))->toBe(0)
        ->and((int) grp('nuke_islamic_khotab', $this->inSeries, 'group_id'))->toBe($this->group)
        ->and((int) grp('nuke_islamic_series', $this->series, 'count'))->toBe(0);
});

// ─── the existing controls ───────────────────────────────────────────────────

it('keeps the migrated «اضف للكل» / «امسح من الكل» working from the same page', function () {
    $session = H::session(H::admin(['khotab.addcat']));

    $this->withSession($session)->post(route('admin.categories.khotab.group.add-all', $this->group), ['category_id' => 2, 'return_to' => $this->url])
        ->assertRedirect($this->url);
    expect(grp('nuke_islamic_khotab', $this->a, 'cat'))->toBe('|1|9|2|');

    $this->withSession($session)->post(route('admin.categories.khotab.group.remove-all', $this->group), ['category_id' => 2, 'return_to' => $this->url])
        ->assertRedirect($this->url);
    expect(grp('nuke_islamic_khotab', $this->a, 'cat'))->toBe('|1|9|');
});

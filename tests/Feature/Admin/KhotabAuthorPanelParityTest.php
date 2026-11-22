<?php

/*
 * OLD public admin parity — the Khotab author page (owner, 2026-10-21):
 * «لوحة تحكم الداعية» (OLD author_cp.htm) on khotab-video-{id}.htm and
 * khotab-audio-{id}.htm, and admin visibility of hidden / empty content
 * (owner decision: admins see what OLD showed them, visitors are unchanged).
 * Reproduced from production OLD (= the old-project working copy), with the
 * owner's corrections: the counter repair touches the invoked author only,
 * and no `nuke_islamic_letters` recount (absent in production).
 */

use App\Domain\Content\Support\AutocompleteFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    $this->tmp = sys_get_temp_dir().'/w2a-author-'.uniqid();
    config(['admin_khotab.media_root' => $this->tmp.'/media', 'media.autocomplete_root' => $this->tmp.'/autocomplete', 'media.base_url' => '/media']);

    // Stored counters deliberately wrong, as on 769 / 713 / 676.
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.', 'vedio' => 40, 'audio' => 9]);
    H::insert('nuke_islamic_authors', ['id' => 8, 'name' => 'التالي', 'prename' => 'أ.', 'vedio' => 77, 'audio' => 66]);
    H::insert('nuke_sat_channels', ['id' => 3, 'title' => 'قناة']);
    H::insert('nuke_islamic_locations', ['id' => 5, 'title' => 'مسجد']);

    $g = fn (array $a) => H::insert('nuke_islamic_groups', $a + ['author_id' => 7, 'vedio' => 1, 'hidden' => 0, 'channel_id' => 0]);
    $this->group = $g(['title' => 'مجموعة ظاهرة', 'count' => 9]);
    $this->hiddenGroup = $g(['title' => 'مجموعة مخفية', 'count' => 1, 'hidden' => 1]);
    $this->emptyGroup = $g(['title' => 'مجموعة فارغة', 'count' => 0]);
    $this->audioGroup = $g(['title' => 'مجموعة صوتية', 'count' => 1, 'vedio' => 0]);
    $this->nextGroup = H::insert('nuke_islamic_groups', ['author_id' => 8, 'title' => 'للتالي', 'vedio' => 1, 'count' => 55, 'hidden' => 0]);

    $s = fn (array $a) => H::insert('nuke_islamic_series', $a + ['author_id' => 7, 'group_id' => 0, 'vedio' => 1, 'hidden' => 0, 'channel_id' => 0, 'lastupdate' => 1]);
    $this->series = $s(['title' => 'سلسلة ظاهرة', 'count' => 8, 'channel_id' => 3]);
    $this->hiddenSeries = $s(['title' => 'سلسلة مخفية', 'count' => 1, 'hidden' => 1]);
    $this->emptySeries = $s(['title' => 'سلسلة فارغة', 'count' => 0]);
    $this->groupedSeries = $s(['title' => 'سلسلة في مجموعة', 'count' => 1, 'group_id' => $this->group]);
    $this->nextSeries = H::insert('nuke_islamic_series', ['author_id' => 8, 'group_id' => 0, 'title' => 'سلسلة التالي', 'vedio' => 1, 'count' => 44]);

    $k = fn (array $a) => H::insert('nuke_islamic_khotab', $a + ['author' => 7, 'vedio' => 1, 'ser_id' => 0, 'group_id' => 0, 'hidden' => 0, 'channel_id' => 0, 'weight' => 1]);
    $this->item = $k(['title' => 'مادة فردية']);
    $this->hiddenItem = $k(['title' => 'مادة فردية مخفية', 'hidden' => 1]);
    // in the series, with no channel and a stray group: the repair gives it the series' channel and group 0
    $this->seriesItem = $k(['title' => 'في السلسلة', 'ser_id' => $this->series, 'group_id' => $this->group]);
    $this->seriesItem2 = $k(['title' => 'في السلسلة 2', 'ser_id' => $this->series]);
    $this->hiddenSeriesItem = $k(['title' => 'في المخفية', 'ser_id' => $this->hiddenSeries]);
    $this->groupItem = $k(['title' => 'في المجموعة', 'group_id' => $this->group]);
    $this->groupedSeriesItem = $k(['title' => 'في سلسلة المجموعة', 'ser_id' => $this->groupedSeries, 'group_id' => 0]);
    $this->hiddenGroupItem = $k(['title' => 'في المخفية ج', 'group_id' => $this->hiddenGroup]);
    $this->audioItem = $k(['title' => 'صوتية', 'vedio' => 0, 'group_id' => $this->audioGroup]);
    $this->nextItem = H::insert('nuke_islamic_khotab', ['author' => 8, 'title' => 'للتالي', 'vedio' => 1, 'ser_id' => $this->nextSeries, 'group_id' => 0]);

    $this->video = '/khotab-video-7.htm';
    $this->audio = '/khotab-audio-7.htm';
    $this->boss = H::superAdmin();
});

function arow(string $table, int $id, string $column)
{
    return DB::connection('main')->table($table)->where('id', $id)->value($column);
}

function authorAudit(): array
{
    return DB::connection('main')->table('admin_audit_log')->orderBy('id')->pluck('action')->all();
}

function authorRoute(string $name, string $media = 'video', int $author = 7): string
{
    return route('admin.khotab.authors.'.$name, ['author' => $author, 'media' => $media]);
}

// ─── the public page ────────────────────────────────────────────────────────

it('shows a visitor no panel and no hidden or empty row, on a public, cacheable page — as before', function () {
    $response = $this->get($this->video)->assertOk();
    // The lists only: the sidebar rankings (OLD Top10 / Gen 2 topitems) never filtered hidden items, before or after.
    $html = strstr($response->getContent(), '<aside', true);

    expect($html)->not->toContain('data-contextual-admin')->not->toContain('لوحة تحكم الداعية')->not->toContain('/admincp/')
        ->toContain('مجموعة ظاهرة')->not->toContain('مجموعة مخفية')->not->toContain('مجموعة فارغة')
        ->toContain('سلسلة ظاهرة')->not->toContain('سلسلة مخفية')->not->toContain('سلسلة فارغة')
        ->toContain('مادة فردية')->not->toContain('مادة فردية مخفية')->not->toContain('++مخفي++')
        ->and((string) $response->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('shows a super-admin OLD\'s panel in OLD\'s order on the video page, and on the audio page for audio', function () {
    foreach (['video' => $this->video, 'audio' => $this->audio] as $media => $url) {
        $html = $this->withSession(H::session($this->boss))->get($url)->assertOk()->getContent();
        expect($html)->toContain('لوحة تحكم الداعية');

        $positions = [];
        foreach (['تعديل بيانات الداعية', 'إضافة مجموعة', 'إضافة سلسلة', 'إضافة مادة', 'حذف الداعية', 'مواد لم تصنف', 'صيانة العدادات'] as $label) {
            $positions[] = strpos($html, '</i> '.$label.'</');
        }
        expect($positions)->not->toContain(false);
        $sorted = $positions;
        sort($sorted);
        expect($positions)->toBe($sorted);

        foreach (['edit', 'groups.create', 'series.create', 'items.create', 'delete', 'items'] as $name) {
            expect($html)->toContain('href="'.authorRoute($name, $media).'"');
        }
        expect($html)->toContain('action="'.authorRoute('repair-counts', $media).'"');
    }

    // The transcripts page (op pdf) had no OLD counterpart and gets no panel.
    expect($this->withSession(H::session($this->boss))->get('/khotab-pdf-7.htm')->getContent())->not->toContain('لوحة تحكم الداعية');
});

it('shows each admin only the controls their permissions reach; «مواد لم تصنف» is open to every admin', function () {
    $plain = H::contextualMarkup($this->withSession(H::session(H::admin([])))->get($this->video)->getContent());
    expect($plain)->toContain(authorRoute('items'))
        ->not->toContain(authorRoute('edit'))->not->toContain(authorRoute('repair-counts'))->not->toContain(authorRoute('delete'))
        ->not->toContain(authorRoute('groups.create'))->not->toContain(authorRoute('series.create'))->not->toContain(authorRoute('items.create'));

    $editor = H::contextualMarkup($this->withSession(H::session(H::admin(['khotab.editauthor', 'khotab.addgroup', ...H::BOTH_SECTIONS], 'ed')))->get($this->video)->getContent());
    expect($editor)->toContain(authorRoute('edit'))->toContain(authorRoute('repair-counts'))->toContain(authorRoute('groups.create'))
        ->not->toContain(authorRoute('delete'))->not->toContain(authorRoute('series.create'))->not->toContain(authorRoute('items.create'));
});

it('refuses every mutation and form without a session (401) and without its permission (403), writing nothing', function () {
    $routes = [
        ['get', 'edit'], ['put', 'update'], ['post', 'image'], ['post', 'repair-counts'],
        ['get', 'groups.create'], ['post', 'groups.store'], ['get', 'series.create'], ['post', 'series.store'],
        ['get', 'items.create'], ['post', 'items.store'], ['get', 'delete'], ['delete', 'destroy'],
    ];

    foreach ($routes as [$method, $name]) {
        $this->{$method}(authorRoute($name))->assertUnauthorized();
    }
    $this->get(authorRoute('items'))->assertUnauthorized();

    // Holds every key the earlier pages use, none of this page's.
    $session = H::session(H::admin(['khotab.editgroup', 'khotab.delgroup', 'khotab.editseries', 'khotab.delseries', 'khotab.edititem', 'khotab.delitem', 'khotab.moveitem', 'khotab.sortitems', 'khotab.addcat', 'khotab.repair']));
    foreach ($routes as [$method, $name]) {
        $this->withSession($session)->{$method}(authorRoute($name))->assertForbidden();
    }

    // The keys do not leak into each other.
    $this->withSession(H::session(H::admin(['khotab.addseries'], 'series-only')))->post(authorRoute('groups.store'), ['Title' => 'x'])->assertForbidden();
    $this->withSession(H::session(H::admin(['khotab.addgroup'], 'group-only')))->post(authorRoute('repair-counts'))->assertForbidden();

    expect((int) arow('nuke_islamic_authors', 7, 'vedio'))->toBe(40)->and(authorAudit())->toBe([]);
});

// ─── hidden / empty content (owner decision: admin parity) ──────────────────

it('shows an admin hidden and empty groups and standalone series with «++مخفي++», and hidden standalone items, privately', function () {
    $response = $this->withSession(H::session(H::admin([])))->get($this->video)->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('مجموعة ظاهرة')->toContain('مجموعة مخفية')->toContain('مجموعة فارغة')->not->toContain('مجموعة صوتية')
        ->toContain('سلسلة ظاهرة')->toContain('سلسلة مخفية')->toContain('سلسلة فارغة')->not->toContain('سلسلة في مجموعة')
        ->toContain('مادة فردية مخفية')
        ->and(substr_count($html, '++مخفي++'))->toBe(2)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

// ─── تعديل بيانات الداعية ─────────────────────────────────────────────────────

it('edit writes OLD\'s fields, regenerates the authors autocomplete list, and returns to the page it came from', function () {
    $session = H::session(H::admin(['khotab.editauthor'], 'ed'));
    $this->withSession($session)->get(authorRoute('edit', 'audio'))->assertOk()->assertSee('name="PreName"', false)->assertSee('كود البلوك');

    $this->withSession($session)->put(authorRoute('update', 'audio'), [
        'PreName' => 'الشيخ', 'Name' => 'محمد', 'Des' => 'تعليق', 'description' => 'وصف <b>x</b>', 'block' => '<script>x</script>',
        'C1' => '1', 'meta_keywords' => 'kw', 'meta_index' => '1',
    ])->assertRedirect($this->audio);

    $row = DB::connection('main')->table('nuke_islamic_authors')->where('id', 7)->first();
    expect($row->prename)->toBe('الشيخ')->and($row->name)->toBe('محمد')->and($row->des)->toBe('تعليق')->and($row->description)->toBe('وصف <b>x</b>')
        ->and($row->block)->toBe('<script>x</script>')->and((int) $row->hidden)->toBe(1)->and($row->meta_keywords)->toBe('kw')
        ->and((int) $row->meta_index)->toBe(1)->and((int) $row->meta_follow)->toBe(0)
        // OLD's format: every name followed by ",|^", ordered by name
        ->and(file_get_contents(AutocompleteFiles::path('authors.txt')))->toBe('التالي,|^محمد,|^')
        ->and(AutocompleteFiles::entries('authors.txt'))->toBe(['التالي', 'محمد', ''])
        ->and(authorAudit())->toBe(['edit_author']);
});

it('the header reads the autocomplete list from the configured directory', function () {
    AutocompleteFiles::write('authors.txt', ['أحمد', 'زيد']);

    expect($this->get($this->video)->getContent())->toContain('"1": "أحمد"')->toContain('"2": "زيد"');
});

it('image upload stores a JPG under 1 MB at OLD\'s path and refuses anything else with OLD\'s messages', function () {
    $session = H::session(H::admin(['khotab.editauthor'], 'ed'));
    $url = authorRoute('image');

    $this->withSession($session)->post($url, [])->assertSessionHasErrors(['photoimg' => 'من فضلك اختر صورة للداعية']);
    $this->withSession($session)->post($url, ['photoimg' => UploadedFile::fake()->create('a.png', 5, 'image/png')])->assertSessionHasErrors(['photoimg' => 'امتداد الملف غير صحيح']);
    $this->withSession($session)->post($url, ['photoimg' => UploadedFile::fake()->create('a.jpg', 5, 'text/plain')])->assertSessionHasErrors('photoimg');
    $this->withSession($session)->post($url, ['photoimg' => UploadedFile::fake()->create('a.jpg', 1024, 'image/jpeg')])->assertSessionHasErrors(['photoimg' => 'حجم الصورة أكبر من واحد ميجا !']);
    expect(authorAudit())->toBe([]);

    $this->withSession($session)->post($url, ['photoimg' => UploadedFile::fake()->createWithContent('a.jpg', str_repeat('j', 2048))])->assertRedirect(authorRoute('edit'));
    $file = $this->tmp.'/media/authors/0/7.jpg';
    expect(filesize($file))->toBe(2048)->and(authorAudit())->toBe(['upload_author_image']);
    $this->withSession($session)->get(authorRoute('edit'))->assertSee('/media/authors/0/7.jpg', false);
});

// ─── إضافة مجموعة / سلسلة / مادة ─────────────────────────────────────────────

it('add group inserts OLD\'s columns with count 0 and the page\'s media type, refusing a duplicate title', function () {
    $session = H::session(H::admin(['khotab.addgroup', ...H::BOTH_SECTIONS], 'g'));
    $this->withSession($session)->get(authorRoute('groups.create'))->assertOk()->assertSee('name="Channel"', false);

    $this->withSession($session)->post(authorRoute('groups.store'), ['Title' => 'مجموعة ظاهرة'])->assertSessionHasErrors('Title');
    expect(authorAudit())->toBe([]);

    $this->withSession($session)->post(authorRoute('groups.store'), ['Title' => 'جديدة', 'Channel' => 3, 'Des' => 'تعليق', 'description' => 'وصف', 'meta_index' => '1'])
        ->assertRedirect($this->video);
    $row = DB::connection('main')->table('nuke_islamic_groups')->orderByDesc('id')->first();
    expect($row->title)->toBe('جديدة')->and((int) $row->author_id)->toBe(7)->and((int) $row->vedio)->toBe(1)->and((int) $row->channel_id)->toBe(3)
        ->and((int) $row->count)->toBe(0)->and($row->des)->toBe('تعليق')->and((int) $row->meta_follow)->toBe(0);

    // The same title on the audio page is another media type: allowed, as in OLD.
    $this->withSession($session)->post(authorRoute('groups.store', 'audio'), ['Title' => 'جديدة'])->assertRedirect($this->audio);
    expect((int) DB::connection('main')->table('nuke_islamic_groups')->orderByDesc('id')->value('vedio'))->toBe(0)
        ->and(authorAudit())->toBe(['add_group', 'add_group']);

    // The new, empty group is on the page for the admin who made it.
    expect($this->withSession($session)->get($this->video)->getContent())->toContain('جديدة');
});

it('add series creates a standalone series (group 0) with the chosen channel', function () {
    $session = H::session(H::admin(['khotab.addseries', ...H::BOTH_SECTIONS], 's'));
    $this->withSession($session)->get(authorRoute('series.create', 'audio'))->assertOk()->assertSee('بدون مجموعة');

    $this->withSession($session)->post(authorRoute('series.store', 'audio'), ['title' => 'سلسلة جديدة', 'Channel' => 3, 'MoreInfo' => 'ت'])->assertRedirect($this->audio);
    $row = DB::connection('main')->table('nuke_islamic_series')->orderByDesc('id')->first();
    expect($row->title)->toBe('سلسلة جديدة')->and((int) $row->group_id)->toBe(0)->and((int) $row->author_id)->toBe(7)->and((int) $row->vedio)->toBe(0)
        ->and((int) $row->channel_id)->toBe(3)->and((int) $row->time)->toBeGreaterThan(0)->and($row->des)->toBe('ت')
        ->and(authorAudit())->toBe(['add_series']);

    expect($this->withSession($session)->get($this->audio)->getContent())->toContain('سلسلة جديدة');
});

it('add item creates a standalone item: author counter + 1, no series or group, the chosen channel, the author\'s location', function () {
    $session = H::session(H::admin(['khotab.additem', ...H::BOTH_SECTIONS], 'i'));
    $this->withSession($session)->get(authorRoute('items.create'))->assertOk()->assertSee('name="Channel"', false)->assertSee('بدون سلسلة');

    $this->withSession($session)->post(authorRoute('items.store'), ['link' => 'l', 'title' => 'ab'])->assertSessionHasErrors('title');

    $this->withSession($session)->post(authorRoute('items.store'), [
        'link' => 'https://cdn.example/new.mp4', 'title' => 'مادة جديدة', 'size' => '2', 'Channel' => 3, 'Location' => 5, 'newslist' => '1', 'MoreInfo' => 'ن',
    ])->assertRedirect($this->video);

    $row = DB::connection('main')->table('nuke_islamic_khotab')->orderByDesc('id')->first();
    expect($row->title)->toBe('مادة جديدة')->and((int) $row->ser_id)->toBe(0)->and((int) $row->group_id)->toBe(0)->and((int) $row->vedio)->toBe(1)
        ->and((int) $row->channel_id)->toBe(3)->and((int) $row->location_id)->toBe(5)->and((int) $row->linksize)->toBe(2097152)
        ->and((int) $row->down)->toBe(2)->and((int) $row->weight)->toBe((int) $row->id)
        ->and((int) arow('nuke_islamic_authors', 7, 'vedio'))->toBe(41)
        ->and((int) DB::connection('main')->table('nuke_islamic_authors_location')->where('author_id', 7)->where('location_id', 5)->value('count'))->toBe(1)
        ->and((int) arow('nuke_islamic_series', $this->series, 'count'))->toBe(8)->and((int) arow('nuke_islamic_groups', $this->group, 'count'))->toBe(9)
        ->and(authorAudit())->toBe(['add_item']);

    // OLD's duplicate-link refusal.
    $this->withSession($session)->post(authorRoute('items.store'), ['link' => 'https://cdn.example/new.mp4', 'title' => 'مكرر'])->assertSessionHasErrors('link');
});

// ─── حذف الداعية ─────────────────────────────────────────────────────────────

it('delete is refused while any item exists — a hidden one alone is enough — and otherwise removes only the author row', function () {
    $session = H::session(H::admin(['khotab.delauthor'], 'del'));
    $this->withSession($session)->get(authorRoute('delete'))->assertOk()->assertSee("هل ترغب في مسح '' د. الشيخ '' من قاعدة بيانات الموقع????", false);

    DB::connection('main')->table('nuke_islamic_khotab')->where('author', 7)->where('id', '!=', $this->hiddenItem)->delete();
    $this->withSession($session)->delete(authorRoute('destroy'))->assertOk()->assertSee('عفواً .... لن تتمكن من مسح');
    expect(DB::connection('main')->table('nuke_islamic_authors')->where('id', 7)->exists())->toBeTrue()->and(authorAudit())->toBe([]);

    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->hiddenItem)->delete();
    $this->withSession($session)->delete(authorRoute('destroy', 'audio'))->assertRedirect('/khotab-audio.htm');

    expect(DB::connection('main')->table('nuke_islamic_authors')->where('id', 7)->exists())->toBeFalse()
        // OLD deleted the author row only: groups and series stay
        ->and(DB::connection('main')->table('nuke_islamic_groups')->where('author_id', 7)->count())->toBe(4)
        ->and(DB::connection('main')->table('nuke_islamic_series')->where('author_id', 7)->count())->toBe(4)
        ->and(AutocompleteFiles::entries('authors.txt'))->toBe(['التالي', ''])
        ->and(authorAudit())->toBe(['del_author']);
});

// ─── مواد لم تصنف ───────────────────────────────────────────────────────────

it('the author list shows every item of the author in the page\'s media type, hidden included, by id', function () {
    $html = $this->withSession(H::session(H::admin([])))->get(authorRoute('items'))->assertOk()->getContent();

    expect($html)->toContain('( 8 مادة )')->toContain('مادة فردية مخفية')->toContain('في المخفية ج')->not->toContain('>صوتية<')->not->toContain('>للتالي<');
    expect(strpos($html, 'khotab-item-'.$this->item.'.htm'))->toBeLessThan(strpos($html, 'khotab-item-'.$this->hiddenGroupItem.'.htm'));

    expect($this->withSession(H::session(H::admin([], 'a2')))->get(authorRoute('items', 'audio'))->getContent())->toContain('( 1 مادة )')->toContain('صوتية');
});

// ─── صيانة العدادات ──────────────────────────────────────────────────────────

it('the counter repair repairs the invoked author exactly as OLD did — and never the next author, with no sweep', function () {
    $response = $this->withSession(H::session(H::admin(['khotab.editauthor'], 'fix')))->post(authorRoute('repair-counts'));
    $response->assertRedirect($this->video);   // one request, back to the page: no OLD-style refresh chain

    // audio / vedio: all items, hidden included
    expect((int) arow('nuke_islamic_authors', 7, 'vedio'))->toBe(8)->and((int) arow('nuke_islamic_authors', 7, 'audio'))->toBe(1)
        // series: count from all its items, its channel onto channel-less items, its group onto all items
        ->and((int) arow('nuke_islamic_series', $this->series, 'count'))->toBe(2)
        ->and((int) arow('nuke_islamic_khotab', $this->seriesItem, 'channel_id'))->toBe(3)
        ->and((int) arow('nuke_islamic_khotab', $this->seriesItem, 'group_id'))->toBe(0)
        ->and((int) arow('nuke_islamic_series', $this->emptySeries, 'count'))->toBe(0)
        ->and((int) arow('nuke_islamic_khotab', $this->groupedSeriesItem, 'group_id'))->toBe($this->group)
        // groups: count from all items in the group, after the series pass
        ->and((int) arow('nuke_islamic_groups', $this->group, 'count'))->toBe(2)
        ->and((int) arow('nuke_islamic_groups', $this->hiddenGroup, 'count'))->toBe(1)
        ->and((int) arow('nuke_islamic_groups', $this->emptyGroup, 'count'))->toBe(0)
        // nothing else
        ->and((int) arow('nuke_islamic_khotab', $this->item, 'channel_id'))->toBe(0);

    // the next author by id — which OLD would have repaired instead — is untouched
    expect((int) arow('nuke_islamic_authors', 8, 'vedio'))->toBe(77)->and((int) arow('nuke_islamic_authors', 8, 'audio'))->toBe(66)
        ->and((int) arow('nuke_islamic_series', $this->nextSeries, 'count'))->toBe(44)
        ->and((int) arow('nuke_islamic_groups', $this->nextGroup, 'count'))->toBe(55)
        ->and(authorAudit())->toBe(['repair_counts']);
});

it('the counter repair leaves pdf, docx, advfile and location counts alone', function () {
    DB::connection('main')->table('nuke_islamic_authors')->where('id', 7)->update(['pdf' => 33, 'docx' => 22, 'advfile' => 11]);
    DB::connection('main')->table('nuke_islamic_authors_location')->insert(['author_id' => 7, 'location_id' => 5, 'count' => 99]);

    $this->withSession(H::session($this->boss))->post(authorRoute('repair-counts', 'audio'))->assertRedirect($this->audio);

    $row = DB::connection('main')->table('nuke_islamic_authors')->where('id', 7)->first();
    expect((int) $row->pdf)->toBe(33)->and((int) $row->docx)->toBe(22)->and((int) $row->advfile)->toBe(11)
        ->and((int) DB::connection('main')->table('nuke_islamic_authors_location')->where('author_id', 7)->value('count'))->toBe(99);
});

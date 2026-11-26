<?php

/*
 * Multi-author material creation (owner decisions 2026-11-25). «إضافة مادة» on the author, group and series pages may
 * also create independent standalone copies for up to 10 other visible sheikhs, in one transaction, authorized per
 * destination; images are downloaded once and copied; post-commit image problems are reported for multi-author
 * submissions only. Single-author creation is unchanged. Plus the server-side additional-sheikh search.
 *
 * Isolated in-memory data (SQLite). InnoDB locking, the category triggers and real concurrency are proven separately
 * on a disposable MySQL database (docs/reports/khotab-multi-author-material-investigation.md).
 */

use App\Domain\Admin\Actions\Khotab\InsertKhotabAuthorItemAction;
use App\Domain\Admin\Actions\Khotab\KhotabAdditionalAuthorCopies;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Admin\Support\Constraints\KhotabMediaTarget;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Support\MediaPathResolver;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;

uses(RefreshDatabase::class);

const MAC_LINK = 'https://archive.org/download/shared/lecture.mp4';
const MAC_GIF = 'https://archive.org/download/shared/lecture.gif';
const MAC_FRAME = 'https://ia800.us.archive.org/shared/frame.jpg';

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

    $this->tmp = sys_get_temp_dir().'/w2a-mac-'.uniqid();
    config(['admin_khotab.media_root' => $this->tmp.'/media', 'media.base_url' => '/media']);
    Http::preventStrayRequests();

    $a = fn (int $id, string $name, int $hidden = 0) => H::insert('nuke_islamic_authors', ['id' => $id, 'name' => $name, 'prename' => '', 'vedio' => 10, 'audio' => 5, 'hidden' => $hidden]);
    $a(7, 'محمد حسان');
    $a(8, 'أحمد شاكر');
    $a(9, 'إبراهيم الدويش');
    $a(10, 'مصطفى العدوي');
    $a(11, 'داعية مخفي', 1);
    $a(12, 'فاطمة الزهراء');
    H::insert('nuke_sat_channels', ['id' => 3, 'title' => 'قناة']);
    H::insert('nuke_islamic_locations', ['id' => 5, 'title' => 'مسجد']);
    $this->group = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'مجموعة', 'vedio' => 1, 'hidden' => 0, 'channel_id' => 0, 'count' => 2]);
    $this->series = H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => 0, 'title' => 'سلسلة', 'vedio' => 1, 'hidden' => 0, 'channel_id' => 3, 'count' => 4, 'lastupdate' => 1]);
    $this->boss = H::superAdmin('mac-boss');
});

afterEach(function () {
    if (is_dir($this->tmp)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->tmp);
    }
});

/** [store URL, create URL, primary's expected group_id, ser_id, channel_id] for a context. */
function macContext($test, string $context): array
{
    return match ($context) {
        'author' => [route('admin.khotab.authors.items.store', [7, 'video']), route('admin.khotab.authors.items.create', [7, 'video']), 0, 0, 3],
        'group' => [route('admin.khotab.groups.items.store', $test->group), route('admin.khotab.groups.items.create', $test->group), $test->group, 0, 0],
        'series' => [route('admin.khotab.series.items.store', $test->series), route('admin.khotab.series.items.create', $test->series), 0, $test->series, 3],
    };
}

function macForm(array $extra = []): array
{
    return $extra + [
        'link' => MAC_LINK, 'title' => 'لقاء مشترك', 'description' => 'وصف', 'size' => '2', 'MoreInfo' => 'تعليق',
        'Location' => 5, 'Channel' => 3, 'newslist' => '1', 'fixed' => 'on', 'meta_keywords' => 'ك', 'meta_description' => 'و',
        'meta_index' => '1', 'meta_follow' => '1',
    ];
}

function macMain(): Connection
{
    return DB::connection('main');
}

/** Everything a creation can change, to prove a refusal or a rollback changed nothing. */
function macState($test): array
{
    $m = macMain();

    return [
        'items' => $m->table('nuke_islamic_khotab')->count(),
        'authors' => $m->table('nuke_islamic_authors')->orderBy('id')->get(['id', 'vedio', 'audio'])->map(fn ($r) => (array) $r)->all(),
        'group' => (int) $m->table('nuke_islamic_groups')->where('id', $test->group)->value('count'),
        'series' => (int) $m->table('nuke_islamic_series')->where('id', $test->series)->value('count'),
        'locations' => $m->table('nuke_islamic_authors_location')->count(),
        'audit' => $m->table('admin_audit_log')->count(),
    ];
}

function macFile(string $folder, int $id, string $ext): string
{
    return config('admin_khotab.media_root').'/'.substr(MediaPathResolver::path($folder, $id, $ext), strlen('media/'));
}

// ─── single-author behaviour ────────────────────────────────────────────────────────────────────────────────────

it('creates exactly one item with the existing message when no additional sheikh is posted', function (string $context) {
    [$store, , $group, $series] = macContext($this, $context);

    $this->withSession(H::session($this->boss))->post($store, macForm())
        ->assertRedirect()->assertSessionHas('contextual_admin_success', 'تمت إضافة المادة')
        ->assertSessionMissing(KhotabAdditionalAuthorCopies::IMAGE_WARNING_FLASH);

    $rows = macMain()->table('nuke_islamic_khotab')->get();
    expect($rows)->toHaveCount(1)
        ->and((int) $rows[0]->author)->toBe(7)->and((int) $rows[0]->group_id)->toBe($group)->and((int) $rows[0]->ser_id)->toBe($series)
        ->and((int) $rows[0]->newslist)->toBe(1)->and((int) $rows[0]->fixed)->toBe(1)
        ->and(macMain()->table('admin_audit_log')->count())->toBe(1)
        ->and(json_decode((string) macMain()->table('admin_audit_log')->value('metadata'), true))->not->toHaveKey('created_with');
})->with(['author', 'group', 'series']);

it('keeps the single-author duplicate-link message exactly as before', function (string $context) {
    [$store] = macContext($this, $context);
    H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'سابقة', 'vedio' => 1, 'link' => MAC_LINK, 'channel_id' => 0]);

    $this->withSession(H::session($this->boss))->post($store, macForm())
        ->assertSessionHasErrors(['link' => 'هذه الوصلة مضافة بقاعدة بيانات الموقع في المادة : #1 سابقة']);
})->with(['author', 'group', 'series']);

// ─── multi-author creation ──────────────────────────────────────────────────────────────────────────────────────

it('creates independent standalone copies for every additional sheikh, in each context', function (string $context) {
    [$store, , $group, $series, $channel] = macContext($this, $context);
    $before = macState($this);

    $response = $this->withSession(H::session($this->boss))->post($store, macForm(['additional_authors' => ['9', '8']]));

    $rows = macMain()->table('nuke_islamic_khotab')->orderBy('id')->get();
    [$primary, $copyA, $copyB] = $rows->all();
    $response->assertRedirect()->assertSessionHas('contextual_admin_success', 'تمت إضافة المادة لـ 3 دعاة (المواد: #'.$primary->id.'، #'.$copyA->id.'، #'.$copyB->id.')');

    expect($rows)->toHaveCount(3)
        ->and([(int) $primary->author, (int) $copyA->author, (int) $copyB->author])->toBe([7, 8, 9])   // copies ascending by author
        ->and([(int) $primary->group_id, (int) $primary->ser_id, (int) $primary->newslist, (int) $primary->fixed])->toBe([$group, $series, 1, 1]);

    foreach ([$copyA, $copyB] as $copy) {
        // same content
        foreach (['title', 'description', 'meta_keywords', 'meta_description', 'meta_index', 'meta_follow', 'link', 'linksize', 'notes', 'vedio', 'location_id', 'time', 'lastmirror', 'channel_id'] as $column) {
            expect((string) $copy->{$column})->toBe((string) $primary->{$column}, $column);
        }
        // own identity, outside the primary's group / series, not «new» and not pinned, normal backup state
        expect((int) $copy->channel_id)->toBe($channel)
            ->and([(int) $copy->group_id, (int) $copy->ser_id, (int) $copy->newslist, (int) $copy->fixed])->toBe([0, 0, 0, 0])
            ->and([(int) $copy->down, (int) $copy->trial, (int) $copy->booking])->toBe([2, 0, 0])
            ->and((int) $copy->weight)->toBe((int) $copy->id)
            ->and([(int) $copy->hits, (int) $copy->comments, (int) $copy->mirror])->toBe([0, 0, 0]);
    }

    $after = macState($this);
    $counter = fn (array $state, int $id) => collect($state['authors'])->firstWhere('id', $id)['vedio'];
    expect($counter($after, 7) - $counter($before, 7))->toBe(1)
        ->and($counter($after, 8) - $counter($before, 8))->toBe(1)
        ->and($counter($after, 9) - $counter($before, 9))->toBe(1)
        ->and($counter($after, 10))->toBe($counter($before, 10))
        ->and($after['group'] - $before['group'])->toBe($context === 'group' ? 1 : 0)
        ->and($after['series'] - $before['series'])->toBe($context === 'series' ? 1 : 0);

    // each author's location count, the primary's group / series location as before
    foreach ([7, 8, 9] as $author) {
        expect((int) macMain()->table('nuke_islamic_authors_location')->where('author_id', $author)->where('location_id', 5)->value('count'))->toBe(1);
    }

    // one audit row per record; copies carry the primary's id
    $audit = macMain()->table('admin_audit_log')->orderBy('id')->get();
    expect($audit->pluck('action')->all())->toBe(['add_item', 'add_item', 'add_item'])
        ->and($audit->pluck('entity_id')->map(fn ($v) => (int) $v)->all())->toBe([(int) $primary->id, (int) $copyA->id, (int) $copyB->id])
        ->and(json_decode((string) $audit[1]->metadata, true)['created_with'])->toBe((int) $primary->id)
        ->and(json_decode((string) $audit[2]->metadata, true))->toMatchArray(['created_with' => (int) $primary->id, 'author_id' => 9, 'group_id' => 0, 'series_id' => 0]);
})->with(['author', 'group', 'series']);

it('refuses invalid selections and writes nothing', function (mixed $ids, string $errorKey) {
    [$store] = macContext($this, 'author');
    $before = macState($this);

    $this->withSession(H::session($this->boss))->post($store, macForm(['additional_authors' => $ids]))->assertSessionHasErrors($errorKey);

    expect(macState($this))->toBe($before);
})->with([
    'the primary itself' => [[7], 'additional_authors'],
    'a hidden sheikh' => [[8, 11], 'additional_authors'],
    'an unknown id' => [[999], 'additional_authors'],
    'the same sheikh twice' => [[8, 8], 'additional_authors.1'],
    'not a number' => [['x'], 'additional_authors.0'],
    'not a list' => ['8', 'additional_authors'],
]);

it('enforces the central limit on the server (request and action)', function () {
    [$store] = macContext($this, 'author');
    for ($id = 20; $id < 31; $id++) {
        H::insert('nuke_islamic_authors', ['id' => $id, 'name' => 'داعية '.$id, 'prename' => '', 'hidden' => 0]);
    }
    $before = macState($this);

    $this->withSession(H::session($this->boss))->post($store, macForm(['additional_authors' => range(20, 30)]))   // 11
        ->assertSessionHasErrors(['additional_authors' => 'يمكن اختيار 10 دعاة مشاركين على الأكثر.']);
    expect(macState($this))->toBe($before);

    config(['admin_khotab.additional_authors.max' => 2]);   // one setting drives everything
    $this->withSession(H::session($this->boss))->post($store, macForm(['additional_authors' => [8, 9, 10]]))->assertSessionHasErrors('additional_authors');
    expect(macState($this))->toBe($before);

    $this->withSession(H::session($this->boss))->post($store, macForm(['additional_authors' => [8, 9]]))->assertSessionHasNoErrors();
    expect(macMain()->table('nuke_islamic_khotab')->count())->toBe(3);
});

it('refuses the whole submission when any selected sheikh already has the link, naming every conflict', function () {
    [$store] = macContext($this, 'group');
    $a = H::insert('nuke_islamic_khotab', ['author' => 8, 'title' => 'عند أحمد', 'vedio' => 1, 'link' => MAC_LINK, 'channel_id' => 0]);
    $b = H::insert('nuke_islamic_khotab', ['author' => 10, 'title' => 'عند مصطفى', 'vedio' => 0, 'link' => MAC_LINK, 'channel_id' => 0]);
    $p = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'عند محمد', 'vedio' => 1, 'link' => MAC_LINK, 'channel_id' => 0]);
    $before = macState($this);

    $this->withSession(H::session($this->boss))->post($store, macForm(['additional_authors' => [8, 9, 10]]))
        ->assertSessionHasErrors(['link' => 'هذه الوصلة مضافة بالفعل لدى: محمد حسان (المادة #'.$p.' عند محمد)، أحمد شاكر (المادة #'.$a.' عند أحمد)، مصطفى العدوي (المادة #'.$b.' عند مصطفى)']);

    expect(macState($this))->toBe($before);
});

// ─── authorization ──────────────────────────────────────────────────────────────────────────────────────────────

it('authorizes every destination on the server, in the item\'s section', function () {
    $calls = [];
    $real = app(AdminRecordAuthorizer::class);
    app()->instance(AdminRecordAuthorizer::class, new class($real, $calls) extends AdminRecordAuthorizer
    {
        public function __construct(private AdminRecordAuthorizer $real, private array &$calls) {}

        public function allows(AdminUser $admin, string $permission, ?object $record = null): bool
        {
            $this->calls[] = [$permission, $record instanceof KhotabMediaTarget ? 'media:'.$record->vedio : ($record === null ? null : $record::class)];

            return $this->real->allows($admin, $permission, $record);
        }
    });

    $sectionChecks = function () use (&$calls) {   // by reference: the spy keeps appending
        return count(array_filter($calls, fn ($c) => $c === ['khotab.additem', 'media:1']));
    };
    $store = route('admin.khotab.authors.items.store', [7, 'video']);

    $this->withSession(H::session($this->boss))->post($store, macForm())->assertSessionHasNoErrors();
    $single = $sectionChecks();
    $calls = [];
    $this->withSession(H::session($this->boss))->post($store, macForm(['link' => MAC_LINK.'?multi', 'additional_authors' => [8, 9, 10]]))->assertSessionHasNoErrors();

    expect($single)->toBeGreaterThanOrEqual(1)
        ->and($sectionChecks() - $single)->toBe(3);   // exactly one more section check per additional sheikh
});

it('refuses additional sheikhs for an admin outside the section or without the permission, writing nothing', function (array $permissions, string $media, int $status) {
    $admin = H::admin($permissions, 'mac-'.uniqid());
    $before = macState($this);

    $this->withSession(H::session($admin))->post(route('admin.khotab.authors.items.store', [7, $media]), macForm(['additional_authors' => [8, 9]]))
        ->assertStatus($status);

    expect(macState($this))->toBe($before);
})->with([
    'video-only on the audio page' => [['khotab.additem', 'khotab.media_video'], 'audio', 403],
    'audio-only on the video page' => [['khotab.additem', 'khotab.media_audio'], 'video', 403],
    'no add permission' => [['khotab.edititem', ...H::BOTH_SECTIONS], 'video', 403],
]);

it('lets a section moderator add copies in their own section (standalone and forum-linked)', function (int $uid) {
    $admin = H::admin(['khotab.additem', 'khotab.media_audio'], 'mac-'.uniqid());
    $admin->forceFill(['uid' => $uid])->save();

    $this->withSession(H::session($admin->fresh()))->post(route('admin.khotab.authors.items.store', [7, 'audio']), macForm(['additional_authors' => [8]]))
        ->assertSessionHasNoErrors()->assertRedirect();

    expect(macMain()->table('nuke_islamic_khotab')->where('vedio', 0)->pluck('author')->map(fn ($v) => (int) $v)->sort()->values()->all())->toBe([7, 8]);
})->with(['standalone' => [0], 'forum-linked' => [4242]]);

// ─── one transaction ────────────────────────────────────────────────────────────────────────────────────────────

it('rolls everything back when any copy fails: no item, counter, location count or audit row is left', function (string $context) {
    [$store] = macContext($this, $context);
    macMain()->statement("CREATE TRIGGER mac_fail BEFORE INSERT ON nuke_islamic_khotab WHEN NEW.author = 10 BEGIN SELECT RAISE(ABORT, 'forced failure'); END");
    $before = macState($this);

    $this->withSession(H::session($this->boss))->post($store, macForm(['additional_authors' => [8, 10]]))->assertStatus(500);

    expect(macState($this))->toBe($before)
        ->and(macMain()->table('nuke_islamic_groups_location')->count() + macMain()->table('nuke_islamic_series_location')->count())->toBe(0);
})->with(['author', 'group', 'series']);

it('refuses to run inside an already open transaction on main (stale snapshot guard)', function () {
    $action = app(InsertKhotabAuthorItemAction::class);
    $author = Author::query()->findOrFail(7);

    macMain()->beginTransaction();
    try {
        expect(fn () => $action->execute($this->boss, $author, 1, macForm()))->toThrow(LogicException::class);
    } finally {
        macMain()->rollBack();
    }
    expect(macMain()->table('nuke_islamic_khotab')->count())->toBe(0);
});

// ─── independence after creation ───────────────────────────────────────────────────────────────────────────────

it('keeps the copies independent: editing or deleting one leaves the others, their files and counters intact', function () {
    Http::fake(['*archive.org/*' => Http::response('IMAGE-BYTES')]);
    $this->withSession(H::session($this->boss))->post(route('admin.khotab.groups.items.store', $this->group), macForm(['additional_authors' => [8, 9], 'archive_gif' => MAC_GIF, 'archive_frame' => MAC_FRAME]));
    [$primary, $copy8, $copy9] = macMain()->table('nuke_islamic_khotab')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all();

    $this->withSession(H::session($this->boss))->put(route('admin.khotab.items.update', $copy8), ['title' => 'عنوان معدل لنسخة أحمد'])->assertRedirect();
    expect(macMain()->table('nuke_islamic_khotab')->where('id', $copy8)->value('title'))->toBe('عنوان معدل لنسخة أحمد')
        ->and(macMain()->table('nuke_islamic_khotab')->whereIn('id', [$primary, $copy9])->pluck('title')->unique()->all())->toBe(['لقاء مشترك']);

    $vedio9 = (int) macMain()->table('nuke_islamic_authors')->where('id', 9)->value('vedio');
    $vedio8 = (int) macMain()->table('nuke_islamic_authors')->where('id', 8)->value('vedio');
    $groupCount = (int) macMain()->table('nuke_islamic_groups')->where('id', $this->group)->value('count');
    $this->withSession(H::session($this->boss))->delete(route('admin.khotab.items.destroy', $copy9))->assertRedirect();

    expect(macMain()->table('nuke_islamic_khotab')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all())->toBe([$primary, $copy8])
        ->and((int) macMain()->table('nuke_islamic_authors')->where('id', 9)->value('vedio'))->toBe($vedio9 - 1)
        ->and((int) macMain()->table('nuke_islamic_authors')->where('id', 8)->value('vedio'))->toBe($vedio8)
        ->and((int) macMain()->table('nuke_islamic_groups')->where('id', $this->group)->value('count'))->toBe($groupCount);   // the copy was never in the group
    foreach ([$primary, $copy8, $copy9] as $id) {   // deletion removes no file (existing behaviour), and never another item's file
        expect(is_file(macFile('khotab_gifs', $id, 'gif')))->toBeTrue()->and(is_file(macFile('khotab_frames', $id, 'jpg')))->toBeTrue();
    }
});

// ─── images ─────────────────────────────────────────────────────────────────────────────────────────────────────

it('downloads each archive.org image once and gives every copy its own file', function (string $context) {
    Http::fake(['*archive.org/*' => Http::response('IMAGE-BYTES')]);
    [$store] = macContext($this, $context);

    $this->withSession(H::session($this->boss))->post($store, macForm(['additional_authors' => [8, 9], 'archive_gif' => MAC_GIF, 'archive_frame' => MAC_FRAME]))
        ->assertSessionMissing(KhotabAdditionalAuthorCopies::IMAGE_WARNING_FLASH);

    Http::assertSentCount(2);   // gif + frame, whatever the number of copies
    foreach (macMain()->table('nuke_islamic_khotab')->get(['id', 'gif', 'frame']) as $row) {
        expect([(int) $row->gif, (int) $row->frame])->toBe([1, 1])
            ->and(file_get_contents(macFile('khotab_gifs', (int) $row->id, 'gif')))->toBe('IMAGE-BYTES')
            ->and(is_file(macFile('khotab_frames', (int) $row->id, 'jpg')))->toBeTrue();
    }
})->with(['author', 'group', 'series']);

it('reports a failed download for every record of a multi-author submission, and stays silent for a single one', function () {
    Http::fake(['*lecture.gif' => Http::response('', 500), '*' => Http::response('FRAME')]);
    $store = route('admin.khotab.authors.items.store', [7, 'video']);

    $this->withSession(H::session($this->boss))->post($store, macForm(['archive_gif' => MAC_GIF, 'archive_frame' => MAC_FRAME]))
        ->assertSessionHas('contextual_admin_success', 'تمت إضافة المادة')->assertSessionMissing(KhotabAdditionalAuthorCopies::IMAGE_WARNING_FLASH);

    $response = $this->withSession(H::session($this->boss))->post($store, macForm(['link' => MAC_LINK.'?2', 'additional_authors' => [8], 'archive_gif' => MAC_GIF, 'archive_frame' => MAC_FRAME]));
    [$primary, $copy] = macMain()->table('nuke_islamic_khotab')->where('link', MAC_LINK.'?2')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all();

    $response->assertSessionHas('contextual_admin_success')   // the materials exist …
        ->assertSessionHas(KhotabAdditionalAuthorCopies::IMAGE_WARNING_FLASH,   // … and the image problem is said separately
            'أُضيفت المواد بنجاح، لكن تعذّر حفظ صور archive.org للمواد: #'.$primary.' (الصورة المتحركة)، #'.$copy.' (الصورة المتحركة) — استخدم «سحب الصور» من صفحة كل مادة.');
    expect(macMain()->table('nuke_islamic_khotab')->whereIn('id', [$primary, $copy])->get(['gif', 'frame'])->map(fn ($r) => [(int) $r->gif, (int) $r->frame])->all())
        ->toBe([[0, 1], [0, 1]]);
});

it('reports a failed copy only for that copy; the others keep their images', function () {
    Http::fake(['*archive.org/*' => Http::response('IMAGE-BYTES')]);
    $next = (int) macMain()->table('nuke_islamic_khotab')->max('id') + 1;   // primary = $next, copies $next+1, $next+2
    mkdir(macFile('khotab_frames', $next + 2, 'jpg'), 0775, true);          // a directory where copy #2's frame must go

    $response = $this->withSession(H::session($this->boss))->post(route('admin.khotab.series.items.store', $this->series),
        macForm(['additional_authors' => [8, 9], 'archive_gif' => MAC_GIF, 'archive_frame' => MAC_FRAME]));

    $response->assertSessionHas(KhotabAdditionalAuthorCopies::IMAGE_WARNING_FLASH, 'أُضيفت المواد بنجاح، لكن تعذّر حفظ صور archive.org للمواد: #'.($next + 2).' (الصورة الثابتة) — استخدم «سحب الصور» من صفحة كل مادة.');
    expect(macMain()->table('nuke_islamic_khotab')->orderBy('id')->get(['gif', 'frame'])->map(fn ($r) => [(int) $r->gif, (int) $r->frame])->all())
        ->toBe([[1, 1], [1, 1], [1, 0]]);
});

it('shows the image warning on the page the admin returns to', function () {
    $this->withSession([...H::session($this->boss), KhotabAdditionalAuthorCopies::IMAGE_WARNING_FLASH => 'تحذير الصور للاختبار'])
        ->get('/khotab-video-7.htm')->assertOk()->assertSee('data-khotab-image-warning', false)->assertSee('تحذير الصور للاختبار');
});

// ─── the form ───────────────────────────────────────────────────────────────────────────────────────────────────

it('renders the optional selector on all three forms without loading any author list', function (string $context) {
    [, $create] = macContext($this, $context);
    $html = (string) $this->withSession(H::session($this->boss))->get($create)->assertOk()->getContent();

    expect($html)->toContain('data-additional-authors')
        ->toContain('data-search-url="'.route('admin.khotab.author-search', ['media' => 'video']).'"')
        ->toContain('data-exclude="7"')->toContain('data-max="10"')->toContain('جارٍ الحفظ')
        ->not->toContain('أحمد شاكر')->not->toContain('إبراهيم الدويش')->not->toContain('name="additional_authors[]"');
})->with(['author', 'series']);   // the group page orders locations with MySQL's BINARY (existing code), so it is checked in the browser

it('keeps the selected sheikhs after a failed submission (visible ones only, never the primary)', function () {
    [$store, $create] = macContext($this, 'author');

    $this->withSession(H::session($this->boss))->from($create)->post($store, macForm(['title' => 'ab', 'additional_authors' => [8, 11, 7]]))
        ->assertRedirect($create);
    $html = (string) $this->get($create)->getContent();

    expect($html)->toContain('أحمد شاكر')->toContain('name="additional_authors[]" value="8"')
        ->not->toContain('داعية مخفي')->not->toContain('value="11"')->not->toContain('name="additional_authors[]" value="7"');
});

// Acceptance testing 2026-11-26: a refused submission used to lose archive.org's hidden fields, so the corrected
// resubmission created every material without images and without archive.org's size.
it('keeps the archive.org images and size of a refused submission for the resubmission', function (string $context) {
    [$store, $create] = macContext($this, $context);

    $this->withSession(H::session($this->boss))->from($create)
        ->post($store, macForm(['title' => 'ab', 'additional_authors' => [8], 'archive_gif' => MAC_GIF, 'archive_frame' => MAC_FRAME, 'original_size' => 26489512]))
        ->assertRedirect($create);
    $html = (string) $this->get($create)->getContent();

    expect($html)->toContain('name="archive_gif" value="'.e(MAC_GIF).'"')
        ->toContain('name="archive_frame" value="'.e(MAC_FRAME).'"')
        ->toContain('name="original_size" value="26489512"')
        ->toContain('data-w2a-archive-kept');
})->with(['author', 'series']);

it('renders no archive.org fields on a fresh form, and blocks «أضف» while «التالي» is reading archive.org', function (string $context) {
    [, $create] = macContext($this, $context);
    $html = (string) $this->withSession(H::session($this->boss))->get($create)->assertOk()->getContent();

    expect($html)->not->toContain('name="archive_gif"')->not->toContain('name="original_size"')
        ->toContain("new CustomEvent('w2a:archive-start')")->toContain("new CustomEvent('w2a:archive-end')")
        ->toContain("addEventListener('w2a:archive-start'")->toContain('جارٍ جلب بيانات المادة من الأرشيف…')->toContain('if (id !== lookup) { return; }')->toContain("querySelectorAll('[data-w2a-archive]')")
        ->toContain('.w2a-aa [hidden]{display:none !important}');
})->with(['author', 'series']);

it('blocks «أضف» while reading archive.org on the group page too', function () {
    // The group create page itself is rendered in the browser check (MySQL-only ordering); its script is checked here.
    $view = (string) file_get_contents(resource_path('views/admin/khotab/groups/item-create.blade.php'));

    expect($view)->toContain("new CustomEvent('w2a:archive-start')")->toContain("new CustomEvent('w2a:archive-end')")
        ->toContain('if (id !== lookup) { return; }')->toContain('controller.abort()')
        ->toContain("@include('admin.khotab._additional-authors'");
});

// ─── author search ──────────────────────────────────────────────────────────────────────────────────────────────

function macSearch($test, string $q, array $extra = [], ?AdminUser $admin = null, string $media = 'video')
{
    return $test->withSession(H::session($admin ?? $test->boss))
        ->getJson(route('admin.khotab.author-search', ['media' => $media] + ['q' => $q, 'exclude' => 7] + $extra));
}

it('finds sheikhs across Arabic spelling variants, without changing stored names', function (string $q, string $expected) {
    H::insert('nuke_islamic_authors', ['id' => 40, 'name' => 'عبد الرحمن الشيـخ', 'prename' => 'الشيخ', 'hidden' => 0]);
    H::insert('nuke_islamic_authors', ['id' => 41, 'name' => 'مُحَمَّد يعقوب', 'prename' => '', 'hidden' => 0]);

    $names = collect(macSearch($this, $q)->assertOk()->json('results'))->pluck('name')->all();

    expect($names)->toContain($expected)
        ->and(macMain()->table('nuke_islamic_authors')->where('id', 41)->value('name'))->toBe('مُحَمَّد يعقوب');
})->with([
    'alef without hamza' => ['احمد', 'أحمد شاكر'],
    'alef with hamza below' => ['ابراهيم', 'إبراهيم الدويش'],
    'ya for alef maqsura' => ['مصطفي', 'مصطفى العدوي'],
    'ha for ta marbuta' => ['فاطمه', 'فاطمة الزهراء'],
    'tatweel ignored' => ['الشيخ', 'الشيخ عبد الرحمن الشيـخ'],
    'diacritics ignored' => ['محمد يعقوب', 'مُحَمَّد يعقوب'],
]);

it('returns only visible sheikhs, never the primary, with only id and name', function () {
    H::insert('nuke_islamic_authors', ['id' => 42, 'name' => null, 'prename' => 'د.', 'hidden' => 0]);   // a NULL name never breaks the query

    $json = macSearch($this, 'محمد')->assertOk()->json();
    expect($json['results'])->toBe([])->and($json['more'])->toBeFalse();   // 7 is the primary

    $results = macSearch($this, 'داعية')->assertOk()->json('results');
    expect($results)->toBe([]);   // 11 is hidden

    $results = macSearch($this, 'شاكر')->assertOk()->json('results');
    expect($results)->toBe([['id' => 8, 'name' => 'أحمد شاكر']]);
});

it('needs two characters and treats LIKE wildcards literally', function () {
    expect(macSearch($this, 'ا')->assertOk()->json())->toBe(['results' => [], 'more' => false])
        ->and(macSearch($this, ' ـ ')->assertOk()->json('results'))->toBe([])
        ->and(macSearch($this, '%%')->assertOk()->json('results'))->toBe([])
        ->and(macSearch($this, '__')->assertOk()->json('results'))->toBe([]);
});

it('pages by 20 with a more flag', function () {
    for ($id = 100; $id < 125; $id++) {
        H::insert('nuke_islamic_authors', ['id' => $id, 'name' => sprintf('عبدالله رقم %02d', $id - 100), 'prename' => '', 'hidden' => 0]);
    }

    $first = macSearch($this, 'عبدالله')->assertOk()->json();
    $second = macSearch($this, 'عبدالله', ['page' => 2])->assertOk()->json();

    expect($first['results'])->toHaveCount(20)->and($first['more'])->toBeTrue()
        ->and($second['results'])->toHaveCount(5)->and($second['more'])->toBeFalse()
        ->and(array_intersect(array_column($first['results'], 'id'), array_column($second['results'], 'id')))->toBe([])
        ->and($first['results'][0])->toBe(['id' => 100, 'name' => 'عبدالله رقم 00']);
});

it('refuses the search without the add permission or outside the admin\'s section', function () {
    $audioOnly = H::admin(['khotab.additem', 'khotab.media_audio'], 'mac-a');
    $noAdd = H::admin(['khotab.edititem', ...H::BOTH_SECTIONS], 'mac-n');

    macSearch($this, 'احمد', [], $audioOnly, 'video')->assertForbidden();
    macSearch($this, 'احمد', [], $audioOnly, 'audio')->assertOk();
    macSearch($this, 'احمد', [], $noAdd)->assertForbidden();

    Auth::forgetGuards();
    $this->flushSession();
    $this->getJson(route('admin.khotab.author-search', ['media' => 'video', 'q' => 'احمد']))->assertStatus(401);
});

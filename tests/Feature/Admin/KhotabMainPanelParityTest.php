<?php

/*
 * OLD public admin parity — Khotab Main (owner, 2026-10-21): OLD
 * `Department2()`'s panel (admincp.htm) on khotab-video.htm and
 * khotab-audio.htm — add author, the counter sweep, the pending-comments
 * queue — and the admin's full author list. OLD's «ملفات مفقودة» had no
 * handler and is not offered. Reproduced from production OLD (= the
 * old-project working copy), with the owner's corrections: the sweep is one
 * server-side operation, add author completes (OLD died on sql_nextid), the
 * queue's pagination works (OLD's held a literal {PAGE}).
 */

use App\Domain\Admin\Actions\Khotab\RepairAllKhotabAuthorCountersAction;
use App\Domain\Admin\Actions\Khotab\RepairKhotabAuthorCountersAction;
use App\Domain\Content\Support\AutocompleteFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    config(['media.autocomplete_root' => sys_get_temp_dir().'/w2a-main-'.uniqid()]);

    // Visible to visitors on the video page / on the audio page / on neither.
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'أحمد', 'prename' => 'الشيخ', 'vedio' => 40, 'audio' => 0]);
    H::insert('nuke_islamic_authors', ['id' => 8, 'name' => 'بكر', 'prename' => 'الشيخ', 'vedio' => 0, 'audio' => 66]);
    H::insert('nuke_islamic_authors', ['id' => 9, 'name' => 'تامر', 'prename' => 'الشيخ', 'vedio' => 5, 'audio' => 5, 'hidden' => 1]);
    H::insert('nuke_islamic_authors', ['id' => 10, 'name' => 'ثابت', 'prename' => 'الشيخ', 'vedio' => 0, 'audio' => 0]);

    $this->item = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مادة', 'vedio' => 1, 'comments' => 1]);
    $this->item2 = H::insert('nuke_islamic_khotab', ['author' => 8, 'title' => 'مادة ثانية', 'vedio' => 0, 'comments' => 0]);
    H::insert('nuke_islamic_comments', ['khid' => $this->item, 'uname' => 'منشور', 'comment' => 'قديم', 'view' => 1, 'mytime' => 1]);
    $this->p1 = H::insert('nuke_islamic_comments', ['khid' => $this->item, 'uname' => 'زائر1', 'uemail' => 'a@x.test', 'comment' => 'أول', 'view' => 0, 'mytime' => 2]);
    $this->p2 = H::insert('nuke_islamic_comments', ['khid' => $this->item, 'uname' => 'زائر2', 'comment' => 'ثان', 'view' => 0, 'mytime' => 3]);
    $this->p3 = H::insert('nuke_islamic_comments', ['khid' => $this->item2, 'uname' => 'زائر3', 'comment' => 'ثالث', 'view' => 0, 'mytime' => 4]);

    $this->boss = H::superAdmin();
});

function mrow(string $table, int $id, string $column)
{
    return DB::connection('main')->table($table)->where('id', $id)->value($column);
}

function mainAudit(): array
{
    return DB::connection('main')->table('admin_audit_log')->orderBy('id')->pluck('action')->all();
}

function mainRoute(string $name, string $media = 'video', array $extra = []): string
{
    return route('admin.khotab.main.'.$name, ['media' => $media] + $extra);
}

// ─── the Main pages ─────────────────────────────────────────────────────────

it('shows a visitor no panel and exactly today\'s filtered author list, cacheably', function () {
    $video = $this->get('/khotab-video.htm')->assertOk();
    $audio = $this->get('/khotab-audio.htm')->assertOk();

    expect($video->getContent())->not->toContain('data-contextual-admin')->not->toContain('/admincp/')
        ->toContain('khotab-video-7.htm')->not->toContain('khotab-video-8.htm')->not->toContain('khotab-video-9.htm')->not->toContain('khotab-video-10.htm')
        ->and($audio->getContent())->toContain('khotab-audio-8.htm')->not->toContain('khotab-audio-7.htm')->not->toContain('khotab-audio-9.htm')
        ->and((string) $video->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('shows a super-admin OLD\'s panel in OLD\'s order with the live pending count, on the video and the audio page', function () {
    foreach (['video', 'audio'] as $media) {
        $html = $this->withSession(H::session($this->boss))->get("/khotab-{$media}.htm")->assertOk()->getContent();

        $positions = [];
        foreach (['أضـــف شـــيـــخ', 'إصــــلاح الـــعــدادات', 'تعليقات تنتظر النشر (3)'] as $label) {
            $positions[] = strpos($html, '</i> '.$label.'</');
        }
        expect($positions)->not->toContain(false);
        $sorted = $positions;
        sort($sorted);
        expect($positions)->toBe($sorted)
            ->and($html)->toContain('href="'.mainRoute('authors.create', $media).'"')
            ->toContain('action="'.mainRoute('repair-counts', $media).'"')
            ->toContain('href="'.mainRoute('comments', $media).'"')
            ->not->toContain('ملفات مفقودة')->not->toContain('ListDown');
    }

    DB::connection('main')->table('nuke_islamic_comments')->where('id', $this->p1)->update(['view' => 1]);
    expect($this->withSession(H::session($this->boss))->get('/khotab-video.htm')->getContent())->toContain('تعليقات تنتظر النشر (2)');

    // No panel on the transcripts directory (no OLD counterpart).
    expect($this->withSession(H::session($this->boss))->get('/khotab-pdf.htm')->getContent())->not->toContain('أضـــف شـــيـــخ');
});

it('lists every author to any admin — hidden, zero-count, other media — privately', function () {
    $response = $this->withSession(H::session(H::admin([])))->get('/khotab-video.htm')->assertOk();

    expect($response->getContent())->toContain('khotab-video-7.htm')->toContain('khotab-video-8.htm')
        ->toContain('khotab-video-9.htm')->toContain('khotab-video-10.htm')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('shows each admin only the controls their permissions reach', function () {
    expect(H::contextualMarkup($this->withSession(H::session(H::admin([])))->get('/khotab-video.htm')->getContent()))->toBe('');

    $approver = H::contextualMarkup($this->withSession(H::session(H::admin(['khotab.approvecomment'], 'ap')))->get('/khotab-video.htm')->getContent());
    expect($approver)->toContain(mainRoute('comments'))->not->toContain(mainRoute('authors.create'))->not->toContain(mainRoute('repair-counts'));

    $deleter = H::contextualMarkup($this->withSession(H::session(H::admin(['khotab.delcomment'], 'dl')))->get('/khotab-video.htm')->getContent());
    expect($deleter)->toContain(mainRoute('comments'));

    $editor = H::contextualMarkup($this->withSession(H::session(H::admin(['khotab.addauthor', 'khotab.editauthor'], 'ed')))->get('/khotab-video.htm')->getContent());
    expect($editor)->toContain(mainRoute('authors.create'))->toContain(mainRoute('repair-counts'))->not->toContain(mainRoute('comments'));
});

it('refuses every route without a session (401) and without its permission (403)', function () {
    $routes = [['get', 'authors.create'], ['post', 'authors.store'], ['post', 'repair-counts'], ['get', 'comments'], ['post', 'comments.moderate']];

    foreach ($routes as [$method, $name]) {
        $this->{$method}(mainRoute($name))->assertUnauthorized();
    }

    $session = H::session(H::admin(['khotab.edititem', 'khotab.delauthor', 'khotab.addgroup', 'khotab.repair']));
    foreach ($routes as [$method, $name]) {
        $this->withSession($session)->{$method}(mainRoute($name))->assertForbidden();
    }

    expect(mainAudit())->toBe([])->and((int) mrow('nuke_islamic_authors', 7, 'vedio'))->toBe(40);
});

// ─── أضـــف شـــيـــخ ─────────────────────────────────────────────────────────

it('add author stores OLD\'s fields with OLD\'s defaults, refreshes the autocomplete list and opens the new author in the same media', function () {
    $session = H::session(H::admin(['khotab.addauthor'], 'add'));
    $this->withSession($session)->get(mainRoute('authors.create', 'audio'))->assertOk()
        ->assertSee('value="الشيخ"', false)->assertDontSee('author_image', false)->assertDontSee('name="C1"', false);

    $response = $this->withSession($session)->post(mainRoute('authors.store', 'audio'), [
        'PreName' => 'الشيخ', 'Name' => "عبد الله O'X", 'Des' => 'تعليق', 'description' => 'وصف', 'block' => '<b>b</b>',
        'meta_keywords' => 'kw', 'meta_description' => 'md', 'meta_index' => '1', 'C1' => '1',
    ]);

    $row = DB::connection('main')->table('nuke_islamic_authors')->orderByDesc('id')->first();
    $response->assertRedirect('/khotab-audio-'.$row->id.'.htm');
    expect($row->name)->toBe("عبد الله O'X")->and($row->prename)->toBe('الشيخ')->and($row->des)->toBe('تعليق')
        ->and($row->description)->toBe('وصف')->and($row->block)->toBe('<b>b</b>')->and($row->meta_keywords)->toBe('kw')
        ->and((int) $row->meta_index)->toBe(1)->and((int) $row->meta_follow)->toBe(0)
        ->and((int) $row->hidden)->toBe(0)->and((int) $row->vedio)->toBe(0)->and((int) $row->audio)->toBe(0)
        ->and(AutocompleteFiles::entries('authors.txt'))->toContain("عبد الله O'X")
        ->and(mainAudit())->toBe(['add_author']);

    // A zero-count author: invisible to visitors, listed to admins on both Main pages.
    expect($this->withSession($session)->get('/khotab-video.htm')->getContent())->toContain('khotab-video-'.$row->id.'.htm');

    $this->withSession($session)->post(mainRoute('authors.store'), ['PreName' => 'د.', 'Name' => 'جديد'])->assertRedirect();
    expect((int) DB::connection('main')->table('nuke_islamic_authors')->orderByDesc('id')->value('id'))->toBeGreaterThan((int) $row->id);
});

it('add author refuses the same name with the same title, and allows the same name with another title', function () {
    $session = H::session(H::admin(['khotab.addauthor'], 'add'));

    $this->withSession($session)->post(mainRoute('authors.store'), ['PreName' => 'الشيخ', 'Name' => 'أحمد'])->assertSessionHasErrors('Name');
    expect(DB::connection('main')->table('nuke_islamic_authors')->where('name', 'أحمد')->count())->toBe(1)->and(mainAudit())->toBe([]);

    $this->withSession($session)->post(mainRoute('authors.store'), ['PreName' => 'د.', 'Name' => 'أحمد'])->assertRedirect();
    expect(DB::connection('main')->table('nuke_islamic_authors')->where('name', 'أحمد')->count())->toBe(2);
});

// ─── إصــــلاح الـــعــدادات ──────────────────────────────────────────────────

it('the sweep repairs every author once with the approved per-author writes, and terminates', function () {
    $main = DB::connection('main');
    // Author 7: a series with a channel and a stray group, a group with a wrong count.
    $group = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'م', 'vedio' => 1, 'count' => 50]);
    $series = H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $group, 'channel_id' => 3, 'title' => 'س', 'vedio' => 1, 'count' => 40]);
    $inSeries = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'ف', 'vedio' => 1, 'ser_id' => $series, 'group_id' => 0, 'channel_id' => 0]);
    H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مخفية', 'vedio' => 0, 'hidden' => 1]);
    $main->table('nuke_islamic_authors')->update(['pdf' => 3, 'docx' => 2, 'advfile' => 1]);

    $this->withSession(H::session(H::admin(['khotab.editauthor'], 'fix')))->post(mainRoute('repair-counts', 'audio'))
        ->assertRedirect('/khotab-audio.htm')->assertSessionHas('contextual_admin_success');

    // counters from all items, hidden included
    expect([(int) mrow('nuke_islamic_authors', 7, 'vedio'), (int) mrow('nuke_islamic_authors', 7, 'audio')])->toBe([2, 1])
        ->and([(int) mrow('nuke_islamic_authors', 8, 'vedio'), (int) mrow('nuke_islamic_authors', 8, 'audio')])->toBe([0, 1])
        ->and([(int) mrow('nuke_islamic_authors', 9, 'vedio'), (int) mrow('nuke_islamic_authors', 9, 'audio')])->toBe([0, 0])
        // series: count, channel onto channel-less items, group onto its items; group: count
        ->and((int) mrow('nuke_islamic_series', $series, 'count'))->toBe(1)
        ->and((int) mrow('nuke_islamic_khotab', $inSeries, 'channel_id'))->toBe(3)
        ->and((int) mrow('nuke_islamic_khotab', $inSeries, 'group_id'))->toBe($group)
        ->and((int) mrow('nuke_islamic_groups', $group, 'count'))->toBe(1)
        // nothing else
        ->and($main->table('nuke_islamic_authors')->where('pdf', 3)->where('docx', 2)->where('advfile', 1)->count())->toBe(4);

    // one audit row per author whose stored values changed — 7, 8, 9 (10 was already right)
    expect(DB::connection('main')->table('admin_audit_log')->where('action', 'repair_counts')->orderBy('entity_id')->pluck('entity_id')->map(fn ($v) => (int) $v)->all())->toBe([7, 8, 9]);
});

it('the sweep ends in exactly the state of the approved per-author repair run for every author in id order', function () {
    $main = DB::connection('main');
    $g7 = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'ج7', 'vedio' => 1, 'count' => 9]);
    $g8 = H::insert('nuke_islamic_groups', ['author_id' => 8, 'title' => 'ج8', 'vedio' => 0, 'count' => 0]);
    $orphanGroup = H::insert('nuke_islamic_groups', ['author_id' => 999, 'title' => 'يتيمة', 'vedio' => 1, 'count' => 77]);
    $s7 = H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $g7, 'channel_id' => 3, 'title' => 'س7', 'vedio' => 1, 'count' => 5]);
    $s8 = H::insert('nuke_islamic_series', ['author_id' => 8, 'group_id' => 0, 'channel_id' => 4, 'title' => 'س8', 'vedio' => 0, 'count' => 0]);
    $orphanSeries = H::insert('nuke_islamic_series', ['author_id' => 999, 'group_id' => $g7, 'channel_id' => 6, 'title' => 'يتيمة', 'vedio' => 1, 'count' => 88]);
    foreach ([
        ['author' => 7, 'vedio' => 1, 'ser_id' => $s7, 'group_id' => 0, 'channel_id' => 0],
        ['author' => 7, 'vedio' => 1, 'ser_id' => $s7, 'group_id' => $g8, 'channel_id' => 5],
        ['author' => 8, 'vedio' => 1, 'ser_id' => $s7, 'group_id' => 0, 'channel_id' => 0],   // another author's item in 7's series
        ['author' => 8, 'vedio' => 0, 'ser_id' => $s8, 'group_id' => $g8, 'channel_id' => 0, 'hidden' => 1],
        ['author' => 9, 'vedio' => 0, 'ser_id' => $orphanSeries, 'group_id' => 0, 'channel_id' => 0],
        ['author' => 10, 'vedio' => 1, 'ser_id' => 0, 'group_id' => $g8, 'channel_id' => 0],
        ['author' => 10, 'vedio' => 2, 'ser_id' => 0, 'group_id' => $orphanGroup, 'channel_id' => 0],
    ] as $i => $item) {
        H::insert('nuke_islamic_khotab', $item + ['title' => 'ع'.$i]);
    }

    $state = fn () => [
        'authors' => $main->table('nuke_islamic_authors')->orderBy('id')->get(['id', 'audio', 'vedio', 'pdf', 'docx', 'advfile'])->toArray(),
        'series' => $main->table('nuke_islamic_series')->orderBy('id')->get(['id', 'count'])->toArray(),
        'groups' => $main->table('nuke_islamic_groups')->orderBy('id')->get(['id', 'count'])->toArray(),
        'items' => $main->table('nuke_islamic_khotab')->orderBy('id')->get(['id', 'ser_id', 'group_id', 'channel_id'])->toArray(),
    ];

    $main->beginTransaction();
    $perAuthor = app(RepairKhotabAuthorCountersAction::class);
    foreach ($main->table('nuke_islamic_authors')->orderBy('id')->pluck('id') as $id) {
        $perAuthor->repair((int) $id);
    }
    $expected = $state();
    $main->rollBack();

    $summary = app(RepairAllKhotabAuthorCountersAction::class)->execute($this->boss);

    expect($state())->toEqual($expected)
        // every author, once; the orphan series / group (no author) left as they were
        ->and($summary['authors'])->toBe(4)->and($summary['series'])->toBe(2)->and($summary['groups'])->toBe(2)
        ->and((int) mrow('nuke_islamic_series', $orphanSeries, 'count'))->toBe(88)
        ->and((int) mrow('nuke_islamic_groups', $orphanGroup, 'count'))->toBe(77);

    // Run again: nothing left to change, and it finishes the same way.
    $again = app(RepairAllKhotabAuthorCountersAction::class)->execute($this->boss);
    expect($again['authors_changed'])->toBe(0)->and($again['series_changed'])->toBe(0)->and($again['groups_changed'])->toBe(0);
});

it('the sweep counts a group after every series has placed its items — the one corner where OLD\'s order left a stale count', function () {
    // Author 7's group holds an item of author 8's series; OLD counted 7's group before
    // repairing 8's series (which takes the item out), so OLD stored 2 where 1 is true.
    $g7 = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'ج7', 'vedio' => 1, 'count' => 9]);
    $s7 = H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $g7, 'title' => 'س7', 'vedio' => 1, 'count' => 0]);
    $s8 = H::insert('nuke_islamic_series', ['author_id' => 8, 'group_id' => 0, 'title' => 'س8', 'vedio' => 1, 'count' => 0]);
    H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'أ', 'vedio' => 1, 'ser_id' => $s7, 'group_id' => $g7]);
    H::insert('nuke_islamic_khotab', ['author' => 8, 'title' => 'ب', 'vedio' => 1, 'ser_id' => $s8, 'group_id' => $g7]);

    app(RepairAllKhotabAuthorCountersAction::class)->execute($this->boss);

    expect((int) mrow('nuke_islamic_groups', $g7, 'count'))->toBe(1);
});

// ─── تعليقات تنتظر النشر ─────────────────────────────────────────────────────

it('the queue lists every pending comment oldest first, with OLD\'s details and only the choices the admin may make', function () {
    $both = $this->withSession(H::session($this->boss))->get(mainRoute('comments'))->assertOk()->getContent();
    expect($both)->toContain('(إجمالي عدد التعليقات : 3 تعليق)')->toContain('a@x.test')->toContain('زائر1')->toContain('khotab-item-'.$this->item.'.htm')
        ->toContain('value="1" name="Comment['.$this->p1.']"')->toContain('value="2" name="Comment['.$this->p1.']"')
        ->not->toContain('قديم')
        ->and(strpos($both, 'أول'))->toBeLessThan(strpos($both, 'ثالث'));

    $approveOnly = $this->withSession(H::session(H::admin(['khotab.approvecomment'], 'ap')))->get(mainRoute('comments'))->assertOk()->getContent();
    expect($approveOnly)->toContain('نشر التعليق')->not->toContain('مسح التعليق');

    $deleteOnly = $this->withSession(H::session(H::admin(['khotab.delcomment'], 'dl')))->get(mainRoute('comments'))->assertOk()->getContent();
    expect($deleteOnly)->toContain('مسح التعليق')->not->toContain('نشر التعليق');
});

it('a mixed save publishes, hides and leaves rows as chosen, and recounts each affected item', function () {
    $this->withSession(H::session($this->boss))->post(mainRoute('comments.moderate'), [
        'Comment' => [$this->p1 => 1, $this->p2 => 0, $this->p3 => 2], 'page' => 1,
    ])->assertRedirect(mainRoute('comments', 'video', ['page' => 1]));

    expect((int) mrow('nuke_islamic_comments', $this->p1, 'view'))->toBe(1)
        ->and((int) mrow('nuke_islamic_comments', $this->p2, 'view'))->toBe(0)
        ->and((int) mrow('nuke_islamic_comments', $this->p3, 'view'))->toBe(2)
        ->and((int) mrow('nuke_islamic_khotab', $this->item, 'comments'))->toBe(2)
        ->and((int) mrow('nuke_islamic_khotab', $this->item2, 'comments'))->toBe(0)
        ->and(mainAudit())->toBe(['publish_comment', 'hide_comment']);
});

it('one permission never authorizes the other choice', function () {
    $this->withSession(H::session(H::admin(['khotab.approvecomment'], 'ap')))->post(mainRoute('comments.moderate'), ['Comment' => [$this->p1 => 2]])->assertForbidden();
    $this->withSession(H::session(H::admin(['khotab.delcomment'], 'dl')))->post(mainRoute('comments.moderate'), ['Comment' => [$this->p1 => 1, $this->p2 => 2]])->assertForbidden();
    expect((int) mrow('nuke_islamic_comments', $this->p1, 'view'))->toBe(0)->and((int) mrow('nuke_islamic_comments', $this->p2, 'view'))->toBe(0);

    $this->withSession(H::session(H::admin(['khotab.delcomment'], 'dl2')))->post(mainRoute('comments.moderate'), ['Comment' => [$this->p2 => 2]])->assertRedirect();
    $this->withSession(H::session(H::admin(['khotab.approvecomment'], 'ap2')))->post(mainRoute('comments.moderate'), ['Comment' => [$this->p1 => 1]])->assertRedirect();
    expect((int) mrow('nuke_islamic_comments', $this->p1, 'view'))->toBe(1)->and((int) mrow('nuke_islamic_comments', $this->p2, 'view'))->toBe(2);
});

it('pages beyond 30 are reachable, and an empty queue leads back to the Main page it came from', function () {
    for ($i = 0; $i < 31; $i++) {
        H::insert('nuke_islamic_comments', ['khid' => $this->item, 'uname' => 'كثير'.$i, 'comment' => 'تعليق رقم '.$i, 'view' => 0, 'mytime' => 10 + $i]);
    }
    $session = H::session($this->boss);

    $first = $this->withSession($session)->get(mainRoute('comments'))->getContent();
    expect(substr_count($first, 'data-pending-comment='))->toBe(30)->and($first)->toContain(mainRoute('comments', 'video', ['page' => 2]));
    $second = $this->withSession($session)->get(mainRoute('comments', 'video', ['page' => 2]))->getContent();
    expect(substr_count($second, 'data-pending-comment='))->toBe(4)->and($second)->toContain('تعليق رقم 30');

    DB::connection('main')->table('nuke_islamic_comments')->where('view', 0)->update(['view' => 1]);
    $this->withSession($session)->get(mainRoute('comments', 'audio'))->assertRedirect('/khotab-audio.htm');
});

it('the comment page opened from the queue returns to the queue; opened from the item, to the item', function () {
    $session = H::session($this->boss);

    $edit = $this->withSession($session)->get(route('admin.khotab.comments.edit', ['comment' => $this->p1, 'queue' => 'audio', 'page' => 1]))->assertOk()->getContent();
    expect($edit)->toContain('name="queue" value="audio"')->toContain(mainRoute('comments', 'audio', ['page' => 1]));

    $this->withSession($session)->put(route('admin.khotab.comments.publish', $this->p1), ['comment' => 'معدل', 'queue' => 'audio', 'page' => 1])
        ->assertRedirect(mainRoute('comments', 'audio', ['page' => 1]));
    $this->withSession($session)->post(route('admin.khotab.comments.hide', $this->p2), ['queue' => 'video', 'page' => 2])
        ->assertRedirect(mainRoute('comments', 'video', ['page' => 2]));

    // Item entry, and anything that is not a known media, keep the approved landing.
    $this->withSession($session)->post(route('admin.khotab.comments.hide', $this->p3), ['queue' => 'https://evil.test'])
        ->assertRedirect('/khotab-item-'.$this->item2.'.htm');
    expect($this->withSession($session)->get(route('admin.khotab.comments.edit', $this->p1))->getContent())->not->toContain('name="queue"');
});

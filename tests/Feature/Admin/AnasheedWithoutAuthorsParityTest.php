<?php

/*
 * OLD public admin parity — `anasheed_without_authors.htm` (OLD
 * `anasheed_without_authors()`, `modules/Anasheed/index.php:303-345`; owner
 * decisions 2026-10-29): an admin-only list of the Anasheed items whose
 * `author_id IS NULL`, 50 per page, newest id first, each row's «تعديل»
 * opening the existing T4 form and — as OLD's `redirct_to` did — returning to
 * the same list page after a successful save. T4 without a return is unchanged.
 */

use App\Domain\Identity\Models\VbUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'المنشد', 'prename' => 'أ.']);
    H::insert('nuke_anasheed_groups', ['id' => 158, 'title' => 'إصدارات الموقع', 'parent_id' => 0, 'anasheed' => 0]);
    $this->group = H::insert('nuke_anasheed_groups', ['title' => 'القسم', 'parent_id' => 0, 'anasheed' => 0]);
    $this->url = '/anasheed_without_authors.htm';
});

function waItem(?int $author, string $title = 'نشيد', array $extra = []): int
{
    return H::insert('nuke_anasheed_anasheed', array_merge([
        'title' => $title, 'group_id' => test()->group, 'parent_id' => test()->group, 'author_id' => $author, 'link' => 'https://x/a.mp3',
    ], $extra));
}

/** The ids the page lists, in page order. @return list<int> */
function waListed(string $html): array
{
    preg_match_all('#<td><strong><a href="/var-item-(\d+)\.htm">#', $html, $m);

    return array_map('intval', $m[1]);
}

function waEdit(array $overrides = []): array
{
    return $overrides + [
        'title' => 'نشيد معدل', 'vedio' => '0', 'fixed' => '0', 'is_published' => '1', 'Anasheed_link' => 'https://x/new.mp3',
        'Channel' => '', 'Author' => '', 'Anasheedsize' => '0.00', 'Des' => '', 'description' => '', 'meta_keywords' => '',
        'meta_description' => '', 'group' => (string) test()->group,
    ];
}

function waEditLink(int $id, string $return): string
{
    return route('admin.anasheed.items.edit', $id).'?return_to='.rawurlencode($return);
}

// ─── the list ───────────────────────────────────────────────────────────────

it('lists exactly OLD\'s author_id IS NULL items, newest id first — never author_id 0 or a sheikh, hidden or not', function () {
    $first = waItem(null);
    $hidden = waItem(null, 'مخفي', ['hidden' => 2]);
    $unpublished = waItem(null, 'غير منشور', ['is_published' => 0]);
    waItem(0, 'بدون شيخ');
    waItem(7, 'له شيخ');

    $html = $this->withSession(H::session(H::admin(['anasheed.editanasheed'], 'wa')))->get($this->url)->assertOk()->getContent();

    expect(waListed($html))->toBe([$unpublished, $hidden, $first])
        ->and($html)->toContain('قائمة بالمنوعات التي لا تندرج تحت شيخ بعينه')->toContain('إسم المادة')
        ->toContain('<th scope="col" width="10">م</th>')->not->toContain('بدون شيخ</a>')->not->toContain('له شيخ</a>');
});

it('shows 50 per page, numbers rows across pages, and uses OLD\'s page URLs', function () {
    $ids = [];
    for ($i = 1; $i <= 120; $i++) {
        $ids[] = waItem(null, "نشيد {$i}");
    }
    $newestFirst = array_reverse($ids);
    $session = H::session(H::admin(['anasheed.editanasheed'], 'wa'));

    $first = $this->withSession($session)->get($this->url)->assertOk()->getContent();
    expect(waListed($first))->toBe(array_slice($newestFirst, 0, 50))
        ->and($first)->toContain('href="'.url('/anasheed_without_authors-page-2.htm').'"')
        ->not->toContain('?page=');

    $third = $this->withSession($session)->get('/anasheed_without_authors-page-3.htm')->assertOk()->getContent();
    expect(waListed($third))->toBe(array_slice($newestFirst, 100, 20))
        ->and($third)->toContain('<td>101</td>')
        ->and($third)->toContain('href="'.url('/anasheed_without_authors.htm'));

    expect(waListed($this->withSession($session)->get('/anasheed_without_authors-page-2.htm')->getContent()))->toBe(array_slice($newestFirst, 50, 50));
    $this->withSession($session)->get('/anasheed_without_authors-page-9.htm')->assertOk()->assertSee('لا توجد مواد بدون شيخ في هذه الصفحة');
});

it('links each title to its item and «تعديل» to the T4 form with a return to the canonical list page', function () {
    $ids = [];
    for ($i = 1; $i <= 60; $i++) {
        $ids[] = waItem(null, $i === 60 ? 'نشيد <b>اختبار</b>' : "نشيد {$i}");
    }
    $session = H::session(H::admin(['anasheed.editanasheed'], 'wa'));

    $html = $this->withSession($session)->get($this->url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->getContent();
    expect($html)->toContain('<a href="/var-item-'.$ids[59].'.htm">نشيد &lt;b&gt;اختبار&lt;/b&gt;</a>')
        ->toContain('href="'.waEditLink($ids[59], '/anasheed_without_authors.htm').'"')
        ->toContain('تعديل');

    // OLD's page 1 under its numbered form still returns to the bare URL, never `-page-.htm` / `-page-1.htm`.
    expect($this->withSession($session)->get('/anasheed_without_authors-page-1.htm')->getContent())
        ->toContain('href="'.waEditLink($ids[59], '/anasheed_without_authors.htm').'"')
        ->not->toContain('return_to=%2Fanasheed_without_authors-page-');
    expect($this->withSession($session)->get('/anasheed_without_authors-page-2.htm')->getContent())
        ->toContain('href="'.waEditLink($ids[0], '/anasheed_without_authors-page-2.htm').'"');
});

it('writes nothing itself', function () {
    waItem(null);
    $before = DB::connection('main')->table('nuke_anasheed_anasheed')->get()->toArray();

    $this->withSession(H::session(H::admin(['anasheed.editanasheed'], 'wa')))->get($this->url)->assertOk();
    $this->withSession(H::session(H::admin(['anasheed.editanasheed'], 'wa2')))->get('/anasheed_without_authors-page-2.htm')->assertOk();

    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->get()->toArray())->toEqual($before)
        ->and(DB::connection('main')->table('admin_audit_log')->count())->toBe(0);
    $this->post($this->url)->assertStatus(405);
});

it('refuses a visitor or member (401) and an admin without anasheed.editanasheed (403), at both URLs', function () {
    waItem(null);
    $urls = [$this->url, '/anasheed_without_authors-page-2.htm'];

    foreach ($urls as $url) {
        $this->get($url)->assertUnauthorized();
    }
    $member = (new VbUser)->forceFill(['userid' => 55, 'username' => 'member']);
    foreach ($urls as $url) {
        $this->actingAs($member, 'vbulletin')->get($url)->assertUnauthorized();
    }
    H::visitor();

    $session = H::session(H::admin(['anasheed.addcat', 'anasheed.delanasheed', 'anasheed.addcomment', 'khotab.addcat'], 'wa-other'));
    foreach ($urls as $url) {
        $this->withSession($session)->get($url)->assertForbidden();
    }
});

it('lets a super-admin in through the existing bypass', function () {
    $id = waItem(null);

    $html = $this->withSession(H::session(H::superAdmin()))->get($this->url)->assertOk()->getContent();

    expect(waListed($html))->toBe([$id])->and($html)->toContain(route('admin.anasheed.items.edit', $id));
});

// ─── the contextual return through T4 ───────────────────────────────────────

it('carries the return into the T4 form, and a successful save lands on the bare list page', function () {
    $id = waItem(null);
    $session = H::session(H::admin(['anasheed.editanasheed'], 'wa'));

    $this->withSession($session)->get(waEditLink($id, '/anasheed_without_authors.htm'))->assertOk()
        ->assertSee('<input type="hidden" name="return_to" value="/anasheed_without_authors.htm">', false);

    $this->withSession($session)->put(route('admin.anasheed.items.update', $id), waEdit(['return_to' => '/anasheed_without_authors.htm']))
        ->assertRedirect('/anasheed_without_authors.htm')->assertSessionHas('contextual_admin_success', 'تم تعديل المادة');

    // «غير مصنف» keeps author_id NULL, so the item stays listed.
    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $id)->value('author_id'))->toBeNull()
        ->and(DB::connection('main')->table('admin_audit_log')->pluck('action')->all())->toBe(['edit_item'])
        ->and(waListed($this->withSession($session)->get($this->url)->getContent()))->toBe([$id]);
});

it('returns to page 2 after a save opened from page 2, and an assigned author takes the item off the list', function () {
    $ids = [];
    for ($i = 1; $i <= 60; $i++) {
        $ids[] = waItem(null, "نشيد {$i}");
    }
    $session = H::session(H::admin(['anasheed.editanasheed'], 'wa'));
    $onPage2 = $ids[0];

    $this->withSession($session)->put(route('admin.anasheed.items.update', $onPage2), waEdit(['Author' => '7', 'return_to' => '/anasheed_without_authors-page-2.htm']))
        ->assertRedirect('/anasheed_without_authors-page-2.htm');
    expect((int) DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $onPage2)->value('author_id'))->toBe(7);

    // «بدون شيخ» (0) is also an author choice OLD does not list.
    $this->withSession($session)->put(route('admin.anasheed.items.update', $ids[1]), waEdit(['Author' => '0', 'return_to' => '/anasheed_without_authors-page-2.htm']))
        ->assertRedirect('/anasheed_without_authors-page-2.htm');

    $page2 = waListed($this->withSession($session)->get('/anasheed_without_authors-page-2.htm')->getContent());
    expect($page2)->not->toContain($onPage2)->not->toContain($ids[1])->toBe(array_slice(array_reverse($ids), 50, 8));
});

it('keeps the approved T4 redirect to the item page without a return_to', function () {
    $id = waItem(null);
    $session = H::session(H::admin(['anasheed.editanasheed'], 'wa'));

    $this->withSession($session)->get(route('admin.anasheed.items.edit', $id))->assertOk()->assertDontSee('name="return_to"', false);

    $this->withSession($session)->put(route('admin.anasheed.items.update', $id), waEdit())
        ->assertRedirect("/var-item-{$id}.htm")->assertSessionHas('contextual_admin_success', 'تم تعديل المادة');
});

it('never redirects off-site or into AdminCP: an invalid return_to falls back to the item page', function () {
    $id = waItem(null);
    $session = H::session(H::admin(['anasheed.editanasheed'], 'wa'));

    foreach (['https://evil.example/x', '//evil.example/x', '/\\evil.example', 'javascript:alert(1)', '/admincp/anasheed', '/deploy'] as $bad) {
        $this->withSession($session)->put(route('admin.anasheed.items.update', $id), waEdit(['return_to' => $bad]))
            ->assertRedirect("/var-item-{$id}.htm");
        $this->withSession($session)->get(waEditLink($id, $bad))->assertOk()->assertDontSee('name="return_to"', false);
    }
});

it('keeps a valid return across a validation failure and writes nothing', function () {
    $id = waItem(null);
    $session = H::session(H::admin(['anasheed.editanasheed'], 'wa'));
    $edit = waEditLink($id, '/anasheed_without_authors-page-2.htm');

    $this->withSession($session)->from($edit)
        ->put(route('admin.anasheed.items.update', $id), waEdit(['title' => '', 'return_to' => '/anasheed_without_authors-page-2.htm']))
        ->assertRedirect($edit)->assertSessionHasErrors('title');

    // The redisplayed form (old input) still carries the return, even without the query string.
    $this->get(route('admin.anasheed.items.edit', $id))->assertOk()
        ->assertSee('<input type="hidden" name="return_to" value="/anasheed_without_authors-page-2.htm">', false);

    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $id)->value('title'))->toBe('نشيد')
        ->and(DB::connection('main')->table('admin_audit_log')->count())->toBe(0);
});

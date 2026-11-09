<?php

/*
 * OLD public admin parity — `anasheed_without_channels.htm` (OLD
 * `anasheed_without_channels()`, `modules/Anasheed/index.php:345-387`; owner
 * decisions 2026-10-29): an admin-only list of the Anasheed items with
 * `channel_id IS NULL OR channel_id = 0` — the rows OLD production actually
 * showed — paged over that same set (OLD's NULL-only page count not
 * reproduced), 50 per page, newest id first, each row's «تعديل» opening the
 * existing T4 form and returning to the same list page after a successful save.
 */

use App\Domain\Identity\Models\VbUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    H::insert('nuke_sat_channels', ['id' => 3, 'title' => 'قناة']);
    H::insert('nuke_anasheed_groups', ['id' => 158, 'title' => 'إصدارات الموقع', 'parent_id' => 0, 'anasheed' => 0]);
    $this->group = H::insert('nuke_anasheed_groups', ['title' => 'القسم', 'parent_id' => 0, 'anasheed' => 0]);
    $this->url = '/anasheed_without_channels.htm';
});

function wchItem(?int $channel, string $title = 'نشيد', array $extra = []): int
{
    return H::insert('nuke_anasheed_anasheed', array_merge([
        'title' => $title, 'group_id' => test()->group, 'parent_id' => test()->group, 'channel_id' => $channel, 'link' => 'https://x/a.mp3',
    ], $extra));
}

/** The ids the page lists, in page order. @return list<int> */
function wchListed(string $html): array
{
    preg_match_all('#<td><strong><a href="/var-item-(\d+)\.htm">#', $html, $m);

    return array_map('intval', $m[1]);
}

function wchEdit(array $overrides = []): array
{
    return $overrides + [
        'title' => 'نشيد معدل', 'vedio' => '0', 'fixed' => '0', 'is_published' => '1', 'Anasheed_link' => 'https://x/new.mp3',
        'Channel' => '', 'Author' => '', 'Anasheedsize' => '0.00', 'Des' => '', 'description' => '', 'meta_keywords' => '',
        'meta_description' => '', 'group' => (string) test()->group,
    ];
}

function wchEditLink(int $id, string $return): string
{
    return route('admin.anasheed.items.edit', $id).'?return_to='.rawurlencode($return);
}

function wchChannel(int $id): ?int
{
    $value = DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $id)->value('channel_id');

    return $value === null ? null : (int) $value;
}

// ─── the list ───────────────────────────────────────────────────────────────

it('lists channel_id NULL and 0 — the rows OLD production showed — newest id first, never a channel, hidden or unpublished included', function () {
    $null = wchItem(null);
    $zero = wchItem(0, 'بدون قناة');
    wchItem(3, 'له قناة');
    $hiddenNull = wchItem(null, 'مخفي', ['hidden' => 2]);
    $hiddenZero = wchItem(0, 'مخفي صفر', ['hidden' => 2]);
    $unpublishedNull = wchItem(null, 'غير منشور', ['is_published' => 0]);
    $unpublishedZero = wchItem(0, 'غير منشور صفر', ['is_published' => 0]);

    $html = $this->withSession(H::session(H::admin(['anasheed.editanasheed'], 'wch')))->get($this->url)->assertOk()->getContent();

    expect(wchListed($html))->toBe([$unpublishedZero, $unpublishedNull, $hiddenZero, $hiddenNull, $zero, $null])
        ->and($html)->toContain('قائمة بالمنوعات التي لا تندرج تحت قناة بعينها')->toContain('إسم المادة')
        ->toContain('<th scope="col" width="10">م</th>')->not->toContain('له قناة</a>');
});

it('pages the combined NULL + 0 set, 50 per page, numbering across pages, with OLD\'s page URLs', function () {
    // 70 NULL + 50 zero rows, interleaved: OLD would link only ceil(70 / 50) = 2 pages of these 120 rows.
    $ids = [];
    for ($i = 1; $i <= 120; $i++) {
        $ids[] = wchItem($i % 12 < 5 ? 0 : null, "نشيد {$i}");
    }
    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->where('channel_id', 0)->count())->toBe(50);
    $newestFirst = array_reverse($ids);
    $session = H::session(H::admin(['anasheed.editanasheed'], 'wch'));

    $first = $this->withSession($session)->get($this->url)->assertOk()->getContent();
    expect(wchListed($first))->toBe(array_slice($newestFirst, 0, 50))
        ->and($first)->toContain('href="'.url('/anasheed_without_channels-page-2.htm').'"')
        ->toContain('href="'.url('/anasheed_without_channels-page-3.htm').'"')
        ->not->toContain('?page=');

    $third = $this->withSession($session)->get('/anasheed_without_channels-page-3.htm')->assertOk()->getContent();
    expect(wchListed($third))->toBe(array_slice($newestFirst, 100, 20))
        ->and($third)->toContain('<td>101</td>')
        ->and($third)->toContain('href="'.url('/anasheed_without_channels.htm'));

    expect(wchListed($this->withSession($session)->get('/anasheed_without_channels-page-2.htm')->getContent()))->toBe(array_slice($newestFirst, 50, 50));
    $this->withSession($session)->get('/anasheed_without_channels-page-9.htm')->assertOk()->assertSee('لا توجد مواد بدون قناة في هذه الصفحة');
});

it('links each title to its item and «تعديل» to the T4 form with a return to the canonical list page', function () {
    $ids = [];
    for ($i = 1; $i <= 60; $i++) {
        $ids[] = wchItem($i % 2 === 0 ? 0 : null, $i === 60 ? 'نشيد <b>اختبار</b>' : "نشيد {$i}");
    }
    $session = H::session(H::admin(['anasheed.editanasheed'], 'wch'));

    $html = $this->withSession($session)->get($this->url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->getContent();
    expect($html)->toContain('<a href="/var-item-'.$ids[59].'.htm">نشيد &lt;b&gt;اختبار&lt;/b&gt;</a>')
        ->toContain('href="'.wchEditLink($ids[59], '/anasheed_without_channels.htm').'"')
        ->toContain('تعديل');

    // Page 1 under its numbered form still returns to the bare URL, never `-page-.htm` / `-page-1.htm`.
    expect($this->withSession($session)->get('/anasheed_without_channels-page-1.htm')->getContent())
        ->toContain('href="'.wchEditLink($ids[59], '/anasheed_without_channels.htm').'"')
        ->not->toContain('return_to=%2Fanasheed_without_channels-page-');
    expect($this->withSession($session)->get('/anasheed_without_channels-page-2.htm')->getContent())
        ->toContain('href="'.wchEditLink($ids[0], '/anasheed_without_channels-page-2.htm').'"');
});

it('writes nothing itself and accepts no POST', function () {
    wchItem(null);
    wchItem(0);
    $before = DB::connection('main')->table('nuke_anasheed_anasheed')->get()->toArray();

    $this->withSession(H::session(H::admin(['anasheed.editanasheed'], 'wch')))->get($this->url)->assertOk();
    $this->withSession(H::session(H::admin(['anasheed.editanasheed'], 'wch2')))->get('/anasheed_without_channels-page-2.htm')->assertOk();

    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->get()->toArray())->toEqual($before)
        ->and(DB::connection('main')->table('admin_audit_log')->count())->toBe(0);
    $this->post($this->url)->assertStatus(405);
    $this->post('/anasheed_without_channels-page-2.htm')->assertStatus(405);
});

it('refuses a visitor or member (401) and an admin without anasheed.editanasheed (403), at both URLs', function () {
    wchItem(null);
    $urls = [$this->url, '/anasheed_without_channels-page-2.htm'];

    foreach ($urls as $url) {
        $this->get($url)->assertUnauthorized();
    }
    $member = (new VbUser)->forceFill(['userid' => 55, 'username' => 'member']);
    foreach ($urls as $url) {
        $this->actingAs($member, 'vbulletin')->get($url)->assertUnauthorized();
    }
    H::visitor();

    $session = H::session(H::admin(['anasheed.addcat', 'anasheed.delanasheed', 'anasheed.addcomment', 'khotab.addcat'], 'wch-other'));
    foreach ($urls as $url) {
        $this->withSession($session)->get($url)->assertForbidden();
    }
});

it('lets a super-admin in through the existing bypass', function () {
    $id = wchItem(0);

    $html = $this->withSession(H::session(H::superAdmin()))->get($this->url)->assertOk()->getContent();

    expect(wchListed($html))->toBe([$id])->and($html)->toContain(route('admin.anasheed.items.edit', $id));
});

// ─── the contextual return through T4 (no T4 change) ────────────────────────

it('carries the return into the T4 form, and a save keeping channel NULL lands on the bare list with the item still listed', function () {
    $id = wchItem(null);
    $session = H::session(H::admin(['anasheed.editanasheed'], 'wch'));

    $this->withSession($session)->get(wchEditLink($id, '/anasheed_without_channels.htm'))->assertOk()
        ->assertSee('<input type="hidden" name="return_to" value="/anasheed_without_channels.htm">', false);

    $this->withSession($session)->put(route('admin.anasheed.items.update', $id), wchEdit(['return_to' => '/anasheed_without_channels.htm']))
        ->assertRedirect('/anasheed_without_channels.htm')->assertSessionHas('contextual_admin_success', 'تم تعديل المادة');

    expect(wchChannel($id))->toBeNull()
        ->and(DB::connection('main')->table('admin_audit_log')->pluck('action')->all())->toBe(['edit_item'])
        ->and(wchListed($this->withSession($session)->get($this->url)->getContent()))->toBe([$id]);
});

it('returns to page 2 from page 2; NULL ↔ 0 keeps the item listed, a positive channel removes it', function () {
    $ids = [];
    for ($i = 1; $i <= 60; $i++) {
        $ids[] = wchItem(null, "نشيد {$i}");
    }
    $zeroItem = $ids[1];
    DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $zeroItem)->update(['channel_id' => 0]);
    $session = H::session(H::admin(['anasheed.editanasheed'], 'wch'));
    $back = '/anasheed_without_channels-page-2.htm';

    // NULL → 0 («بدون قناة»).
    $this->withSession($session)->put(route('admin.anasheed.items.update', $ids[0]), wchEdit(['Channel' => '0', 'return_to' => $back]))->assertRedirect($back);
    // 0 → NULL («غير مصنف»).
    $this->withSession($session)->put(route('admin.anasheed.items.update', $zeroItem), wchEdit(['Channel' => '', 'return_to' => $back]))->assertRedirect($back);
    // NULL → a channel.
    $this->withSession($session)->put(route('admin.anasheed.items.update', $ids[2]), wchEdit(['Channel' => '3', 'return_to' => $back]))->assertRedirect($back);

    expect(wchChannel($ids[0]))->toBe(0)->and(wchChannel($zeroItem))->toBeNull()->and(wchChannel($ids[2]))->toBe(3);

    $page2 = wchListed($this->withSession($session)->get($back)->getContent());
    expect($page2)->toContain($ids[0])->toContain($zeroItem)->not->toContain($ids[2])
        ->toBe(array_values(array_diff(array_slice(array_reverse($ids), 50), [$ids[2]])));
});

it('keeps the approved T4 redirect to the item page without a return_to', function () {
    $id = wchItem(0);
    $session = H::session(H::admin(['anasheed.editanasheed'], 'wch'));

    $this->withSession($session)->get(route('admin.anasheed.items.edit', $id))->assertOk()->assertDontSee('name="return_to"', false);

    $this->withSession($session)->put(route('admin.anasheed.items.update', $id), wchEdit(['Channel' => '0']))
        ->assertRedirect("/var-item-{$id}.htm")->assertSessionHas('contextual_admin_success', 'تم تعديل المادة');
});

it('never redirects off-site or into AdminCP, and keeps T4\'s channel validation', function () {
    $id = wchItem(null);
    $session = H::session(H::admin(['anasheed.editanasheed'], 'wch'));

    foreach (['https://evil.example/x', '//evil.example/x', '/\\evil.example', 'javascript:alert(1)', '/admincp/anasheed', '/deploy'] as $bad) {
        $this->withSession($session)->put(route('admin.anasheed.items.update', $id), wchEdit(['return_to' => $bad]))
            ->assertRedirect("/var-item-{$id}.htm");
        $this->withSession($session)->get(wchEditLink($id, $bad))->assertOk()->assertDontSee('name="return_to"', false);
    }

    // A channel that does not exist is still refused (OLD did not check; T4 does), and the return survives the redisplay.
    $edit = wchEditLink($id, '/anasheed_without_channels.htm');
    $this->withSession($session)->from($edit)
        ->put(route('admin.anasheed.items.update', $id), wchEdit(['Channel' => '99', 'return_to' => '/anasheed_without_channels.htm']))
        ->assertRedirect($edit)->assertSessionHasErrors(['Channel' => 'القناة غير موجودة بقواعد البيانات']);
    $this->get(route('admin.anasheed.items.edit', $id))->assertOk()
        ->assertSee('<input type="hidden" name="return_to" value="/anasheed_without_channels.htm">', false);
    expect(wchChannel($id))->toBeNull();
});

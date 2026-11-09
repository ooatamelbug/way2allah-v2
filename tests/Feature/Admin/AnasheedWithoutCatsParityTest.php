<?php

/*
 * OLD public admin parity — `anasheed_without_cats.htm` (OLD
 * `anasheed_without_cats()`, `modules/Anasheed/index.php:259-301`; owner
 * decisions 2026-10-29): an admin-only list of the Anasheed items without a
 * category, 50 per page, newest id first, each row's «إضافة تصنيف» opening
 * the existing T7 picker and returning to the item after a successful add.
 */

use App\Domain\Identity\Models\VbUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    H::category(5, 'أناشيد');
    $this->url = '/anasheed_without_cats.htm';
});

function wcItem(?string $cat, string $title = 'نشيد', array $extra = []): int
{
    return H::insert('nuke_anasheed_anasheed', array_merge(['title' => $title, 'group_id' => 0, 'cat_id' => $cat], $extra));
}

/** The ids the page lists, in page order. @return list<int> */
function wcListed(string $html): array
{
    preg_match_all('#<td><strong><a href="/var-item-(\d+)\.htm">#', $html, $m);

    return array_map('intval', $m[1]);
}

it('lists exactly OLD\'s no-category items — cat_id NULL, \'\' or \'|\' — newest id first, hidden or not', function () {
    $null = wcItem(null);
    $empty = wcItem('');
    $pipe = wcItem('|');
    $hidden = wcItem(null, 'مخفي', ['hidden' => 2]);
    wcItem('|5|');
    wcItem('5|');
    wcItem('||');

    $html = $this->withSession(H::session(H::admin(['anasheed.addcat'], 'wc')))->get($this->url)->assertOk()->getContent();

    expect(wcListed($html))->toBe([$hidden, $pipe, $empty, $null])
        ->and($html)->toContain('قائمة بالمنوعات الغير مصنفة')->toContain('إسم المادة')->toContain('<th scope="col" width="10">م</th>');
});

it('shows 50 per page, numbers rows across pages, and uses OLD\'s page URLs', function () {
    $ids = [];
    for ($i = 1; $i <= 120; $i++) {
        $ids[] = wcItem(null, "نشيد {$i}");
    }
    $newestFirst = array_reverse($ids);
    $session = H::session(H::admin(['anasheed.addcat'], 'wc'));

    $first = $this->withSession($session)->get($this->url)->assertOk()->getContent();
    expect(wcListed($first))->toBe(array_slice($newestFirst, 0, 50))
        ->and($first)->toContain('href="'.url('/anasheed_without_cats-page-2.htm').'"')
        ->not->toContain('?page=');

    $third = $this->withSession($session)->get('/anasheed_without_cats-page-3.htm')->assertOk()->getContent();
    expect(wcListed($third))->toBe(array_slice($newestFirst, 100, 20))
        ->and($third)->toContain('<td>101</td>')
        ->and($third)->toContain('href="'.url('/anasheed_without_cats.htm'));

    // Page 2 of the legacy form is the same page as ?page=2 would be; out of range shows a message, not a blank page.
    expect(wcListed($this->withSession($session)->get('/anasheed_without_cats-page-2.htm')->getContent()))->toBe(array_slice($newestFirst, 50, 50));
    $this->withSession($session)->get('/anasheed_without_cats-page-9.htm')->assertOk()->assertSee('لا توجد مواد غير مصنفة في هذه الصفحة');
});

it('links each title to its item page and «إضافة تصنيف» to the T7 picker with a return to the item', function () {
    $id = wcItem(null, 'نشيد <b>اختبار</b>');

    $html = $this->withSession(H::session(H::admin(['anasheed.addcat'], 'wc')))->get($this->url)->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')->getContent();

    expect($html)->toContain('<a href="/var-item-'.$id.'.htm">نشيد &lt;b&gt;اختبار&lt;/b&gt;</a>')
        ->toContain('href="'.route('admin.categories.anasheed.item', $id).'?return_to=%2Fvar-item-'.$id.'.htm"')
        ->toContain('إضافة تصنيف');
});

it('carries the return into the picker, and a successful add lands on the item page with one T7 write', function () {
    $id = wcItem(null);
    $session = H::session(H::admin(['anasheed.addcat'], 'wc'));

    $this->withSession($session)->get(route('admin.categories.anasheed.item', $id).'?return_to=%2Fvar-item-'.$id.'.htm')->assertOk()
        ->assertSee('<input type="hidden" name="return_to" value="/var-item-'.$id.'.htm">', false);

    $this->withSession($session)->post(route('admin.categories.anasheed.item.add', $id), ['category_id' => 5, 'return_to' => "/var-item-{$id}.htm"])
        ->assertRedirect("/var-item-{$id}.htm");

    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $id)->value('cat_id'))->toBe('|5|')
        ->and((int) DB::connection('main')->table('nuke_w2a_cat')->where('id', 5)->value('anasheed_count'))->toBe(11)
        ->and(DB::connection('main')->table('admin_audit_log')->pluck('action')->all())->toBe(['add_cat_item']);

    // The item now has a category, so it leaves the list.
    expect(wcListed($this->withSession($session)->get($this->url)->getContent()))->toBe([]);
});

it('writes nothing itself', function () {
    wcItem(null);
    $before = DB::connection('main')->table('nuke_anasheed_anasheed')->get()->toArray();

    $this->withSession(H::session(H::admin(['anasheed.addcat'], 'wc')))->get($this->url)->assertOk();
    $this->withSession(H::session(H::admin(['anasheed.addcat'], 'wc2')))->get('/anasheed_without_cats-page-2.htm')->assertOk();

    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->get()->toArray())->toEqual($before)
        ->and(DB::connection('main')->table('admin_audit_log')->count())->toBe(0)
        ->and(DB::connection('main')->table('nuke_w2a_cat')->where('id', 5)->value('anasheed_count'))->toBe(10);
    $this->post($this->url)->assertStatus(405);
});

it('refuses a visitor or member (401) and an admin without anasheed.addcat (403), at both URLs', function () {
    wcItem(null);
    $urls = [$this->url, '/anasheed_without_cats-page-2.htm'];

    foreach ($urls as $url) {
        $this->get($url)->assertUnauthorized();
    }
    $member = (new VbUser)->forceFill(['userid' => 55, 'username' => 'member']);
    foreach ($urls as $url) {
        $this->actingAs($member, 'vbulletin')->get($url)->assertUnauthorized();
    }
    H::visitor();

    $session = H::session(H::admin(['anasheed.addmirror', 'anasheed.editanasheed', 'anasheed.addcomment', 'khotab.addcat'], 'wc-other'));
    foreach ($urls as $url) {
        $this->withSession($session)->get($url)->assertForbidden();
    }
});

it('lets a super-admin in through the existing bypass', function () {
    $id = wcItem(null);

    $html = $this->withSession(H::session(H::superAdmin()))->get($this->url)->assertOk()->getContent();

    expect(wcListed($html))->toBe([$id])->and($html)->toContain(route('admin.categories.anasheed.item', $id));
});

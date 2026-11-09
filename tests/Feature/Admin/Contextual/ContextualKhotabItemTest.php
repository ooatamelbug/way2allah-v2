<?php

/*
 * Contextual surface C1 — the Khotab item page (`khotab-item-{id}.htm`):
 * K1 add mirror, K2 edit / K3 delete / K4a pick per mirror, K4b move here,
 * K5 add category, K6 remove category, K7 request promotional file,
 * K8 link status. Each one is rendered from the real public page and checked
 * against the real admin route.
 */

use App\Domain\Admin\Http\Controllers\KhotabMirrorController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => '']);
    H::category(1, 'العقيدة');
    H::category(2, 'الفقه');
    $this->item = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'درس الفجر', 'vedio' => 1, 'hidden' => 0, 'mirror' => 2, 'advfile' => 0, 'cat' => '|1|2|']);
    // The public page lists categories from the index; category 2 is in the item's list but not shown there.
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => $this->item, 'category_id' => 1]);
    $this->m1 = H::insert('nuke_islamic_mirror', ['khid' => $this->item, 'link' => 'https://x/a.mp4', 'comment' => 'جودة عالية', 'vedio' => 1]);
    $this->m2 = H::insert('nuke_islamic_mirror', ['khid' => $this->item, 'link' => 'https://x/b.mp3', 'comment' => 'صوت', 'vedio' => 0]);
    $this->url = "/khotab-item-{$this->item}.htm";
    $this->back = 'return_to='.urlencode($this->url);
});

function khotabPage($test, $admin = null): string
{
    $request = $admin ? $test->withSession(H::session($admin)) : $test;

    return $request->get($test->url)->assertOk()->getContent();
}

it('shows a visitor nothing', function () {
    $html = khotabPage($this);

    expect($html)->not->toContain('data-contextual-admin')->not->toContain('/admincp/')->toContain('جودة عالية');
});

it('shows an admin without permissions only what any admin may do (request a promotional file)', function () {
    $html = H::contextualMarkup(khotabPage($this, H::admin()));

    expect($html)->toContain('action="'.route('admin.advfiles.request', $this->item).'"')
        ->not->toContain('/admincp/mirrors')->not->toContain('/admincp/categories')->not->toContain('/admincp/link-quality');
});

it('denies the direct routes to an admin without the permission', function () {
    $this->withSession(H::session(H::admin()));

    $this->get(route('admin.mirrors.khotab.show', $this->item))->assertForbidden();
    $this->delete(route('admin.mirrors.khotab.destroy', $this->m1))->assertForbidden();
    $this->get(route('admin.categories.khotab.item', $this->item))->assertForbidden();
    $this->post(route('admin.categories.khotab.item.remove', $this->item), ['category_id' => 1])->assertForbidden();
    $this->get(route('admin.link-quality.khotab.item', $this->item))->assertForbidden();
});

it('puts edit / pick / delete beside each mirror for a mirror admin', function () {
    $html = khotabPage($this, H::admin(['khotab.addmirror', 'khotab.editmirror', 'khotab.delmirror']));

    expect($html)->toContain('href="'.route('admin.mirrors.khotab.show', $this->item).'?'.$this->back.'"');

    foreach ([$this->m1, $this->m2] as $mirror) {
        expect($html)->toContain('href="'.route('admin.mirrors.khotab.edit', $mirror).'?'.$this->back.'"')
            ->toContain('action="'.route('admin.mirrors.khotab.pick', $mirror).'"')
            ->toContain('action="'.route('admin.mirrors.khotab.destroy', $mirror).'"');
    }

    // Record-specific: mirror 1's controls sit inside mirror 1's card, before mirror 2's.
    $card1 = strpos($html, "/khotab-mirror-{$this->item}-{$this->m1}.htm");
    $card2 = strpos($html, "/khotab-mirror-{$this->item}-{$this->m2}.htm");
    $edit1 = strpos($html, route('admin.mirrors.khotab.edit', $this->m1));
    expect($card1)->toBeLessThan($edit1)->and($edit1)->toBeLessThan($card2);

    // Delete is a DELETE form with CSRF and a confirmation, never a link.
    expect($html)->toMatch('#<form method="post" action="'.preg_quote(route('admin.mirrors.khotab.destroy', $this->m1), '#').'"[^>]*onsubmit="return confirm\(#')
        ->and(substr_count($html, 'name="_method" value="DELETE"'))->toBe(2)
        ->and($html)->not->toContain('href="'.route('admin.mirrors.khotab.destroy', $this->m1).'"');
});

it('shows add-mirror only to an admin who may add, even though the page it opens accepts edit/delete holders', function () {
    $html = khotabPage($this, H::admin(['khotab.delmirror']));

    expect($html)->not->toContain('href="'.route('admin.mirrors.khotab.show', $this->item))
        ->toContain('action="'.route('admin.mirrors.khotab.destroy', $this->m1).'"')
        ->not->toContain(route('admin.mirrors.khotab.edit', $this->m1));
});

it('offers "move the picked mirror here" only when another item\'s mirror is picked', function () {
    $other = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'آخر', 'vedio' => 1, 'hidden' => 0]);
    $picked = H::insert('nuke_islamic_mirror', ['khid' => $other, 'link' => 'https://x/c.mp4', 'comment' => 'ج', 'vedio' => 1]);
    $admin = H::admin(['khotab.editmirror']);

    $html = $this->withSession(H::session($admin) + [KhotabMirrorController::CLIPBOARD_KEY => $picked])->get($this->url)->assertOk()->getContent();
    expect($html)->toContain('action="'.route('admin.mirrors.khotab.move', $picked).'"')
        ->toContain('name="target_item_id" value="'.$this->item.'"')
        ->toContain('action="'.route('admin.mirrors.khotab.clipboard.clear').'"');

    $own = $this->withSession(H::session($admin) + [KhotabMirrorController::CLIPBOARD_KEY => $this->m1])->get($this->url)->getContent();
    expect($own)->not->toContain(route('admin.mirrors.khotab.move', $this->m1));
});

it('puts remove beside each of the item\'s categories, and lists the ones the page does not show', function () {
    $html = khotabPage($this, H::admin(['khotab.addcat']));

    expect($html)->toContain('href="'.route('admin.categories.khotab.item', $this->item).'?'.$this->back.'"')
        ->and(substr_count($html, 'action="'.route('admin.categories.khotab.item.remove', $this->item).'"'))->toBe(2)
        ->and($html)->toContain('name="category_id" value="1"')->toContain('name="category_id" value="2"')
        ->toContain('تصنيفات أخرى مسجلة للمادة');

    // Category 1's remove control follows its chain link in the page bar.
    $chain = strpos($html, '<a href="/category-1.htm">العقيدة</a>');
    $remove1 = strpos($html, 'name="category_id" value="1"');
    expect($chain)->toBeLessThan($remove1)->and($html)->not->toContain('/admincp/mirrors');
});

it('links the link status page for a repair admin, without return_to (status page, not an operation)', function () {
    $html = khotabPage($this, H::admin(['khotab.repair']));

    expect($html)->toContain('href="'.route('admin.link-quality.khotab.item', $this->item).'"')
        ->not->toContain(route('admin.link-quality.khotab.item', $this->item).'?return_to');
});

it('hides the promotional-file request once one is requested or published', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->update(['advfile' => -1]);

    expect(khotabPage($this, H::superAdmin()))->not->toContain(route('admin.advfiles.request', $this->item));
});

it('shows a super-admin every Khotab item control', function () {
    $html = khotabPage($this, H::superAdmin());

    foreach ([
        route('admin.mirrors.khotab.show', $this->item), route('admin.mirrors.khotab.edit', $this->m1),
        route('admin.mirrors.khotab.pick', $this->m1), route('admin.mirrors.khotab.destroy', $this->m2),
        route('admin.categories.khotab.item', $this->item), route('admin.categories.khotab.item.remove', $this->item),
        route('admin.advfiles.request', $this->item), route('admin.link-quality.khotab.item', $this->item),
    ] as $target) {
        expect($html)->toContain($target);
    }
});

it('runs the real operations from the page and comes back to it', function () {
    $admin = H::superAdmin();
    $this->withSession(H::session($admin));

    $this->post(route('admin.categories.khotab.item.remove', $this->item), ['category_id' => 2, 'return_to' => $this->url])->assertRedirect($this->url);
    expect(DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->value('cat'))->toBe('|1|');

    $this->delete(route('admin.mirrors.khotab.destroy', $this->m2), ['return_to' => $this->url])->assertRedirect($this->url);
    expect(DB::connection('main')->table('nuke_islamic_mirror')->where('id', $this->m2)->exists())->toBeFalse();

    $this->post(route('admin.mirrors.khotab.pick', $this->m1), ['return_to' => $this->url])->assertRedirect($this->url);
    expect(session(KhotabMirrorController::CLIPBOARD_KEY))->toBe($this->m1);

    $this->post(route('admin.advfiles.request', $this->item), ['return_to' => $this->url])->assertRedirect($this->url);
    expect((int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->value('advfile'))->toBe(-1);
});

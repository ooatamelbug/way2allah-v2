<?php

/*
 * Contextual surface C3 — the Anasheed item page (`var-item-{id}.htm`):
 * A1 add mirror, A2 edit / A3 delete per mirror, A4 add category, A5 remove
 * category. Retired by the owner (2026-10-15) and therefore absent: the share
 * queue and the link-status block.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    H::category(1, 'أناشيد الأطفال');
    H::category(3, 'المناسبات');
    $this->item = H::insert('nuke_anasheed_anasheed', ['title' => 'نشيد', 'mirror' => 2, 'cat_id' => '|1|3|']);
    $this->m1 = H::insert('nuke_anasheed_mirror', ['khid' => $this->item, 'title' => 'جودة عالية', 'link' => 'https://x/a.mp4', 'vedio' => 1]);
    $this->m2 = H::insert('nuke_anasheed_mirror', ['khid' => $this->item, 'title' => 'صوت', 'link' => 'https://x/b.mp3', 'vedio' => 0]);
    $this->url = "/var-item-{$this->item}.htm";
    $this->back = 'return_to='.urlencode($this->url);
});

function anasheedPage($test, $admin = null): string
{
    return ($admin ? $test->withSession(H::session($admin)) : $test)->get($test->url)->assertOk()->getContent();
}

it('shows a visitor nothing', function () {
    expect(anasheedPage($this))->not->toContain('data-contextual-admin')->not->toContain('/admincp/')->toContain('جودة عالية');
});

it('shows an admin without Anasheed permissions nothing, and the routes refuse them', function () {
    $admin = H::admin(['khotab.addmirror', 'khotab.addcat']);

    expect(anasheedPage($this, $admin))->not->toContain('data-contextual-admin');

    $this->withSession(H::session($admin));
    $this->get(route('admin.mirrors.anasheed.show', $this->item))->assertForbidden();
    $this->delete(route('admin.mirrors.anasheed.destroy', $this->m1))->assertForbidden();
    $this->post(route('admin.categories.anasheed.item.remove', $this->item), ['category_id' => 1])->assertForbidden();
});

it('puts edit / delete beside each Anasheed mirror', function () {
    $html = anasheedPage($this, H::admin(['anasheed.addmirror', 'anasheed.editmirror', 'anasheed.delmirror']));

    expect($html)->toContain('href="'.route('admin.mirrors.anasheed.show', $this->item).'?'.$this->back.'"');
    foreach ([$this->m1, $this->m2] as $mirror) {
        expect($html)->toContain('href="'.route('admin.mirrors.anasheed.edit', $mirror).'?'.$this->back.'"')
            ->toContain('action="'.route('admin.mirrors.anasheed.destroy', $mirror).'"');
    }

    $card1 = strpos($html, "/var-mirror-{$this->item}-{$this->m1}.htm");
    $edit1 = strpos($html, route('admin.mirrors.anasheed.edit', $this->m1));
    $card2 = strpos($html, "/var-mirror-{$this->item}-{$this->m2}.htm");
    expect($card1)->toBeLessThan($edit1)->and($edit1)->toBeLessThan($card2)
        ->and(substr_count($html, 'name="_method" value="DELETE"'))->toBe(2)
        ->and($html)->not->toContain('/admincp/categories');
});

it('lists the item\'s categories with a remove control each, plus add category', function () {
    $html = anasheedPage($this, H::admin(['anasheed.addcat']));

    expect($html)->toContain('href="'.route('admin.categories.anasheed.item', $this->item).'?'.$this->back.'"')
        ->and(substr_count($html, 'action="'.route('admin.categories.anasheed.item.remove', $this->item).'"'))->toBe(2)
        ->and($html)->toContain('<a href="/var-category-1.htm">أناشيد الأطفال</a>')->toContain('name="category_id" value="3"')
        ->not->toContain('/admincp/mirrors');
});

it('shows a super-admin every Anasheed control, and none of the retired ones', function () {
    $html = anasheedPage($this, H::superAdmin());

    expect($html)->toContain(route('admin.mirrors.anasheed.show', $this->item))->toContain(route('admin.mirrors.anasheed.edit', $this->m2))
        ->toContain(route('admin.mirrors.anasheed.destroy', $this->m1))->toContain(route('admin.categories.anasheed.item', $this->item))
        ->toContain(route('admin.categories.anasheed.item.remove', $this->item))
        ->not->toContain('WaitingFB')->not->toContain('/admincp/link-quality');
});

it('runs the real operations and returns to the page', function () {
    $this->withSession(H::session(H::superAdmin()));

    $this->post(route('admin.categories.anasheed.item.remove', $this->item), ['category_id' => 3, 'return_to' => $this->url])->assertRedirect($this->url);
    expect(DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $this->item)->value('cat_id'))->toBe('|1|');

    $this->delete(route('admin.mirrors.anasheed.destroy', $this->m1), ['return_to' => $this->url])->assertRedirect($this->url);
    expect(DB::connection('main')->table('nuke_anasheed_mirror')->where('id', $this->m1)->exists())->toBeFalse();
});

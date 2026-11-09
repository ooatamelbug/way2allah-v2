<?php

/*
 * Contextual surface C2 — Khotab group and series pages: «اضف للكل» /
 * «امسح من الكل» beside each of the container's categories (G1/G2, S1/S2),
 * Phase 3's bulk operations.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => '']);
    H::category(1, 'العقيدة');
    H::category(2, 'الفقه');
    $this->group = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'مجموعة', 'hidden' => 0, 'cat' => '|1|2|']);
    $this->series = H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $this->group, 'title' => 'سلسلة', 'hidden' => 0, 'vedio' => 1, 'cat' => '|2|']);
    $this->loose = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مفرد', 'vedio' => 1, 'hidden' => 0, 'group_id' => $this->group, 'ser_id' => 0, 'cat' => '|1|2|']);
    $this->inSeries = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'ضمن سلسلة', 'vedio' => 1, 'hidden' => 0, 'group_id' => $this->group, 'ser_id' => $this->series, 'cat' => '|2|']);
});

dataset('containers', [
    'group' => ['group', fn () => "/khotab-group-{$this->group}.htm", fn () => $this->group, [1, 2]],
    'series' => ['series', fn () => "/khotab-series-{$this->series}.htm", fn () => $this->series, [2]],
]);

it('shows a visitor nothing', function (string $type, string $url, int $id, array $categories) {
    $html = $this->get($url)->assertOk()->getContent();

    expect($html)->not->toContain('data-contextual-admin')->not->toContain('/admincp/');
})->with('containers');

it('shows an admin without khotab.addcat no category controls, and the route refuses them', function (string $type, string $url, int $id, array $categories) {
    $admin = H::admin(['khotab.addmirror']);
    $html = $this->withSession(H::session($admin))->get($url)->assertOk()->getContent();

    // Both pages also carry OLD's «مواد لم تصنف», which OLD opened to every
    // administrator (KhotabGroupPanelParityTest, KhotabSeriesPanelParityTest);
    // no category control may appear.
    expect($html)->not->toContain(route("admin.categories.khotab.{$type}.add-all", $id))
        ->not->toContain(route("admin.categories.khotab.{$type}.remove-all", $id));
    $this->post(route("admin.categories.khotab.{$type}.add-all", $id), ['category_id' => 2])->assertForbidden();
    $this->post(route("admin.categories.khotab.{$type}.remove-all", $id), ['category_id' => 2])->assertForbidden();
})->with('containers');

it('puts add-all / remove-all beside each of the container\'s own categories', function (string $type, string $url, int $id, array $categories) {
    $html = $this->withSession(H::session(H::admin(['khotab.addcat'])))->get($url)->assertOk()->getContent();

    expect(substr_count($html, 'action="'.route("admin.categories.khotab.{$type}.add-all", $id).'"'))->toBe(count($categories))
        ->and(substr_count($html, 'action="'.route("admin.categories.khotab.{$type}.remove-all", $id).'"'))->toBe(count($categories))
        ->and($html)->toContain('name="return_to" value="'.$url.'"')->toContain('return confirm(');

    foreach ($categories as $categoryId) {
        // Each category's title is immediately followed by its own pair of controls.
        $title = strpos($html, '<a href="/category-'.$categoryId.'.htm">');
        $field = strpos($html, 'name="category_id" value="'.$categoryId.'"', $title);
        expect($title)->not->toBeFalse()->and($field)->toBeGreaterThan($title);
    }
})->with('containers');

it('shows a super-admin the same controls', function (string $type, string $url, int $id, array $categories) {
    $html = $this->withSession(H::session(H::superAdmin()))->get($url)->assertOk()->getContent();

    expect($html)->toContain(route("admin.categories.khotab.{$type}.add-all", $id))->toContain(route("admin.categories.khotab.{$type}.remove-all", $id));
})->with('containers');

it('runs the real bulk operation and returns to the page', function () {
    $url = "/khotab-series-{$this->series}.htm";

    $this->withSession(H::session(H::superAdmin()))
        ->post(route('admin.categories.khotab.series.remove-all', $this->series), ['category_id' => 2, 'return_to' => $url])
        ->assertRedirect($url);

    expect(DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->inSeries)->value('cat'))->not->toContain('|2|')
        ->and(DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->loose)->value('cat'))->toBe('|1|2|');
});

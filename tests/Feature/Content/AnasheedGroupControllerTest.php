<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForAnasheedGroup(): void
{
    InMemoryConnection::setup('main', [
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForAnasheedGroup();
});

it('show: renders sub-groups and items, increments the group\'s hits', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert([
        ['id' => 1, 'title' => 'Parent Group', 'parent_id' => 0, 'hits' => 3],
        ['id' => 2, 'title' => 'Sub Group', 'parent_id' => 1, 'hits' => 0],
    ]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Group Item', 'group_id' => 1,
    ]);

    $response = $this->get('/var-group-1.htm');

    $response->assertOk()->assertSee('Sub Group')->assertSee('Group Item');
    expect(DB::connection('main')->table('nuke_anasheed_groups')->find(1)->hits)->toBe(4);
});

it('show: group 98\'s items also include group 16\'s items — the confirmed hardcoded special case', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 98, 'title' => 'News Group', 'parent_id' => 0]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Item In 98', 'group_id' => 98],
        ['id' => 2, 'title' => 'Item In 16', 'group_id' => 16],
        ['id' => 3, 'title' => 'Item In Other Group', 'group_id' => 5],
    ]);

    $response = $this->get('/var-group-98.htm');

    $response->assertOk()->assertSee('Item In 98')->assertSee('Item In 16')->assertDontSee('Item In Other Group');
});

it('show: a non-special group only shows its own items', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 5, 'title' => 'Group Five', 'parent_id' => 0]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Item In Five', 'group_id' => 5],
        ['id' => 2, 'title' => 'Item In Sixteen', 'group_id' => 16],
    ]);

    $response = $this->get('/var-group-5.htm');

    $response->assertOk()->assertSee('Item In Five')->assertDontSee('Item In Sixteen');
});

it('show: 404s for a nonexistent group', function () {
    $this->get('/var-group-999.htm')->assertNotFound();
});

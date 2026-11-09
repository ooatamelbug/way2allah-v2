<?php

/*
 * Visitor-parity fix (owner-approved, 2026-11-08): Gen 2 `channels/author.php:75-82` renders
 * «اخترنا لك هذه المادة» filled by `randomitems()` (`functions.php:1092`) — one random sitewide
 * Khotab item with gif = 1 or frame = 1, linked to its item page. Laravel restores it on
 * `channel-{id}-{author}.htm` through the shared `khotabRandomFeatured()` (hidden items excluded, #94).
 */

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
    ]);

    $db = DB::connection('main');
    $db->table('nuke_sat_channels')->insert([['id' => 5, 'title' => 'Chan'], ['id' => 6, 'title' => 'Other Chan']]);
    $db->table('nuke_islamic_authors')->insert([
        ['id' => 9, 'name' => 'Shaikh Q', 'prename' => 'Dr.'],
        ['id' => 42, 'name' => 'Other Shaikh', 'prename' => 'الشيخ'],
    ]);
});

/** @param array<string, mixed> $row */
function carItem(array $row): void
{
    DB::connection('main')->table('nuke_islamic_khotab')->insert($row + [
        'ser_id' => 0, 'group_id' => 0, 'vedio' => 1, 'hits' => 1, 'time' => 1, 'hidden' => 0, 'frame' => 0, 'gif' => 0,
    ]);
}

/** The «اخترنا لك هذه المادة» portlet's HTML, or '' when absent. */
function carBox(string $html): string
{
    $start = strpos($html, 'اخترنا لك هذه المادة');
    if ($start === false) {
        return '';
    }
    $end = strpos($html, '<div class="caption"><i class="fa fa-child"></i> الأكثر تحميلا</div>', $start);

    return substr($html, $start, ($end === false ? strlen($html) : $end) - $start);
}

it('renders the «اخترنا لك هذه المادة» box once, with Gen 2\'s fa-child portlet heading, before the two empty IF-012 portlets', function () {
    carItem(['id' => 1, 'channel_id' => 5, 'author' => 9, 'title' => 'Author Item']);
    carItem(['id' => 70, 'channel_id' => 6, 'author' => 42, 'title' => 'Featured Pick', 'frame' => 1]);

    $html = $this->get('/channel-5-9.htm')->assertOk()->getContent();

    expect(substr_count($html, 'اخترنا لك هذه المادة'))->toBe(1)
        ->and($html)->toContain('<div class="caption"><i class="fa fa-child"></i> اخترنا لك هذه المادة</div>')
        ->and(strpos($html, 'اخترنا لك هذه المادة'))->toBeLessThan(strpos($html, '<div class="caption"><i class="fa fa-child"></i> الأكثر تحميلا</div>'))
        ->and(strpos($html, '<div class="caption"><i class="fa fa-child"></i> الأكثر تحميلا</div>'))->toBeLessThan(strpos($html, '<div class="caption"><i class="fa fa-child"></i> جديد المواد</div>'));
});

it('picks a Khotab item sitewide (as randomitems(): not scoped to the channel or author) and links to its item page', function () {
    carItem(['id' => 1, 'channel_id' => 5, 'author' => 9, 'title' => 'Author Item']);
    carItem(['id' => 70, 'channel_id' => 6, 'author' => 42, 'title' => 'Featured Pick', 'frame' => 1]);

    $box = carBox($this->get('/channel-5-9.htm')->assertOk()->getContent());

    expect($box)->toContain('href="/khotab-item-70.htm"')
        ->toContain('Featured Pick')
        ->toContain('الشيخ Other Shaikh')
        ->toContain('/media/khotab_frames/0/70.jpg')
        ->not->toContain('Author Item');
});

it('uses the gif image when gif = 1, as Gen 2 did', function () {
    carItem(['id' => 1500, 'channel_id' => 6, 'author' => 42, 'title' => 'Gif Pick', 'gif' => 1]);

    $box = carBox($this->get('/channel-5-9.htm')->assertOk()->getContent());

    expect($box)->toContain('/media/khotab_gifs/1/1500.gif')->toContain('href="/khotab-item-1500.htm"');
});

it('only offers items with a gif or frame — an item without either is never the pick', function () {
    carItem(['id' => 71, 'channel_id' => 5, 'author' => 9, 'title' => 'No Image Item']);

    $box = carBox($this->get('/channel-5-9.htm')->assertOk()->getContent());

    expect($box)->not->toBe('')
        ->and($box)->not->toContain('No Image Item')
        ->and($box)->not->toContain('w2a-featured-item-card');
});

it('excludes hidden items from the recommendation (owner-approved #94 visibility rule)', function () {
    carItem(['id' => 80, 'channel_id' => 6, 'author' => 42, 'title' => 'Hidden Pick', 'frame' => 1, 'hidden' => 1]);
    carItem(['id' => 81, 'channel_id' => 6, 'author' => 42, 'title' => 'Visible Pick', 'frame' => 1]);

    for ($i = 0; $i < 5; $i++) {
        $html = $this->get('/channel-5-9.htm')->assertOk()->getContent();
        expect($html)->not->toContain('Hidden Pick')->not->toContain('khotab-item-80.htm')
            ->and(carBox($html))->toContain('Visible Pick');
    }
});

it('leaves the author page otherwise unchanged: listing filtered by author, empty IF-012 boxes, no channel sidebar widgets', function () {
    carItem(['id' => 1, 'channel_id' => 5, 'author' => 9, 'title' => 'By author 9']);
    carItem(['id' => 2, 'channel_id' => 5, 'author' => 42, 'title' => 'By other author', 'hits' => 999]);

    $html = $this->get('/channel-5-9.htm')->assertOk()->getContent();

    expect($html)->toContain('By author 9')
        ->not->toContain('By other author')
        ->toContain('<div class="caption"><i class="fa fa-child"></i> الأكثر تحميلا</div>')
        ->toContain('<div class="caption"><i class="fa fa-child"></i> جديد المواد</div>')
        ->not->toContain('media-list')
        ->not->toContain('fa-cloud-download')
        ->not->toContain('fa-flash')
        ->toContain('<title>مرئيات Dr. Shaikh Q - قناة Chan - ');
});

it('the unfiltered channel page is unaffected — it has no such box (Gen 2 channel.php has none)', function () {
    carItem(['id' => 70, 'channel_id' => 5, 'author' => 42, 'title' => 'Featured Pick', 'frame' => 1]);

    expect($this->get('/channel-5.htm')->assertOk()->getContent())->not->toContain('اخترنا لك هذه المادة');
});

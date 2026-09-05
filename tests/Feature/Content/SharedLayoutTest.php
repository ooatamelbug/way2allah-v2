<?php

use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * The homepage (`/`) queries the real `main` connection (latest videos/
 * audios/fatawa/telawah/anasheed widgets, slider rows, etc.) — this file
 * previously had no `InMemoryConnection::setup('main', ...)` of its own,
 * so it silently depended on whatever the `main` connection happened to
 * resolve to (a real local MySQL instance on a developer machine; nothing
 * reachable in CI, where `.env`'s `DB_MAIN_*` values are intentionally
 * blank) — same isolated in-memory fixture pattern already established in
 * `HomeControllerTest`, reused here rather than redefined.
 */
function useInMemoryMainConnectionForSharedLayout(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
        'nuke_telawah_groups' => MainSchema::nukeTelawahGroups(),
        'nuke_options' => MainSchema::nukeOptions(),
        'nuke_albums_images' => MainSchema::nukeAlbumsImages(),
        'nuke_ads' => MainSchema::nukeAds(),
        'nuke_poll_desc' => MainSchema::nukePollDesc(),
        'nuke_poll_data' => MainSchema::nukePollData(),
        'nuke_pollcomments' => MainSchema::nukePollcomments(),
        'nuke_7amalat' => MainSchema::nuke7amalat(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForSharedLayout();
});

it('does not load the retired AddThis service or render its empty placeholder', function () {
    $content = $this->get('/privacy')->assertOk()->getContent();

    expect($content)->not->toContain('//s7.addthis.com/js/300/addthis_widget.js')
        ->and($content)->not->toContain('addthis_inline_share_toolbox');
});

it('loads the global premium layer that owns responsive navigation, dialogs, pagination, and local footer art', function () {
    $content = $this->get('/privacy')->assertOk()->getContent();
    $css = file_get_contents(public_path('assets/frontend/layout/css/premium-ui.css'));

    expect($content)->toContain('/assets/frontend/layout/css/premium-ui.css')
        ->toContain('/assets/frontend/layout/scripts/premium-ui.js')
        ->and($css)->toContain('.w2a-pagination')
        ->toContain('.modal .modal-dialog')
        ->toContain('.header .header-navigation.is-open')
        ->toContain('url("images/way_bottom_content_bg.png")')
        ->toContain('url("images/way_footer_bg.jpg")');
});

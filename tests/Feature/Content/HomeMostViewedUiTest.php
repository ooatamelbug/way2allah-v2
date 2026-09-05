<?php

it('keeps the most-viewed thumbnail wrapper as a block with reserved 16:9 space', function () {
    $css = file_get_contents(public_path('assets/frontend/layout/css/premium-ui.css'));

    expect($css)->toMatch(
        '/\.w2a-watching-thumb-wrap\s*\{[^}]*display:\s*block\s*!important;[^}]*padding-top:\s*56\.25%\s*!important;/s'
    );
});

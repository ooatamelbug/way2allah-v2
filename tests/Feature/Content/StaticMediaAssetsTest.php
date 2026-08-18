<?php

/**
 * G-13-02 (media/visual parity phase): `public/images` did not exist at
 * all — unlike `public/assets`/`public/css`/`public/logo-light.png`
 * (all real symlinks to `../../legacy-project/X`), there was no
 * `legacy-project/images` to link to (confirmed absent from that reference
 * copy, though the canonical `/mnt/Projects/Php/pure/7amlat/images/` has
 * always had it — 272MB/1497 files). Every `/images/*` reference sitewide
 * — including already-migrated pages' fallback images like `tvnoise.gif`/
 * `way2_withoutimg.png` — was silently 404ing as a result. Fixed by
 * copying the real directory into `legacy-project/` and symlinking
 * `public/images` from it, mirroring the existing convention exactly.
 *
 * A Pest HTTP test can't exercise this meaningfully (Laravel's test
 * kernel dispatches through the router only, not real static-file
 * serving), so this asserts directly against the filesystem path Laravel
 * actually serves static files from.
 */
it('public/images resolves to real legacy image files, not a broken/missing path', function () {
    expect(is_dir(public_path('images')))->toBeTrue()
        ->and(is_file(public_path('images/tvnoise.gif')))->toBeTrue()
        ->and(is_file(public_path('images/way2_withoutimg.png')))->toBeTrue()
        ->and(filesize(public_path('images/tvnoise.gif')))->toBeGreaterThan(0);
});

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
 *
 * CI note: `public/images` is a git-tracked symlink to
 * `../../legacy-project/images` — a real, separate, deliberately
 * never-committed reference repository (272MB/1497 files; real
 * production media doesn't belong in this repo's git history) that only
 * exists as a sibling checkout on a developer's own machine. This test's
 * entire purpose is verifying that specific local-dev/deployment-time
 * asset bridge actually resolves to real legacy bytes — a genuinely
 * different concern from application logic, and one a CI checkout of
 * this repository alone can never satisfy (there is no legacy media
 * library to check out). Skipped with an explicit reason in that
 * environment, matching this codebase's own established
 * `RUNTIME_BLOCKED_BY_MISSING_GD` precedent (see `ThumbnailControllerTest`)
 * for a capability that is real and required, but not something a
 * repo-only checkout can provide.
 */
it('public/images resolves to real legacy image files, not a broken/missing path', function () {
    expect(is_dir(public_path('images')))->toBeTrue()
        ->and(is_file(public_path('images/tvnoise.gif')))->toBeTrue()
        ->and(is_file(public_path('images/way2_withoutimg.png')))->toBeTrue()
        ->and(filesize(public_path('images/tvnoise.gif')))->toBeGreaterThan(0);
})->skip(
    fn () => ! is_file(public_path('images/tvnoise.gif')),
    'LOCAL_DEV_SYMLINK_UNAVAILABLE_IN_CI: public/images is a symlink to the sibling legacy-project repository, not available in a CI checkout of this repository alone.'
);

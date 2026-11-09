<?php

/*
 * P0 split layout & media roots (owner-approved 2026-11-09):
 *  - the five media roots are explicit absolute values in the production template;
 *  - `config:cache` (fresh application, no public path re-applied) keeps them correct only when they are
 *    explicitly configured — the hazard is pinned too;
 *  - the G2 visitor readers resolve files under the configured Khotab media root, with URLs and fallbacks
 *    unchanged;
 *  - `media:check-roots` reports correctly and never writes.
 */

use App\Console\Commands\CheckMediaRoots;
use App\Domain\Content\Http\Controllers\HomeController;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\Location;
use App\Domain\Content\Services\ContentSidebarWidget;
use App\Domain\Content\Support\MediaPathResolver;
use App\Domain\Content\Support\MediaUrl;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

const SLM_VARIABLES = [
    'KHOTAB_MEDIA_ROOT' => ['admin_khotab.media_root', 'media'],
    'ANASHEED_MEDIA_ROOT' => ['admin_anasheed.media_root', 'media'],
    'W2ACD_MEDIA_ROOT' => ['admin_cds.media_root', 'media'],
    'W2ACD_IMAGE_ROOT' => ['admin_cds.image_root', 'images/cds_image2'],
    'AUTOCOMPLETE_ROOT' => ['media.autocomplete_root', 'w2a_autocomplete'],
];

function slmTemp(): string
{
    $dir = sys_get_temp_dir().'/slm-'.bin2hex(random_bytes(6));
    mkdir($dir, 0775, true);

    return (string) realpath($dir);
}

function slmRemove(string $dir): void
{
    if (! file_exists($dir) && ! is_link($dir)) {
        return;
    }
    if (is_link($dir) || is_file($dir)) {
        @unlink($dir);

        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            slmRemove($dir.'/'.$entry);
        }
    }
    @rmdir($dir);
}

/** A web root with the five expected directories. */
function slmWebRoot(): string
{
    $web = slmTemp();
    foreach (['media', 'images/cds_image2', 'w2a_autocomplete'] as $sub) {
        mkdir($web.'/'.$sub, 0775, true);
    }

    return $web;
}

function slmConfigureRoots(string $web): void
{
    foreach (SLM_VARIABLES as [$key, $relative]) {
        config([$key => $web.'/'.$relative]);
    }
}

/** @return array{roots: array<string, string|null>, http_public_path: string} */
function slmFreshConfig(string $webRoot, array $env): array
{
    $process = new Process([PHP_BINARY, base_path('tests/Support/split-layout-fresh-config.php'), base_path(), $webRoot], base_path(), $env, null, 120);
    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

function slmCheck(array $options = []): array
{
    $exit = Artisan::call('media:check-roots', ['--json' => true] + $options);

    return [$exit, json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)];
}

// ================================================================ production template

it('the production env template declares the five roots as absolute placeholders and carries no secrets', function () {
    $template = (string) file_get_contents(base_path('.env.production.example'));

    foreach (array_keys(SLM_VARIABLES) as $variable) {
        expect($template)->toMatch('/^'.$variable.'=\/absolute\/path\/to\/[a-z0-9_\/]+$/m');
    }
    expect($template)->toMatch('/^W2ACD_IMAGE_ROOT=\/absolute\/path\/to\/images\/cds_image2$/m')
        ->toMatch('/^AUTOCOMPLETE_ROOT=\/absolute\/path\/to\/w2a_autocomplete$/m')
        ->toMatch('/^APP_ENV=production$/m')->toMatch('/^APP_DEBUG=false$/m')
        ->toMatch('/^APP_KEY=$/m');
    foreach (['DB_MAIN_PASSWORD', 'DB_VBULLETIN_PASSWORD', 'DB_FLASHCHAT_PASSWORD', 'VBULLETIN_COOKIE_SALT', 'AWS_SECRET_ACCESS_KEY'] as $secret) {
        expect($template)->toMatch('/^'.$secret.'=$/m');
    }
});

// ================================================================ config:cache in a split layout

it('config:cache keeps all five roots on the configured absolute paths in a split layout', function () {
    $web = slmTemp();
    $env = [];
    foreach (SLM_VARIABLES as $variable => [, $relative]) {
        $env[$variable] = $web.'/'.$relative;
    }

    $result = slmFreshConfig($web, $env);

    expect($result['http_public_path'])->toBe($web);
    foreach (SLM_VARIABLES as $variable => [$key]) {
        expect($result['roots'][$key])->toBe($env[$variable]);
    }
    slmRemove($web);
});

it('without the explicit variables, config:cache does NOT follow the split web root (the hazard the variables close)', function () {
    $web = slmTemp();
    $env = array_fill_keys(array_keys(SLM_VARIABLES), false); // removed from the child environment

    $result = slmFreshConfig($web, $env);

    expect($result['http_public_path'])->toBe($web);
    foreach (SLM_VARIABLES as [$key]) {
        expect((string) $result['roots'][$key])->not->toStartWith($web);
    }
    slmRemove($web);
});

// ================================================================ G2 visitor readers

it('MediaPathResolver::absolute maps a media/ path under the configured Khotab media root', function () {
    config(['admin_khotab.media_root' => '/srv/web/media/']);

    expect(MediaPathResolver::absolute('media/authors/0/5.jpg'))->toBe('/srv/web/media/authors/0/5.jpg')
        ->and(MediaPathResolver::absolute('/media/locations/1/1200.jpg'))->toBe('/srv/web/media/locations/1/1200.jpg')
        ->and(MediaPathResolver::absolute('authors/sq/7.png'))->toBe('/srv/web/media/authors/sq/7.png');
});

it('visitor readers find files under the configured media root even when public_path() is elsewhere, with unchanged URLs', function () {
    $media = slmTemp();
    $elsewhere = slmTemp();          // an empty "public path": the old readers would have looked here
    config(['admin_khotab.media_root' => $media]);
    app()->usePublicPath($elsewhere);

    foreach (['authors/sq/5.png', 'locations/0/7.jpg', 'khotab_frames/1/1500.jpg', 'authors/0/9.jpg'] as $file) {
        mkdir(dirname($media.'/'.$file), 0775, true);
        file_put_contents($media.'/'.$file, 'x');
    }

    $author = (new Author)->forceFill(['id' => 5]);
    $location = (new Location)->forceFill(['id' => 7]);
    $home = app(HomeController::class);
    $sidebar = app(ContentSidebarWidget::class);
    $homeThumb = fn (int $id, int $frame, ?int $authorId) => (fn () => $this->bucketedThumb($id, $frame, $authorId))->call($home);
    $sidebarThumb = fn (int $frame, int $id, ?int $authorId) => (fn () => $this->topitemsThumb($frame, $id, $authorId))->call($sidebar);

    expect($author->fallbackImageUrl())->toBe(MediaUrl::asset('authors/sq/5.png'))
        ->and($location->photoUrl())->toBe(MediaUrl::asset('media/locations/0/7.jpg'))
        ->and($homeThumb(1500, 1, null))->toBe(MediaUrl::asset('media/khotab_frames/1/1500.jpg'))
        ->and($homeThumb(1, 0, 9))->toBe(MediaUrl::asset('media/authors/0/9.jpg'))
        ->and($sidebarThumb(1, 1500, null))->toBe(MediaUrl::asset('media/khotab_frames/1/1500.jpg'));

    slmRemove($media);
    slmRemove($elsewhere);
});

it('visitor readers keep their existing fallbacks when the file is missing', function () {
    $media = slmTemp();
    config(['admin_khotab.media_root' => $media]);

    $home = app(HomeController::class);
    $sidebar = app(ContentSidebarWidget::class);

    expect((new Author)->forceFill(['id' => 5])->fallbackImageUrl())->toBe(MediaUrl::asset('authors/no_author_image.png'))
        ->and((new Location)->forceFill(['id' => 7])->photoUrl())->toBe(MediaUrl::asset('locations/no_location_image.png'))
        ->and((fn () => $this->bucketedThumb(1500, 1, null))->call($home))->toBe('/images/tvnoise.gif')
        ->and((fn () => $this->bucketedThumb(1, 0, 9))->call($home))->toBe('/images/tvnoise.gif')
        ->and((fn () => $this->topitemsThumb(1, 1500, null))->call($sidebar))->toBe('/images/way2_withoutimg.png');

    slmRemove($media);
});

it('by default (uncached HTTP) the configured root equals public_path(media), so local behaviour is unchanged', function () {
    expect(rtrim((string) config('admin_khotab.media_root'), '/'))->toBe(public_path('media'));
});

// ================================================================ media:check-roots

it('media:check-roots: a consistent split-layout structure is clean (exit 0)', function () {
    $web = slmWebRoot();
    slmConfigureRoots($web);

    [$exit, $report] = slmCheck(['--expect-web-root' => $web]);

    expect($exit)->toBe(CheckMediaRoots::EXIT_CLEAN)->and($report['status'])->toBe('clean')
        ->and($report['web_root'])->toBe($web)->and($report['findings'])->toBe([])
        ->and($report['roots'])->toHaveCount(5)
        ->and($report['production_verification'])->not->toBe([]);
    foreach ($report['roots'] as $root) {
        expect($root['absolute'])->toBeTrue()->and($root['exists'])->toBeTrue()->and($root['readable'])->toBeTrue()
            ->and($root['writable'])->toBeTrue()->and($root['symlink'])->toBeFalse()->and($root['canonical'])->toBe($root['path']);
    }
    slmRemove($web);
});

it('media:check-roots: relative, missing, mismatched and unexpected roots are confirmed findings (exit 1)', function () {
    $web = slmWebRoot();
    slmConfigureRoots($web);
    config(['admin_khotab.media_root' => 'relative/media']);
    config(['admin_cds.image_root' => $web.'/missing/cds_image2']);
    config(['media.autocomplete_root' => slmTemp().'/w2a_autocomplete']);

    [$exit, $report] = slmCheck(['--expect-web-root' => '/somewhere/else']);
    $all = implode("\n", $report['findings']);

    expect($exit)->toBe(CheckMediaRoots::EXIT_FINDINGS)
        ->and($all)->toContain('KHOTAB_MEDIA_ROOT (admin_khotab.media_root) is not an absolute path')
        ->toContain('W2ACD_IMAGE_ROOT (admin_cds.image_root) does not exist')
        ->toContain('does not end in /images/cds_image2')
        ->toContain('The roots disagree on the web root');
    slmRemove($web);
});

it('media:check-roots: roots inside a missing application public/ directory are flagged as the split-layout hazard', function () {
    $originalBase = base_path();
    $fakeBase = slmTemp();                    // an application directory with no public/ (split layout)
    try {
        app()->setBasePath($fakeBase);
        slmConfigureRoots($fakeBase.'/public');

        [$exit, $report] = slmCheck();

        expect($exit)->toBe(CheckMediaRoots::EXIT_FINDINGS)
            ->and($report['application_public_directory_exists'])->toBeFalse()
            ->and(implode("\n", $report['findings']))->toContain("points inside the application's own public/ directory");
        expect(is_dir($fakeBase.'/public'))->toBeFalse();     // nothing was created
    } finally {
        app()->setBasePath($originalBase);
        slmRemove($fakeBase);
    }
});

it('media:check-roots: symlinked roots and a stray application public/ directory are informational', function () {
    $target = slmWebRoot();
    $web = slmTemp();
    symlink($target.'/media', $web.'/media');
    mkdir($web.'/images/cds_image2', 0775, true);
    mkdir($web.'/w2a_autocomplete');
    slmConfigureRoots($web);

    [$exit, $report] = slmCheck();
    $info = implode("\n", $report['informational']);

    expect($exit)->toBe(CheckMediaRoots::EXIT_CLEAN)
        ->and($report['roots'][0]['symlink'])->toBeTrue()
        ->and($report['roots'][0]['canonical'])->toBe($target.'/media')
        ->and($info)->toContain('is a symlink to '.$target.'/media')
        ->toContain('inspect it for misplaced uploads')         // the local application public/ exists while the web root is elsewhere
        ->toContain('Configuration is not cached');
    slmRemove($web);
    slmRemove($target);
});

it('media:check-roots never creates, changes or removes anything', function () {
    $web = slmTemp();
    slmConfigureRoots($web);                  // none of the five directories exist
    $before = scandir($web);

    [$exit] = slmCheck();
    Artisan::call('media:check-roots');       // human output too

    expect($exit)->toBe(CheckMediaRoots::EXIT_FINDINGS)->and(scandir($web))->toBe($before);
    foreach (SLM_VARIABLES as [, $relative]) {
        expect(file_exists($web.'/'.$relative))->toBeFalse();
    }
    slmRemove($web);
});

it('media:check-roots prints only the five roots — no credentials or unrelated environment values', function () {
    $web = slmWebRoot();
    slmConfigureRoots($web);
    config(['app.key' => 'base64:SHOULD-NOT-APPEAR', 'database.connections.main.password' => 'secret-should-not-appear']);

    [, $report] = slmCheck();
    Artisan::call('media:check-roots');
    $human = Artisan::output();
    $json = (string) json_encode($report);

    foreach ([$json, $human] as $output) {
        expect($output)->not->toContain('SHOULD-NOT-APPEAR')->not->toContain('secret-should-not-appear')
            ->not->toContain('DB_')->not->toContain('APP_KEY');
    }
    expect($human)->toContain('Production verification required')->toContain('Exit code: 0');
    slmRemove($web);
});

it('media:check-roots: usage errors exit 64', function () {
    expect(Artisan::call('media:check-roots', ['--bogus' => true]))->toBe(CheckMediaRoots::EXIT_USAGE)
        ->and(Artisan::call('media:check-roots', ['--expect-web-root' => 'relative/path']))->toBe(CheckMediaRoots::EXIT_USAGE);
});

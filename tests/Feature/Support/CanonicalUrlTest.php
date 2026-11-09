<?php

/*
 * N3 (owner-approved option A, 2026-11-08) — APP_FORCE_ROOT_URL: when enabled, absolute URLs Laravel
 * generates use APP_URL's root and scheme instead of the request Host header. Synthetic requests with
 * Host attacker.example; APP_URL https://way2allah.com. Default off: request-based behaviour unchanged.
 */

use App\Providers\AppServiceProvider;
use App\Support\Http\CanonicalUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

const CU_CANON = 'https://way2allah.com';
const CU_EVIL = 'http://attacker.example';

beforeEach(function () {
    H::setUp();
    $this->media = sys_get_temp_dir().'/w2a-canonical-'.bin2hex(random_bytes(4));
    config([
        'admin_khotab.media_root' => $this->media,
        'admin_cds.media_root' => $this->media,
        'media.base_url' => '/media',
        'app.url' => CU_CANON,
    ]);
    DB::connection('main')->table('nuke_config')->update(['sitename' => 'الطريق إلى الله']);
});

afterEach(function () {
    File::deleteDirectory($this->media);
});

function cuEnable(string $appUrl = CU_CANON): void
{
    config(['app.url' => $appUrl, 'app.force_root_url' => true]);
    CanonicalUrl::apply(app('url'), false);
}

function cuGif(string $path): void
{
    File::ensureDirectoryExists(dirname($path));
    file_put_contents($path, base64_decode('R0lGODlhAgABAIAAAP///wAAACH5BAEAAAAALAAAAAACAAEAAAICBAoAOw=='));
}

/** The five permanent redirects found by the N3 investigation. */
function cuRedirects(): array
{
    return [
        'modules.php audio' => ['/modules.php?name=Khotab&op=Audio', '/khotab-audio.htm'],
        'modules.php audio date' => ['/modules.php?name=Khotab&op=Day&video=0&d=5&m=11&y=2020', '/khotab-audiodate-5-11-2020.htm'],
        'radio operation' => ['/playlist-item-12.htm', '/radio.htm'],
        'khotab comment page' => ['/khotab-item-77-page-2.htm', '/khotab-item-77.htm?page=2'],
        'cds-group-0' => ['/cds-group-0.htm', '/cds-main.htm'],
        'khotab-pdf item transcript' => ['/khotab-pdf-{pdfItem}.htm', '/khotab-item-pdf-{pdfItem}.htm'],
    ];
}

function cuSeedPdfItem(): int
{
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.', 'vedio' => 3]);

    return H::insert('nuke_islamic_khotab', ['id' => 5000, 'author' => 7, 'title' => 'مادة', 'vedio' => 1, 'pdf' => 4]);
}

// ================================================================ redirects

it('enabled: every permanent redirect points at APP_URL over HTTPS, same path and query, still 301', function (string $from, string $to) {
    $pdf = cuSeedPdfItem();
    [$from, $to] = [str_replace('{pdfItem}', (string) $pdf, $from), str_replace('{pdfItem}', (string) $pdf, $to)];
    cuEnable();

    $response = $this->get(CU_EVIL.$from);

    $location = (string) $response->headers->get('Location');
    expect($response->getStatusCode())->toBe(301)
        ->and($location)->toBe(CU_CANON.$to)
        ->and($location)->toStartWith('https://')
        ->and($location)->not->toContain('attacker.example');
})->with(cuRedirects());

it('disabled (default): the same redirects keep Laravel\'s request-based behaviour', function () {
    expect(config('app.force_root_url'))->toBeFalse();

    $this->get(CU_EVIL.'/playlist-item-12.htm')->assertStatus(301)->assertHeader('Location', CU_EVIL.'/radio.htm');
});

it('leaves relative redirects relative', function () {
    cuEnable();

    $this->get(CU_EVIL.'/vars-categories.htm')->assertRedirect()->assertHeader('Location', '/categories.htm');
    $this->get(CU_EVIL.'/fatawa-today.htm')->assertHeader('Location', '/fatwa-today.htm');
});

// ================================================================ banner embed codes

it('enabled: the Khotab banner embed codes use APP_URL for the item and the image', function (int $type) {
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.', 'vedio' => 3]);
    $id = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مادة', 'vedio' => 1, 'banner' => 1]);
    cuGif($this->media.'/banner/0/'.$id.'.gif');
    cuEnable();

    $html = $this->get(CU_EVIL."/khotab-code-{$id}-{$type}.htm")->assertOk()->getContent();
    $code = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    expect($code)->toContain(CU_CANON.'/khotab-item-'.$id.'.htm')
        ->toContain(CU_CANON.'/media/banner/0/'.$id.'.gif')
        ->not->toContain('attacker.example');
    $type === 1
        ? expect($code)->toContain('<a target="_blank" href="'.CU_CANON.'/khotab-item-'.$id.'.htm"')
        : expect($code)->toContain('[URL="'.CU_CANON.'/khotab-item-'.$id.'.htm"][IMG]'.CU_CANON.'/media/banner/0/'.$id.'.gif');
})->with([1, 2, 3]);

it('enabled: the W2ACD banner embed codes use APP_URL for the release and the image', function (int $type) {
    $group = H::insert('nuke_w2acd_groups', ['title' => 'قسم', 'parent_id' => 0, 'w2acd' => 1, 'module_type' => 'cd']);
    $cd = H::insert('nuke_w2acd_w2acd', ['group_id' => $group, 'title' => 'إسطوانة', 'banner' => '1', 'hidden' => 0, 'link' => 'https://archive.org/a.iso', 'cd' => 'ISO', 'thumbnail' => '']);
    cuGif($this->media.'/w2acd/banner/0/'.$cd.'.gif');
    cuEnable();

    $code = html_entity_decode($this->get(CU_EVIL."/cds-code-{$cd}-{$type}.htm")->assertOk()->getContent(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    expect($code)->toContain(CU_CANON.'/cds-item-'.$cd.'.htm')
        ->toContain(CU_CANON.'/media/w2acd/banner/0/'.$cd.'.gif')
        ->not->toContain('attacker.example');
})->with([1, 2, 3]);

it('disabled: the embed codes still follow the request host (unchanged behaviour)', function () {
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.', 'vedio' => 3]);
    $id = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مادة', 'vedio' => 1, 'banner' => 1]);
    cuGif($this->media.'/banner/0/'.$id.'.gif');

    $code = html_entity_decode($this->get(CU_EVIL."/khotab-code-{$id}-1.htm")->getContent(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    expect($code)->toContain(CU_EVIL.'/khotab-item-'.$id.'.htm');
});

// ================================================================ representative URLs

it('enabled: named routes, form actions and asset URLs use the canonical root', function () {
    cuEnable();

    $html = $this->get(CU_EVIL.'/admincp/')->assertOk()->getContent();

    expect($html)->toContain('action="'.CU_CANON.'/admincp/login"')
        ->toContain('href="'.CU_CANON.'/assets/global/plugins/font-awesome/css/font-awesome.min.css"')
        ->not->toContain('attacker.example')
        ->and(route('khotab.authors.index', ['op' => 'video']))->toBe(CU_CANON.'/khotab-video.htm')
        ->and(url('/x'))->toBe(CU_CANON.'/x');
});

it('enabled: default paginator links use the canonical root and keep the path and query', function () {
    cuEnable();
    app('url')->setRequest(Request::create(CU_EVIL.'/khotab-video.htm?sort=1'));

    app()->instance('request', Request::create(CU_EVIL.'/khotab-video.htm?sort=1'));
    // As the query builder's paginate() does: the path comes from the paginator's current-path resolver.
    $paginator = (new LengthAwarePaginator([], 50, 10, 1, ['path' => Paginator::resolveCurrentPath()]))->withQueryString();

    expect($paginator->url(2))->toStartWith(CU_CANON.'/khotab-video.htm?')
        ->toContain('page=2')
        ->not->toContain('attacker.example');
});

it('disabled: paginator links keep the request host (unchanged behaviour)', function () {
    $this->get(CU_EVIL.'/privacy');
    app('url')->setRequest(Request::create(CU_EVIL.'/khotab-video.htm'));
    app()->instance('request', Request::create(CU_EVIL.'/khotab-video.htm'));

    expect((new LengthAwarePaginator([], 50, 10, 1, ['path' => Paginator::resolveCurrentPath()]))->url(2))->toBe(CU_EVIL.'/khotab-video.htm?page=2');
});

it('keeps social metadata on APP_URL with the flag on or off', function (bool $forced) {
    if ($forced) {
        cuEnable();
    }

    $html = $this->get(CU_EVIL.'/privacy')->assertOk()->getContent();

    expect($html)->toContain('<meta property="og:url" content="'.CU_CANON.'/privacy">')->not->toContain('attacker.example');
})->with([true, false]);

// ================================================================ flag / configuration

it('is wired into AppServiceProvider and reads only configuration (config-cache safe)', function () {
    config(['app.force_root_url' => true, 'app.url' => CU_CANON]);
    app()->register(AppServiceProvider::class, force: true);

    expect(url('/khotab-video.htm'))->toBe(CU_CANON.'/khotab-video.htm');

    $sources = file_get_contents(app_path('Support/Http/CanonicalUrl.php')).file_get_contents(app_path('Providers/AppServiceProvider.php'));
    expect($sources)->not->toContain('env(')
        ->and(file_get_contents(config_path('app.php')))->toContain("'force_root_url' => (bool) env('APP_FORCE_ROOT_URL', false)");
});

it('defaults to off', function () {
    expect(config('app.force_root_url'))->toBeFalse();
    CanonicalUrl::apply(app('url'), false);   // no-op while off

    app('url')->setRequest(Request::create(CU_EVIL.'/'));
    expect(url('/x'))->toBe(CU_EVIL.'/x');
});

it('accepts only an absolute http(s) URL with a valid host', function (mixed $appUrl, ?array $expected) {
    expect(CanonicalUrl::validatedRoot($appUrl))->toBe($expected);
})->with([
    'https host' => ['https://way2allah.com', ['root' => 'https://way2allah.com', 'scheme' => 'https']],
    'trailing slash' => ['https://way2allah.com/', ['root' => 'https://way2allah.com', 'scheme' => 'https']],
    'http' => ['http://way2allah.com', ['root' => 'http://way2allah.com', 'scheme' => 'http']],
    'sub-path' => ['https://way2allah.com/app', ['root' => 'https://way2allah.com/app', 'scheme' => 'https']],
    'port' => ['https://way2allah.com:8443', ['root' => 'https://way2allah.com:8443', 'scheme' => 'https']],
    'empty' => ['', null],
    'null' => [null, null],
    'no scheme' => ['way2allah.com', null],
    'ftp' => ['ftp://way2allah.com', null],
    'javascript' => ['javascript:alert(1)', null],
    'no host' => ['https://', null],
    'user info' => ['https://user:pw@way2allah.com', null],
    'query' => ['https://way2allah.com/?x=1', null],
    'fragment' => ['https://way2allah.com/#x', null],
    'bad host' => ['https://way2allah..com', null],
    'surrounding space' => [' https://way2allah.com', null],
]);

it('refuses to force an invalid APP_URL: an HTTP request fails clearly, the console skips, nothing is guessed', function () {
    config(['app.force_root_url' => true, 'app.url' => 'not a url']);

    expect(fn () => CanonicalUrl::apply(app('url'), false))
        ->toThrow(RuntimeException::class, 'APP_FORCE_ROOT_URL is enabled but APP_URL is not a valid');

    try {
        CanonicalUrl::apply(app('url'), false);
    } catch (RuntimeException $e) {
        expect($e->getMessage())->not->toContain('not a url');   // the value is not echoed
    }

    CanonicalUrl::apply(app('url'), true);   // console: no exception, no forcing
    app('url')->setRequest(Request::create(CU_EVIL.'/'));
    expect(url('/x'))->toBe(CU_EVIL.'/x');
});

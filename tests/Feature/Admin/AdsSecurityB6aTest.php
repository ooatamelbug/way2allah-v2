<?php

/*
 * F01 / B6a — security of the advertising administration: permissions, raw HTML/JS ads (super-admin only),
 * upload validation, HTTP methods and CSRF, escaping in admin previews, and audit content.
 */

use App\Domain\Admin\Actions\Ads\CreateAdAction;
use App\Domain\Admin\Actions\Ads\DeleteAdAction;
use App\Domain\Admin\Actions\Ads\ToggleAdAction;
use App\Domain\Admin\Actions\Ads\UpdateAdAction;
use App\Domain\Admin\Support\AdminDashboardModules;
use App\Domain\Content\Models\HomeAd;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdAdminHarness as H;

uses(RefreshDatabase::class);

const B6A_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
const B6A_GIF = 'R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';

beforeEach(function () {
    H::setUp();
    $this->media = sys_get_temp_dir().'/w2a-b6a-'.uniqid();
    mkdir($this->media, 0775, true);
    config(['admin_khotab.media_root' => $this->media, 'media.base_url' => '/media', 'app.url' => 'https://way2allah.com']);
    $this->legacy = H::legacyRow();
    $this->code = H::codeRow();
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->media));
});

function b6aRoutes(): array
{
    return [
        ['get', 'admin.ads.index', false], ['get', 'admin.ads.create', false], ['post', 'admin.ads.store', false],
        ['get', 'admin.ads.reports', false], ['get', 'admin.ads.edit', true], ['put', 'admin.ads.update', true],
        ['delete', 'admin.ads.destroy', true], ['post', 'admin.ads.toggle', true],
    ];
}

// ─── permissions ─────────────────────────────────────────────────────────────

it('registers exactly the eight administration routes, all under /admincp behind ads.manageads', function () {
    $routes = collect(app('router')->getRoutes()->getRoutesByName())->filter(fn ($r, $n) => str_starts_with($n, 'admin.ads.'));

    expect($routes->keys()->sort()->values()->all())->toBe(collect(b6aRoutes())->pluck(1)->sort()->values()->all());
    foreach ($routes as $route) {
        expect($route->uri())->toStartWith('admincp/ads')
            ->and($route->gatherMiddleware())->toContain('web')->toContain('admin.role')->toContain('admin.permission:ads.manageads');
    }
});

it('a visitor is never served any ads administration page or action', function () {
    $before = H::snapshot();
    foreach (b6aRoutes() as [$method, $name, $id]) {
        $status = $this->{$method}(route($name, $id ? [$this->legacy] : []), H::imageInput())->status();
        expect($status)->toBeIn([302, 401]);
    }
    expect(H::snapshot())->toBe($before);
});

it('an admin without ads.manageads gets 403 everywhere and changes nothing — Advfiles\' khotab.addadv included', function () {
    $this->actingAs(H::admin(['khotab.addadv', 'polls.managepolls']), 'admin');
    $before = H::snapshot();

    foreach (b6aRoutes() as [$method, $name, $id]) {
        $this->{$method}(route($name, $id ? [$this->legacy] : []), H::imageInput(['show' => '1']))->assertForbidden();
    }

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('every action re-checks authorization itself, independent of the route', function () {
    $outsider = H::admin(['khotab.addadv']);
    $ad = HomeAd::query()->findOrFail($this->legacy);
    $before = H::snapshot();

    expect(fn () => app(CreateAdAction::class)->execute($outsider, H::imageInput(), null))->toThrow(AuthorizationException::class);
    expect(fn () => app(UpdateAdAction::class)->execute($outsider, $ad, H::imageInput(), null))->toThrow(AuthorizationException::class);
    expect(fn () => app(DeleteAdAction::class)->execute($outsider, $ad))->toThrow(AuthorizationException::class);
    expect(fn () => app(ToggleAdAction::class)->execute($outsider, $ad, true))->toThrow(AuthorizationException::class);

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('the menu shows «الإعلانات» only to holders of ads.manageads (and super-admins), separate from Advfiles', function () {
    $routes = fn ($admin) => collect(app(AdminDashboardModules::class)->visibleFor($admin))
        ->flatMap(fn ($m) => $m['children'] ?? [$m])->pluck('route')->all();

    expect($routes(H::admin([], 'none')))->not->toContain('admin.ads.index')
        ->and($routes(H::admin(['khotab.addadv'], 'adv')))->not->toContain('admin.ads.index')
        ->and($routes(H::admin(['ads.manageads'], 'ads')))->toContain('admin.ads.index')
        ->and($routes(H::superAdmin()))->toContain('admin.ads.index');
});

// ─── raw HTML / JavaScript ads: super-admin only ─────────────────────────────

it('a non-super-admin cannot create a code ad, and the form does not offer one', function () {
    $this->actingAs(H::admin(), 'admin');

    $html = $this->get(route('admin.ads.create'))->assertOk()->getContent();
    expect($html)->not->toContain('name="google_code"')->not->toContain('name="type" value="0"')->toContain('يضيفها ويعدلها المدير العام فقط');

    $before = H::snapshot();
    $this->post(route('admin.ads.store'), H::imageInput(['type' => '0', 'google_code' => '<script>alert(1)</script>']))->assertForbidden();
    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('a non-super-admin cannot open, edit, convert to code, or toggle a code ad; the list hides those controls', function () {
    $this->actingAs(H::admin(), 'admin');
    $before = H::snapshot();

    $this->get(route('admin.ads.edit', $this->code))->assertForbidden();
    $this->put(route('admin.ads.update', $this->code), H::imageInput())->assertForbidden();
    $this->post(route('admin.ads.toggle', $this->code), ['show' => '0'])->assertForbidden();
    $this->put(route('admin.ads.update', $this->legacy), H::imageInput(['type' => '0', 'google_code' => '<b>x</b>']))->assertForbidden();

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);

    $html = $this->get(route('admin.ads.index'))->assertOk()->getContent();
    expect($html)->not->toContain('href="'.route('admin.ads.edit', $this->code).'"')
        ->not->toContain('action="'.route('admin.ads.toggle', $this->code).'"')
        ->toContain('action="'.route('admin.ads.toggle', $this->legacy).'"');
});

it('only a super-admin may delete a code ad: a manager gets 403 from the route and the action, and sees no delete control', function () {
    $manager = H::admin();
    $this->actingAs($manager, 'admin');
    $before = H::snapshot();

    $this->delete(route('admin.ads.destroy', $this->code))->assertForbidden();
    expect(fn () => app(DeleteAdAction::class)->execute($manager, HomeAd::query()->findOrFail($this->code)))->toThrow(AuthorizationException::class);
    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);

    $html = $this->get(route('admin.ads.index'))->assertOk()->getContent();
    expect($html)->not->toContain('action="'.route('admin.ads.destroy', $this->code).'"')
        ->toContain('action="'.route('admin.ads.destroy', $this->legacy).'"');

    // Ordinary image ads are unaffected.
    $this->delete(route('admin.ads.destroy', $this->legacy))->assertSessionHas('success');

    $this->actingAs(H::superAdmin(), 'admin');
    $this->delete(route('admin.ads.destroy', $this->code))->assertSessionHas('success');
    expect(H::row($this->code))->toBeNull();
});

it('every code-ad operation is refused to a manager at the action level, independent of routes and forms', function () {
    $manager = H::admin();
    $code = HomeAd::query()->findOrFail($this->code);
    $image = HomeAd::query()->findOrFail($this->legacy);
    $before = H::snapshot();
    $toCode = H::imageInput(['type' => '0', 'google_code' => '<ins></ins>', 'path_type' => '', 'image_url' => '']);

    foreach ([
        'create code' => fn () => app(CreateAdAction::class)->execute($manager, $toCode, null),
        'edit code' => fn () => app(UpdateAdAction::class)->execute($manager, $code, H::imageInput(), null),
        'convert image to code' => fn () => app(UpdateAdAction::class)->execute($manager, $image, $toCode, null),
        'enable/disable code' => fn () => app(ToggleAdAction::class)->execute($manager, $code, false),
        'delete code' => fn () => app(DeleteAdAction::class)->execute($manager, $code),
    ] as $operation => $call) {
        expect($call)->toThrow(AuthorizationException::class, null, $operation);
    }

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('re-checks the code rule on the locked row: an ad that became code after it was loaded is still refused', function () {
    $manager = H::admin();
    $stale = HomeAd::query()->findOrFail($this->legacy);   // loaded as an image ad
    DB::connection('main')->table('nuke_ads')->where('id', $this->legacy)->update(['type' => 0, 'image_path' => '<ins></ins>']);
    $before = H::snapshot();

    expect(fn () => app(ToggleAdAction::class)->execute($manager, $stale, true))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteAdAction::class)->execute($manager, $stale))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateAdAction::class)->execute($manager, $stale, H::imageInput(), null))->toThrow(AuthorizationException::class);

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('a super-admin creates and edits code ads; the code is stored raw (OLD parity) and shown only escaped', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $code = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script><ins class="adsbygoogle" data-ad-slot="1"></ins>';

    $this->post(route('admin.ads.store'), H::imageInput(['type' => '0', 'google_code' => $code, 'path_type' => '', 'image_url' => '']))->assertRedirect();
    $id = (int) DB::connection('main')->table('nuke_ads')->max('id');
    $row = H::row($id);
    expect($row->image_path)->toBe($code)->and((int) $row->path_type)->toBe(2)->and((int) $row->type)->toBe(0);

    $html = $this->get(route('admin.ads.edit', $id))->assertOk()->getContent();
    expect($html)->toContain(e($code))->not->toContain('<script async src="https://pagead2.googlesyndication.com');

    $this->put(route('admin.ads.update', $id), H::imageInput(['type' => '0', 'google_code' => $code.'<!-- v2 -->', 'path_type' => '', 'image_url' => '']))
        ->assertSessionHas('success');
    expect(H::row($id)->image_path)->toBe($code.'<!-- v2 -->');
});

// ─── upload validation ───────────────────────────────────────────────────────

it('stores a real image under media/bann with an extension taken from its content and an absolute URL, as OLD', function () {
    $this->actingAs(H::admin(), 'admin');

    $this->post(route('admin.ads.store'), H::imageInput([
        'path_type' => '1', 'image_url' => '', 'image_file' => UploadedFile::fake()->createWithContent('banner.png', base64_decode(B6A_PNG)),
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $row = DB::connection('main')->table('nuke_ads')->orderByDesc('id')->first();
    expect($row->image_path)->toMatch('#^https://way2allah\.com/media/bann/\d+_[0-9a-f]{8}\.png$#')
        ->and((int) $row->path_type)->toBe(1);
    $file = $this->media.'/bann/'.basename($row->image_path);
    expect(file_get_contents($file))->toBe(base64_decode(B6A_PNG));
});

it('replacing the image on edit stores a new file and keeps the old one on disk', function () {
    $this->actingAs(H::admin(), 'admin');
    mkdir($this->media.'/bann', 0775, true);
    file_put_contents($this->media.'/bann/1742505317.jpg', 'old');

    $this->put(route('admin.ads.update', $this->legacy), [
        'name' => 'إعلان قديم', 'type' => '1', 'path_type' => '1', 'link' => 'ttps://www.youtube.com/watch?v=rxg2d7ZUN', 'position' => '4',
        'ads_show_type' => 'numview', 'required_num_view' => '10000000',
        'image_file' => UploadedFile::fake()->createWithContent('n.gif', base64_decode(B6A_GIF)),
    ])->assertSessionHas('success');

    expect(H::row($this->legacy)->image_path)->toEndWith('.gif')
        ->and(file_get_contents($this->media.'/bann/1742505317.jpg'))->toBe('old');
});

it('rejects executable, SVG, disguised and oversized uploads, and writes no file', function (string $name, string $content) {
    $this->actingAs(H::admin(), 'admin');
    $before = H::snapshot();

    $this->post(route('admin.ads.store'), H::imageInput([
        'path_type' => '1', 'image_url' => '', 'image_file' => UploadedFile::fake()->createWithContent($name, $content),
    ]))->assertSessionHasErrors('image_file');

    expect(H::snapshot())->toBe($before)->and(is_dir($this->media.'/bann') ? glob($this->media.'/bann/*') : [])->toBe([]);
})->with([
    'php file' => ['shell.php', '<?php system($_GET["c"]); ?>'],
    'php named .png' => ['shell.png', '<?php system($_GET["c"]); ?>'],
    'image bytes named .php' => ['shell.php', base64_decode(B6A_GIF)],
    'double extension' => ['banner.png.php', base64_decode(B6A_PNG)],
    'svg' => ['banner.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'svg named .png' => ['banner.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'html named .jpg' => ['banner.jpg', '<html><body><script>alert(1)</script></body></html>'],
    'truncated png header' => ['banner.png', substr(base64_decode(B6A_PNG), 0, 24)],
    'over 2 MiB' => ['big.png', base64_decode(B6A_PNG).str_repeat("\0", 2 * 1024 * 1024 + 10)],
]);

// ─── HTTP methods and CSRF ───────────────────────────────────────────────────

it('state changes are never reachable by GET (or by the wrong verb)', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $before = H::snapshot();

    $this->get('/admincp/ads/'.$this->legacy.'/toggle?show=1')->assertStatus(405);
    $this->post('/admincp/ads/'.$this->legacy, H::imageInput())->assertStatus(405);   // update is PUT, delete is DELETE
    $this->get('/admincp/ads/'.$this->legacy)->assertStatus(405);
    $this->put(route('admin.ads.store'), H::imageInput())->assertStatus(405);

    expect(H::snapshot())->toBe($before);
});

it('every form carries a CSRF token, and the web group\'s CSRF middleware rejects a tokenless POST', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    foreach ([route('admin.ads.index'), route('admin.ads.create'), route('admin.ads.edit', $this->legacy)] as $page) {
        expect($this->get($page)->assertOk()->getContent())->toContain('name="_token"');
    }

    expect(app('router')->getMiddlewareGroups()['web'])->toContain(PreventRequestForgery::class);

    // Laravel skips CSRF while running tests; exercise the web group's middleware directly with that bypass off,
    // for a cross-site POST without a token.
    $middleware = new class(app(), app('encrypter')) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    };
    $request = Request::create(route('admin.ads.toggle', $this->legacy), 'POST', ['show' => '1']);
    $request->headers->set('Sec-Fetch-Site', 'cross-site');
    $request->setLaravelSession(app('session.store'));

    expect(fn () => $middleware->handle($request, fn () => response('passed')))->toThrow(TokenMismatchException::class);
});

// ─── stored XSS in administrative previews ───────────────────────────────────

it('escapes ad names, URLs and links in the list and in the edit preview; unsafe URLs are not rendered as src/href', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $xss = H::legacyRow([
        'name' => '<img src=x onerror=alert(1)>', 'image_path' => 'javascript:alert(2)', 'link' => 'javascript:alert(3)', 'path_type' => 2,
    ]);

    $list = $this->get(route('admin.ads.index'))->assertOk()->getContent();
    expect($list)->not->toContain('<img src=x onerror=alert(1)>')->toContain(e('<img src=x onerror=alert(1)>'));

    $edit = $this->get(route('admin.ads.edit', $xss))->assertOk()->getContent();
    expect($edit)->not->toContain('<img src=x onerror')
        ->not->toContain('src="javascript:alert')->not->toContain('href="javascript:alert')
        ->toContain('value="javascript:alert(3)"');   // shown as an escaped form value only
});

it('the preview renders a safe http(s) image as <img>', function () {
    $this->actingAs(H::admin(), 'admin');

    expect($this->get(route('admin.ads.edit', $this->legacy))->assertOk()->getContent())
        ->toContain('<img src="https://way2allah.com/media/bann/1742505317.jpg"');
});

// ─── audit ───────────────────────────────────────────────────────────────────

it('audits create, edit, toggle and delete with actor, entity and structured values', function () {
    $admin = H::admin();
    $this->actingAs($admin, 'admin');

    $this->post(route('admin.ads.store'), H::imageInput())->assertRedirect();
    $id = (int) DB::connection('main')->table('nuke_ads')->max('id');
    $this->put(route('admin.ads.update', $id), H::imageInput(['name' => 'معدل']))->assertSessionHas('success');
    $this->post(route('admin.ads.toggle', $id), ['show' => '0'])->assertSessionHas('success');
    $this->delete(route('admin.ads.destroy', $id))->assertSessionHas('success');

    $rows = H::auditRows();
    expect(collect($rows)->pluck('action')->all())->toBe(['add_ad', 'edit_ad', 'toggle_ad', 'del_ad'])
        ->and(collect($rows)->pluck('module')->unique()->all())->toBe(['ads'])
        ->and(collect($rows)->pluck('entity_type')->unique()->all())->toBe(['ad'])
        ->and(collect($rows)->every(fn ($r) => (int) $r->entity_id === $id && (int) $r->actor_admin_id === (int) $admin->id))->toBeTrue();

    $edit = json_decode($rows[1]->metadata, true);
    expect($edit)->toBe(['before' => ['name' => 'إعلان جديد'], 'after' => ['name' => 'معدل']]);
    expect(json_decode($rows[2]->metadata, true))->toBe(['before' => ['show' => 1], 'after' => ['show' => 0]]);
});

it('never copies raw ad code (or markup names) into the audit log: a fingerprint is recorded instead', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $code = '<script>alert("x")</script>';

    $this->post(route('admin.ads.store'), H::imageInput(['name' => '<b>اسم</b>', 'type' => '0', 'google_code' => $code, 'path_type' => '', 'image_url' => '']))->assertRedirect();
    $this->delete(route('admin.ads.destroy', $this->code))->assertSessionHas('success');

    foreach (H::auditRows() as $row) {
        expect($row->metadata)->not->toContain('<script')->not->toContain('<b>')->not->toContain('<ins');
    }
    $created = json_decode(H::auditRows()[0]->metadata, true)['created'];
    expect($created['image_path'])->toBe(['code_sha256' => hash('sha256', $code), 'length' => mb_strlen($code)])
        ->and($created['name'])->toBe(['redacted' => 'markup', 'length' => mb_strlen('<b>اسم</b>')]);
});

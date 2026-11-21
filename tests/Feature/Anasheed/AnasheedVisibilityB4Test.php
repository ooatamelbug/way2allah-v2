<?php

/*
 * F08 / B4 — hide / unhide a Variety (Anasheed) item through the legacy `hidden` column: the admin toggle
 * (authorization, CSRF, audit), and every Laravel public surface (listings, search, home widgets, related lists,
 * direct access, playback, downloads, comments, send-to-friend), cache behaviour, and the admin preview.
 */

use App\Domain\Admin\Actions\Anasheed\SetAnasheedItemVisibilityAction;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Services\ContentListingService;
use App\Support\Http\AdminViewer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\AnasheedVisibilityHarness as H;

uses(RefreshDatabase::class);

const B4_SECRET = 'B4-SECRET-CLIP';

beforeEach(function () {
    H::setUp();
    Mail::fake();
    H::department(98, 300);     // «الأناشيد»
    H::department(57, 570);     // «الكرتون» (home widget)
    H::department(12, 120);     // «الأفلام الوثائقية» (home widget)
    H::department(158, 1580);   // «إصدارات الموقع» (home widget)
    $this->visible = H::item(1, 'مقطع ظاهر', 300, 98, ['fixed' => 1]);
    $this->clip = H::item(2, B4_SECRET, 300, 98, ['hidden' => 1, 'fixed' => 1, 'hits' => 999, 'downcount' => 999, 'lastvisit' => 1900000000, 'cat_id' => '|5|']);
    foreach ([[3, 570, 57], [4, 120, 12], [5, 1580, 158]] as [$id, $group, $root]) {
        H::item($id, 'مقطع ظاهر '.$id, $group, $root);
    }
    foreach ([[6, 570, 57], [7, 120, 12], [8, 1580, 158]] as [$id, $group, $root]) {
        H::item($id, B4_SECRET.'-'.$id, $group, $root, ['hidden' => 1]);
    }
});

function b4Hide(int $id, bool $hidden, ?object $actor = null): void
{
    test()->actingAs($actor ?? H::admin(aid: 'b4-toggler-'.uniqid()), 'admin');
    test()->put(route('admin.anasheed.items.visibility', $id), ['hidden' => $hidden ? '1' : '0'])->assertRedirect('/var-item-'.$id.'.htm');
    auth('admin')->forgetUser();
}

/** Every public HTML surface that may list or show the clip, as a visitor sees it. */
function b4PublicSurfaces(): array
{
    $t = test();

    return [
        'home' => $t->get('/')->assertOk()->getContent(),
        'group page' => $t->get('/var-group-300.htm')->assertOk()->getContent(),
        'group page (paged)' => $t->get('/var-group-300-page-1.htm')->assertOk()->getContent(),
        'department news' => $t->get('/anasheed-news.htm')->assertOk()->getContent(),
        'related lists on a sibling item' => $t->get('/var-item-1.htm')->assertOk()->getContent(),
        'search' => $t->post('/search.htm', ['kh_title' => 'CLIP', 'kh_dept' => 'anasheed'])->assertOk()->getContent(),
        'group .grx playlist' => $t->get('/var-series-300.grx')->assertOk()->getContent(),
    ];
}

// ─── the toggle ──────────────────────────────────────────────────────────────

it('hides and unhides by writing only `hidden`; is_published, ids and every other column are untouched; audited', function () {
    $before = H::row($this->visible);
    $admin = H::admin();
    $this->actingAs($admin, 'admin');

    $this->put(route('admin.anasheed.items.visibility', $this->visible), ['hidden' => '1'])
        ->assertRedirect('/var-item-1.htm')->assertSessionHas('contextual_admin_success');
    expect(H::row($this->visible))->toBe(array_merge($before, ['hidden' => 1]));

    $this->put(route('admin.anasheed.items.visibility', $this->visible), ['hidden' => '0'])->assertRedirect('/var-item-1.htm');
    expect(H::row($this->visible))->toBe($before);

    $audit = H::audit();
    expect($audit)->toHaveCount(2)
        ->and([$audit[0]->action, $audit[0]->module, $audit[0]->entity_type, (int) $audit[0]->entity_id, (int) $audit[0]->actor_admin_id])
        ->toBe(['hide_item', 'anasheed', 'item', $this->visible, (int) $admin->getKey()])
        ->and(json_decode($audit[0]->metadata, true))->toBe(['hidden' => ['before' => 0, 'after' => 1]])
        ->and($audit[1]->action)->toBe('unhide_item')
        ->and(json_decode($audit[1]->metadata, true))->toBe(['hidden' => ['before' => 1, 'after' => 0]]);
});

it('setting the current state again writes nothing and records nothing', function () {
    $this->actingAs(H::admin(), 'admin');

    $this->put(route('admin.anasheed.items.visibility', $this->visible), ['hidden' => '0'])->assertSessionHas('contextual_admin_success', 'لم يتغير شيء');
    $this->put(route('admin.anasheed.items.visibility', 6), ['hidden' => '1'])->assertSessionHas('contextual_admin_success', 'لم يتغير شيء');

    expect(H::audit())->toBe([]);
});

it('a legacy hidden value of 2 is hidden; unhiding sets 0 and audits the original value', function () {
    DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $this->visible)->update(['hidden' => 2]);
    $this->get('/var-item-1.htm')->assertNotFound();

    b4Hide($this->visible, false);

    expect((int) H::row($this->visible)['hidden'])->toBe(0)
        ->and(json_decode(H::audit()[0]->metadata, true))->toBe(['hidden' => ['before' => 2, 'after' => 0]]);
    $this->get('/var-item-1.htm')->assertOk();
});

it('validates the target state', function (mixed $value) {
    $this->actingAs(H::admin(), 'admin');
    $before = H::row($this->visible);

    $this->put(route('admin.anasheed.items.visibility', $this->visible), $value === null ? [] : ['hidden' => $value])->assertSessionHasErrors('hidden');

    expect(H::row($this->visible))->toBe($before)->and(H::audit())->toBe([]);
})->with(['missing' => null, 'two' => '2', 'text' => 'yes', 'array' => [['1']]]);

it('does not delete or touch the item\'s media files', function () {
    $media = sys_get_temp_dir().'/w2a-b4-'.uniqid();
    $frame = $media.'/anasheed/frame/0/1.jpg';
    mkdir(dirname($frame), 0775, true);
    file_put_contents($frame, 'jpeg');
    config(['admin_anasheed.media_root' => $media]);

    b4Hide($this->visible, true);
    b4Hide($this->visible, false);

    expect(file_get_contents($frame))->toBe('jpeg');
    unlink($frame);
});

// ─── authorization, methods, CSRF ────────────────────────────────────────────

it('only anasheed.editanasheed holders (and super-admins) can change visibility', function () {
    $before = H::row($this->visible);

    expect($this->put(route('admin.anasheed.items.visibility', $this->visible), ['hidden' => '1'])->status())->toBeIn([302, 401]);   // visitor
    expect(H::row($this->visible))->toBe($before);

    foreach ([['anasheed.addanasheed', 'anasheed.delanasheed', 'anasheed.addmirror'], ['khotab.edititem']] as $i => $permissions) {
        $this->actingAs(H::admin($permissions, 'b4-other-'.$i), 'admin');
        $this->put(route('admin.anasheed.items.visibility', $this->visible), ['hidden' => '1'])->assertForbidden();
    }
    expect(H::row($this->visible))->toBe($before)->and(H::audit())->toBe([]);

    $this->actingAs(H::superAdmin(), 'admin');
    $this->put(route('admin.anasheed.items.visibility', $this->visible), ['hidden' => '1'])->assertRedirect('/var-item-1.htm');
    expect((int) H::row($this->visible)['hidden'])->toBe(1);
});

it('the action re-checks authorization itself', function () {
    $outsider = H::admin(['anasheed.addanasheed'], 'b4-outsider');
    $item = AnasheedItem::findOrFail($this->visible);

    expect(fn () => app(SetAnasheedItemVisibilityAction::class)->execute($outsider, $item, true))->toThrow(AuthorizationException::class)
        ->and((int) H::row($this->visible)['hidden'])->toBe(0);
});

it('is a PUT behind CSRF; GET and POST change nothing', function () {
    $this->actingAs(H::admin(), 'admin');
    $url = route('admin.anasheed.items.visibility', $this->visible);

    $this->get($url)->assertStatus(405);
    $this->post($url, ['hidden' => '1'])->assertStatus(405);
    expect((int) H::row($this->visible)['hidden'])->toBe(0);

    expect(app('router')->getMiddlewareGroups()['web'])->toContain(PreventRequestForgery::class);
    $middleware = new class(app(), app('encrypter')) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    };
    $forged = Request::create($url, 'POST', ['_method' => 'PUT', 'hidden' => '1']);
    $forged->headers->set('Sec-Fetch-Site', 'cross-site');
    $forged->setLaravelSession(app('session.store'));
    expect(fn () => $middleware->handle($forged, fn () => response('passed')))->toThrow(TokenMismatchException::class);

    $route = collect(app('router')->getRoutes()->getRoutesByName())->get('admin.anasheed.items.visibility');
    expect($route->methods())->toBe(['PUT'])->and($route->gatherMiddleware())->toContain('admin.permission:anasheed.editanasheed');
});

it('offers the control on the admin edit page and in the item page toolbar, with the target state', function () {
    $this->actingAs(H::admin(), 'admin');

    expect($this->get(route('admin.anasheed.items.edit', $this->visible))->assertOk()->getContent())
        ->toContain('action="'.route('admin.anasheed.items.visibility', $this->visible).'"')
        ->toContain('name="hidden" value="1"')->toContain('إخفاء المادة')->toContain('ظاهرة للزوار');

    expect($this->get(route('admin.anasheed.items.edit', 6))->assertOk()->getContent())
        ->toContain('name="hidden" value="0"')->toContain('إظهار المادة')->toContain('مخفية عن الزوار');

    expect($this->get('/var-item-1.htm')->assertOk()->getContent())->toContain('إخفاء المادة')
        ->toContain(route('admin.anasheed.items.visibility', $this->visible));
});

// ─── public surfaces ─────────────────────────────────────────────────────────

it('a hidden item appears on no public surface, and its title is in no response', function () {
    foreach (b4PublicSurfaces() as $surface => $html) {
        expect($surface)->toBeString();
        expect($html)->not->toContain(B4_SECRET)
            ->not->toContain('/var-item-2.htm')
            ->not->toContain('/var-download-2.htm');
    }
});

it('home widgets (latest per department and «تشاهدون الآن») skip hidden items', function () {
    $listing = app(ContentListingService::class);

    foreach ([158, 12, 57, 98] as $root) {
        expect($listing->homeAnasheedByParent($root, 50)->pluck('id')->all())->not->toContain(2)->not->toContain(6)->not->toContain(7)->not->toContain(8);
    }
    expect($listing->homeTrendingAnasheed(50)->pluck('id')->all())->not->toContain(2)->toContain(1);
});

it('category listing, advanced search and the .grx playlist skip hidden items', function () {
    $listing = app(ContentListingService::class);

    expect($listing->anasheedItemsByCategory(5)->pluck('id')->all())->toBe([])
        ->and(collect($listing->anasheedAdvancedSearch(['title' => 'CLIP'], 98)->items())->pluck('id')->all())->toBe([])
        ->and($listing->anasheedItemsForGroupDownload(300)->pluck('id')->all())->toBe([1]);
});

it('direct access, the paged URL, playback, downloads, comments and send-to-friend are refused for visitors', function () {
    $before = H::row($this->clip);

    $this->get('/var-item-2.htm')->assertNotFound();
    $this->get('/var-item-2-page-2.htm')->assertNotFound();
    $this->get('/var-download-2.htm')->assertNotFound();
    $this->call('HEAD', '/var-download-2.htm')->assertNotFound();
    $this->get('/var-mirror-2-'.DB::connection('main')->table('nuke_anasheed_mirror')->where('khid', 2)->value('id').'.htm')->assertNotFound();
    expect($this->post('/media-player', ['id' => 2, 'type' => 'anasheed'])->assertOk()->getContent())->toBe('');
    $this->post('/var-item-2/comments', ['user_nickname' => 'زائر', 'user_comment' => 'تعليق'])->assertNotFound();
    $this->post('/send-friend-anasheed-2.htm', ['your_name' => 'أ', 'your_email' => 'a@example.org', 'friend_name' => 'ب', 'friend_email' => 'b@example.org'])->assertNotFound();

    Mail::assertNothingSent();
    expect(H::row($this->clip))->toBe($before)
        ->and(DB::connection('main')->table('nuke_anasheed_comments')->where('khid', 2)->count())->toBe(0);
});

it('a visible item is unaffected on every route', function () {
    $this->get('/var-item-1.htm')->assertOk()->assertSee('مقطع ظاهر');
    $this->call('HEAD', '/var-download-1.htm')->assertOk()->assertHeader('Content-Disposition');   // F04: proxied download, no source contact on HEAD
    expect($this->post('/media-player', ['id' => 1, 'type' => 'anasheed'])->assertOk()->getContent())->not->toBe('');
    expect($this->post('/var-item-1/comments', ['user_nickname' => 'زائر', 'user_comment' => 'تعليق'])->assertOk()->getContent())->toBe('1');
    expect($this->post('/send-friend-anasheed-1.htm', ['your_name' => 'أ', 'your_email' => 'a@example.org', 'friend_name' => 'ب', 'friend_email' => 'b@example.org'])->assertOk()->getContent())->toBe('1');
});

// ─── hide → immediately gone; unhide → immediately back (no stale cache) ─────

it('hiding takes effect immediately on every surface, and unhiding restores it, with caches warm', function () {
    H::item(9, 'B4-TOGGLED-CLIP', 300, 98, ['fixed' => 1, 'lastvisit' => 1950000000, 'cat_id' => '|5|']);
    $listing = app(ContentListingService::class);

    $warm = b4PublicSurfaces();   // fills every cache the pages use
    foreach (['home', 'group page', 'department news', 'search', 'group .grx playlist'] as $surface) {
        expect($warm[$surface])->toContain($surface === 'group .grx playlist' ? '/var-download-9.htm' : 'B4-TOGGLED-CLIP');
    }
    $this->get('/var-item-9.htm')->assertOk();

    b4Hide(9, true);

    foreach (b4PublicSurfaces() as $surface => $html) {
        expect($html)->not->toContain('B4-TOGGLED-CLIP')->not->toContain('/var-item-9.htm')->not->toContain('/var-download-9.htm');
    }
    $this->get('/var-item-9.htm')->assertNotFound();
    expect($listing->anasheedItemsByCategory(5)->pluck('id')->all())->toBe([]);

    b4Hide(9, false);

    $restored = b4PublicSurfaces();
    foreach (['home', 'group page', 'department news', 'search'] as $surface) {
        expect($restored[$surface])->toContain('B4-TOGGLED-CLIP');
    }
    expect($restored['group .grx playlist'])->toContain('/var-download-9.htm');
    $this->get('/var-item-9.htm')->assertOk();
    expect($listing->anasheedItemsByCategory(5)->pluck('id')->all())->toBe([9]);
});

// ─── administrator preview (existing behaviour, preserved) ───────────────────

it('a signed-in administrator still opens a hidden item: OLD\'s suppressed details, a notice, a private response', function () {
    DB::connection('main')->table('nuke_anasheed_comments')->insert(['khid' => 2, 'uname' => 'U', 'comment' => 'Hidden comment', 'view' => 1, 'mytime' => 1]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', 2)->update(['comments' => 1, 'notes' => 'Hidden note']);
    $this->actingAs(H::admin(), 'admin');

    $response = $this->get('/var-item-2.htm')->assertOk();
    $html = $response->getContent();

    expect($html)->toContain(B4_SECRET)->toContain('هذه المادة مخفية عن الزوار')->toContain('إظهار المادة')
        ->not->toContain('/var-download-2.htm')->not->toContain('w2a_play(')->not->toContain('Hidden comment')->not->toContain('Hidden note')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    // Media stay refused for administrators too (unchanged).
    $this->get('/var-download-2.htm')->assertNotFound();
});

it('on the group page an administrator sees hidden items marked «مخفية»; visitors do not; the admin response is private', function () {
    $this->actingAs(H::admin(), 'admin');
    $response = $this->get('/var-group-300.htm')->assertOk();

    expect($response->getContent())->toContain(B4_SECRET)->toContain('مخفية')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    // Visitors: covered by "a hidden item appears on no public surface" (group page and its paged URL).
});

it('visitors get no hidden marker on the group page', function () {
    expect($this->get('/var-group-300.htm')->assertOk()->getContent())->not->toContain(B4_SECRET)->not->toContain('w2a-hidden-item-marker');
});

it('visitor Variety pages are not sent as publicly cacheable (no shared cache can keep a stale listing)', function () {
    foreach (['/var-group-300.htm', '/anasheed-news.htm', '/var-item-1.htm', '/'] as $page) {
        $cacheControl = (string) $this->get($page)->assertOk()->headers->get('Cache-Control');
        expect($cacheControl)->not->toContain('public')->not->toContain('s-maxage')->not->toMatch('/max-age=[1-9]/');
    }
});

// ─── hidden-item preview authorization (owner review, 2026-11-15) ───────────

it('administrators WITHOUT anasheed.editanasheed get exactly what visitors get for a hidden item', function (array $permissions) {
    $this->actingAs(H::admin($permissions, 'b4-noperm-'.md5(implode(',', $permissions))), 'admin');
    $mirror = DB::connection('main')->table('nuke_anasheed_mirror')->where('khid', 2)->value('id');
    $before = H::row($this->clip);

    $this->get('/var-item-2.htm')->assertNotFound()->assertDontSee(B4_SECRET);
    $this->get('/var-item-2-page-2.htm')->assertNotFound()->assertDontSee(B4_SECRET);
    $this->get('/var-download-2.htm')->assertNotFound();
    $this->get('/var-mirror-2-'.$mirror.'.htm')->assertNotFound();
    expect($this->post('/media-player', ['id' => 2, 'type' => 'anasheed'])->assertOk()->getContent())->toBe('')
        ->and($this->post('/media-player', ['id' => $mirror, 'type' => 'anasheed_mirror'])->assertOk()->getContent())->toBe('');
    expect($this->get('/var-group-300.htm')->assertOk()->getContent())->not->toContain(B4_SECRET)->not->toContain('w2a-hidden-item-marker');
    $this->put(route('admin.anasheed.items.visibility', 2), ['hidden' => '0'])->assertForbidden();

    expect(H::row($this->clip))->toBe($before);
    // A visible item is unaffected for them.
    $this->get('/var-item-1.htm')->assertOk()->assertSee('مقطع ظاهر');
})->with([
    'no permissions at all' => [[]],
    'other Anasheed keys' => [['anasheed.addanasheed', 'anasheed.delanasheed', 'anasheed.addmirror', 'anasheed.editmirror', 'anasheed.addcomment']],
    'a Khotab key' => [['khotab.edititem']],
]);

it('a super-admin (bypass) and an anasheed.editanasheed holder may preview a hidden item; media stay refused', function (string $who) {
    $this->actingAs($who === 'super-admin' ? H::superAdmin() : H::admin(), 'admin');

    $response = $this->get('/var-item-2.htm')->assertOk();
    expect($response->getContent())->toContain(B4_SECRET)->toContain('هذه المادة مخفية عن الزوار')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->get('/var-item-2-page-2.htm')->assertOk();
    expect($this->get('/var-group-300.htm')->assertOk()->getContent())->toContain(B4_SECRET)->toContain('w2a-hidden-item-marker');
    $this->get('/var-download-2.htm')->assertNotFound();
    expect($this->post('/media-player', ['id' => 2, 'type' => 'anasheed'])->assertOk()->getContent())->toBe('');
})->with(['super-admin', 'editanasheed holder']);

it('AdminViewer::can(): false for visitors and non-holders, true for holders and super-admins', function () {
    $viewer = fn () => app(AdminViewer::class);

    expect($viewer()->can('anasheed.editanasheed'))->toBeFalse();
    $this->actingAs(H::admin(['khotab.edititem'], 'b4-can-1'), 'admin');
    expect($viewer()->can('anasheed.editanasheed'))->toBeFalse()->and($viewer()->can('khotab.edititem'))->toBeTrue();
    $this->actingAs(H::admin(['anasheed.editanasheed'], 'b4-can-2'), 'admin');
    expect($viewer()->can('anasheed.editanasheed'))->toBeTrue();
    $this->actingAs(H::superAdmin('b4-can-3'), 'admin');
    expect($viewer()->can('anasheed.editanasheed'))->toBeTrue()->and($viewer()->can('not.a.seeded.key'))->toBeTrue();
});

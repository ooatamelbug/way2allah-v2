<?php

use App\Domain\Admin\Jobs\RecheckKhotabLinkSizeJob;
use App\Domain\Admin\Jobs\RecheckMirrorLinkSizeJob;
use App\Domain\Admin\Support\AdminDashboardModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\Support\MirrorAdminHarness as H;
use Tests\Support\ReportingHarness as R;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 2 — the `down = 0` lost-link report and the
 * contextual item link-health page, both gated by `khotab.repair`.
 */
beforeEach(function () {
    R::setUp();
});

function reportAuthor(string $prename, string $name): int
{
    return (int) DB::connection('main')->table('nuke_islamic_authors')->insertGetId(['prename' => $prename, 'name' => $name]);
}

// --------------------------------------------------------------- access

it('gates both pages with khotab.repair', function () {
    $item = H::khotabItem();

    $this->get(route('admin.link-quality.khotab.lost'))->assertUnauthorized();

    $this->actingAs(H::admin(H::KHOTAB_PERMISSIONS, 'mirrors-only'), 'admin');
    $this->get(route('admin.link-quality.khotab.lost'))->assertForbidden();
    $this->get(route('admin.link-quality.khotab.item', $item))->assertForbidden();

    $this->actingAs(H::admin(['khotab.repair'], 'repairer'), 'admin');
    $this->get(route('admin.link-quality.khotab.lost'))->assertOk();
    $this->get(route('admin.link-quality.khotab.item', $item))->assertOk();

    $this->actingAs(H::superAdmin(), 'admin');
    $this->get(route('admin.link-quality.khotab.lost'))->assertOk();
});

it('shows the lost-link entry in the sidebar to khotab.repair holders', function () {
    $labels = fn ($admin) => collect(app(AdminDashboardModules::class)->visibleFor($admin))
        ->flatMap(fn ($m) => array_merge([$m['label']], array_column($m['children'] ?? [], 'label')))->all();

    expect($labels(H::admin(['khotab.repair'], 'r')))->toContain('الوصلات التي لا تعمل')
        ->and($labels(H::admin(H::KHOTAB_PERMISSIONS, 'm')))->not->toContain('الوصلات التي لا تعمل');
});

// ---------------------------------------------------------- lost links

it('lists only down = 0 items, oldest id first, with OLD\'s headline total', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $a = H::khotabItem(['down' => 0, 'title' => 'lost-item-a']);
    H::khotabItem(['down' => 1, 'title' => 'fine-item-1']);
    H::khotabItem(['down' => 2, 'title' => 'fine-item-2']);
    $b = H::khotabItem(['down' => 0, 'title' => 'lost-item-b']);

    $html = $this->get(route('admin.link-quality.khotab.lost'))->assertOk()
        ->assertSee('عدد الوصلات التي لا تعمل = 2 وصلة')
        ->assertDontSee('fine-item-1')->assertDontSee('fine-item-2')
        ->getContent();

    expect(strpos($html, 'lost-item-a'))->toBeLessThan(strpos($html, 'lost-item-b'));
});

it('renders author, group and series, and links each row to the item and mirror pages', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $author = reportAuthor('الشيخ', 'محمد');
    $group = (int) DB::connection('main')->table('nuke_islamic_groups')->insertGetId(['title' => 'مجموعة الدروس']);
    $series = (int) DB::connection('main')->table('nuke_islamic_series')->insertGetId(['title' => 'سلسلة التفسير']);
    $item = H::khotabItem(['down' => 0, 'author' => $author, 'group_id' => $group, 'ser_id' => $series, 'link' => 'https://cdn.example/lost.mp3']);

    $this->get(route('admin.link-quality.khotab.lost'))->assertOk()
        ->assertSee('الشيخ محمد')->assertSee('مجموعة الدروس')->assertSee('سلسلة التفسير')
        ->assertSee('https://cdn.example/lost.mp3')
        ->assertSee(route('admin.link-quality.khotab.item', $item), false)
        ->assertSee(route('admin.mirrors.khotab.show', $item), false);
});

it('tolerates a missing author, group or series', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    H::khotabItem(['down' => 0, 'title' => 'orphaned-refs', 'author' => 999, 'group_id' => 999, 'ser_id' => 999]);

    $this->get(route('admin.link-quality.khotab.lost'))->assertOk()->assertSee('orphaned-refs');
});

it('does not offer OLD\'s dead repair form or the externally-owned item editor', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    H::khotabItem(['down' => 0]);

    $this->get(route('admin.link-quality.khotab.lost'))->assertOk()
        ->assertDontSee('KhotabRepairLostedLink')->assertDontSee('KhotabEditKhotab');
});

it('escapes item titles and links', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    H::khotabItem(['down' => 0, 'title' => '<b>lost-title</b>', 'link' => '"><script>linkEvil()</script>']);

    $html = $this->get(route('admin.link-quality.khotab.lost'))->assertOk()->getContent();

    expect($html)->not->toContain('<b>lost-title</b>')->and($html)->not->toContain('<script>linkEvil()')
        ->and($html)->toContain('&lt;b&gt;lost-title&lt;/b&gt;');
});

it('paginates at OLD\'s 50 per page', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    for ($i = 1; $i <= 55; $i++) {
        H::khotabItem(['down' => 0, 'title' => sprintf('lostrow-%02d', $i)]);
    }

    preg_match_all('/lostrow-(\d\d)/', $this->get(route('admin.link-quality.khotab.lost'))->getContent(), $m1);
    preg_match_all('/lostrow-(\d\d)/', $this->get(route('admin.link-quality.khotab.lost', ['page' => 2]))->getContent(), $m2);

    expect(array_map('intval', $m1[1]))->toBe(range(1, 50))
        ->and(array_map('intval', $m2[1]))->toBe(range(51, 55));
});

it('shows an empty state', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $this->get(route('admin.link-quality.khotab.lost'))->assertOk()
        ->assertSee('عدد الوصلات التي لا تعمل = 0 وصلة')->assertSee('لا توجد وصلات معطلة.');
});

it('keeps the query count flat as the report fills — no N+1 on author, group or series', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $count = function () {
        $n = 0;
        DB::connection('main')->listen(function ($q) use (&$n) {
            if ($q->connectionName === 'main') {
                $n++;
            }
        });
        $this->get(route('admin.link-quality.khotab.lost'))->assertOk();

        return $n;
    };
    $seed = function (int $count) {
        for ($i = 0; $i < $count; $i++) {
            H::khotabItem([
                'down' => 0,
                'author' => reportAuthor('p', "a{$i}"),
                'group_id' => (int) DB::connection('main')->table('nuke_islamic_groups')->insertGetId(['title' => "g{$i}"]),
                'ser_id' => (int) DB::connection('main')->table('nuke_islamic_series')->insertGetId(['title' => "s{$i}"]),
            ]);
        }
    };

    $seed(1);
    $count();          // warm-up
    $few = $count();
    $seed(30);
    $many = $count();

    expect($many)->toBe($few);
});

// ---------------------------------------------------- item link health

it('shows the primary link and every mirror with their stored quality values', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $item = H::khotabItem(['link' => 'https://cdn.example/primary.mp3', 'linksize' => 1000, 'online' => 1000, 'percent' => 100, 'down' => 1, 'trial' => 0, 'checktime' => 1760000000]);
    $mirror = H::khotabMirror($item, ['link' => 'https://cdn.example/m1.mp3', 'linksize' => 500, 'online' => 0, 'down' => 2, 'trial' => 4]);

    $this->get(route('admin.link-quality.khotab.item', $item))->assertOk()
        ->assertSee('https://cdn.example/primary.mp3')
        ->assertSee('https://cdn.example/m1.mp3')
        ->assertSee(date('Y-m-d H:i', 1760000000))
        ->assertSee(route('admin.link-quality.khotab.recheck', $item), false)
        ->assertSee(route('admin.link-quality.mirror.recheck', $mirror), false)
        ->assertSee(route('admin.mirrors.khotab.show', $item), false);
});

it('offers fix-size only where a measured size exists and differs', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $item = H::khotabItem(['linksize' => 1000, 'online' => 1000]);                 // matches -> no fix
    $neverMeasured = H::khotabMirror($item, ['linksize' => 500, 'online' => 0]);    // unmeasured -> no fix
    $differs = H::khotabMirror($item, ['linksize' => 500, 'online' => 800]);        // differs -> fix

    $this->get(route('admin.link-quality.khotab.item', $item))->assertOk()
        ->assertDontSee(route('admin.link-quality.khotab.fix-size', $item), false)
        ->assertDontSee(route('admin.link-quality.mirror.fix-size', $neverMeasured), false)
        ->assertSee(route('admin.link-quality.mirror.fix-size', $differs), false);
});

it('drives the EXISTING recheck and fix-size actions from item context, unchanged', function () {
    Bus::fake();
    $this->actingAs(H::admin(['khotab.repair'], 'repairer'), 'admin');
    $item = H::khotabItem(['linksize' => 100, 'online' => 900]);
    $mirror = H::khotabMirror($item, ['linksize' => 500, 'online' => 800]);
    $page = route('admin.link-quality.khotab.item', $item);

    $this->from($page)->post(route('admin.link-quality.khotab.recheck', $item))->assertRedirect($page);
    $this->from($page)->post(route('admin.link-quality.mirror.recheck', $mirror))->assertRedirect($page);
    Bus::assertDispatched(RecheckKhotabLinkSizeJob::class);
    Bus::assertDispatched(RecheckMirrorLinkSizeJob::class);

    $this->from($page)->post(route('admin.link-quality.mirror.fix-size', $mirror))->assertRedirect($page);
    expect((int) DB::connection('main')->table('nuke_islamic_mirror')->where('id', $mirror)->value('linksize'))->toBe(800);
});

it('404s an item that does not exist', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $this->get(route('admin.link-quality.khotab.item', 999999))->assertNotFound();
});

it('writes nothing when either report page is viewed', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $item = H::khotabItem(['down' => 0]);
    H::khotabMirror($item);

    expect(R::writesDuring(function () use ($item) {
        $this->get(route('admin.link-quality.khotab.lost'))->assertOk();
        $this->get(route('admin.link-quality.khotab.item', $item))->assertOk();
    }))->toBe([]);
});

// ------------------------------- integration into the Phase 1 item page

it('links the Phase 1 item page to link health for khotab.repair holders and super-admins', function (Closure $who) {
    $this->actingAs($who(), 'admin');
    $item = H::khotabItem();

    $this->get(route('admin.mirrors.khotab.show', $item))->assertOk()
        ->assertSee(route('admin.link-quality.khotab.item', $item), false)
        ->assertSee('صحة الروابط وإعادة الفحص');
})->with([
    'repair + mirror permissions' => [fn () => H::admin([...H::KHOTAB_PERMISSIONS, 'khotab.repair'], 'both')],
    'super-admin' => [fn () => H::superAdmin()],
]);

it('hides the link from an admin without khotab.repair, so it never leads to a 403', function () {
    $this->actingAs(H::admin(H::KHOTAB_PERMISSIONS, 'mirrors-only'), 'admin');
    $item = H::khotabItem();

    $this->get(route('admin.mirrors.khotab.show', $item))->assertOk()
        ->assertDontSee(route('admin.link-quality.khotab.item', $item), false);

    // The page it would have linked to is indeed refused to this admin.
    $this->get(route('admin.link-quality.khotab.item', $item))->assertForbidden();
});

it('triggers nothing when the Phase 1 item page renders the link', function () {
    Bus::fake();
    $this->actingAs(H::superAdmin(), 'admin');
    $item = H::khotabItem();
    H::khotabMirror($item);

    expect(R::writesDuring(fn () => $this->get(route('admin.mirrors.khotab.show', $item))->assertOk()))->toBe([]);
    Bus::assertNothingDispatched();
});

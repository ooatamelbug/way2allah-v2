<?php

/*
 * OLD public admin parity — the Khotab item page (owner, 2026-10-21):
 * «لوحة تحكم المشرفين» (OLD AdminPanel()), «إحصائيات متطورة»
 * (AdvancedStats()), the comment controls (Comments()), and admin visibility
 * of hidden content (owner decision: admins see what OLD showed them,
 * visitors are unchanged). Reproduced from production OLD (= the old-project
 * working copy). The fixture items mirror the investigation's representative
 * items: 106335 (mirror, comments, categories, pdf, docx), 76392 (banner),
 * 140204 (banner requested), 108823 (no pdf / docx / banner), 240988
 * (promotional file requested), 256050 (hidden).
 */

use App\Domain\Admin\Http\Controllers\KhotabMirrorController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    foreach ([
        'nuke_islamic_locations' => MainSchema::nukeIslamicLocations(),
        'nuke_islamic_authors_location' => MainSchema::nukeIslamicAuthorsLocation(),
        'nuke_islamic_groups_location' => MainSchema::nukeIslamicGroupsLocation(),
        'nuke_islamic_series_location' => MainSchema::nukeIslamicSeriesLocation(),
        'nuke_brokenlinks' => MainSchema::nukeBrokenlinks(),
    ] as $table => $schema) {
        Schema::connection('main')->create($table, $schema);
    }

    $this->root = sys_get_temp_dir().'/w2a-item-media-'.uniqid();
    config(['admin_khotab.media_root' => $this->root, 'media.base_url' => '/media']);

    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.', 'vedio' => 10, 'audio' => 4, 'pdf' => 3, 'docx' => 2]);
    H::insert('nuke_islamic_authors', ['id' => 8, 'name' => 'آخر', 'prename' => 'أ.', 'vedio' => 5, 'audio' => 1]);
    H::insert('nuke_sat_channels', ['id' => 3, 'title' => 'قناة']);
    H::insert('nuke_islamic_locations', ['id' => 5, 'title' => 'مسجد']);
    H::insert('nuke_islamic_locations', ['id' => 6, 'title' => 'قاعة']);
    H::category(1, 'العقيدة');

    $this->group = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'مجموعة', 'hidden' => 0, 'vedio' => 1, 'count' => 5]);
    $this->group2 = H::insert('nuke_islamic_groups', ['author_id' => 8, 'title' => 'مجموعة ب', 'hidden' => 0, 'vedio' => 1, 'count' => 0]);
    $this->audioGroup = H::insert('nuke_islamic_groups', ['author_id' => 8, 'title' => 'صوتية', 'hidden' => 0, 'vedio' => 0, 'count' => 0]);
    $this->series = H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => $this->group, 'title' => 'سلسلة', 'hidden' => 0, 'vedio' => 1, 'count' => 1]);
    $this->series2 = H::insert('nuke_islamic_series', ['author_id' => 8, 'group_id' => $this->group2, 'title' => 'سلسلة ب', 'hidden' => 0, 'vedio' => 1, 'count' => 0]);

    $base = ['author' => 7, 'vedio' => 1, 'group_id' => $this->group, 'channel_id' => 0, 'linksize' => 1048576, 'down' => 2];
    // 106335: in a group and a series, a mirror, published comments, a transcript and a Word file.
    $this->item = H::insert('nuke_islamic_khotab', $base + ['title' => 'درس الفجر', 'ser_id' => $this->series, 'location_id' => 5, 'mirror' => 1,
        'comments' => 2, 'cat' => '|1|', 'pdf' => 810, 'docx' => 1, 'link' => 'https://archive.org/download/myitem/a.mp4', 'trial' => 3]);
    // 76392 / 140204 / 108823 / 240988 / 256050.
    $this->bannered = H::insert('nuke_islamic_khotab', $base + ['title' => 'له بانر', 'banner' => 1]);
    $this->requested = H::insert('nuke_islamic_khotab', $base + ['title' => 'بانر مطلوب', 'banner' => -1]);
    $this->plain = H::insert('nuke_islamic_khotab', $base + ['title' => 'بلا ملفات', 'comments' => 1]);
    $this->advRequested = H::insert('nuke_islamic_khotab', $base + ['title' => 'رئيسية مطلوبة', 'advfile' => -1]);
    $this->hidden = H::insert('nuke_islamic_khotab', $base + ['title' => 'مادة مخفية', 'hidden' => 1]);

    $this->mirror = H::insert('nuke_islamic_mirror', ['khid' => $this->item, 'link' => 'https://x/a.mp4', 'comment' => 'جودة عالية', 'vedio' => 1]);
    $this->c1 = H::insert('nuke_islamic_comments', ['khid' => $this->item, 'uid' => 0, 'uname' => 'زائر', 'comment' => 'تعليق أول', 'view' => 1, 'mytime' => 1]);
    $this->c2 = H::insert('nuke_islamic_comments', ['khid' => $this->item, 'uid' => 0, 'uname' => 'زائر ثان', 'comment' => 'تعليق ثان', 'view' => 1, 'mytime' => 2]);
    $this->pending = H::insert('nuke_islamic_comments', ['khid' => $this->item, 'uid' => 0, 'uname' => 'منتظر', 'comment' => 'بانتظار', 'view' => 0, 'mytime' => 3]);
    H::insert('nuke_islamic_comments', ['khid' => $this->plain, 'uid' => 0, 'uname' => 'ز', 'comment' => 'على بلا ملفات', 'view' => 1, 'mytime' => 1]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => $this->item, 'category_id' => 1]);
    DB::connection('main')->table('nuke_islamic_authors_location')->insert(['author_id' => 7, 'location_id' => 5, 'count' => 1]);
    DB::connection('main')->table('nuke_islamic_groups_location')->insert(['group_id' => $this->group, 'location_id' => 5, 'count' => 1]);
    DB::connection('main')->table('nuke_islamic_series_location')->insert(['series_id' => $this->series, 'location_id' => 5, 'count' => 1]);

    $this->boss = H::superAdmin();
});

function page($test, int $item, $admin = null)
{
    $request = $admin ? $test->withSession(H::session($admin)) : $test;

    return $request->get("/khotab-item-{$item}.htm");
}

function col(string $table, int $id, string $column)
{
    return DB::connection('main')->table($table)->where('id', $id)->value($column);
}

function itemAudit(): array
{
    return DB::connection('main')->table('admin_audit_log')->orderBy('id')->pluck('action')->all();
}

function locationCount(string $table, string $owner, int $ownerId, int $location): ?int
{
    $count = DB::connection('main')->table($table)->where($owner, $ownerId)->where('location_id', $location)->value('count');

    return $count === null ? null : (int) $count;
}

// ─── the public page ────────────────────────────────────────────────────────

it('shows a visitor no admin markup at all, on every representative item', function () {
    foreach ([$this->item, $this->bannered, $this->requested, $this->plain, $this->advRequested] as $id) {
        $html = page($this, $id)->assertOk()->getContent();
        expect($html)->not->toContain('data-contextual-admin')->not->toContain('/admincp/')
            ->not->toContain('إحصائيات متطورة')->not->toContain('إضافة تعليق إداري')->not->toContain('تعديل التعليق')
            ->not->toContain('تنزيل الوورد');
    }
});

it('shows a super-admin OLD\'s full panel in OLD\'s order, with the migrated controls kept', function () {
    $html = page($this, $this->item, $this->boss)->assertOk()->getContent();

    expect($html)->toContain('لوحة تحكم المشرفين');
    $positions = [];
    foreach (['تعديل', 'نقل', 'مسح', 'إضافة تصنيف', 'إضافة وصلة', 'رفع بانر', 'تعديل التفريغ', 'حذف التفريغ', 'تعديل الوورد', 'حذف الوورد', 'طلب رئيسية', 'طلب بانر', 'سحب الصور', 'حالة الوصلات'] as $label) {
        $positions[$label] = strpos($html, '</i> '.$label.'</');
    }
    expect($positions)->not->toContain(false);
    $sorted = array_values($positions);
    sort($sorted);
    expect(array_values($positions))->toBe($sorted);

    expect($html)
        ->toContain('href="'.route('admin.khotab.items.edit', $this->item).'"')
        ->toContain('href="'.route('admin.khotab.items.move', $this->item).'"')
        ->toContain('href="'.route('admin.khotab.items.delete', $this->item).'"')
        ->toContain('href="'.route('admin.khotab.items.banner', $this->item).'"')
        ->toContain('href="'.route('admin.khotab.items.pdf', $this->item).'"')
        ->toContain('href="'.route('admin.khotab.items.pdf.delete', $this->item).'"')
        ->toContain('href="'.route('admin.khotab.items.docx', $this->item).'"')
        ->toContain('href="'.route('admin.khotab.items.docx.delete', $this->item).'"')
        ->toContain('action="'.route('admin.khotab.items.banner.request', $this->item).'"')
        ->toContain('action="'.route('admin.khotab.items.images', $this->item).'"')
        ->toContain('href="/media/docx/'.intdiv($this->item, 1000).'/'.$this->item.'.docx"')
        // the controls migrated in earlier phases
        ->toContain(route('admin.categories.khotab.item', $this->item))
        ->toContain('action="'.route('admin.categories.khotab.item.remove', $this->item).'"')
        ->toContain(route('admin.mirrors.khotab.show', $this->item))
        ->toContain(route('admin.mirrors.khotab.edit', $this->mirror))
        ->toContain('action="'.route('admin.mirrors.khotab.pick', $this->mirror).'"')
        ->toContain('action="'.route('admin.mirrors.khotab.destroy', $this->mirror).'"')
        ->toContain('action="'.route('admin.advfiles.request', $this->item).'"')
        ->toContain('href="'.route('admin.link-quality.khotab.item', $this->item).'"')
        // retired / dead stay absent
        ->not->toContain('اضافة للشير')->not->toContain('KhotabWaitingFB')->not->toContain('نسخ مادة');
});

it('follows OLD\'s banner / pdf / docx / advfile conditions on each representative item', function () {
    $labels = fn (int $id) => H::contextualMarkup(page($this, $id, $this->boss)->assertOk()->getContent());

    // 76392 — banner = 1: edit / delete, no request.
    expect($labels($this->bannered))->toContain('تعديل البانر')->toContain('حذف البانر')->not->toContain('رفع بانر')->not->toContain('طلب بانر');
    // 140204 — banner = -1: OLD tested banner == 0, so edit / delete and no request.
    expect($labels($this->requested))->toContain('تعديل البانر')->toContain('حذف البانر')->not->toContain('طلب بانر');
    // 108823 — nothing uploaded: the upload variants and both requests.
    expect($labels($this->plain))->toContain('رفع بانر')->toContain('طلب بانر')->toContain('رفع التفريغ')->toContain('رفع الوورد')
        ->not->toContain('حذف التفريغ')->not->toContain('حذف الوورد')->not->toContain('تنزيل الوورد')->toContain('طلب رئيسية');
    // 240988 — advfile = -1: no «طلب رئيسية».
    expect($labels($this->advRequested))->not->toContain('طلب رئيسية');
});

it('gives each admin only the controls their permissions reach; the stats block and Word download go to any admin', function () {
    $plain = page($this, $this->item, H::admin([]))->assertOk()->getContent();
    expect($plain)->toContain('إحصائيات متطورة')->toContain('تنزيل الوورد')
        ->not->toContain(route('admin.khotab.items.edit', $this->item))->not->toContain(route('admin.khotab.items.delete', $this->item))
        ->not->toContain(route('admin.khotab.items.images', $this->item))->not->toContain('إضافة تعليق إداري')
        ->not->toContain(route('admin.khotab.comments.edit', $this->c1))->not->toContain(route('admin.link-quality.khotab.item', $this->item));

    $editor = page($this, $this->item, H::admin(['khotab.edititem', 'khotab.delcomment', ...H::BOTH_SECTIONS], 'ed'))->getContent();
    expect($editor)->toContain(route('admin.khotab.items.edit', $this->item))->toContain('action="'.route('admin.khotab.items.images', $this->item).'"')
        ->toContain(route('admin.khotab.comments.edit', $this->c1))   // hide alone reaches the comment page
        ->not->toContain(route('admin.khotab.items.move', $this->item))->not->toContain('إضافة تعليق إداري')
        ->not->toContain(route('admin.khotab.items.pdf', $this->item))->not->toContain(route('admin.khotab.items.docx.delete', $this->item));

    $files = page($this, $this->item, H::admin(['khotab.adddocx', 'khotab.uploadpdf'], 'files'))->getContent();
    expect($files)->toContain(route('admin.khotab.items.docx', $this->item))->toContain(route('admin.khotab.items.pdf.delete', $this->item))
        ->not->toContain(route('admin.khotab.items.docx.delete', $this->item));
});

it('refuses every new route without a session (401) and without its permission (403), writing nothing', function () {
    $routes = [
        ['get', route('admin.khotab.items.edit', $this->item)], ['put', route('admin.khotab.items.update', $this->item)],
        ['post', route('admin.khotab.items.images', $this->item)],
        ['get', route('admin.khotab.items.move', $this->item)], ['put', route('admin.khotab.items.move.save', $this->item)],
        ['get', route('admin.khotab.move-options.groups')], ['get', route('admin.khotab.move-options.series')],
        ['get', route('admin.khotab.items.delete', $this->item)], ['delete', route('admin.khotab.items.destroy', $this->item)],
        ['get', route('admin.khotab.items.banner', $this->item)], ['post', route('admin.khotab.items.banner.upload', $this->item)],
        ['get', route('admin.khotab.items.banner.delete', $this->item)], ['delete', route('admin.khotab.items.banner.destroy', $this->item)],
        ['post', route('admin.khotab.items.banner.request', $this->item)],
        ['get', route('admin.khotab.items.pdf', $this->item)], ['post', route('admin.khotab.items.pdf.upload', $this->item)],
        ['get', route('admin.khotab.items.pdf.delete', $this->item)], ['delete', route('admin.khotab.items.pdf.destroy', $this->item)],
        ['get', route('admin.khotab.items.docx', $this->item)], ['post', route('admin.khotab.items.docx.upload', $this->item)],
        ['get', route('admin.khotab.items.docx.delete', $this->item)], ['delete', route('admin.khotab.items.docx.destroy', $this->item)],
        ['post', route('admin.khotab.items.comments.store', $this->item)],
        ['get', route('admin.khotab.comments.edit', $this->c1)], ['put', route('admin.khotab.comments.publish', $this->c1)],
        ['post', route('admin.khotab.comments.hide', $this->c1)],
    ];

    foreach ($routes as [$method, $url]) {
        $this->{$method}($url)->assertUnauthorized();
    }

    // Holds every permission the earlier phases use, none of this page's.
    $session = H::session(H::admin(['khotab.addmirror', 'khotab.editmirror', 'khotab.delmirror', 'khotab.addcat', 'khotab.addadv', 'khotab.repair', 'khotab.additem']));
    foreach ($routes as [$method, $url]) {
        $this->withSession($session)->{$method}($url)->assertForbidden();
    }

    // The mapped permissions do not leak into each other.
    $this->withSession(H::session(H::admin(['khotab.adddocx'], 'docx-only')))->delete(route('admin.khotab.items.docx.destroy', $this->item))->assertForbidden();
    $this->withSession(H::session(H::admin(['khotab.delcomment'], 'hide-only')))->put(route('admin.khotab.comments.publish', $this->c1))->assertForbidden();
    $this->withSession(H::session(H::admin(['khotab.requestbanner'], 'req-only')))->post(route('admin.khotab.items.banner.upload', $this->item))->assertForbidden();

    expect(DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->exists())->toBeTrue()
        ->and((int) col('nuke_islamic_comments', $this->c1, 'view'))->toBe(1)->and(itemAudit())->toBe([]);
});

// ─── hidden content (owner decision: admin parity) ───────────────────────────

it('opens a hidden item for an admin with « مخفي » and the full panel, privately; a visitor still gets 404', function () {
    page($this, $this->hidden)->assertNotFound();

    $response = page($this, $this->hidden, $this->boss)->assertOk();
    expect($response->getContent())->toContain('مادة مخفية مخفي  - د. الشيخ')->toContain('لوحة تحكم المشرفين')
        ->toContain(route('admin.khotab.items.edit', $this->hidden))
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');

    // Any admin, even one without a single permission.
    page($this, $this->hidden, H::admin([]))->assertOk()->assertSee('إحصائيات متطورة');
});

it('opens an item of a hidden group, and shows a hidden series\' crumb, to an admin only', function () {
    // Visitors first (an admin session cannot be dropped mid-test).
    DB::connection('main')->table('nuke_islamic_series')->where('id', $this->series)->update(['hidden' => 1]);
    $visitor = page($this, $this->item)->assertOk();   // hidden series alone: rendered without its crumb (decision-log #56)
    expect($visitor->getContent())->not->toContain('/khotab-series-'.$this->series.'.htm')->not->toContain('data-contextual-admin')
        ->and((string) $visitor->headers->get('Cache-Control'))->not->toContain('no-store');

    DB::connection('main')->table('nuke_islamic_groups')->where('id', $this->group)->update(['hidden' => 1]);
    page($this, $this->item)->assertNotFound();

    $response = page($this, $this->item, $this->boss)->assertOk();
    expect($response->getContent())->toContain('href="/khotab-group-'.$this->group.'.htm">مجموعة مجموعة</a>')
        ->toContain('href="/khotab-series-'.$this->series.'.htm">سلسلة سلسلة</a>')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

// ─── إحصائيات متطورة ────────────────────────────────────────────────────────

it('shows OLD\'s AdvancedStats values for each download state, the broken reports and the media properties', function () {
    $html = page($this, $this->item, $this->boss)->getContent();
    expect($html)->toContain('إحصائيات متطورة')->toContain('لم يتم (3 محاولة)')->toContain('يجب إنزال الملف أولاً')
        ->toContain('1.00 ميجا بايت')->toContain('لم يتم نسخ الملف حتى الآن')->toContain('لم يتم الإبلاغ')
        ->toContain('غير معروف')->toContain('لا يوجد حقوق نسخ')->toContain('لا يوجد تعليق')
        ->not->toContain('خصائص الصوت')->not->toContain('خصائص الصورة');

    $checked = mktime(12, 0, 0, 7, 23, 2026);
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)
        ->update(['down' => 1, 'online' => 1048576, 'checktime' => $checked, 'cd' => 'CD-12', 'broken' => 1]);
    DB::connection('main')->table('nuke_brokenlinks')->insert(['khid' => $this->item, 'divid' => 1, 'count' => 4]);
    DB::connection('main')->table('nuke_islamic_advanced')->insert(['id' => $this->item, 'adur' => '75000', 'cright' => 'حقوق', 'perf' => 'ملاحظة',
        'astr' => 'a', 'srate' => '44100', 'ares' => '16', 'abit' => '128', 'alist' => 'AAC', 'vstr' => 'v', 'frate' => '25', 'vres' => '24',
        'vbit' => '900', 'width' => 640, 'height' => 360, 'vlist' => 'H264']);

    $html = page($this, $this->item, $this->boss)->getContent();
    expect($html)->toContain('تم تنزيل الملف')->toContain('تعمل')->toContain('الخميس 23 يوليو 2026 مـ')->toContain('CD-12')
        ->toContain('تم الإبلاغ (4 مرة)')->toContain('01:15')->toContain('حقوق')->toContain('ملاحظة')
        ->toContain('خصائص الصوت')->toContain('44100')->toContain('AAC')->toContain('خصائص الصورة')->toContain('640 بكسل')->toContain('H264');

    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->update(['online' => 5]);
    expect(page($this, $this->item, $this->boss)->getContent())->toContain('لا تعمل');

    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->update(['down' => 0]);
    expect(page($this, $this->item, $this->boss)->getContent())->toContain('بإنتظار التنزيل (3 محاولة)');
});

// ─── تعديل ──────────────────────────────────────────────────────────────────

it('edit shows OLD\'s form and writes OLD\'s fields, trial = 0, and the location recounts', function () {
    $session = H::session($this->boss);
    $form = $this->withSession($session)->get(route('admin.khotab.items.edit', $this->item))->assertOk()->getContent();
    expect($form)->toContain('value="1.00"')->toContain('name="khotab_link"')->not->toContain('name="Channel"')   // grouped: channel as text
        ->toContain(route('admin.khotab.items.move', $this->item));

    $this->withSession($session)->put(route('admin.khotab.items.update', $this->item), [
        'title' => 'عنوان جديد', 'description' => 'وصف <b>x</b>', 'Des' => 'تعليق', 'khotab_link' => 'https://archive.org/download/n/n.mp4',
        'khotabsize' => '2.5', 'newslist' => '1', 'hidden' => '1', 'Location' => 6, 'Channel' => 3, 'meta_keywords' => 'kw', 'meta_index' => '1',
    ])->assertRedirect("/khotab-item-{$this->item}.htm");

    $row = DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->first();
    expect($row->title)->toBe('عنوان جديد')->and($row->description)->toBe('وصف <b>x</b>')->and($row->notes)->toBe('تعليق')
        ->and($row->link)->toBe('https://archive.org/download/n/n.mp4')->and((int) $row->linksize)->toBe(2621440)
        ->and((int) $row->trial)->toBe(0)->and((int) $row->newslist)->toBe(1)->and((int) $row->fixed)->toBe(0)->and((int) $row->hidden)->toBe(1)
        ->and((int) $row->channel_id)->toBe(0)   // in a group: OLD's UPDATE left the channel out
        ->and((int) $row->location_id)->toBe(6)->and((int) $row->meta_index)->toBe(1)->and((int) $row->meta_follow)->toBe(0)
        ->and(locationCount('nuke_islamic_authors_location', 'author_id', 7, 6))->toBe(1)
        ->and(locationCount('nuke_islamic_authors_location', 'author_id', 7, 5))->toBe(0)
        ->and(locationCount('nuke_islamic_groups_location', 'group_id', $this->group, 6))->toBe(1)
        ->and(locationCount('nuke_islamic_groups_location', 'group_id', $this->group, 5))->toBe(0)
        ->and(locationCount('nuke_islamic_series_location', 'series_id', $this->series, 6))->toBe(1)
        ->and(locationCount('nuke_islamic_series_location', 'series_id', $this->series, 5))->toBe(0)
        ->and(itemAudit())->toBe(['edit_item']);
});

it('edit locks the link of a downloaded item for everyone but a super-admin, and sets the channel only for a free item', function () {
    $free = H::insert('nuke_islamic_khotab', ['author' => 7, 'vedio' => 1, 'title' => 'فردية', 'down' => 1, 'link' => 'https://a/1.mp4', 'linksize' => 100]);
    $editor = H::session(H::admin(['khotab.edititem', ...H::BOTH_SECTIONS], 'ed'));

    expect($this->withSession($editor)->get(route('admin.khotab.items.edit', $free))->getContent())
        ->toContain('name="khotab_link" class="form-control" value="https://a/1.mp4" type="text" disabled')->toContain('name="Channel"');

    $this->withSession($editor)->put(route('admin.khotab.items.update', $free), ['title' => 'فردية', 'khotab_link' => 'https://b/2.mp4', 'khotabsize' => '9', 'Channel' => 3])->assertRedirect();
    expect(col('nuke_islamic_khotab', $free, 'link'))->toBe('https://a/1.mp4')->and((int) col('nuke_islamic_khotab', $free, 'linksize'))->toBe(100)
        ->and((int) col('nuke_islamic_khotab', $free, 'channel_id'))->toBe(3);

    $this->withSession(H::session($this->boss))->put(route('admin.khotab.items.update', $free), ['title' => 'فردية', 'khotab_link' => 'https://b/2.mp4', 'khotabsize' => '1'])->assertRedirect();
    expect(col('nuke_islamic_khotab', $free, 'link'))->toBe('https://b/2.mp4')->and((int) col('nuke_islamic_khotab', $free, 'linksize'))->toBe(1048576)
        ->and((int) col('nuke_islamic_khotab', $free, 'channel_id'))->toBe(0);
});

// ─── نقل ────────────────────────────────────────────────────────────────────

it('move offers OLD\'s selects and their dependent reloads', function () {
    $session = H::session($this->boss);
    $form = $this->withSession($session)->get(route('admin.khotab.items.move', $this->item))->assertOk()->getContent();
    expect($form)->toContain('<option value="7" selected>الشيخ</option>')->toContain('value="'.$this->group.'" selected')
        ->toContain('value="'.$this->series.'" selected')->toContain(str_replace('/', '\\/', route('admin.khotab.move-options.groups')));

    $groups = $this->withSession($session)->get(route('admin.khotab.move-options.groups', ['author' => 8, 'vedio' => 1]))->assertOk()->getContent();
    expect($groups)->toContain('>مجموعة ب</option>')->not->toContain('>صوتية</option>')->not->toContain('>مجموعة</option>');

    $series = $this->withSession($session)->get(route('admin.khotab.move-options.series', ['group_id' => $this->group2, 'vedio' => 1]))->getContent();
    expect($series)->toContain('>سلسلة ب</option>')->not->toContain('disabled');
    expect($this->withSession($session)->get(route('admin.khotab.move-options.series', ['group_id' => 999, 'vedio' => 1]))->getContent())->toContain('disabled');
});

it('move refuses OLD\'s errors and otherwise writes OLD\'s counters and locations', function () {
    $session = H::session(H::admin(['khotab.moveitem', ...H::BOTH_SECTIONS], 'mover'));

    $this->withSession($session)->put(route('admin.khotab.items.move.save', $this->item), ['author_id' => 99, 'group_id' => $this->audioGroup, 'ser_id' => $this->series])
        ->assertSessionHasErrors('author_id');
    expect((int) col('nuke_islamic_khotab', $this->item, 'author'))->toBe(7)->and(itemAudit())->toBe([]);

    $this->withSession($session)->put(route('admin.khotab.items.move.save', $this->item), ['author_id' => 8, 'group_id' => $this->group2, 'ser_id' => $this->series2])
        ->assertRedirect("/khotab-item-{$this->item}.htm");

    $row = DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->first();
    expect((int) $row->author)->toBe(8)->and((int) $row->group_id)->toBe($this->group2)->and((int) $row->ser_id)->toBe($this->series2)
        ->and((int) col('nuke_islamic_groups', $this->group, 'count'))->toBe(4)->and((int) col('nuke_islamic_groups', $this->group2, 'count'))->toBe(1)
        ->and((int) col('nuke_islamic_series', $this->series, 'count'))->toBe(0)->and((int) col('nuke_islamic_series', $this->series2, 'count'))->toBe(1)
        ->and((int) col('nuke_islamic_authors', 7, 'vedio'))->toBe(9)->and((int) col('nuke_islamic_authors', 8, 'vedio'))->toBe(6)
        ->and(locationCount('nuke_islamic_authors_location', 'author_id', 8, 5))->toBe(1)
        ->and(locationCount('nuke_islamic_authors_location', 'author_id', 7, 5))->toBe(0)
        ->and(locationCount('nuke_islamic_groups_location', 'group_id', $this->group2, 5))->toBe(1)
        ->and(locationCount('nuke_islamic_series_location', 'series_id', $this->series, 5))->toBe(0)
        ->and(locationCount('nuke_islamic_series_location', 'series_id', $this->series2, 5))->toBe(1)
        ->and(itemAudit())->toBe(['move_item']);
});

// ─── مسح ────────────────────────────────────────────────────────────────────

it('delete confirms, then removes only the row and OLD\'s counters, and lands where OLD did', function () {
    $session = H::session($this->boss);
    $this->withSession($session)->get(route('admin.khotab.items.delete', $this->item))->assertOk()
        ->assertSee("هل ترغب في مسح درس د. الشيخ بعنوان<br>''درس الفجر''????", false);

    $this->withSession($session)->delete(route('admin.khotab.items.destroy', $this->item))->assertRedirect("/khotab-series-{$this->series}.htm");

    expect(DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->exists())->toBeFalse()
        ->and((int) col('nuke_islamic_groups', $this->group, 'count'))->toBe(4)->and((int) col('nuke_islamic_series', $this->series, 'count'))->toBe(0)
        ->and((int) col('nuke_islamic_authors', 7, 'vedio'))->toBe(9)
        ->and(locationCount('nuke_islamic_authors_location', 'author_id', 7, 5))->toBe(0)
        ->and(locationCount('nuke_islamic_series_location', 'series_id', $this->series, 5))->toBe(0)
        // OLD left mirrors and comments behind
        ->and(DB::connection('main')->table('nuke_islamic_mirror')->where('id', $this->mirror)->exists())->toBeTrue()
        ->and(DB::connection('main')->table('nuke_islamic_comments')->where('khid', $this->item)->count())->toBe(3)
        ->and(itemAudit())->toBe(['del_item']);

    $free = H::insert('nuke_islamic_khotab', ['author' => 7, 'vedio' => 0, 'title' => 'صوتي']);
    $this->withSession($session)->delete(route('admin.khotab.items.destroy', $free))->assertRedirect('/khotab-audio-7.htm');
    expect((int) col('nuke_islamic_authors', 7, 'audio'))->toBe(3);
    $this->withSession($session)->delete(route('admin.khotab.items.destroy', $this->plain))->assertRedirect("/khotab-group-{$this->group}.htm");
});

// ─── البانر ─────────────────────────────────────────────────────────────────

it('banner upload accepts a GIF up to 40,000 bytes, stores it at OLD\'s path and sets banner = 1', function () {
    $session = H::session(H::admin(['khotab.uploadbanner'], 'designer'));
    $url = route('admin.khotab.items.banner.upload', $this->plain);

    $this->withSession($session)->get(route('admin.khotab.items.banner', $this->plain))->assertOk()->assertSee('أقصى حجم مسموح به 40 كيلو بايت');
    $this->withSession($session)->post($url, [])->assertSessionHasErrors(['file2upl' => 'لم يتم إختيار ملف للتحميل']);
    $this->withSession($session)->post($url, ['file2upl' => UploadedFile::fake()->create('b.png', 5, 'image/png')])->assertSessionHasErrors('file2upl');
    $this->withSession($session)->post($url, ['file2upl' => UploadedFile::fake()->create('b.gif', 5, 'text/plain')])->assertSessionHasErrors('file2upl');
    $this->withSession($session)->post($url, ['file2upl' => UploadedFile::fake()->create('b.gif', 40, 'image/gif')])
        ->assertSessionHasErrors(['file2upl' => 'حجم الملف أكبر من الحجم المسموح به']);
    expect((int) col('nuke_islamic_khotab', $this->plain, 'banner'))->toBe(0)->and(itemAudit())->toBe([]);

    $this->withSession($session)->post($url, ['file2upl' => UploadedFile::fake()->createWithContent('b.gif', str_repeat('g', 30 * 1024))])->assertRedirect("/khotab-item-{$this->plain}.htm");
    $file = $this->root.'/banner/'.intdiv($this->plain, 1000).'/'.$this->plain.'.gif';
    expect(is_file($file))->toBeTrue()->and(filesize($file))->toBe(30 * 1024)
        ->and((int) col('nuke_islamic_khotab', $this->plain, 'banner'))->toBe(1)->and(itemAudit())->toBe(['upload_banner']);

    // «تعديل البانر»: OLD's warning and the current banner, then the replacement overwrites it.
    $this->withSession($session)->get(route('admin.khotab.items.banner', $this->plain))->assertSee('رفع أي بانر جديد سيؤدي إلى مسح البانر الحالي')
        ->assertSee('/media/banner/'.intdiv($this->plain, 1000).'/'.$this->plain.'.gif', false);
    $this->withSession($session)->post($url, ['file2upl' => UploadedFile::fake()->createWithContent('c.gif', str_repeat('h', 10 * 1024))])->assertRedirect();
    expect(filesize($file))->toBe(10 * 1024);
});

it('banner delete exists only for a banner that is on disk, sets banner = 0 and keeps the file', function () {
    $session = H::session(H::admin(['khotab.uploadbanner'], 'designer'));
    $file = $this->root.'/banner/'.intdiv($this->bannered, 1000).'/'.$this->bannered.'.gif';

    // banner = 1 but no file / banner = -1: OLD's empty page, and nothing to submit.
    expect($this->withSession($session)->get(route('admin.khotab.items.banner.delete', $this->bannered))->assertOk()->getContent())
        ->not->toContain('هل أنت متأكد من أنك تريد حذف هذا البانر؟');
    $this->withSession($session)->delete(route('admin.khotab.items.banner.destroy', $this->requested))->assertNotFound();
    expect((int) col('nuke_islamic_khotab', $this->requested, 'banner'))->toBe(-1);

    mkdir(dirname($file), 0775, true);
    file_put_contents($file, 'GIF89a');
    $this->withSession($session)->get(route('admin.khotab.items.banner.delete', $this->bannered))->assertSee('هل أنت متأكد من أنك تريد حذف هذا البانر؟');
    $this->withSession($session)->delete(route('admin.khotab.items.banner.destroy', $this->bannered))->assertRedirect("/khotab-item-{$this->bannered}.htm");

    expect((int) col('nuke_islamic_khotab', $this->bannered, 'banner'))->toBe(0)->and(is_file($file))->toBeTrue()->and(itemAudit())->toBe(['delete_banner']);
});

it('banner request sets banner = -1, and is refused on OLD\'s page for an item that has one', function () {
    $session = H::session(H::admin(['khotab.requestbanner'], 'req'));

    $this->withSession($session)->post(route('admin.khotab.items.banner.request', $this->bannered))->assertOk()
        ->assertSee('عفواً .... هذه المادة لها بانر بالفعل و لا يمكن تقديم طلب تصميم مرة أخرى');
    expect((int) col('nuke_islamic_khotab', $this->bannered, 'banner'))->toBe(1);

    $this->withSession($session)->post(route('admin.khotab.items.banner.request', $this->plain))->assertRedirect("/khotab-item-{$this->plain}.htm");
    expect((int) col('nuke_islamic_khotab', $this->plain, 'banner'))->toBe(-1)->and(itemAudit())->toBe(['req_banner']);
});

// ─── التفريغ و الوورد ───────────────────────────────────────────────────────

it('pdf upload and re-upload each add 1 to the author, as OLD did; delete subtracts 1 and keeps the file', function () {
    $session = H::session(H::admin(['khotab.uploadpdf'], 'pdf'));
    $url = route('admin.khotab.items.pdf.upload', $this->plain);

    $this->withSession($session)->get(route('admin.khotab.items.pdf', $this->plain))->assertOk()->assertDontSee('سيتم حذف الملف القديم');
    $this->withSession($session)->post($url, ['file2upl' => UploadedFile::fake()->create('t.docx', 5, 'application/pdf')])->assertSessionHasErrors('file2upl');
    $this->withSession($session)->post($url, ['file2upl' => UploadedFile::fake()->create('t.pdf', 5, 'text/html')])->assertSessionHasErrors('file2upl');
    $this->withSession($session)->post($url, ['file2upl' => UploadedFile::fake()->create('t.pdf', 14649, 'application/pdf')])->assertSessionHasErrors('file2upl');

    $this->withSession($session)->post($url, ['file2upl' => UploadedFile::fake()->create('t.pdf', 50, 'application/pdf')])->assertRedirect("/khotab-item-{$this->plain}.htm");
    $file = $this->root.'/pdf/'.intdiv($this->plain, 1000).'/'.$this->plain.'.pdf';
    expect(is_file($file))->toBeTrue()->and((int) col('nuke_islamic_khotab', $this->plain, 'pdf'))->toBe(1)
        ->and((int) col('nuke_islamic_khotab', $this->plain, 'pdf_time'))->toBeGreaterThan(0)->and((int) col('nuke_islamic_authors', 7, 'pdf'))->toBe(4);

    $this->withSession($session)->get(route('admin.khotab.items.pdf', $this->plain))->assertSee('سيتم حذف الملف القديم إذا قمت برفع ملف أخر.');
    $this->withSession($session)->post($url, ['file2upl' => UploadedFile::fake()->createWithContent('u.pdf', str_repeat('p', 20 * 1024))])->assertRedirect();
    expect((int) col('nuke_islamic_authors', 7, 'pdf'))->toBe(5)->and(filesize($file))->toBe(20 * 1024);

    $this->withSession($session)->get(route('admin.khotab.items.pdf.delete', $this->plain))->assertSee('هل أنت متأكد من أنك تريد حذف ملف التفريغ لهذه المادة؟');
    $this->withSession($session)->delete(route('admin.khotab.items.pdf.destroy', $this->plain))->assertRedirect("/khotab-item-{$this->plain}.htm");
    expect((int) col('nuke_islamic_khotab', $this->plain, 'pdf'))->toBe(0)->and((int) col('nuke_islamic_authors', 7, 'pdf'))->toBe(4)
        ->and(is_file($file))->toBeTrue()->and(itemAudit())->toBe(['upload_pdf', 'upload_pdf', 'delete_pdf']);
});

it('docx upload / replace / delete write OLD\'s counters, with delete on its own permission', function () {
    $uploader = H::session(H::admin(['khotab.adddocx'], 'word'));
    $url = route('admin.khotab.items.docx.upload', $this->plain);
    $docx = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    $this->withSession($uploader)->post($url, ['file2upl' => UploadedFile::fake()->create('w.doc', 5, 'application/msword')])->assertSessionHasErrors('file2upl');
    $this->withSession($uploader)->post($url, ['file2upl' => UploadedFile::fake()->create('w.docx', 5, $docx)])->assertRedirect("/khotab-item-{$this->plain}.htm");
    $file = $this->root.'/docx/'.intdiv($this->plain, 1000).'/'.$this->plain.'.docx';
    expect(is_file($file))->toBeTrue()->and((int) col('nuke_islamic_khotab', $this->plain, 'docx'))->toBe(1)
        ->and((int) col('nuke_islamic_khotab', $this->plain, 'docx_time'))->toBeGreaterThan(0)->and((int) col('nuke_islamic_authors', 7, 'docx'))->toBe(3);

    $this->withSession($uploader)->post($url, ['file2upl' => UploadedFile::fake()->create('w2.docx', 6, 'application/zip')])->assertRedirect();
    expect((int) col('nuke_islamic_authors', 7, 'docx'))->toBe(4);

    $this->withSession($uploader)->delete(route('admin.khotab.items.docx.destroy', $this->plain))->assertForbidden();
    $this->withSession(H::session(H::admin(['khotab.deletedocx'], 'word-del')))->delete(route('admin.khotab.items.docx.destroy', $this->plain))->assertRedirect();
    expect((int) col('nuke_islamic_khotab', $this->plain, 'docx'))->toBe(0)->and((int) col('nuke_islamic_authors', 7, 'docx'))->toBe(3)
        ->and(is_file($file))->toBeTrue()->and(itemAudit())->toBe(['upload_docx', 'upload_docx', 'delete_docx']);
});

// ─── التعليقات ──────────────────────────────────────────────────────────────

it('shows the moderator form only when the item\'s counter is above 0, and «(تعديل التعليق)» beside each published comment', function () {
    $html = page($this, $this->item, $this->boss)->getContent();
    expect($html)->toContain('إضافة تعليق إداري')->toContain('action="'.route('admin.khotab.items.comments.store', $this->item).'"')
        ->toContain('href="'.route('admin.khotab.comments.edit', $this->c1).'"')->toContain('href="'.route('admin.khotab.comments.edit', $this->c2).'"')
        ->not->toContain(route('admin.khotab.comments.edit', $this->pending));

    expect(page($this, $this->requested, $this->boss)->getContent())->not->toContain('إضافة تعليق إداري');   // comments = 0
});

it('adds OLD\'s moderator comment — published, uid 0, no name — without touching the counter', function () {
    $session = H::session(H::admin(['khotab.approvecomment'], 'mod'));

    $this->withSession($session)->post(route('admin.khotab.items.comments.store', $this->item), ['comment' => ''])->assertSessionHasErrors('comment');
    $this->withSession($session)->post(route('admin.khotab.items.comments.store', $this->item), ['comment' => "سطر\nثان"])->assertRedirect("/khotab-item-{$this->item}.htm");

    $row = DB::connection('main')->table('nuke_islamic_comments')->orderByDesc('id')->first();
    expect((int) $row->khid)->toBe($this->item)->and((int) $row->uid)->toBe(0)->and($row->uname)->toBe('')->and((int) $row->view)->toBe(1)
        ->and($row->comment)->toBe("سطر\nثان")->and((int) $row->mytime)->toBeGreaterThan(0)
        ->and((int) col('nuke_islamic_khotab', $this->item, 'comments'))->toBe(2)->and(itemAudit())->toBe(['add_mod_comment']);

    H::visitor();
    expect(page($this, $this->item)->getContent())->toContain('مشرف التعليقات');
});

it('publishes an edited comment and hides one, recounting the item\'s published comments', function () {
    $publisher = H::session(H::admin(['khotab.approvecomment'], 'pub'));
    $page = $this->withSession($publisher)->get(route('admin.khotab.comments.edit', $this->c1))->assertOk()->getContent();
    expect($page)->toContain('نشر التعليـق')->not->toContain('مسح التعليـق')->toContain('تعليق أول');

    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->update(['comments' => 9]);
    $this->withSession($publisher)->put(route('admin.khotab.comments.publish', $this->pending), ['comment' => 'نص معدل'])->assertRedirect("/khotab-item-{$this->item}.htm");
    expect(col('nuke_islamic_comments', $this->pending, 'comment'))->toBe('نص معدل')->and((int) col('nuke_islamic_comments', $this->pending, 'view'))->toBe(1)
        ->and((int) col('nuke_islamic_khotab', $this->item, 'comments'))->toBe(3);

    $hider = H::session(H::admin(['khotab.delcomment'], 'hider'));
    expect($this->withSession($hider)->get(route('admin.khotab.comments.edit', $this->c1))->getContent())->toContain('مسح التعليـق')->not->toContain('نشر التعليـق');
    $this->withSession($hider)->post(route('admin.khotab.comments.hide', $this->c1))->assertRedirect("/khotab-item-{$this->item}.htm");
    expect((int) col('nuke_islamic_comments', $this->c1, 'view'))->toBe(2)->and(col('nuke_islamic_comments', $this->c1, 'comment'))->toBe('تعليق أول')
        ->and((int) col('nuke_islamic_khotab', $this->item, 'comments'))->toBe(2)->and(itemAudit())->toBe(['publish_comment', 'hide_comment']);
});

// ─── سحب الصور ──────────────────────────────────────────────────────────────

it('«سحب الصور» stores the archive.org GIF and middle thumbnail, sets gif / frame / leeched, and only on a POST', function () {
    Http::fake([
        'www.archive.org/download/myitem/myitem_files.xml' => Http::response(
            '<files><file name="a.mp4" source="original"><format>MPEG4</format><size>2097152</size><length>75</length></file>'.
            '<file name="a.thumbs/a_000001.jpg"><format>Thumbnail</format><original>a.mp4</original></file>'.
            '<file name="a.thumbs/a_000002.jpg"><format>Thumbnail</format><original>a.mp4</original></file>'.
            '<file name="a.gif"><format>Animated GIF</format><original>a.mp4</original></file></files>', 200),
        'archive.org/download/myitem/a.gif' => Http::response('GIF', 200),
        'archive.org/download/myitem/a.thumbs/a_000002.jpg' => Http::response('JPG', 200),
        '*' => Http::response('NO', 404),
    ]);
    $session = H::session(H::admin(['khotab.edititem', ...H::BOTH_SECTIONS], 'ed'));

    $this->withSession($session)->get(route('admin.khotab.items.edit', $this->item))->assertOk();   // the GET is the edit form, nothing leeched
    $this->get(route('admin.khotab.items.images', $this->item))->assertStatus(405);   // no GET route at all
    expect((int) col('nuke_islamic_khotab', $this->item, 'leeched'))->toBe(0);

    $this->withSession($session)->post(route('admin.khotab.items.images', $this->item))->assertOk()
        ->assertSee('الصورة المتحركة للمادة')->assertSee('الصورة الثابتة للمادة');

    $bucket = intdiv($this->item, 1000);
    expect(file_get_contents("{$this->root}/khotab_gifs/{$bucket}/{$this->item}.gif"))->toBe('GIF')
        ->and(file_get_contents("{$this->root}/khotab_frames/{$bucket}/{$this->item}.jpg"))->toBe('JPG')
        ->and((int) col('nuke_islamic_khotab', $this->item, 'gif'))->toBe(1)->and((int) col('nuke_islamic_khotab', $this->item, 'frame'))->toBe(1)
        ->and((int) col('nuke_islamic_khotab', $this->item, 'leeched'))->toBe(1)->and(itemAudit())->toBe(['leech_images']);
});

it('«سحب الصور» leaves a non-archive.org item untouched and never fetches an image off archive.org', function () {
    Http::fake([
        'www.archive.org/download/x/x_files.xml' => Http::response(
            '<files><file name="a.gif"><format>Animated GIF</format><original>a.mp4</original></file></files>', 200),
        '*' => Http::response('NO', 200),
    ]);
    $session = H::session($this->boss);

    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->plain)->update(['link' => 'https://cdn.example/a.mp4']);
    $this->withSession($session)->post(route('admin.khotab.items.images', $this->plain))->assertOk()->assertSee('ليس على archive.org');
    expect((int) col('nuke_islamic_khotab', $this->plain, 'leeched'))->toBe(0)->and(itemAudit())->toBe([]);

    // The XML is archive.org's, but the image would come from another host: refused, leeched still recorded.
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->plain)->update(['link' => 'https://evil.example/archive.org/x/a.mp4']);
    $this->withSession($session)->post(route('admin.khotab.items.images', $this->plain))->assertOk()->assertSee('لا يوجد صور متحركة لهذه المادة');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example'));
    expect((int) col('nuke_islamic_khotab', $this->plain, 'gif'))->toBe(0)->and((int) col('nuke_islamic_khotab', $this->plain, 'leeched'))->toBe(1);
});

// ─── the migrated controls still work from this page ────────────────────────

it('keeps the earlier phases\' operations working from the page and back to it', function () {
    $this->withSession(H::session($this->boss));
    $url = "/khotab-item-{$this->item}.htm";

    $this->post(route('admin.mirrors.khotab.pick', $this->mirror), ['return_to' => $url])->assertRedirect($url);
    expect(session(KhotabMirrorController::CLIPBOARD_KEY))->toBe($this->mirror);
    expect(page($this, $this->plain, $this->boss)->getContent())->toContain('action="'.route('admin.mirrors.khotab.move', $this->mirror).'"');

    $this->post(route('admin.advfiles.request', $this->plain), ['return_to' => $url])->assertRedirect($url);
    expect((int) col('nuke_islamic_khotab', $this->plain, 'advfile'))->toBe(-1);

    $this->post(route('admin.categories.khotab.item.remove', $this->item), ['category_id' => 1, 'return_to' => $url])->assertRedirect($url);
    expect(col('nuke_islamic_khotab', $this->item, 'cat'))->toBe('');

    $this->get(route('admin.link-quality.khotab.item', $this->item))->assertOk();
});

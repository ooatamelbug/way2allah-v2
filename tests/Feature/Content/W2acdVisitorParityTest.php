<?php

/*
 * W2ACD visitor parity W3 (owner, 2026-11-05; decision-log #91) — OLD `Group(0)` → `ListGroup(0)`:
 * cds-main.htm lists the top-level CD groups (`parent_id = 0 ORDER BY id DESC`) above the general CDs, with
 * the same cards as a group page's sub-groups.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $this->media = sys_get_temp_dir().'/w2acd-main-'.bin2hex(random_bytes(4));
    config(['admin_cds.media_root' => $this->media, 'media.base_url' => '/media']);

    // top-a ─┬─ nested (CD: cdNested)
    //        └─ (CD: cdA)
    // top-b (icon = 1), top-c (empty)
    $this->topA = H::insert('nuke_w2acd_groups', ['title' => 'قسم رئيسي أول', 'parent_id' => 0, 'module_type' => 'cd', 'des' => 'تعليق القسم الأول', 'hits' => 1234]);
    $this->nested = H::insert('nuke_w2acd_groups', ['title' => 'قسم متفرع', 'parent_id' => $this->topA, 'module_type' => 'cd', 'des' => '']);
    $this->topB = H::insert('nuke_w2acd_groups', ['title' => 'قسم رئيسي ثان', 'parent_id' => 0, 'module_type' => 'cd', 'des' => '', 'icon' => 1]);
    $this->topC = H::insert('nuke_w2acd_groups', ['title' => 'قسم رئيسي ثالث', 'parent_id' => 0, 'module_type' => 'cd', 'des' => '']);
    $this->cdA = H::insert('nuke_w2acd_w2acd', ['group_id' => $this->topA, 'title' => 'إسطوانة القسم', 'hidden' => 0, 'thumbnail' => '']);
    $this->cdNested = H::insert('nuke_w2acd_w2acd', ['group_id' => $this->nested, 'title' => 'إسطوانة المتفرع', 'hidden' => 0, 'thumbnail' => '']);
    $this->cdGeneral = H::insert('nuke_w2acd_w2acd', ['group_id' => 0, 'title' => 'إسطوانة عامة', 'hidden' => 0, 'thumbnail' => '']);
});

afterEach(function () {
    File::deleteDirectory($this->media);
});

function w3Main(string $url = '/cds-main.htm'): string
{
    H::visitor();

    return test()->get($url)->assertOk()->getContent();
}

/** @return list<int> the group ids carried by the cards, in page order */
function w3CardIds(string $html): array
{
    preg_match_all('/data-cd-subgroup="(\d+)"/', $html, $m);

    return array_map('intval', $m[1]);
}

/** @return string the card for one group */
function w3Card(string $html, int $id): string
{
    preg_match('#<a href="/cds-group-'.$id.'\.htm" class="w2a-subgroup-card w2a-cd-subgroup".*?</a>#s', $html, $m);

    return $m[0] ?? '';
}

it('W3: cds-main lists every top-level group, id DESC, never a nested one', function () {
    $html = w3Main();

    expect(w3CardIds($html))->toBe([$this->topC, $this->topB, $this->topA])
        ->and($html)->not->toContain('data-cd-subgroup="'.$this->nested.'"')
        ->not->toContain('قسم متفرع');
});

it('W3: the panel «قائمة الأقسام الفرعية» comes above «قائمة الإسطوانات العامة»', function () {
    $html = w3Main();

    expect(strpos($html, 'قائمة الأقسام الفرعية'))->toBeLessThan(strpos($html, 'قائمة الإسطوانات العامة'))
        ->and(strpos($html, 'data-cd-subgroups'))->toBeLessThan(strpos($html, 'إسطوانة عامة'));
});

it('W3: each card links to its group and shows live sub-group / CD counts, visits and the comment or «بدون تعليق»', function () {
    $html = w3Main();
    $a = w3Card($html, $this->topA);
    $c = w3Card($html, $this->topC);

    expect($a)->toContain('<span class="w2a-cd-subgroup-title">قسم رئيسي أول</span>')
        ->toContain('الأقسام الفرعية : 1 قسم')
        ->toContain('الإسطوانات : 1 إسطوانة')
        ->toContain('الزيارات : 1,234 زيارة')
        ->toContain('التعليق : تعليق القسم الأول')
        ->and($c)->toContain('الأقسام الفرعية : 0 قسم')
        ->toContain('الإسطوانات : 0 إسطوانة')
        ->toContain('الزيارات : 0 زيارة')
        ->toContain('التعليق : بدون تعليق')
        ->and(w3Card($html, $this->topB))->toContain('التعليق : بدون تعليق');
});

it('W3: a card shows the group icon when icon = 1 and the file exists, otherwise the default image', function () {
    expect(w3Card(w3Main(), $this->topB))->toContain('src="/images/way2_cddefault.png"')
        ->and(w3Card(w3Main(), $this->topA))->toContain('src="/images/way2_cddefault.png"');

    $file = $this->media.'/w2acd/icons/0/'.$this->topB.'.jpg';
    File::ensureDirectoryExists(dirname($file));
    imagejpeg(imagecreatetruecolor(75, 100), $file);

    expect(w3Card(w3Main(), $this->topB))->toContain('src="/media/w2acd/icons/0/'.$this->topB.'.jpg?v=')
        ->and(w3Card(w3Main(), $this->topA))->toContain('src="/images/way2_cddefault.png"');
});

it('W3: no panel when there are no top-level groups; the general CD list is unchanged', function () {
    DB::connection('main')->table('nuke_w2acd_groups')->delete();

    $html = w3Main();

    expect($html)->not->toContain('قائمة الأقسام الفرعية')->not->toContain('data-cd-subgroups')
        ->toContain('قائمة الإسطوانات العامة')->toContain('إسطوانة عامة');
});

it('W3: a group created by M4 «إضافة قسم فرعي» shows first on cds-main after the redirect', function () {
    $manager = H::admin(['w2acd.managecds'], 'cd-manager');

    $this->withSession(H::session($manager))
        ->post(route('admin.cds.groups.store'), ['Title' => 'قسم من M4', 'Des' => 'أضيف الآن'])
        ->assertRedirect('/cds-main.htm');
    $new = (int) DB::connection('main')->table('nuke_w2acd_groups')->where('title', 'قسم من M4')->value('id');

    $html = w3Main();

    expect(w3CardIds($html)[0])->toBe($new)
        ->and(w3Card($html, $new))->toContain('التعليق : أضيف الآن');
});

it('W3: cds-main lists only group-0 CDs, 24 a page, and counts no group visit', function () {
    for ($i = 1; $i <= 25; $i++) {
        H::insert('nuke_w2acd_w2acd', ['group_id' => 0, 'title' => "عامة رقم {$i}", 'hidden' => 0, 'order_in_group' => 0, 'mytime' => $i, 'thumbnail' => '']);
    }
    $hitsBefore = DB::connection('main')->table('nuke_w2acd_groups')->orderBy('id')->pluck('hits')->all();

    $first = w3Main();
    $second = w3Main('/cds-main.htm?page=2');

    expect($first)->toContain('عامة رقم 25<')->not->toContain('إسطوانة القسم')->not->toContain('إسطوانة المتفرع')
        ->and(substr_count($first, '<article class="w2a-cd-card">'))->toBe(24)
        ->and($second)->toContain('عرض')
        ->and(DB::connection('main')->table('nuke_w2acd_groups')->orderBy('id')->pluck('hits')->all())->toBe($hitsBefore);
});

it('W3: a group page still renders its own sub-groups with the shared cards, unchanged', function () {
    H::visitor();
    $html = $this->get("/cds-group-{$this->topA}.htm")->assertOk()->getContent();

    expect(w3CardIds($html))->toBe([$this->nested])
        ->and($html)->toContain('قائمة الأقسام الفرعية')
        ->and(w3Card($html, $this->nested))->toContain('الإسطوانات : 1 إسطوانة')->toContain('التعليق : بدون تعليق')
        ->and($html)->not->toContain('data-cd-subgroup="'.$this->topB.'"');
});

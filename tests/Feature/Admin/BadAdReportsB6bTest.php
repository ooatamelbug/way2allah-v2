<?php

/*
 * F01 / B6b — «الإبلاغ عن اعلانات غير لائقة» (OLD modules/report_ads/admin): list and delete only.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BannerAdsHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
    $this->actingAs(H::admin(['report_ads.managereports']), 'admin');
});

it('the empty list shows OLD\'s message (production has 0 reports)', function () {
    $this->get(route('admin.report_ads.index'))->assertOk()->assertSee('لم يتم إرسال أي إبلاغات حتى الأن');
});

it('lists reports newest first with OLD\'s columns', function () {
    $old = H::report('a@example.org', 'https://example.org/ad-1', '2020-01-01 10:00:00');
    $new = H::report('b@example.org', 'https://example.org/ad-2', '2021-02-02 11:00:00');

    $html = $this->get(route('admin.report_ads.index'))->assertOk()->getContent();

    expect(strpos($html, 'b@example.org'))->toBeLessThan(strpos($html, 'a@example.org'))
        ->and($html)->toContain('بريد المستخدم')->toContain('رابط الإعلان')->toContain('تاريخ الإبلاغ')
        ->toContain('2021-02-02 11:00:00')
        ->toContain('<a href="https://example.org/ad-2" target="_blank" rel="noopener noreferrer nofollow">');
});

it('never renders an unsafe stored URL or e-mail as markup or as a link (OLD printed ads_url raw into href)', function (string $url) {
    H::report('<b>x</b>@example.org', $url);

    $html = $this->get(route('admin.report_ads.index'))->assertOk()->getContent();

    expect($html)->not->toContain('href="'.$url.'"')->not->toContain("href='".$url."'")
        ->not->toContain('<b>x</b>@example.org')->toContain(e('<b>x</b>@example.org'))
        ->toContain(e($url));
})->with([
    'javascript:' => 'javascript:alert(1)',
    'data:' => 'data:text/html,<script>alert(1)</script>',
    'quote breakout' => "https://example.org/x'onmouseover='alert(1)",
    'tag injection' => 'https://example.org/"><script>alert(1)</script>',
    'protocol-relative' => '//evil.example/x',
    'malformed' => 'ttps://example.org',
]);

it('deletes a report (DELETE + CSRF), audits it without copying the e-mail, and leaves the others', function () {
    $keep = H::report('keep@example.org', 'https://example.org/keep');
    $gone = H::report('Gone@Example.org ', 'https://example.org/gone', '2020-05-05 05:05:05');

    $this->delete(route('admin.report_ads.destroy', $gone))->assertRedirect(route('admin.report_ads.index'))->assertSessionHas('success');

    expect(array_column(H::reports(), 'id'))->toBe([$keep]);
    $audit = H::auditRows()[0];
    expect($audit->action)->toBe('del_bad_ad_report')->and($audit->module)->toBe('report_ads')->and((int) $audit->entity_id)->toBe($gone)
        ->and($audit->metadata)->not->toContain('Gone@Example.org')->not->toContain('gone@example.org')
        ->and(json_decode($audit->metadata, true)['deleted'])->toBe([
            'ads_url' => 'https://example.org/gone',
            'user_email_sha256' => hash('sha256', 'gone@example.org'),
            'db_creation_date' => '2020-05-05 05:05:05',
        ]);
});

it('a markup URL is redacted in the audit', function () {
    $id = H::report('x@example.org', '<script>alert(1)</script>');

    $this->delete(route('admin.report_ads.destroy', $id))->assertSessionHas('success');

    expect(H::auditRows()[0]->metadata)->not->toContain('<script');
});

it('deleting a missing report is 404 and records nothing; GET cannot delete', function () {
    $id = H::report('x@example.org', 'https://example.org/');

    $this->delete(route('admin.report_ads.destroy', 99999))->assertNotFound();
    $this->get('/admincp/report-ads/'.$id)->assertStatus(405);
    $this->post('/admincp/report-ads/'.$id)->assertStatus(405);
    $this->get('/admincp/report-ads/'.$id.'/delete')->assertNotFound();

    expect(H::reports())->toHaveCount(1)->and(H::auditRows())->toBe([]);
});

it('deleting a report never deletes or modifies the reported advertisement or the banner set', function () {
    $main = DB::connection('main');
    $main->table('nuke_ads')->insert(['name' => 'reported ad', 'type' => 1, 'position' => 4, 'show' => 1, 'link' => 'https://example.org/reported']);
    $item = H::item('بنر', 1);
    H::member($item);
    $id = H::report('x@example.org', 'https://example.org/reported');
    $snapshot = fn () => [
        $main->table('nuke_ads')->get()->map(fn ($r) => (array) $r)->all(),
        H::set(),
        $main->table('nuke_islamic_khotab')->get()->map(fn ($r) => (array) $r)->all(),
    ];
    $before = $snapshot();

    $this->delete(route('admin.report_ads.destroy', $id))->assertSessionHas('success');

    expect(H::reports())->toBe([])->and($snapshot())->toBe($before);
});

<?php

use App\Domain\Admin\Actions\Advfiles\AdvfileTransitionRefused;
use App\Domain\Admin\Actions\Advfiles\ApproveAdvfileAction;
use App\Domain\Content\Models\KhotabItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdvfileAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 5 — the Advfiles workflow through the real
 * routes: request 0 → -1, approve -1 → 1 (+payload, +counters), reject
 * -1 → 0, edit, remove 1 → 0 (−payload, −counters), credit list (plan §24).
 */
beforeEach(function () {
    H::setUp();
    $this->actingAs(H::superAdmin(), 'admin');
});

function advPayload(array $override = []): array
{
    return array_merge(['credit' => 7, 'code' => '<object data="x.swf"></object>', 'audio_url' => 'https://cdn.example/a.mp3'], $override);
}

// ---- request (OLD RequestAdvFile) ----

it('requests: 0 → -1, nothing else changes', function () {
    $ids = H::item(0);
    $before = H::snapshot();

    $this->post(route('admin.advfiles.request', $ids['item']))
        ->assertRedirect(route('admin.advfiles.item', $ids['item']))->assertSessionHas('success');

    $after = H::snapshot();
    expect(H::state($ids['item']))->toBe(-1)
        ->and(collect($after)->except('khotab')->all())->toBe(collect($before)->except('khotab')->all());
});

it('treats a repeated request as a no-op — no write, no audit', function () {
    $ids = H::item(-1);
    $before = H::snapshot();

    $writes = H::writesDuring(fn () => $this->post(route('admin.advfiles.request', $ids['item']))
        ->assertSessionHas('error', 'يوجد طلب قائم لهذه المادة بالفعل — لم يتغير شيء'));

    expect($writes)->toBe([])->and(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('refuses a request for a published item with OLD message', function (int $state) {
    $ids = H::item($state);
    H::file($ids['item']);
    $before = H::snapshot();

    $this->post(route('admin.advfiles.request', $ids['item']))
        ->assertSessionHas('error', 'عفواً .... هذه المادة لها ملف دعائي بالفعل و لا يمكن تقديم طلب ملف دعائي مرة أخرى');

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
})->with([1, 2]);

// ---- approve (OLD InsertAdvFile / ConfirmInsertAdvFile) ----

it('approves a pending request: payload written, state 1, author/group/series counters +1', function () {
    $ids = H::item(-1, [], 5);

    $this->post(route('admin.advfiles.approve', $ids['item']), advPayload())->assertSessionHas('success');

    $file = H::payload($ids['item']);
    expect(H::state($ids['item']))->toBe(1)
        ->and([(int) $file->credit, $file->code, $file->audio_url, (int) $file->small_thumb, (int) $file->large_thumb])
        ->toBe([7, '<object data="x.swf"></object>', 'https://cdn.example/a.mp3', 0, 0])
        ->and(H::counters($ids))->toBe([6, 6, 6]);
});

it('stores code verbatim and stores an empty audio url and zero credit when omitted', function () {
    $ids = H::item(-1);
    $code = "<script>alert(1)</script>\n[audio]https://x/a.rm[/audio]";

    $this->post(route('admin.advfiles.approve', $ids['item']), ['code' => $code]);

    expect(H::payload($ids['item'])->code)->toBe($code)
        ->and(H::payload($ids['item'])->audio_url)->toBe('')
        ->and((int) H::payload($ids['item'])->credit)->toBe(0);
});

it('refuses to approve an item that was never requested (OLD: request first)', function () {
    $ids = H::item(0);
    $before = H::snapshot();

    $this->post(route('admin.advfiles.approve', $ids['item']), advPayload())
        ->assertSessionHas('error', 'عفواً ... يجب أن يتم التقدم بطلب ملف دعائي للمادة أولاً');
    $this->get(route('admin.advfiles.approve.form', $ids['item']))->assertRedirect(route('admin.advfiles.item', $ids['item']));

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('refuses to approve an already published item — a second approve never double-counts', function () {
    $ids = H::item(-1, [], 5);
    $this->post(route('admin.advfiles.approve', $ids['item']), advPayload());
    $before = H::snapshot();

    $this->post(route('admin.advfiles.approve', $ids['item']), advPayload(['credit' => 99]))
        ->assertSessionHas('error', 'هذه المادة لها ملف دعائي منشور بالفعل — استخدم التعديل');

    expect(H::snapshot())->toBe($before)->and(H::counters($ids))->toBe([6, 6, 6])->and(H::auditRows())->toHaveCount(1);
});

it('replaces a stale payload row on approve instead of failing on the unique key', function () {
    $ids = H::item(-1);
    H::file($ids['item'], ['code' => 'قديم', 'credit' => 3, 'small_thumb' => 1]);

    $this->post(route('admin.advfiles.approve', $ids['item']), advPayload(['code' => 'جديد']));

    $file = H::payload($ids['item']);
    expect(DB::connection('main')->table('nuke_islamic_advfile')->where('id', $ids['item'])->count())->toBe(1)
        ->and([$file->code, (int) $file->credit, (int) $file->small_thumb])->toBe(['جديد', 7, 1])
        ->and(H::state($ids['item']))->toBe(1);
});

it('skips counters for an item with no group or series', function () {
    $ids = H::item(-1, ['group_id' => 0, 'ser_id' => 0], 5);

    $this->post(route('admin.advfiles.approve', $ids['item']), advPayload());

    expect(H::counters($ids))->toBe([6, 5, 5]);
});

// ---- reject (OLD ResetAdvFile) ----

it('rejects a pending request: -1 → 0, nothing else', function () {
    $ids = H::item(-1);
    $before = H::snapshot();

    $this->post(route('admin.advfiles.reject', $ids['item']))->assertSessionHas('success');

    expect(H::state($ids['item']))->toBe(0)
        ->and(collect(H::snapshot())->except('khotab')->all())->toBe(collect($before)->except('khotab')->all());
});

it('refuses to reject a published item (OLD message) and no-ops on an unrequested one', function () {
    $published = H::item(1);
    H::file($published['item']);
    $none = H::item(0);
    $before = H::snapshot();

    $this->post(route('admin.advfiles.reject', $published['item']))->assertSessionHas('error', 'يوجد ملف دعائي لهذا الدرس بالفعل');
    $this->post(route('admin.advfiles.reject', $none['item']))->assertSessionHas('error');

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

// ---- edit (OLD ConfirmEditAdvFile) ----

it('edits credit, code and audio url; state, counters and thumbs stay', function () {
    $ids = H::item(1, [], 5);
    H::file($ids['item'], ['credit' => 2, 'code' => 'أ', 'audio_url' => 'u1', 'large_thumb' => 1]);

    $this->put(route('admin.advfiles.update', $ids['item']), ['credit' => 9, 'code' => 'ب', 'audio_url' => 'u2'])->assertSessionHas('success');

    $file = H::payload($ids['item']);
    expect([(int) $file->credit, $file->code, $file->audio_url, (int) $file->large_thumb])->toBe([9, 'ب', 'u2', 1])
        ->and(H::state($ids['item']))->toBe(1)->and(H::counters($ids))->toBe([5, 5, 5]);
});

it('treats an unchanged edit as a no-op and writes only changed columns otherwise', function () {
    $ids = H::item(1);
    H::file($ids['item'], ['credit' => 2, 'code' => 'أ', 'audio_url' => 'u1']);

    $noop = H::writesDuring(fn () => $this->put(route('admin.advfiles.update', $ids['item']), ['credit' => 2, 'code' => 'أ', 'audio_url' => 'u1'])->assertSessionHas('error'));
    $one = H::writesDuring(fn () => $this->put(route('admin.advfiles.update', $ids['item']), ['credit' => 3, 'code' => 'أ', 'audio_url' => 'u1']));

    $updates = collect($one)->filter(fn ($w) => str_starts_with(strtolower($w['sql']), 'update'))->values()->all();
    expect($noop)->toBe([])
        ->and($updates)->toHaveCount(1)
        ->and($updates[0]['sql'])->toContain('"nuke_islamic_advfile"')->toContain('"credit"')->not->toContain('"code"');
});

it('refuses to edit an item that is not published, or published without a payload row', function () {
    $pending = H::item(-1);
    $orphanState = H::item(1);
    $before = H::snapshot();

    $this->put(route('admin.advfiles.update', $pending['item']), advPayload())->assertSessionHas('error');
    $this->put(route('admin.advfiles.update', $orphanState['item']), advPayload())->assertSessionHas('error');
    $this->get(route('admin.advfiles.edit', $orphanState['item']))->assertRedirect(route('admin.advfiles.item', $orphanState['item']));

    expect(H::snapshot())->toBe($before);
});

// ---- remove (OLD ConfirmDeleteAdvFile) ----

it('removes a published file: row deleted, state 0, counters -1', function () {
    $ids = H::item(1, [], 5);
    H::file($ids['item']);

    $this->delete(route('admin.advfiles.unpublish', $ids['item']))->assertSessionHas('success');

    expect(H::payload($ids['item']))->toBeNull()->and(H::state($ids['item']))->toBe(0)->and(H::counters($ids))->toBe([4, 4, 4]);
});

it('floors counters at zero on remove', function () {
    $ids = H::item(1, [], 0);
    H::file($ids['item']);

    $this->delete(route('admin.advfiles.unpublish', $ids['item']));

    expect(H::counters($ids))->toBe([0, 0, 0]);
});

it('removes a published item that lost its payload row (OLD could not)', function () {
    $ids = H::item(1, [], 5);

    $this->delete(route('admin.advfiles.unpublish', $ids['item']))->assertSessionHas('success');

    expect(H::state($ids['item']))->toBe(0)->and(H::counters($ids))->toBe([4, 4, 4]);
});

it('removes the unexplained state 2 as published, which every OLD reader treats it as', function () {
    $ids = H::item(2, [], 5);
    H::file($ids['item']);

    $this->delete(route('admin.advfiles.unpublish', $ids['item']))->assertSessionHas('success');

    expect(H::state($ids['item']))->toBe(0)->and(H::counters($ids))->toBe([4, 4, 4]);
});

it('refuses to remove from an unpublished item — a stale row there is left and counters untouched', function () {
    $ids = H::item(0, [], 5);
    H::file($ids['item']);
    $before = H::snapshot();

    $this->delete(route('admin.advfiles.unpublish', $ids['item']))->assertSessionHas('error');

    expect(H::snapshot())->toBe($before);
});

it('runs the complete lifecycle: request → reject → request → approve → edit → remove → request', function () {
    $ids = H::item(0, [], 5);
    $item = $ids['item'];

    $this->post(route('admin.advfiles.request', $item));
    expect(H::state($item))->toBe(-1);
    $this->post(route('admin.advfiles.reject', $item));
    expect(H::state($item))->toBe(0);
    $this->post(route('admin.advfiles.request', $item));
    $this->post(route('admin.advfiles.approve', $item), advPayload());
    expect([H::state($item), H::counters($ids)])->toBe([1, [6, 6, 6]]);
    $this->put(route('admin.advfiles.update', $item), advPayload(['credit' => 1]));
    $this->delete(route('admin.advfiles.unpublish', $item));
    expect([H::state($item), H::payload($item), H::counters($ids)])->toBe([0, null, [5, 5, 5]]);
    $this->post(route('admin.advfiles.request', $item));
    expect(H::state($item))->toBe(-1);

    expect(collect(H::auditRows())->pluck('action')->all())
        ->toBe(['request_adv', 'reset_advfile', 'request_adv', 'add_advfile', 'edit_advfile', 'del_advfile', 'request_adv']);
});

// ---- listing ----

it('lists pending requests only, by item id ascending (OLD AdvfilesMain)', function () {
    // Inserted out of id order, so the list order can only come from ORDER BY id.
    H::item(-1, ['id' => 50, 'title' => 'طلب ثان']);
    H::item(-1, ['id' => 10, 'title' => 'طلب أول']);
    H::item(0, ['title' => 'غير مطلوب']);
    H::item(1, ['title' => 'منشور']);

    $content = $this->get(route('admin.advfiles.queue'))->assertOk()->getContent();

    expect($content)->toContain('طلب أول')->toContain('طلب ثان')->not->toContain('غير مطلوب')->not->toContain('منشور')
        ->and(mb_strpos($content, 'طلب أول'))->toBeLessThan(mb_strpos($content, 'طلب ثان'));
});

it('lists credits > 0 largest first and saves only changed rows (OLD Credit / ConfirmCredit)', function () {
    $low = H::item(1, ['title' => 'قليل']);
    $high = H::item(1, ['title' => 'كثير']);
    $zero = H::item(1, ['title' => 'صفر']);
    H::file($low['item'], ['credit' => 3]);
    H::file($high['item'], ['credit' => 900]);
    H::file($zero['item'], ['credit' => 0]);

    $content = $this->get(route('admin.advfiles.credits'))->assertOk()->getContent();
    expect(mb_strpos($content, 'كثير'))->toBeLessThan(mb_strpos($content, 'قليل'))->and($content)->not->toContain('صفر');

    $writes = H::writesDuring(fn () => $this->post(route('admin.advfiles.credits.save'), ['credit' => [$low['item'] => 3, $high['item'] => 10]]));
    expect(collect($writes)->filter(fn ($w) => str_contains($w['sql'], 'nuke_islamic_advfile'))->count())->toBe(1)
        ->and((int) H::payload($high['item'])->credit)->toBe(10);
});

it('ignores credit ids that have no payload row (OLD wrote any id it was sent)', function () {
    $ids = H::item(0);
    $before = H::snapshot();

    $this->post(route('admin.advfiles.credits.save'), ['credit' => [$ids['item'] => 50, 999 => 1]])->assertSessionHas('error');

    expect(H::snapshot())->toBe($before);
});

it('validates payload and credits', function () {
    $ids = H::item(-1);

    $this->post(route('admin.advfiles.approve', $ids['item']), ['credit' => -1])->assertSessionHasErrors('credit');
    $this->post(route('admin.advfiles.approve', $ids['item']), ['credit' => 'abc'])->assertSessionHasErrors('credit');
    $this->post(route('admin.advfiles.credits.save'), ['credit' => [$ids['item'] => 1000000]])->assertSessionHasErrors('credit.'.$ids['item']);
    $this->post(route('admin.advfiles.credits.save'), [])->assertSessionHasErrors('credit');

    expect(H::state($ids['item']))->toBe(-1);
});

it('renders the item page with labels, the code as escaped text, and state-appropriate actions', function () {
    $ids = H::item(1, ['title' => 'درس مميز']);
    H::file($ids['item'], ['code' => '<b>عرض</b>', 'credit' => 4]);

    $this->get(route('admin.advfiles.item', $ids['item']))->assertOk()
        ->assertSee('درس مميز')->assertSee('منشور في الملفات الدعائية')
        ->assertSee('&lt;b&gt;عرض&lt;/b&gt;', false)->assertDontSee('<b>عرض</b>', false)
        ->assertSee(route('admin.advfiles.edit', $ids['item']), false)
        ->assertDontSee(route('admin.advfiles.request', $ids['item']), false);
});

it('returns 404 for a missing item', function () {
    $this->post(route('admin.advfiles.request', 4040))->assertNotFound();
    $this->get(route('admin.advfiles.item', 4040))->assertNotFound();
});

it('decides from the locked row, not a stale model — a concurrent approval cannot double-publish', function () {
    $ids = H::item(-1, [], 5);
    $stale = KhotabItem::query()->findOrFail($ids['item']);   // loaded while -1
    app(ApproveAdvfileAction::class)->execute(H::superAdmin('adv-first'), $stale, ['credit' => 1, 'code' => 'a', 'audio_url' => '']);
    $after = H::snapshot();

    expect(fn () => app(ApproveAdvfileAction::class)->execute(H::superAdmin('adv-second'), $stale, ['credit' => 2, 'code' => 'b', 'audio_url' => '']))
        ->toThrow(AdvfileTransitionRefused::class);

    expect(H::snapshot())->toBe($after)->and(H::counters($ids))->toBe([6, 6, 6]);
});

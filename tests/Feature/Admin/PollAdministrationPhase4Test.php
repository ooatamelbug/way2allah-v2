<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PollAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 4 — PHP-Nuke poll create / edit / remove through
 * the real routes, against the contract recovered from
 * `modules/Surveys/admin/index.php` (plan §23).
 */
beforeEach(function () {
    H::setUp();
    $this->actingAs(H::superAdmin(), 'admin');
});

function createPoll(array $data)
{
    return test()->post(route('admin.polls.store'), $data);
}

// ---- create (OLD poll_createPosted) ----

it('creates a poll with twelve slots, blanks stored empty, counts and voters at zero', function () {
    $before = time();
    createPoll(['pollTitle' => 'أفضل وقت للدروس؟', 'options' => [1 => 'صباحا', 2 => 'مساء', 4 => 'ليلا']])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    $poll = DB::connection('main')->table('nuke_poll_desc')->first();
    expect($poll->pollTitle)->toBe('أفضل وقت للدروس؟')
        ->and((int) $poll->voters)->toBe(0)
        ->and((int) $poll->artid)->toBe(0)
        ->and((int) $poll->comments)->toBe(0)
        ->and($poll->planguage)->toBe('arabic')
        ->and((int) $poll->timeStamp)->toBeGreaterThanOrEqual($before);

    $rows = DB::connection('main')->table('nuke_poll_data')->where('pollID', $poll->pollID)->orderBy('voteID')->get();
    expect($rows)->toHaveCount(12)
        ->and($rows->pluck('voteID')->map(fn ($v) => (int) $v)->all())->toBe(range(1, 12))
        ->and($rows->pluck('optionText')->all())->toBe(['صباحا', 'مساء', '', 'ليلا', '', '', '', '', '', '', '', ''])
        ->and($rows->sum('optionCount'))->toBe(0);
});

it('takes the language from nuke_config, as OLD took $language', function () {
    DB::connection('main')->table('nuke_config')->update(['language' => 'english']);

    createPoll(['pollTitle' => 'Q']);

    expect(DB::connection('main')->table('nuke_poll_desc')->value('planguage'))->toBe('english');
});

it('attaches choices to the NEW poll even when its title duplicates an older one (OLD title-lookup defect corrected)', function () {
    $older = H::poll('مكرر', ['أ', 'ب']);

    createPoll(['pollTitle' => 'مكرر', 'options' => [1 => 'جديد']]);

    $newer = (int) DB::connection('main')->table('nuke_poll_desc')->where('pollID', '!=', $older)->value('pollID');
    expect(DB::connection('main')->table('nuke_poll_data')->where('pollID', $older)->count())->toBe(12)
        ->and(H::texts($older)[1])->toBe('أ')
        ->and(DB::connection('main')->table('nuke_poll_data')->where('pollID', $newer)->count())->toBe(12)
        ->and(H::texts($newer)[1])->toBe('جديد');
});

it('accepts a poll with no choices, as OLD did', function () {
    createPoll(['pollTitle' => 'بلا خيارات'])->assertSessionHasNoErrors();

    expect(DB::connection('main')->table('nuke_poll_data')->where('optionText', '')->count())->toBe(12);
});

it('validates the title and the column lengths, writing nothing on failure', function (array $data, string $field) {
    $before = H::snapshot();

    createPoll($data)->assertSessionHasErrors($field);

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
})->with([
    'missing title' => [[], 'pollTitle'],
    'blank title' => [['pollTitle' => '   '], 'pollTitle'],
    'title over 100' => [['pollTitle' => str_repeat('ا', 101)], 'pollTitle'],
    'choice over 50' => [['pollTitle' => 'Q', 'options' => [1 => str_repeat('ب', 51)]], 'options.1'],
]);

it('ignores slots outside 1–12', function () {
    createPoll(['pollTitle' => 'Q', 'options' => [0 => 'zero', 13 => 'thirteen', 2 => 'two']]);

    $texts = DB::connection('main')->table('nuke_poll_data')->pluck('optionText')->all();
    expect($texts)->toContain('two')->not->toContain('zero')->not->toContain('thirteen')->toHaveCount(12);
});

it('does not write a News story — the OLD announce-story half is not reproduced', function () {
    $writes = H::writesDuring(fn () => createPoll([
        'pollTitle' => 'Q', 'title' => 'خبر', 'hometext' => 'نص', 'bodytext' => 'تفاصيل', 'topic' => 1, 'catid' => 2,
    ]));

    expect(collect($writes)->filter(fn ($w) => str_contains($w['sql'], 'stories')))->toBeEmpty()
        ->and(collect($writes)->map(fn ($w) => preg_match('/(?:into|update|from) "([^"]+)"/', $w['sql'], $m) ? $m[1] : '?')->unique()->sort()->values()->all())
        ->toBe(['admin_audit_log', 'nuke_poll_data', 'nuke_poll_desc']);
});

// ---- edit (OLD polledit / savepoll) ----

it('edits title and choice texts while keeping every vote, voter, timestamp and language', function () {
    $poll = H::poll('قديم', ['أ', 'ب', ''], [5, 3], ['timeStamp' => 1500000000, 'planguage' => 'english', 'comments' => 4]);

    $this->put(route('admin.polls.update', $poll), [
        'pollTitle' => 'جديد', 'options' => [1 => 'أ', 2 => 'ب معدل', 3 => 'ج'] + array_fill_keys(range(4, 12), ''),
    ])->assertRedirect(route('admin.polls.edit', $poll))->assertSessionHas('success');

    $desc = H::desc($poll);
    expect($desc->pollTitle)->toBe('جديد')
        ->and($desc->planguage)->toBe('english')
        ->and((int) $desc->timeStamp)->toBe(1500000000)
        ->and((int) $desc->voters)->toBe(8)
        ->and((int) $desc->comments)->toBe(4)
        ->and(array_slice(H::texts($poll), 0, 3, true))->toBe([1 => 'أ', 2 => 'ب معدل', 3 => 'ج'])
        ->and(DB::connection('main')->table('nuke_poll_data')->where('pollID', $poll)->orderBy('voteID')->pluck('optionCount')->map(fn ($c) => (int) $c)->take(3)->all())->toBe([5, 3, 0]);
});

it('blanks a slot the form leaves out, as OLD savepoll did', function () {
    $poll = H::poll('Q', ['أ', 'ب']);

    $this->put(route('admin.polls.update', $poll), ['pollTitle' => 'Q', 'options' => [1 => 'أ']]);

    expect(H::texts($poll)[2])->toBe('');
});

it('never creates a slot that has no row', function () {
    $poll = H::poll('Q', ['أ']);
    DB::connection('main')->table('nuke_poll_data')->where('pollID', $poll)->where('voteID', '>', 2)->delete();

    $this->put(route('admin.polls.update', $poll), ['pollTitle' => 'Q', 'options' => [1 => 'أ', 2 => 'ب', 3 => 'ج']]);

    expect(DB::connection('main')->table('nuke_poll_data')->where('pollID', $poll)->count())->toBe(2)
        ->and(H::texts($poll))->toBe([1 => 'أ', 2 => 'ب']);
});

it('rewrites every duplicate row of a slot, as OLD UPDATE … WHERE voteID did', function () {
    $poll = H::poll('Q', ['أ']);
    DB::connection('main')->table('nuke_poll_data')->insert(['pollID' => $poll, 'voteID' => 1, 'optionText' => 'نسخة', 'optionCount' => 2]);

    $this->put(route('admin.polls.update', $poll), ['pollTitle' => 'Q', 'options' => [1 => 'موحد'] + array_fill_keys(range(2, 12), '')]);

    expect(DB::connection('main')->table('nuke_poll_data')->where('pollID', $poll)->where('voteID', 1)->pluck('optionText')->all())->toBe(['موحد', 'موحد'])
        ->and((int) DB::connection('main')->table('nuke_poll_data')->where('pollID', $poll)->where('voteID', 1)->sum('optionCount'))->toBe(2);
});

it('treats an unchanged submission as a no-op: no write, no audit', function () {
    $poll = H::poll('Q', ['أ', 'ب'], [1, 1]);
    $before = H::snapshot();

    $writes = H::writesDuring(fn () => $this->put(route('admin.polls.update', $poll), [
        'pollTitle' => 'Q', 'options' => [1 => 'أ', 2 => 'ب'] + array_fill_keys(range(3, 12), ''),
    ])->assertSessionHas('error'));

    expect($writes)->toBe([])->and(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('writes only the changed rows of the edited poll', function () {
    $poll = H::poll('Q', ['أ', 'ب']);
    $other = H::poll('آخر', ['أ', 'ب']);
    $before = H::snapshot();

    $writes = H::writesDuring(fn () => $this->put(route('admin.polls.update', $poll), [
        'pollTitle' => 'Q', 'options' => [1 => 'أ', 2 => 'تغيير'] + array_fill_keys(range(3, 12), ''),
    ]));

    $updates = collect($writes)->filter(fn ($w) => stripos($w['sql'], 'update') === 0)->values()->all();
    expect($updates)->toHaveCount(1)
        ->and($updates[0]['sql'])->toContain('"nuke_poll_data"')
        ->and($updates[0]['bindings'])->toBe(['تغيير', $poll, 2]);
    $after = H::snapshot();
    expect($after['desc'])->toBe($before['desc'])
        ->and(collect($after['data'])->where('pollID', $other)->values()->all())->toBe(collect($before['data'])->where('pollID', $other)->values()->all());
});

it('renders the edit page with each slot and its vote count', function () {
    $poll = H::poll('سؤال', ['نعم', 'لا'], [7, 2]);

    $this->get(route('admin.polls.edit', $poll))->assertOk()
        ->assertSee('سؤال')->assertSee('value="نعم"', false)->assertSee('7 تصويتات')->assertSee('2 تصويتات');
});

// ---- remove (OLD poll_removePosted) ----

it('removes the poll and all its choices and votes, leaving comments and other polls', function () {
    $poll = H::poll('Q', ['أ', 'ب'], [10, 4], ['comments' => 1]);
    $other = H::poll('آخر');
    $comment = H::comment($poll);

    $this->delete(route('admin.polls.destroy', $poll))->assertRedirect(route('admin.polls.index'));

    expect(H::desc($poll))->toBeNull()
        ->and(DB::connection('main')->table('nuke_poll_data')->where('pollID', $poll)->count())->toBe(0)
        ->and(DB::connection('main')->table('nuke_pollcomments')->where('tid', $comment)->exists())->toBeTrue()
        ->and(H::desc($other))->not->toBeNull()
        ->and(DB::connection('main')->table('nuke_poll_data')->where('pollID', $other)->count())->toBe(12);
});

it('returns 404 for a missing poll on edit, update and delete', function () {
    $this->get(route('admin.polls.edit', 4040))->assertNotFound();
    $this->put(route('admin.polls.update', 4040), ['pollTitle' => 'Q'])->assertNotFound();
    $this->delete(route('admin.polls.destroy', 4040))->assertNotFound();
});

it('lists every poll in OLD order (timeStamp ascending), article polls included', function () {
    H::poll('الأحدث', [], [], ['timeStamp' => 300]);
    H::poll('الأقدم', [], [], ['timeStamp' => 100]);
    H::poll('مرتبط بمقال', [], [], ['timeStamp' => 200, 'artid' => 9]);

    $content = $this->get(route('admin.polls.index'))->assertOk()->getContent();

    $positions = array_map(fn ($t) => mb_strpos($content, $t), ['الأقدم', 'مرتبط بمقال', 'الأحدث']);
    expect($positions)->each->toBeInt()->and($positions)->toBe(collect($positions)->sort()->values()->all());
});

it('never touches the custom-survey tables', function () {
    DB::connection('main')->table('nuke_survey')->insert(['title' => 'استبيان مخصص', 'questions' => 2, 'submits' => 5]);
    $before = H::snapshot();

    createPoll(['pollTitle' => 'Q', 'options' => [1 => 'أ']]);
    $poll = (int) DB::connection('main')->table('nuke_poll_desc')->value('pollID');
    $this->put(route('admin.polls.update', $poll), ['pollTitle' => 'R']);
    $this->delete(route('admin.polls.destroy', $poll));

    $after = H::snapshot();
    expect($after['survey'])->toBe($before['survey'])
        ->and($after['survey_questions'])->toBe($before['survey_questions'])
        ->and($after['survey_answers'])->toBe($before['survey_answers']);
});

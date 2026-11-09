<?php

/*
 * Final public features — public poll comments (OLD modules/Surveys/comments.php),
 * owner decision 2026-10-15: the historical URL contracts, OLD's threading,
 * single-comment, reply, preview and posting rules, safe rendering, and the
 * Phase 4 delete offered contextually.
 */

use App\Domain\Identity\Models\VbUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

function pollMember(string $name = 'أبو يحيى', int $id = 501): VbUser
{
    $user = new VbUser;
    $user->forceFill(['userid' => $id, 'username' => $name]);
    $user->exists = true;

    return $user;
}

function pollCommentRow(array $attributes): int
{
    return H::insert('nuke_pollcomments', $attributes + ['pid' => 0, 'date' => '2010-01-01 10:00:00', 'name' => 'عضو', 'subject' => 'موضوع', 'comment' => 'نص', 'score' => 1], 'tid');
}

beforeEach(function () {
    H::setUp();
    DB::connection('main')->table('nuke_config')->update(['anonymous' => 'زائر', 'anonpost' => 0, 'pollcomm' => 1, 'commentlimit' => 4096]);
    $this->poll = H::insert('nuke_poll_desc', ['pollTitle' => 'ما رأيك؟', 'timeStamp' => 1, 'voters' => 0, 'planguage' => 'arabic', 'artid' => 0, 'comments' => 3], 'pollID');
    $this->other = H::insert('nuke_poll_desc', ['pollTitle' => 'آخر', 'timeStamp' => 2, 'voters' => 0, 'planguage' => 'arabic', 'artid' => 0, 'comments' => 0], 'pollID');
    $this->first = pollCommentRow(['pollID' => $this->poll, 'date' => '2010-01-01 10:00:00', 'name' => 'محمود', 'subject' => 'الأول', 'comment' => 'تعليق أول <script>alert(1)</script><b onclick="x()">مهم</b>', 'score' => 1]);
    $this->second = pollCommentRow(['pollID' => $this->poll, 'date' => '2010-03-01 10:00:00', 'name' => '', 'subject' => '', 'comment' => 'تعليق ثان', 'score' => 0]);
    $this->reply = pollCommentRow(['pollID' => $this->poll, 'pid' => $this->first, 'date' => '2010-02-01 10:00:00', 'name' => 'سعيد', 'subject' => 'Re: الأول', 'comment' => 'رد على الأول', 'score' => 1]);
    $this->deep = pollCommentRow(['pollID' => $this->poll, 'pid' => $this->reply, 'date' => '2010-02-02 10:00:00', 'name' => 'خالد', 'subject' => 'رد أعمق', 'comment' => 'نص أعمق', 'score' => 1]);
    $this->foreign = pollCommentRow(['pollID' => $this->other, 'subject' => 'لإستفتاء آخر']);
});

// ---------------------------------------------------------------- reading

it('shows the poll\'s comments: OLD\'s header, full top-level comments, safe rendering', function () {
    $html = $this->get("/survey-comments-{$this->poll}.htm")->assertOk()->getContent();

    expect($html)->toContain('"ما رأيك؟" | <b>4</b> تعليقات')
        ->toContain('<b>الأول</b>')->toContain('(التقييم: 1)')->toContain('بواسطة محمود في 2010-01-01 10:00')
        ->toContain('<b>مهم</b>')->not->toContain('<script>alert')->not->toContain('onclick')
        ->toContain('بواسطة زائر')->toContain('[لا يوجد موضوع]')
        ->toContain('التعليقات مملوكة لأصحابها')
        ->not->toContain('لإستفتاء آخر')
        ->and(strpos($html, 'الأول'))->toBeLessThan(strpos($html, 'تعليق ثان'));
});

it('thread mode lists replies as nested links to their sub-thread; nested and flat show them in full', function () {
    $thread = $this->get("/survey-comments-{$this->poll}.htm")->getContent();
    expect($thread)->toContain('<a href="/survey-showreply-'.$this->reply.'-'.$this->poll.'-'.$this->first.'-thread-0-0.htm#'.$this->reply.'">Re: الأول</a>')
        ->toContain('بواسطة سعيد في 2010-02-01 10:00')->toContain('/survey-showreply-'.$this->deep.'-'.$this->poll.'-'.$this->reply.'-thread-0-0.htm')
        ->not->toContain('رد على الأول');

    foreach (['nested', 'flat'] as $mode) {
        $page = $this->get("/survey-comments-{$this->poll}.htm?mode={$mode}")->getContent();
        expect($page)->toContain('رد على الأول')->toContain('نص أعمق');
    }
});

it('honours OLD\'s order (1 = newest first, 2 = highest score) and threshold', function () {
    $newest = $this->get("/survey-comments-{$this->poll}.htm?order=1")->getContent();
    expect(strpos($newest, 'تعليق ثان'))->toBeLessThan(strpos($newest, 'تعليق أول'));

    $scored = $this->get("/survey-comments-{$this->poll}.htm?thold=1")->getContent();
    expect($scored)->toContain('تعليق أول')->not->toContain('تعليق ثان');
});

it('serves the historical URL contracts', function () {
    $prefs = 'thread-0-0';

    $this->get("/survey-comment-{$this->poll}-tid-{$this->reply}-{$prefs}.htm")->assertOk()->assertSee('رد على الأول')->assertSee('href="/survey-'.$this->poll.'.htm"', false);
    $this->get("/survey-comment-{$this->poll}-pid-{$this->first}-{$prefs}.htm")->assertRedirect(route('engagement.polls.comments', $this->poll));
    $this->get("/survey-showreply-{$this->reply}-{$this->poll}-{$this->first}-{$prefs}.htm")->assertOk()->assertSee('رد على الأول')->assertDontSee('تعليق ثان')->assertSee('الأصل');
    $this->get("/survey-commreply-{$this->first}-{$this->poll}-{$prefs}.htm")->assertOk()->assertSee('التعليق غير مسموح للضيوف');
    // OLD allowed the preference segments to be empty.
    $this->get("/survey-comment-{$this->poll}-tid-{$this->first}---.htm")->assertOk();
    // A comment of another poll is not reachable through this poll.
    $this->get("/survey-comment-{$this->poll}-tid-{$this->foreign}-{$prefs}.htm")->assertNotFound();
});

it('does not register OLD\'s dead, poll-less comment URLs', function () {
    $this->get('/survey-comments.htm')->assertNotFound();
    $this->get("/survey-showreply-{$this->reply}-thread-0-0.htm")->assertNotFound();
});

it('cuts comments longer than commentlimit and links «read the rest» to the single comment', function () {
    DB::connection('main')->table('nuke_config')->update(['commentlimit' => 5]);

    $html = $this->get("/survey-comments-{$this->poll}.htm")->getContent();
    expect($html)->toContain('أقرأ باقي التعليق...')->toContain('/survey-comment-'.$this->poll.'-tid-'.$this->first.'-thread-0-0.htm');

    expect($this->get("/survey-comment-{$this->poll}-tid-{$this->first}-thread-0-0.htm")->getContent())->toContain('<b>مهم</b>');
});

it('is off when PHP-Nuke\'s pollcomm is off', function () {
    DB::connection('main')->table('nuke_config')->update(['pollcomm' => 0]);

    $this->get("/survey-comments-{$this->poll}.htm")->assertNotFound();
    expect($this->get("/survey-results-{$this->poll}.htm")->getContent())->not->toContain('/survey-comments-');
});

it('links the comment page from the poll results', function () {
    expect($this->get("/survey-results-{$this->poll}.htm")->getContent())->toContain('href="/survey-comments-'.$this->poll.'.htm"')->toContain('تعليقات: 4')
        ->and($this->get("/survey-results-{$this->other}.htm")->getContent())->toContain('href="/survey-comments-'.$this->other.'.htm"');
});

it('never shows a poster\'s email or IP', function () {
    DB::connection('main')->table('nuke_pollcomments')->where('tid', $this->first)->update(['email' => 'secret@example.com', 'host_name' => '10.9.8.7']);

    $visitor = $this->get("/survey-comments-{$this->poll}.htm")->getContent();
    $boss = $this->withSession(H::session(H::superAdmin()))->get("/survey-comments-{$this->poll}.htm")->getContent();

    foreach ([$visitor, $boss] as $html) {
        expect($html)->not->toContain('secret@example.com')->not->toContain('10.9.8.7');
    }
});

// ---------------------------------------------------------------- posting

it('lets a member reply: OLD\'s form, then the comment under their name with score 1 and the counter +1', function () {
    $this->actingAs(pollMember(), 'vbulletin');

    $form = $this->get("/survey-commreply-{$this->first}-{$this->poll}-thread-0-0.htm")->assertOk()->getContent();
    expect($form)->toContain('value="Re: الأول"')->toContain('تعليق أول')->toContain('name="_token"')->toContain('اسمك:</b> أبو يحيى')
        ->toContain('value="preview"')->toContain('value="ok"')->not->toContain('xanonpost');

    $response = $this->post(route('engagement.polls.comments.submit', $this->poll), [
        'op' => 'ok', 'subject' => 'Re: الأول <b>x</b>', 'comment' => "سطر <script>alert(1)</script>\nسطر ثان", 'pid' => $this->first,
    ]);

    $row = DB::connection('main')->table('nuke_pollcomments')->orderByDesc('tid')->first();
    $response->assertRedirect(route('engagement.polls.comments', $this->poll).'#'.$row->tid);
    expect($row->pid)->toBe($this->first)->and($row->name)->toBe('أبو يحيى')->and((int) $row->score)->toBe(1)
        ->and($row->subject)->toBe('Re: الأول x')->and($row->email)->toBe('')->and($row->host_name)->toBe('127.0.0.1')
        ->and($row->comment)->toBe('سطر &lt;script&gt;alert(1)&lt;/script&gt;<br>'."\n".'سطر ثان')
        ->and((int) DB::connection('main')->table('nuke_poll_desc')->where('pollID', $this->poll)->value('comments'))->toBe(4);

    // A reply: thread mode lists it as a link; nested mode shows its body.
    expect($this->get(route('engagement.polls.comments', $this->poll).'?mode=nested')->getContent())->toContain('سطر &lt;script&gt;')->not->toContain('<script>alert');
});

it('previews without saving', function () {
    $this->actingAs(pollMember(), 'vbulletin');

    $html = $this->post(route('engagement.polls.comments.submit', $this->poll), ['op' => 'preview', 'subject' => 'معاينة', 'comment' => "<b>نص</b>\nثان", 'pid' => 0])
        ->assertOk()->getContent();

    expect($html)->toContain('عرض تعليق على الإستفتاء')->toContain('&lt;b&gt;نص&lt;/b&gt;<br>')->toContain('value="معاينة"')
        ->and(DB::connection('main')->table('nuke_pollcomments')->count())->toBe(5);
});

it('refuses guests while anonpost is off, and an admin session that is not a member', function () {
    $this->post(route('engagement.polls.comments.submit', $this->poll), ['op' => 'ok', 'comment' => 'x'])->assertForbidden();
    $this->withSession(H::session(H::superAdmin()))->post(route('engagement.polls.comments.submit', $this->poll), ['op' => 'ok', 'comment' => 'x'])->assertForbidden();

    expect(DB::connection('main')->table('nuke_pollcomments')->count())->toBe(5);
});

it('lets guests post anonymously when anonpost is on, and members choose to', function () {
    DB::connection('main')->table('nuke_config')->update(['anonpost' => 1]);

    $this->post(route('engagement.polls.comments.submit', $this->poll), ['op' => 'ok', 'subject' => 'زائر', 'comment' => 'من زائر'])->assertRedirect();
    $guest = DB::connection('main')->table('nuke_pollcomments')->orderByDesc('tid')->first();
    expect($guest->name)->toBe('')->and((int) $guest->score)->toBe(0);

    $this->actingAs(pollMember(), 'vbulletin');
    expect($this->get("/survey-commreply-0-{$this->poll}-thread-0-0.htm")->getContent())->toContain('name="xanonpost"');
    $this->post(route('engagement.polls.comments.submit', $this->poll), ['op' => 'ok', 'comment' => 'متخف', 'xanonpost' => 1])->assertRedirect();
    $hidden = DB::connection('main')->table('nuke_pollcomments')->orderByDesc('tid')->first();
    expect($hidden->name)->toBe('')->and((int) $hidden->score)->toBe(0);
});

it('validates input and refuses a parent from another poll', function () {
    $this->actingAs(pollMember(), 'vbulletin');
    $from = "/survey-commreply-0-{$this->poll}-thread-0-0.htm";

    $this->from($from)->post(route('engagement.polls.comments.submit', $this->poll), ['op' => 'ok', 'comment' => '   '])->assertRedirect($from)->assertSessionHasErrors('comment');
    $this->from($from)->post(route('engagement.polls.comments.submit', $this->poll), ['op' => 'ok', 'comment' => 'x', 'subject' => str_repeat('م', 61)])->assertSessionHasErrors('subject');
    $this->from($from)->post(route('engagement.polls.comments.submit', $this->poll), ['op' => 'ok', 'comment' => 'x', 'pid' => $this->foreign])->assertSessionHasErrors('pid');
    $this->from($from)->post(route('engagement.polls.comments.submit', $this->poll), ['op' => 'drop', 'comment' => 'x'])->assertSessionHasErrors('op');
    $this->post(route('engagement.polls.comments.submit', 999999), ['op' => 'ok', 'comment' => 'x'])->assertNotFound();

    expect(DB::connection('main')->table('nuke_pollcomments')->count())->toBe(5);
});

// ---------------------------------------------------------------- contextual delete (Phase 4)

it('offers «مسح» beside each comment to a super-admin only, and it runs Phase 4\'s thread delete', function () {
    expect($this->get("/survey-comments-{$this->poll}.htm")->getContent())->not->toContain('data-contextual-admin');
    expect($this->withSession(H::session(H::admin(['polls.managepolls'])))->get("/survey-comments-{$this->poll}.htm")->getContent())->not->toContain(route('admin.polls.comments.destroy', $this->first));

    $html = $this->withSession(H::session(H::superAdmin()))->get("/survey-comments-{$this->poll}.htm")->getContent();
    expect($html)->toContain('action="'.route('admin.polls.comments.destroy', $this->first).'"')->toContain('name="_method" value="DELETE"');

    $this->delete(route('admin.polls.comments.destroy', $this->first), ['return_to' => "/survey-comments-{$this->poll}.htm"])
        ->assertRedirect("/survey-comments-{$this->poll}.htm");

    expect(DB::connection('main')->table('nuke_pollcomments')->whereIn('tid', [$this->first, $this->reply, $this->deep])->count())->toBe(0)
        ->and(DB::connection('main')->table('nuke_pollcomments')->where('tid', $this->second)->exists())->toBeTrue();
});

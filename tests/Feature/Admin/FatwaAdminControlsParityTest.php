<?php

/*
 * OLD public admin parity — Fatwa (owner, 2026-10-30): the fourteen working
 * OLD public controls — C1; T1, T2; Q1, Q2, Q4, Q5, Q6; A1–A5; X2 — behind
 * one permission (`fatwa.manage`), with the owner-approved data-integrity
 * corrections: the sheikh's `fatwa` counter, the canonical fatwa `topic_id`
 * (first topic of the first question), place_of_fatwa preserved, decimal
 * media_size, NULL dates, validated targets, relationship order / minimums,
 * T1's duplicate check, C1's single q_count walk, transactions. Dead OLD
 * controls (C2, T3, Q3, D1–D7, X1) must stay absent; visitors see nothing.
 */

use App\Domain\Admin\Contextual\Controls\FatwaControls;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\LegacyAuditLogCompatibilityWriter;
use App\Domain\Identity\Models\VbUser;
use App\Support\Permission\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();

    // root category 100 → category 101 (the page); an unrelated category 200.
    H::insert('nuke_w2a_cat', ['id' => 100, 'title' => 'الفقه', 'main_cat' => 0, 'q_count' => 5]);
    H::insert('nuke_w2a_cat', ['id' => 101, 'title' => 'العبادات', 'main_cat' => 100, 'q_count' => 2]);
    H::insert('nuke_w2a_cat', ['id' => 200, 'title' => 'المعاملات', 'main_cat' => 0, 'q_count' => 1]);

    H::insert('nuke_fatwa_topics', ['id' => 10, 'topic_name' => 'الصلاة', 'parent_id' => 101, 'description' => 'وصف', 'meta_keywords' => 'k', 'meta_description' => 'd']);
    H::insert('nuke_fatwa_topics', ['id' => 11, 'topic_name' => 'الزكاة', 'parent_id' => 101]);
    H::insert('nuke_fatwa_topics', ['id' => 20, 'topic_name' => 'الصيام', 'parent_id' => 200]);

    H::insert('nuke_fatwa_general_questions', ['id' => 1, 'question_text' => 'حكم الصلاة', 'topic_id' => '|10|', 'description' => 'شرح']);
    H::insert('nuke_fatwa_general_questions', ['id' => 2, 'question_text' => 'حكم الزكاة', 'topic_id' => '|11|20|']);
    H::insert('nuke_fatwa_general_questions', ['id' => 3, 'question_text' => 'حكم الصيام', 'topic_id' => '|20|']);

    H::insert('nuke_islamic_authors', ['id' => 1, 'name' => 'الشيخ الأول', 'prename' => 'الشيخ', 'fatwa' => 50]);
    H::insert('nuke_islamic_authors', ['id' => 2, 'name' => 'الشيخ الثاني', 'prename' => 'الشيخ', 'fatwa' => 0]);
    H::insert('nuke_sat_channels', ['id' => 1, 'title' => 'قناة']);

    $f = fn (array $a) => H::insert('nuke_fatwa_questions', $a + ['answer_text' => 'الجواب', 'media_type' => 'audio', 'media_link' => 'https://m/x.mp3', 'channel_id' => 0]);
    $this->f1 = $f(['general_question_id' => '|1|', 'topic_id' => 10, 'question_text' => 'فتوى أولى', 'auther_id' => 1, 'media_size' => 2.25,
        'place_of_fatwa' => 'المسجد الكبير', 'date_of_fatwa' => '2020-01-02', 'db_insertion_date' => '2020-01-03', 'channel_id' => 1]);
    $this->f2 = $f(['general_question_id' => '|1|', 'topic_id' => 99, 'question_text' => 'فتوى قديمة', 'auther_id' => 1, 'media_size' => 1]);
    $this->f3 = $f(['general_question_id' => '|3|1|', 'topic_id' => 20, 'question_text' => 'فتوى مشتركة', 'auther_id' => 2]);
    $this->f4 = $f(['general_question_id' => '|2|', 'topic_id' => 11, 'question_text' => 'فتوى الزكاة', 'auther_id' => 2]);

    $this->boss = H::superAdmin();
});

function fwRow(string $table, int $id): object
{
    return DB::connection('main')->table($table)->where('id', $id)->first();
}

function fwFatwa(int $id): ?object
{
    return DB::connection('main')->table('nuke_fatwa_questions')->where('id', $id)->first();
}

/** @return list<string> */
function fwAudit(): array
{
    return DB::connection('main')->table('admin_audit_log')->orderBy('id')->pluck('action')->all();
}

/** @return array<string, mixed> */
function fwAuditRow(): array
{
    $row = DB::connection('main')->table('admin_audit_log')->orderByDesc('id')->first();

    return ['module' => $row->module, 'entity_type' => $row->entity_type, 'entity_id' => (int) $row->entity_id, 'metadata' => json_decode((string) $row->metadata, true)];
}

function fwAuthorCount(int $id): int
{
    return (int) DB::connection('main')->table('nuke_islamic_authors')->where('id', $id)->value('fatwa');
}

/** A valid Q1 / A4 form, OLD field names. @return array<string, mixed> */
function fwForm(array $overrides = []): array
{
    return $overrides + ['authers' => '2', 'fatwa_text' => 'نص فتوى جديدة', 'fatwa_answer_text' => 'نص الرد', 'audio_or_video' => 'video',
        'media_link' => 'https://m/new.mp4', 'media_size' => '3.75', 'channel' => '1', 'fatwa_date' => '2024-05-06'];
}

/** @return list<array{0: string, 1: string}> */
function fwRoutes(): array
{
    $r = [
        ['get', route('admin.fatwa.categories.topics.create', 101)], ['post', route('admin.fatwa.categories.topics.store', 101)],
        ['get', route('admin.fatwa.topics.questions.create', 10)], ['post', route('admin.fatwa.topics.questions.store', 10)],
        ['get', route('admin.fatwa.topics.edit', 10)], ['put', route('admin.fatwa.topics.update', 10)],
        ['get', route('admin.fatwa.questions.fatwas.create', 1)], ['post', route('admin.fatwa.questions.fatwas.store', 1)],
        ['get', route('admin.fatwa.questions.edit', 1)], ['put', route('admin.fatwa.questions.update', 1)],
        ['get', route('admin.fatwa.fatwas.edit', 1)], ['put', route('admin.fatwa.fatwas.update', 1)],
        ['get', route('admin.fatwa.fatwas.delete', 1)], ['delete', route('admin.fatwa.fatwas.destroy', 1)],
        ['get', route('admin.fatwa.media-size', ['url' => 'https://m/x.mp3'])],
    ];
    foreach (['add', 'move', 'remove'] as $mode) {
        $r[] = ['get', route('admin.fatwa.questions.topics.'.$mode, 1)];
        $r[] = ['put', route('admin.fatwa.questions.topics.'.$mode.'.save', 1)];
        $r[] = ['get', route('admin.fatwa.fatwas.questions.'.$mode, 1)];
        $r[] = ['put', route('admin.fatwa.fatwas.questions.'.$mode.'.save', 1)];
    }

    return $r;
}

const FW_PAGES = ['/fatawa-topics-101-1.htm', '/fatawa-category-101.htm', '/fatawa-group-10-101.htm', '/fatawa-all-1.htm', '/auther-all-fatawa-1-1.htm', '/fatawa-1.htm'];
const FW_DEAD = ['حذف التصنيف من قسم الفتاوى', 'حذف الموضوع', 'حذف السؤال', 'رابط لا يعمل', 'اضافة فتوى للموضوع', 'تعديل الفتوى', 'حذف الفتوى<'];

// ─── definitions & authorization ────────────────────────────────────────────

it('defines one fatwa.manage permission, grants it to nobody, and mints no per-operation Fatwa key', function () {
    expect(Permission::query()->where('guard_name', 'admin')->where('name', 'fatwa.manage')->count())->toBe(1)
        ->and(Permission::query()->where('name', 'like', 'fatwa.%')->pluck('name')->all())->toBe(['fatwa.manage'])
        ->and(DB::table('model_has_permissions')->count())->toBe(0)
        ->and(DB::table('role_has_permissions')->count())->toBe(0);

    foreach (fwRoutes() as [$method, $url]) {
        $name = Route::getRoutes()->match(request()->create($url, strtoupper($method)))->getName();
        expect($name)->toStartWith('admin.fatwa.');
    }
    foreach (Route::getRoutes()->getRoutesByName() as $name => $route) {
        if (str_starts_with($name, 'admin.fatwa.')) {
            expect($route->gatherMiddleware())->toContain('admin.permission:fatwa.manage');
        }
    }
});

it('refuses every page and mutation to a visitor and a member (401) and to an admin without fatwa.manage (403)', function () {
    foreach (fwRoutes() as [$method, $url]) {
        $this->{$method}($url)->assertUnauthorized();
    }

    $member = (new VbUser)->forceFill(['userid' => 55, 'username' => 'member']);
    foreach (fwRoutes() as [$method, $url]) {
        $this->actingAs($member, 'vbulletin')->{$method}($url)->assertUnauthorized();
    }
    H::visitor();

    $session = H::session(H::admin(['telawah.edittelawah', 'khotab.edititem', 'anasheed.editanasheed'], 'unrelated'));
    foreach (fwRoutes() as [$method, $url]) {
        $this->withSession($session)->{$method}($url, fwForm(['title' => 'عنوان جديد', 'topicname' => 'x', 'ids' => [20], 'id' => 20]))->assertForbidden();
    }

    expect(DB::connection('main')->table('nuke_fatwa_questions')->count())->toBe(4)
        ->and(DB::connection('main')->table('nuke_fatwa_topics')->count())->toBe(3)
        ->and(fwRow('nuke_fatwa_general_questions', 1)->topic_id)->toBe('|10|')
        ->and(fwAudit())->toBe([]);
});

it('lets fatwa.manage and the super-admin through, and binds only real records', function () {
    foreach ([H::session(H::admin(['fatwa.manage'], 'fatwa-admin')), H::session($this->boss)] as $session) {
        foreach (fwRoutes() as [$method, $url]) {
            if ($method === 'get' && ! str_contains($url, 'media-size')) {
                $this->withSession($session)->get($url)->assertOk();
            }
        }
    }

    $session = H::session($this->boss);
    $this->withSession($session)->get(route('admin.fatwa.categories.topics.create', 999))->assertNotFound();
    $this->withSession($session)->get(route('admin.fatwa.topics.edit', 999))->assertNotFound();
    $this->withSession($session)->put(route('admin.fatwa.questions.update', 999), ['title' => 'xyz'])->assertNotFound();
    $this->withSession($session)->delete(route('admin.fatwa.fatwas.destroy', 999))->assertNotFound();
});

// ─── visibility ─────────────────────────────────────────────────────────────

it('shows a visitor and a member no Fatwa control on any Fatwa page', function () {
    $member = (new VbUser)->forceFill(['userid' => 55, 'username' => 'member']);
    foreach (FW_PAGES as $url) {
        foreach ([fn () => $this->get($url), fn () => $this->actingAs($member, 'vbulletin')->get($url)] as $request) {
            H::visitor();
            $html = $request()->assertOk()->getContent();
            expect($html)->not->toContain('data-contextual-admin')->not->toContain('/admincp/fatwa')->not->toContain('لوحة تحكم المشرفين')
                ->not->toContain('اضافة الى اسئلة اخرى');
        }
    }
});

it('shows an authorized admin OLD\'s controls in OLD\'s order, and none of the dead ones', function () {
    $order = function (string $panel, array $labels) {
        $positions = array_map(fn ($label) => strpos($panel, '</i> '.$label.'</a>'), $labels);
        expect($positions)->not->toContain(false);
        $sorted = $positions;
        sort($sorted);
        expect($positions)->toBe($sorted);
    };

    foreach ([H::session($this->boss), H::session(H::admin(['fatwa.manage'], 'fatwa-admin'))] as $session) {
        $category = H::contextualMarkup($this->withSession($session)->get('/fatawa-topics-101-1.htm')->assertOk()->getContent());
        $order($category, ['اضافة موضوع للتصنيف']);
        expect($category)->toContain('return_to=%2Ffatawa-topics-101-1.htm');
        expect(H::contextualMarkup($this->withSession($session)->get('/fatawa-category-101.htm')->getContent()))->toContain('اضافة موضوع للتصنيف');

        $topic = H::contextualMarkup($this->withSession($session)->get('/fatawa-group-10-101.htm')->assertOk()->getContent());
        $order($topic, ['اضافة سؤال للموضوع', 'تعديل الموضوع']);

        // G1 is in one topic: «نقل لموضوع أخر», not «حذف من موضوع». F1 / F2 are under one question each («نقل الفتوى …»);
        // F3 (|3|1|) is listed here too — membership, blocker fix R2 — with «حذف الفتوى من على سؤال» instead.
        $question = $this->withSession($session)->get('/fatawa-all-1.htm')->assertOk()->getContent();
        $panel = H::contextualMarkup($question);
        $order($panel, ['اضافة فتوى للسؤال', 'تعديل السؤال', 'اضافة لموضوع أخر', 'نقل لموضوع أخر']);
        expect($panel)->not->toContain('</i> حذف من موضوع</a>');
        foreach ([$this->f1, $this->f2, $this->f3] as $fatwa) {
            expect(substr_count($question, 'href="'.route('admin.fatwa.fatwas.edit', $fatwa)))->toBe(1);
        }
        $order($panel, ['اضافة الى اسئلة اخرى', 'نقل الفتوى من سؤال لأخر', 'تعديل', 'حذف']);
        expect($question)->toContain('href="'.route('admin.fatwa.fatwas.questions.move', $this->f1).'?')
            ->toContain('href="'.route('admin.fatwa.fatwas.questions.remove', $this->f3).'?')
            ->not->toContain('href="'.route('admin.fatwa.fatwas.questions.move', $this->f3).'?')
            ->not->toContain('href="'.route('admin.fatwa.fatwas.questions.remove', $this->f1).'?');

        // G2 is in two topics: «حذف من موضوع».
        $multi = H::contextualMarkup($this->withSession($session)->get('/fatawa-all-2.htm')->assertOk()->getContent());
        expect($multi)->toContain('</i> حذف من موضوع</a>')->not->toContain('</i> نقل لموضوع أخر</a>');

        // The author's question page carries the same panel and icons.
        $author = H::contextualMarkup($this->withSession($session)->get('/auther-all-fatawa-1-1.htm')->assertOk()->getContent());
        $order($author, ['اضافة فتوى للسؤال', 'تعديل السؤال', 'اضافة لموضوع أخر', 'نقل لموضوع أخر']);
        expect($author)->toContain('return_to=%2Fauther-all-fatawa-1-1.htm');

        // The single-fatwa page keeps no admin control (D1–D7 / X1 are dead).
        expect($this->withSession($session)->get('/fatawa-1.htm')->assertOk()->getContent())->not->toContain('data-contextual-admin');

        foreach (['/fatawa-topics-101-1.htm', '/fatawa-group-10-101.htm', '/fatawa-all-1.htm', '/fatawa-all-2.htm'] as $url) {
            $markup = H::contextualMarkup($this->withSession($session)->get($url)->getContent());
            foreach (FW_DEAD as $dead) {
                expect($markup)->not->toContain($dead);
            }
        }
    }

    // A fatwa under several questions gets «حذف الفتوى من على سؤال» instead of the move.
    $labels = array_map(fn ($c) => $c->label, FatwaControls::fatwa($this->f3, '|3|1|'));
    expect($labels)->toBe(['اضافة الى اسئلة اخرى', 'حذف الفتوى من على سؤال', 'تعديل', 'حذف']);

    expect(Route::has('admin.fatwa.categories.destroy'))->toBeFalse()
        ->and(Route::has('admin.fatwa.topics.destroy'))->toBeFalse()
        ->and(Route::has('admin.fatwa.questions.destroy'))->toBeFalse();
});

it('hides every Fatwa control from an admin without fatwa.manage', function () {
    $session = H::session(H::admin(['telawah.edittelawah', 'khotab.edititem'], 'unrelated'));
    foreach (FW_PAGES as $url) {
        expect($this->withSession($session)->get($url)->assertOk()->getContent())->not->toContain('/admincp/fatwa');
    }
});

// ─── C1 «اضافة موضوع للتصنيف» ───────────────────────────────────────────────

it('C1 adds a topic under the category, counts it once up the tree, and returns to the category page', function () {
    $session = H::session(H::admin(['fatwa.manage'], 'adder'));
    $this->withSession($session)->get(route('admin.fatwa.categories.topics.create', ['category' => 101, 'return_to' => '/fatawa-topics-101-1.htm']))->assertOk()
        ->assertSee('إضافة موضوع جديد تحت تصنيف العبادات')->assertSee('name="title"', false)->assertSee('name="meta_index"', false)
        ->assertSee('value="/fatawa-topics-101-1.htm"', false);

    $this->withSession($session)->post(route('admin.fatwa.categories.topics.store', 101), [
        'title' => 'الطهارة', 'description' => 'وصف الطهارة', 'meta_keywords' => 'كلمات', 'meta_description' => 'وصف', 'meta_index' => '1',
        'return_to' => '/fatawa-topics-101-1.htm',
    ])->assertRedirect('/fatawa-topics-101-1.htm')->assertSessionHas('contextual_admin_success');

    $row = DB::connection('main')->table('nuke_fatwa_topics')->where('topic_name', 'الطهارة')->first();
    expect((int) $row->parent_id)->toBe(101)->and($row->description)->toBe('وصف الطهارة')->and($row->meta_keywords)->toBe('كلمات')
        ->and((int) $row->meta_index)->toBe(1)->and((int) $row->meta_follow)->toBe(0)
        ->and((int) fwRow('nuke_w2a_cat', 101)->q_count)->toBe(3)   // +1, not OLD's +2
        ->and((int) fwRow('nuke_w2a_cat', 100)->q_count)->toBe(6)   // ancestor +1
        ->and((int) fwRow('nuke_w2a_cat', 200)->q_count)->toBe(1)
        ->and(fwAudit())->toBe(['add_topic'])
        ->and(fwAuditRow())->toMatchArray(['module' => 'fatwa', 'entity_type' => 'topic', 'entity_id' => (int) $row->id])
        ->and(fwAuditRow()['metadata']['q_count_incremented'])->toBe([101, 100]);

    // Without a return_to: OLD's intended category page (not AdminCP).
    $this->withSession($session)->post(route('admin.fatwa.categories.topics.store', 200), ['title' => 'البيوع'])->assertRedirect('/fatawa-topics-200-1.htm');
    $this->get('/fatawa-topics-101-1.htm')->assertSee('الطهارة');
});

it('C1 refuses an empty, short or same-category duplicate name, and allows the name in another category', function () {
    $session = H::session($this->boss);
    $this->withSession($session)->post(route('admin.fatwa.categories.topics.store', 101), ['title' => ''])->assertSessionHasErrors('title');
    $this->withSession($session)->post(route('admin.fatwa.categories.topics.store', 101), ['title' => 'اب'])->assertSessionHasErrors('title');
    $this->withSession($session)->post(route('admin.fatwa.categories.topics.store', 101), ['title' => 'الصلاة'])
        ->assertSessionHasErrors(['title' => '(*)هذا الموضوع موجود من قبل برقم كودى هو : 10']);

    expect(DB::connection('main')->table('nuke_fatwa_topics')->count())->toBe(3)
        ->and((int) fwRow('nuke_w2a_cat', 101)->q_count)->toBe(2)
        ->and(fwAudit())->toBe([]);

    $this->withSession($session)->post(route('admin.fatwa.categories.topics.store', 200), ['title' => 'الصلاة'])->assertSessionHasNoErrors();
    expect(DB::connection('main')->table('nuke_fatwa_topics')->where('topic_name', 'الصلاة')->count())->toBe(2);
});

// ─── T1 «اضافة سؤال للموضوع» / T2 «تعديل الموضوع» ──────────────────────────

it('T1 adds a general question in OLD\'s pipe format and returns to the topic page', function () {
    $session = H::session(H::admin(['fatwa.manage'], 'adder'));
    $this->withSession($session)->get(route('admin.fatwa.topics.questions.create', 10))->assertOk()->assertSee('إضافة سؤال الى موضوع الصلاة');

    $this->withSession($session)->post(route('admin.fatwa.topics.questions.store', 10), ['title' => 'حكم صلاة الجمعة', 'description' => 'وصف', 'meta_follow' => '1'])
        ->assertRedirect('/fatawa-group-10-101.htm')->assertSessionHas('contextual_admin_success');

    $row = DB::connection('main')->table('nuke_fatwa_general_questions')->where('question_text', 'حكم صلاة الجمعة')->first();
    expect($row->topic_id)->toBe('|10|')->and($row->description)->toBe('وصف')
        ->and((int) $row->meta_index)->toBe(0)->and((int) $row->meta_follow)->toBe(1)
        ->and(fwAudit())->toBe(['add_general_question'])
        ->and(fwAuditRow())->toMatchArray(['module' => 'fatwa', 'entity_type' => 'general_question', 'entity_id' => (int) $row->id]);
});

it('T1 checks duplicates against the actual topic, not the category (OLD\'s bug)', function () {
    $session = H::session($this->boss);

    // Same text already in this topic (also when it is one of several topics) → refused.
    $this->withSession($session)->post(route('admin.fatwa.topics.questions.store', 10), ['title' => 'حكم الصلاة'])
        ->assertSessionHasErrors(['title' => '(*) هذا السؤال موجود من قبل برقم كودي هو : 1']);
    $this->withSession($session)->post(route('admin.fatwa.topics.questions.store', 20), ['title' => 'حكم الزكاة'])
        ->assertSessionHasErrors(['title' => '(*) هذا السؤال موجود من قبل برقم كودي هو : 2']);

    // OLD compared with `|{category}|`: a question filed as `|101|` would have blocked topic 10 — it no longer does.
    H::insert('nuke_fatwa_general_questions', ['id' => 50, 'question_text' => 'سؤال مكرر', 'topic_id' => '|101|']);
    $this->withSession($session)->post(route('admin.fatwa.topics.questions.store', 10), ['title' => 'سؤال مكرر'])->assertSessionHasNoErrors();
    // Same text in another topic only → allowed here.
    $this->withSession($session)->post(route('admin.fatwa.topics.questions.store', 11), ['title' => 'حكم الصلاة'])->assertSessionHasNoErrors();

    expect(DB::connection('main')->table('nuke_fatwa_general_questions')->where('question_text', 'حكم الصلاة')->pluck('topic_id')->all())->toBe(['|10|', '|11|'])
        ->and(fwAudit())->toBe(['add_general_question', 'add_general_question']);
});

it('T2 edits the topic\'s name, description and SEO, keeps its category, and returns to the topic page', function () {
    $session = H::session(H::admin(['fatwa.manage'], 'editor'));
    $this->withSession($session)->get(route('admin.fatwa.topics.edit', 10))->assertOk()
        ->assertSee('value="الصلاة"', false)->assertSee('وصف')->assertDontSee('name="parent_id"', false);

    $this->withSession($session)->put(route('admin.fatwa.topics.update', 10), [
        'topicname' => 'الصلوات', 'description' => 'جديد', 'meta_keywords' => 'kk', 'meta_description' => 'dd', 'meta_index' => '1', 'parent_id' => 200,
    ])->assertRedirect('/fatawa-group-10-101.htm')->assertSessionHas('contextual_admin_success');

    $row = fwRow('nuke_fatwa_topics', 10);
    expect($row->topic_name)->toBe('الصلوات')->and($row->description)->toBe('جديد')->and($row->meta_keywords)->toBe('kk')
        ->and((int) $row->meta_index)->toBe(1)->and((int) $row->meta_follow)->toBe(0)
        ->and((int) $row->parent_id)->toBe(101)
        ->and(fwAudit())->toBe(['edit_topic']);

    $this->withSession($session)->put(route('admin.fatwa.topics.update', 10), ['topicname' => ''])->assertSessionHasErrors('topicname');
});

// ─── Q1 «اضافة فتوى للسؤال» ────────────────────────────────────────────────

it('Q1 adds a fatwa: canonical first-topic topic_id, decimal size, the sheikh\'s exact count, today\'s insertion date', function () {
    $session = H::session(H::admin(['fatwa.manage'], 'adder'));
    $this->withSession($session)->get(route('admin.fatwa.questions.fatwas.create', 2))->assertOk()
        ->assertSee('اسم الشيخ')->assertSee('مكان إصدار الفتوى')->assertSee('بدون قناة')->assertSee('أختر اسم الشيخ')
        ->assertSee(route('admin.fatwa.media-size'), false)->assertDontSee('name="place_of_fatwa"', false);

    // G2 is in topics |11|20|: OLD's fatawa-all panel passed the LAST (20); the canonical value is the FIRST (11).
    $this->withSession($session)->post(route('admin.fatwa.questions.fatwas.store', 2), fwForm(['main_topic' => '20', 'return_to' => '/fatawa-all-2.htm']))
        ->assertRedirectContains('/fatawa-all-2.htm#')->assertSessionHas('contextual_admin_success');

    $row = DB::connection('main')->table('nuke_fatwa_questions')->where('question_text', 'نص فتوى جديدة')->first();
    expect($row->general_question_id)->toBe('|2|')->and((int) $row->topic_id)->toBe(11)
        ->and((int) $row->auther_id)->toBe(2)->and($row->answer_text)->toBe('نص الرد')->and($row->media_type)->toBe('video')
        ->and($row->media_link)->toBe('https://m/new.mp4')->and((float) $row->media_size)->toBe(3.75)
        ->and((int) $row->channel_id)->toBe(1)->and($row->date_of_fatwa)->toBe('2024-05-06')
        ->and((string) $row->db_insertion_date)->toBe(now()->toDateString())
        ->and($row->place_of_fatwa)->toBeNull()
        ->and(fwAuthorCount(2))->toBe(3)    // recounted: F3, F4 and the new one
        ->and(fwAuthorCount(1))->toBe(50)   // untouched
        ->and(fwAudit())->toBe(['add_fatwa'])
        ->and(fwAuditRow()['metadata']['author_fatwa_count'])->toBe(['2' => ['from' => 0, 'to' => 3]]);
});

it('Q1 stores no date as NULL, «بدون قناة» as 0, and redirects to the question page without a return_to', function () {
    $this->withSession(H::session($this->boss))->post(route('admin.fatwa.questions.fatwas.store', 1), fwForm(['fatwa_date' => '', 'channel' => '0', 'media_size' => '0']))
        ->assertRedirectContains('/fatawa-all-1.htm#');

    $row = DB::connection('main')->table('nuke_fatwa_questions')->where('question_text', 'نص فتوى جديدة')->first();
    expect($row->date_of_fatwa)->toBeNull()->and((int) $row->channel_id)->toBe(0)->and((int) $row->topic_id)->toBe(10);
});

it('Q1 refuses OLD\'s «أختر اسم الشيخ» (0), unknown sheikhs and channels, a bad date and a missing size', function () {
    $session = H::session($this->boss);
    $url = route('admin.fatwa.questions.fatwas.store', 1);
    $this->withSession($session)->post($url, fwForm(['authers' => '0']))->assertSessionHasErrors('authers');
    $this->withSession($session)->post($url, fwForm(['authers' => '999']))->assertSessionHasErrors('authers');
    $this->withSession($session)->post($url, fwForm(['channel' => '999']))->assertSessionHasErrors('channel');
    $this->withSession($session)->post($url, fwForm(['fatwa_date' => '2024-13-40']))->assertSessionHasErrors('fatwa_date');
    $this->withSession($session)->post($url, fwForm(['media_size' => '']))->assertSessionHasErrors('media_size');
    $this->withSession($session)->post($url, fwForm(['media_size' => 'abc']))->assertSessionHasErrors('media_size');
    $this->withSession($session)->post($url, fwForm(['fatwa_text' => '']))->assertSessionHasErrors('fatwa_text');
    $this->withSession($session)->post($url, fwForm(['audio_or_video' => 'pdf']))->assertSessionHasErrors('audio_or_video');

    expect(DB::connection('main')->table('nuke_fatwa_questions')->count())->toBe(4)->and(fwAudit())->toBe([]);
});

// ─── Q2 «تعديل السؤال» ──────────────────────────────────────────────────────

it('Q2 edits the question text, description and SEO, never its topics, and returns to the question page', function () {
    $session = H::session(H::admin(['fatwa.manage'], 'editor'));
    $this->withSession($session)->get(route('admin.fatwa.questions.edit', 2))->assertOk()->assertSee('value="حكم الزكاة"', false);

    $this->withSession($session)->put(route('admin.fatwa.questions.update', 2), ['title' => 'حكم زكاة المال', 'description' => 'شرح', 'meta_index' => '1', 'meta_follow' => '1', 'topic_id' => '|20|'])
        ->assertRedirect('/fatawa-all-2.htm')->assertSessionHas('contextual_admin_success');

    $row = fwRow('nuke_fatwa_general_questions', 2);
    expect($row->question_text)->toBe('حكم زكاة المال')->and($row->description)->toBe('شرح')->and($row->topic_id)->toBe('|11|20|')
        ->and(fwAudit())->toBe(['edit_general_question']);
});

// ─── Q4 / Q5 / Q6 — the question's topics ──────────────────────────────────

it('Q4 appends topics after the stored ones, without duplicates, and re-files the fatwas whose first question it is', function () {
    $session = H::session(H::admin(['fatwa.manage'], 'editor'));
    $page = $this->withSession($session)->get(route('admin.fatwa.questions.topics.add', 1))->assertOk()->getContent();
    expect($page)->toContain('اضافة السؤال لأكثر من موضوع')->toContain('الصيام');

    $this->withSession($session)->put(route('admin.fatwa.questions.topics.add.save', 1), ['ids' => [20, 10, 11], 'return_to' => '/fatawa-all-1.htm'])
        ->assertRedirect('/fatawa-all-1.htm')->assertSessionHas('contextual_admin_success');

    // Stored order kept (10 first), new ones appended in the chosen order — no alphabetical rebuild.
    expect(fwRow('nuke_fatwa_general_questions', 1)->topic_id)->toBe('|10|20|11|')
        ->and((int) fwFatwa($this->f1)->topic_id)->toBe(10)
        ->and((int) fwFatwa($this->f2)->topic_id)->toBe(10)   // drifted 99 → canonical
        ->and((int) fwFatwa($this->f3)->topic_id)->toBe(20)   // first question is G3, untouched
        ->and(fwAudit())->toBe(['add_question_topics'])
        ->and(fwAuditRow()['metadata'])->toMatchArray(['from' => '|10|', 'to' => '|10|20|11|', 'fatwas_refiled' => [$this->f2]]);

    // Nothing new, or an unknown topic → refused, nothing written.
    $this->withSession($session)->put(route('admin.fatwa.questions.topics.add.save', 1), ['ids' => [10, 20]])->assertSessionHasErrors('topics');
    $this->withSession($session)->put(route('admin.fatwa.questions.topics.add.save', 1), ['ids' => [999]])->assertSessionHasErrors('ids.0');
    $this->withSession($session)->put(route('admin.fatwa.questions.topics.add.save', 1), ['ids' => []])->assertSessionHasErrors('ids');
    expect(fwRow('nuke_fatwa_general_questions', 1)->topic_id)->toBe('|10|20|11|')->and(fwAudit())->toBe(['add_question_topics']);
});

it('Q5 moves a single-topic question and re-files its fatwas; it refuses a multi-topic question', function () {
    $session = H::session($this->boss);
    $this->withSession($session)->get(route('admin.fatwa.questions.topics.move', 1))->assertOk()->assertSee('نقل السؤال من موضوع لأخر')->assertDontSee('data-fatwa-blocked', false);

    $this->withSession($session)->put(route('admin.fatwa.questions.topics.move.save', 1), ['id' => 20])
        ->assertRedirect('/fatawa-all-1.htm')->assertSessionHas('contextual_admin_success');

    expect(fwRow('nuke_fatwa_general_questions', 1)->topic_id)->toBe('|20|')
        ->and((int) fwFatwa($this->f1)->topic_id)->toBe(20)->and((int) fwFatwa($this->f2)->topic_id)->toBe(20)
        ->and((int) fwFatwa($this->f3)->topic_id)->toBe(20)  // G3 first — unchanged by G1's move
        ->and(fwAudit())->toBe(['move_question_topic']);

    $this->withSession($session)->get(route('admin.fatwa.questions.topics.move', 2))->assertOk()->assertSee('لا يمكنك نقل هذا السؤال لأنه ينتمى لأكثر من موضوع');
    $this->withSession($session)->put(route('admin.fatwa.questions.topics.move.save', 2), ['id' => 10])->assertSessionHasErrors('topics');
    $this->withSession($session)->put(route('admin.fatwa.questions.topics.move.save', 3), ['id' => 999])->assertSessionHasErrors('id');
    $this->withSession($session)->put(route('admin.fatwa.questions.topics.move.save', 3), ['id' => 20])->assertSessionHasErrors('topics');

    expect(fwRow('nuke_fatwa_general_questions', 2)->topic_id)->toBe('|11|20|')->and(fwRow('nuke_fatwa_general_questions', 3)->topic_id)->toBe('|20|')
        ->and(fwAudit())->toBe(['move_question_topic']);
});

it('Q6 removes chosen topics from a multi-topic question, keeps order, never removes the last, and re-files', function () {
    H::insert('nuke_fatwa_general_questions', ['id' => 4, 'question_text' => 'سؤال متعدد', 'topic_id' => '|20|10|11|']);
    $f5 = H::insert('nuke_fatwa_questions', ['general_question_id' => '|4|', 'topic_id' => 20, 'question_text' => 'ف', 'auther_id' => 1]);
    $session = H::session($this->boss);

    $this->withSession($session)->get(route('admin.fatwa.questions.topics.remove', 4))->assertOk()->assertSee('اختر مواضيع لتحذف من السؤال');
    $this->withSession($session)->put(route('admin.fatwa.questions.topics.remove.save', 4), ['ids' => [20]])->assertRedirect('/fatawa-all-4.htm');

    expect(fwRow('nuke_fatwa_general_questions', 4)->topic_id)->toBe('|10|11|')->and((int) fwFatwa($f5)->topic_id)->toBe(10)
        ->and(fwAudit())->toBe(['remove_question_topics']);

    // Every remaining topic selected → refused (server-side, not only JavaScript).
    $this->withSession($session)->put(route('admin.fatwa.questions.topics.remove.save', 4), ['ids' => [10, 11]])->assertSessionHasErrors('topics');
    // A single-topic question → refused.
    $this->withSession($session)->get(route('admin.fatwa.questions.topics.remove', 1))->assertOk()->assertSee('لا يمكنك حذف الموضوع الوحيد المرتبط');
    $this->withSession($session)->put(route('admin.fatwa.questions.topics.remove.save', 1), ['ids' => [10]])->assertSessionHasErrors('topics');
    // A topic the question is not in → refused.
    $this->withSession($session)->put(route('admin.fatwa.questions.topics.remove.save', 2), ['ids' => [10]])->assertSessionHasErrors('topics');

    expect(fwRow('nuke_fatwa_general_questions', 4)->topic_id)->toBe('|10|11|')->and(fwRow('nuke_fatwa_general_questions', 1)->topic_id)->toBe('|10|')
        ->and(fwAudit())->toBe(['remove_question_topics']);

    // G2 |11|20| → remove 11: F4's topic follows the new first topic.
    $this->withSession($session)->put(route('admin.fatwa.questions.topics.remove.save', 2), ['ids' => [11]]);
    expect(fwRow('nuke_fatwa_general_questions', 2)->topic_id)->toBe('|20|')->and((int) fwFatwa($this->f4)->topic_id)->toBe(20);
});

// ─── A1 / A2 / A3 — the fatwa's questions ──────────────────────────────────

it('A1 appends questions after the stored ones, without duplicates, keeping the canonical topic_id', function () {
    $session = H::session(H::admin(['fatwa.manage'], 'editor'));
    $this->withSession($session)->get(route('admin.fatwa.fatwas.questions.add', $this->f1))->assertOk()->assertSee('اضافة الفتوى لأكثر من سؤال');

    $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.add.save', $this->f1), ['ids' => [3, 2, 1], 'return_to' => '/fatawa-all-1.htm'])
        ->assertRedirect('/fatawa-all-1.htm#'.$this->f1)->assertSessionHas('contextual_admin_success');

    expect(fwFatwa($this->f1)->general_question_id)->toBe('|1|3|2|')->and((int) fwFatwa($this->f1)->topic_id)->toBe(10)
        ->and(fwAudit())->toBe(['add_fatwa_questions']);

    // A drifted fatwa is re-filed by the first-question rule.
    $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.add.save', $this->f2), ['ids' => [3]]);
    expect(fwFatwa($this->f2)->general_question_id)->toBe('|1|3|')->and((int) fwFatwa($this->f2)->topic_id)->toBe(10);

    $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.add.save', $this->f1), ['ids' => [1, 3]])->assertSessionHasErrors('questions');
    $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.add.save', $this->f1), ['ids' => [999]])->assertSessionHasErrors('ids.0');
    expect(fwFatwa($this->f1)->general_question_id)->toBe('|1|3|2|');
});

it('A2 moves a single-question fatwa and re-files it; it refuses a multi-question fatwa', function () {
    $session = H::session($this->boss);
    $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.move.save', $this->f1), ['id' => 3, 'return_to' => '/fatawa-all-1.htm'])
        ->assertRedirect('/fatawa-all-1.htm#'.$this->f1);

    expect(fwFatwa($this->f1)->general_question_id)->toBe('|3|')->and((int) fwFatwa($this->f1)->topic_id)->toBe(20)
        ->and(fwAudit())->toBe(['move_fatwa_question'])
        ->and(fwAuditRow()['metadata']['topic_id'])->toBe(['from' => 10, 'to' => 20]);

    // Without a return_to: the fatwa's (new) first question page.
    $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.move.save', $this->f2), ['id' => 2])->assertRedirect('/fatawa-all-2.htm#'.$this->f2);
    expect((int) fwFatwa($this->f2)->topic_id)->toBe(11);

    $this->withSession($session)->get(route('admin.fatwa.fatwas.questions.move', $this->f3))->assertOk()->assertSee('لا يمكنك نقل هذه الفتوى لأنها تنتمى لأكثر من سؤال');
    $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.move.save', $this->f3), ['id' => 2])->assertSessionHasErrors('questions');
    $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.move.save', $this->f4), ['id' => 999])->assertSessionHasErrors('id');
    expect(fwFatwa($this->f3)->general_question_id)->toBe('|3|1|')->and(fwFatwa($this->f4)->general_question_id)->toBe('|2|');
});

it('A3 removes a question from a multi-question fatwa, never the last, and re-files from the new first question', function () {
    $session = H::session($this->boss);
    $this->withSession($session)->get(route('admin.fatwa.fatwas.questions.remove', $this->f3))->assertOk()->assertSee('اختر اسئلة لتحذف من الفتوى');

    $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.remove.save', $this->f3), ['ids' => [1, 3]])->assertSessionHasErrors('questions');
    expect(fwFatwa($this->f3)->general_question_id)->toBe('|3|1|');

    $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.remove.save', $this->f3), ['ids' => [3]])->assertRedirect('/fatawa-all-1.htm#'.$this->f3);
    expect(fwFatwa($this->f3)->general_question_id)->toBe('|1|')->and((int) fwFatwa($this->f3)->topic_id)->toBe(10)
        ->and(fwAudit())->toBe(['remove_fatwa_questions']);

    $this->withSession($session)->get(route('admin.fatwa.fatwas.questions.remove', $this->f1))->assertOk()->assertSee('لا يمكنك حذف السؤال الوحيد المرتبط');
    $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.remove.save', $this->f1), ['ids' => [1]])->assertSessionHasErrors('questions');
    expect(fwFatwa($this->f1)->general_question_id)->toBe('|1|');
});

// ─── A4 «تعديل» ─────────────────────────────────────────────────────────────

it('A4 edits the fatwa, keeps place_of_fatwa and the stored size, and never touches its questions', function () {
    $session = H::session(H::admin(['fatwa.manage'], 'editor'));
    $this->withSession($session)->get(route('admin.fatwa.fatwas.edit', $this->f1))->assertOk()
        ->assertSee('value="2.25"', false)->assertSee('value="2020-01-02"', false)->assertSee('فتوى أولى')
        ->assertSee('حفظ التعديلات للفتوى')->assertDontSee('name="place_of_fatwa"', false)->assertDontSee('name="general_question_id"', false);

    $this->withSession($session)->put(route('admin.fatwa.fatwas.update', $this->f1), fwForm([
        'authers' => '1', 'fatwa_text' => 'فتوى معدلة', 'media_size' => '2.25', 'fatwa_date' => '2020-01-02', 'general_question_id' => '|3|', 'return_to' => '/fatawa-all-1.htm',
    ]))->assertRedirect('/fatawa-all-1.htm#'.$this->f1)->assertSessionHas('contextual_admin_success');

    $row = fwFatwa($this->f1);
    expect($row->question_text)->toBe('فتوى معدلة')->and($row->media_type)->toBe('video')->and((float) $row->media_size)->toBe(2.25)
        ->and($row->place_of_fatwa)->toBe('المسجد الكبير')
        ->and($row->general_question_id)->toBe('|1|')->and((int) $row->topic_id)->toBe(10)
        ->and($row->date_of_fatwa)->toBe('2020-01-02')->and((string) $row->db_insertion_date)->toBe('2020-01-03')
        ->and(fwAuthorCount(1))->toBe(50)   // no sheikh change → not touched
        ->and(fwAudit())->toBe(['edit_fatwa']);

    // A new decimal size is stored as given; a cleared date is NULL, not ''.
    $this->withSession($session)->put(route('admin.fatwa.fatwas.update', $this->f1), fwForm(['authers' => '1', 'media_size' => '4.57', 'fatwa_date' => '']));
    expect((float) fwFatwa($this->f1)->media_size)->toBe(4.57)->and(fwFatwa($this->f1)->date_of_fatwa)->toBeNull()
        ->and(fwFatwa($this->f1)->place_of_fatwa)->toBe('المسجد الكبير');
});

it('A4 recomputes a drifted topic_id and recounts both sheikhs when the sheikh changes', function () {
    $this->withSession(H::session($this->boss))->put(route('admin.fatwa.fatwas.update', $this->f2), fwForm(['authers' => '2', 'media_size' => '1']))
        ->assertRedirect('/fatawa-all-1.htm#'.$this->f2);

    expect((int) fwFatwa($this->f2)->auther_id)->toBe(2)->and((int) fwFatwa($this->f2)->topic_id)->toBe(10)   // 99 → canonical
        ->and(fwAuthorCount(1))->toBe(1)    // was 50 (drifted); now its real count: F1
        ->and(fwAuthorCount(2))->toBe(3)    // F3, F4, F2
        ->and(fwAuditRow()['metadata']['author_fatwa_count'])->toBe(['1' => ['from' => 50, 'to' => 1], '2' => ['from' => 0, 'to' => 3]]);
});

it('A4 refuses an unknown sheikh or channel and leaves the row unchanged', function () {
    $session = H::session($this->boss);
    $this->withSession($session)->put(route('admin.fatwa.fatwas.update', $this->f1), fwForm(['authers' => '0']))->assertSessionHasErrors('authers');
    $this->withSession($session)->put(route('admin.fatwa.fatwas.update', $this->f1), fwForm(['channel' => '7']))->assertSessionHasErrors('channel');
    expect(fwFatwa($this->f1)->question_text)->toBe('فتوى أولى')->and(fwAudit())->toBe([]);
});

// ─── A5 «حذف» ───────────────────────────────────────────────────────────────

it('A5 confirms, deletes the fatwa itself, recounts its sheikh and returns to the question page', function () {
    $session = H::session(H::admin(['fatwa.manage'], 'deleter'));
    $this->withSession($session)->get(route('admin.fatwa.fatwas.delete', $this->f3))->assertOk()
        ->assertSee('هل ترغب في حذف هذا الفتوى')->assertSee('نعم متأكد')->assertSee('حكم الصيام')->assertSee('حكم الصلاة');
    expect(fwFatwa($this->f3))->not->toBeNull();

    $this->withSession($session)->delete(route('admin.fatwa.fatwas.destroy', $this->f3), ['return_to' => '/fatawa-all-1.htm'])
        ->assertRedirect('/fatawa-all-1.htm')->assertSessionHas('contextual_admin_success');

    expect(fwFatwa($this->f3))->toBeNull()
        ->and(fwAuthorCount(2))->toBe(1)    // F4 left
        ->and(DB::connection('main')->table('nuke_fatwa_questions')->count())->toBe(3)
        ->and(fwAudit())->toBe(['delete_fatwa'])
        ->and(fwAuditRow())->toMatchArray(['module' => 'fatwa', 'entity_type' => 'fatwa', 'entity_id' => $this->f3]);

    // Without a return_to: OLD's redire, the fatwa's first question page.
    $this->withSession($session)->delete(route('admin.fatwa.fatwas.destroy', $this->f4))->assertRedirect('/fatawa-all-2.htm');
    expect(fwAuthorCount(2))->toBe(0);
});

// ─── X2 size lookup ─────────────────────────────────────────────────────────

it('X2 fills the size in MB through LinkSizeChecker with a HEAD request, and reports failure as null', function () {
    Http::fake([
        'https://media.example/ok.mp3' => Http::response('', 200, ['Content-Length' => '13107200']),
        'https://media.example/missing.mp3' => Http::response('', 404),
        'https://media.example/nolength.mp3' => Http::response('', 200),
    ]);
    $session = H::session(H::admin(['fatwa.manage'], 'sizer'));

    $this->withSession($session)->getJson(route('admin.fatwa.media-size', ['url' => 'https://media.example/ok.mp3']))->assertOk()->assertExactJson(['size' => '12.50']);
    $this->withSession($session)->getJson(route('admin.fatwa.media-size', ['url' => 'https://media.example/missing.mp3']))->assertExactJson(['size' => null]);
    $this->withSession($session)->getJson(route('admin.fatwa.media-size', ['url' => 'https://media.example/nolength.mp3']))->assertExactJson(['size' => null]);
    $this->withSession($session)->getJson(route('admin.fatwa.media-size', ['url' => 'file:///etc/passwd']))->assertExactJson(['size' => null]);
    $this->withSession($session)->getJson(route('admin.fatwa.media-size'))->assertExactJson(['size' => null]);

    Http::assertSentCount(3);
    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'HEAD');
    Http::assertNotSent(fn (HttpRequest $request) => $request->method() === 'GET');
});

it('X2 is never required: a network failure still lets a typed size be saved', function () {
    Http::fake(fn () => throw new RuntimeException('network down'));
    $session = H::session($this->boss);

    $this->withSession($session)->getJson(route('admin.fatwa.media-size', ['url' => 'https://media.example/x.mp3']))->assertOk()->assertExactJson(['size' => null]);
    $this->withSession($session)->post(route('admin.fatwa.questions.fatwas.store', 1), fwForm(['media_size' => '7.3']))->assertSessionHasNoErrors();

    expect((float) DB::connection('main')->table('nuke_fatwa_questions')->where('question_text', 'نص فتوى جديدة')->value('media_size'))->toBe(7.3);
});

// ─── transactions, return_to safety ─────────────────────────────────────────

it('rolls a multi-write operation back entirely when a later write fails', function () {
    app()->instance(AdminAuditRecorder::class, new class(app(LegacyAuditLogCompatibilityWriter::class)) extends AdminAuditRecorder
    {
        public function record(AdminUser $actor, string $action, string $module, string $entityType, int $entityId, array $metadata = [], ?string $ip = null): never
        {
            throw new RuntimeException('audit store down');
        }
    });
    $this->withoutExceptionHandling();
    $session = H::session($this->boss);

    $attempts = [
        fn () => $this->withSession($session)->post(route('admin.fatwa.questions.fatwas.store', 1), fwForm()),
        fn () => $this->withSession($session)->put(route('admin.fatwa.fatwas.update', $this->f2), fwForm(['authers' => '2', 'media_size' => '1'])),
        fn () => $this->withSession($session)->delete(route('admin.fatwa.fatwas.destroy', $this->f3)),
        fn () => $this->withSession($session)->put(route('admin.fatwa.questions.topics.move.save', 1), ['id' => 20]),
        fn () => $this->withSession($session)->put(route('admin.fatwa.fatwas.questions.move.save', $this->f1), ['id' => 3]),
        fn () => $this->withSession($session)->post(route('admin.fatwa.categories.topics.store', 101), ['title' => 'جديد']),
    ];
    foreach ($attempts as $attempt) {
        try {
            $attempt();
            $this->fail('expected the failing audit write to abort the operation');
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toBe('audit store down');
        }
    }

    expect(DB::connection('main')->table('nuke_fatwa_questions')->count())->toBe(4)
        ->and((int) fwFatwa($this->f2)->auther_id)->toBe(1)->and((int) fwFatwa($this->f2)->topic_id)->toBe(99)
        ->and(fwFatwa($this->f3))->not->toBeNull()
        ->and(fwAuthorCount(1))->toBe(50)->and(fwAuthorCount(2))->toBe(0)
        ->and(fwRow('nuke_fatwa_general_questions', 1)->topic_id)->toBe('|10|')->and((int) fwFatwa($this->f1)->topic_id)->toBe(10)
        ->and(fwFatwa($this->f1)->general_question_id)->toBe('|1|')
        ->and(DB::connection('main')->table('nuke_fatwa_topics')->count())->toBe(3)
        ->and((int) fwRow('nuke_w2a_cat', 101)->q_count)->toBe(2)->and((int) fwRow('nuke_w2a_cat', 100)->q_count)->toBe(5);
});

it('never follows an unsafe return_to: it falls back to OLD\'s public page', function () {
    $session = H::session($this->boss);
    foreach (['https://evil.example/x', '//evil.example', '/admincp/permissions'] as $unsafe) {
        $this->withSession($session)->put(route('admin.fatwa.questions.update', 1), ['title' => 'حكم الصلاة', 'return_to' => $unsafe])
            ->assertRedirect('/fatawa-all-1.htm');
    }
});

// ─── regression ─────────────────────────────────────────────────────────────

it('keeps the visitor Fatwa pages rendering their content', function () {
    $this->get('/fatawa-topics-101-1.htm')->assertOk()->assertSee('الصلاة')->assertSee('الزكاة');
    $this->get('/fatawa-group-10-101.htm')->assertOk()->assertSee('حكم الصلاة');
    $this->get('/fatawa-all-1.htm')->assertOk()->assertSee('فتوى أولى')->assertSee('فتوى قديمة');
    $this->get('/auther-all-fatawa-1-1.htm')->assertOk()->assertSee('فتوى أولى');
    $this->get('/fatawa-'.$this->f1.'.htm')->assertOk()->assertSee('فتوى أولى');
});

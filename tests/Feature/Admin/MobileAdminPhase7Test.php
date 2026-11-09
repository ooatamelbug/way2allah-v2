<?php

use App\Domain\Admin\Actions\Mobile\MobileCategoryAdmin;
use App\Domain\Admin\Actions\Mobile\MobileMaterialAdmin;
use App\Domain\Admin\Actions\Mobile\MobileRequestModeration;
use App\Domain\Admin\Actions\Mobile\MobileVoteModeration;
use App\Domain\Content\Models\Mobile\MobileMaterial;
use App\Domain\Content\Models\Mobile\MobileVote;
use App\Support\Permission\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MobileHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 7 — Mobile Downloads staff administration and
 * moderation (OLD `modules/Mobile/admin`), plan §26.
 */
beforeEach(function () {
    H::setUp();
    $this->cid = H::category('القرآن');
});

function staffFields(int $cid, array $override = []): array
{
    return array_merge([
        'cid' => $cid, 'title' => 'مادة الإدارة', 'url' => 'https://archive.org/download/s/s.mp3',
        'description' => 'وصف', 'name' => '', 'email' => '', 'filesize' => '', 'version' => '', 'homepage' => '',
    ], $override);
}

// ---- authorization ----

it('forbids all 27 staff routes without mobile.managedownloads; other content permissions grant nothing', function () {
    $lid = H::material($this->cid);
    $vote = H::vote($lid, ['ratingcomments' => 'x']);
    $sub = (int) DB::connection('main')->table('nuke_downloads_newdownload')->insertGetId(['cid' => $this->cid, 'title' => 't', 'url' => 'https://a', 'description' => 'd'], 'lid');
    $req = (int) DB::connection('main')->table('nuke_downloads_modrequest')->insertGetId(['lid' => $lid, 'title' => 'n', 'brokendownload' => 0], 'requestid');
    $routes = collect(app('router')->getRoutes()->getRoutesByName())->filter(fn ($r, $n) => str_starts_with($n, 'admin.mobile.'));
    expect($routes)->toHaveCount(27);
    $before = H::snapshot();
    $this->actingAs(H::admin(['w2acd.managecds', 'polls.managepolls', 'khotab.addadv']), 'admin');

    foreach ($routes as $name => $route) {
        $params = collect($route->parameterNames())->mapWithKeys(fn ($p) => [$p => match ($p) {
            'category' => $this->cid, 'material', 'lid' => $lid, 'vote' => $vote, 'submission' => $sub, 'changeRequest' => $req,
        }])->all();
        $method = strtolower(collect($route->methods())->first(fn ($m) => $m !== 'HEAD'));
        $this->{$method}(route($name, $params))->assertForbidden();
    }

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('opens the section to mobile.managedownloads holders and to a super-admin', function (bool $super) {
    $this->actingAs($super ? H::superAdmin() : H::admin(['mobile.managedownloads']), 'admin');

    foreach (['admin.mobile.dashboard', 'admin.mobile.categories', 'admin.mobile.materials', 'admin.mobile.submissions', 'admin.mobile.broken', 'admin.mobile.corrections', 'admin.mobile.materials.create'] as $page) {
        $this->get(route($page))->assertOk();
    }
})->with([false, true]);

it('re-checks the permission inside the actions', function () {
    $outsider = H::admin(['khotab.addadv']);
    $lid = H::material($this->cid);
    $vote = H::vote($lid);
    $before = H::snapshot();

    expect(fn () => app(MobileCategoryAdmin::class)->create($outsider, 'x', '', 0))->toThrow(AuthorizationException::class);
    expect(fn () => app(MobileMaterialAdmin::class)->delete($outsider, MobileMaterial::query()->findOrFail($lid)))->toThrow(AuthorizationException::class);
    expect(fn () => app(MobileRequestModeration::class)->ignoreBrokenReports($outsider, $lid))->toThrow(AuthorizationException::class);
    expect(fn () => app(MobileVoteModeration::class)->deleteVote($outsider, MobileVote::query()->findOrFail($vote)))->toThrow(AuthorizationException::class);

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('defines exactly one mobile permission, granted to no one', function () {
    expect(Permission::where('name', 'like', 'mobile.%')->pluck('name')->all())->toBe(['mobile.managedownloads'])
        ->and(DB::table('model_has_permissions')->count())->toBe(0);
});

describe('as staff', function () {
    beforeEach(function () {
        $this->actingAs(H::admin(['mobile.managedownloads'], 'mobile-staff'), 'admin');
    });

    // ---- categories ----

    it('adds top-level and sub categories, refusing duplicate titles (global / among siblings)', function () {
        $this->post(route('admin.mobile.categories.store'), ['title' => 'أناشيد', 'parentid' => 0])->assertSessionHas('success');
        $this->post(route('admin.mobile.categories.store'), ['title' => 'أناشيد', 'parentid' => 0])->assertSessionHas('error');
        $this->post(route('admin.mobile.categories.store'), ['title' => 'فرعي', 'parentid' => $this->cid])->assertSessionHas('success');
        $this->post(route('admin.mobile.categories.store'), ['title' => 'فرعي', 'parentid' => $this->cid])->assertSessionHas('error');

        $sub = DB::connection('main')->table('nuke_downloads_categories')->where('title', 'فرعي')->first();
        expect((int) $sub->parentid)->toBe($this->cid)->and(DB::connection('main')->table('nuke_downloads_categories')->count())->toBe(3);
    });

    it('allows the same subcategory title under a different parent — the check is among siblings only', function () {
        $other = H::category('أدعية');
        H::category('مختارات', $this->cid);

        $this->post(route('admin.mobile.categories.store'), ['title' => 'مختارات', 'parentid' => $other])->assertSessionHas('success');

        expect(DB::connection('main')->table('nuke_downloads_categories')->where('title', 'مختارات')->count())->toBe(2);
    });

    it('edits title and description only; an unchanged edit writes nothing', function () {
        $this->put(route('admin.mobile.categories.update', $this->cid), ['title' => 'القرآن الكريم', 'cdescription' => 'وصف'])->assertSessionHas('success');
        $writes = H::writesDuring(fn () => $this->put(route('admin.mobile.categories.update', $this->cid), ['title' => 'القرآن الكريم', 'cdescription' => 'وصف'])->assertSessionHas('error'));

        expect(H::row('nuke_downloads_categories', 'cid', $this->cid)->title)->toBe('القرآن الكريم')->and($writes)->toBe([]);
    });

    it('deletes a top-level category with its subcategories and all their materials; a subcategory with its own only (OLD DelCat)', function () {
        $sub = H::category('فرعي', $this->cid);
        $other = H::category('آخر');
        $m1 = H::material($this->cid);
        $m2 = H::material($sub);
        $keep = H::material($other);

        $this->delete(route('admin.mobile.categories.destroy', $this->cid))->assertSessionHas('success');

        expect(DB::connection('main')->table('nuke_downloads_categories')->pluck('cid')->map(fn ($c) => (int) $c)->all())->toBe([$other])
            ->and(DB::connection('main')->table('nuke_downloads_downloads')->pluck('lid')->map(fn ($l) => (int) $l)->all())->toBe([$keep]);
        $meta = json_decode(H::auditRows()[0]->metadata, true);
        expect($meta['deleted_categories'])->toBe([$this->cid, $sub])->and($meta['deleted_materials'])->toBe([$m1, $m2]);
    });

    it('transfers every material of one category to another', function () {
        $to = H::category('هدف');
        $a = H::material($this->cid);
        $b = H::material($this->cid);

        $this->post(route('admin.mobile.categories.transfer'), ['from' => $this->cid, 'to' => $to])->assertSessionHas('success');

        expect(DB::connection('main')->table('nuke_downloads_downloads')->where('cid', $to)->count())->toBe(2)
            ->and(json_decode(H::auditRows()[0]->metadata, true))->toBe(['to_cid' => $to, 'materials' => [$a, $b]]);
        $this->post(route('admin.mobile.categories.transfer'), ['from' => $this->cid, 'to' => $to])->assertSessionHas('error');
    });

    // ---- materials ----

    it('adds a material dated now with zero counters; refuses a URL already used', function () {
        $this->post(route('admin.mobile.materials.store'), staffFields($this->cid))->assertSessionHas('success');
        $this->post(route('admin.mobile.materials.store'), staffFields($this->cid, ['title' => 'مكرر']))->assertSessionHas('error');

        $m = DB::connection('main')->table('nuke_downloads_downloads')->first();
        expect([$m->title, (int) $m->hits, (int) $m->totalvotes, $m->submitter])->toBe(['مادة الإدارة', 0, 0, ''])
            ->and(strtotime((string) $m->date))->toBeGreaterThan(time() - 60)
            ->and(DB::connection('main')->table('nuke_downloads_downloads')->count())->toBe(1);
    });

    it('edits every field including the hit count; unchanged edits write nothing', function () {
        $lid = H::material($this->cid, ['hits' => 3]);

        $this->put(route('admin.mobile.materials.update', $lid), staffFields($this->cid, ['title' => 'جديد', 'hits' => 40]))->assertSessionHas('success');
        $m = H::row('nuke_downloads_downloads', 'lid', $lid);
        expect([$m->title, (int) $m->hits])->toBe(['جديد', 40]);

        $writes = H::writesDuring(fn () => $this->put(route('admin.mobile.materials.update', $lid), staffFields($this->cid, ['title' => 'جديد', 'hits' => 40])));
        expect($writes)->toBe([]);
    });

    it('deletes a material row only — its votes stay, as in OLD', function () {
        $lid = H::material($this->cid);
        H::vote($lid);

        $this->delete(route('admin.mobile.materials.destroy', $lid))->assertSessionHas('success');

        expect(H::row('nuke_downloads_downloads', 'lid', $lid))->toBeNull()->and(DB::connection('main')->table('nuke_downloads_votedata')->count())->toBe(1);
    });

    it('keeps historical stored URLs untouched when other fields are edited', function () {
        $url = 'http://way2allah.knoz4arab.com/media/mobile/do3a/Do3a2.wav';
        $lid = H::material($this->cid, ['url' => $url]);

        $this->put(route('admin.mobile.materials.update', $lid), staffFields($this->cid, ['url' => $url, 'title' => 'معدل']));

        expect(H::row('nuke_downloads_downloads', 'lid', $lid)->url)->toBe($url);
    });

    // ---- editorials ----

    it('adds, edits and deletes the staff review, recording the admin', function () {
        $lid = H::material($this->cid);

        $this->put(route('admin.mobile.materials.editorial', $lid), ['editorialtitle' => 'رأي', 'editorialtext' => 'نص'])->assertSessionHas('success');
        expect(H::row('nuke_downloads_editorials', 'downloadid', $lid)->adminid)->toBe('mobile-staff');
        $this->put(route('admin.mobile.materials.editorial', $lid), ['editorialtitle' => 'رأي', 'editorialtext' => 'نص'])->assertSessionHas('error');
        $this->put(route('admin.mobile.materials.editorial', $lid), ['editorialtitle' => 'رأي 2', 'editorialtext' => 'نص'])->assertSessionHas('success');
        $this->delete(route('admin.mobile.materials.editorial.destroy', $lid))->assertSessionHas('success');

        expect(H::row('nuke_downloads_editorials', 'downloadid', $lid))->toBeNull()
            ->and(collect(H::auditRows())->pluck('action')->all())->toBe(['add_mobile_editorial', 'edit_mobile_editorial', 'del_mobile_editorial']);
    });

    // ---- submissions ----

    it('approves a submission: published as edited, submitter kept, submission removed', function () {
        $sub = (int) DB::connection('main')->table('nuke_downloads_newdownload')->insertGetId([
            'cid' => $this->cid, 'title' => 'مقترح', 'url' => 'https://archive.org/download/m/m.sis', 'description' => 'و', 'submitter' => 'عضو',
        ], 'lid');

        $this->post(route('admin.mobile.submissions.approve', $sub), staffFields($this->cid, ['title' => 'مقترح معدل', 'url' => 'https://archive.org/download/m/m.sis']))->assertSessionHas('success');

        $m = DB::connection('main')->table('nuke_downloads_downloads')->first();
        expect([$m->title, $m->submitter])->toBe(['مقترح معدل', 'عضو'])
            ->and(DB::connection('main')->table('nuke_downloads_newdownload')->count())->toBe(0);
    });

    it('refuses to approve a submission whose URL is already published, leaving it pending', function () {
        H::material($this->cid, ['url' => 'https://archive.org/download/dup.sis']);
        $sub = (int) DB::connection('main')->table('nuke_downloads_newdownload')->insertGetId(['cid' => $this->cid, 'title' => 'م', 'url' => 'https://archive.org/download/dup.sis', 'description' => 'و'], 'lid');

        $this->post(route('admin.mobile.submissions.approve', $sub), staffFields($this->cid, ['url' => 'https://archive.org/download/dup.sis']))->assertSessionHas('error');

        expect(DB::connection('main')->table('nuke_downloads_newdownload')->count())->toBe(1)->and(H::auditRows())->toBe([]);
    });

    it('rejects a submission', function () {
        $sub = (int) DB::connection('main')->table('nuke_downloads_newdownload')->insertGetId(['cid' => $this->cid, 'title' => 'م', 'url' => 'https://a', 'description' => 'و'], 'lid');

        $this->delete(route('admin.mobile.submissions.reject', $sub))->assertSessionHas('success');

        expect(DB::connection('main')->table('nuke_downloads_newdownload')->count())->toBe(0)->and(H::auditRows()[0]->action)->toBe('reject_mobile_submission');
    });

    // ---- broken-link reports ----

    it('deletes a reported material with every request on it (OLD DelBrokenDownloads)', function () {
        $lid = H::material($this->cid);
        $keep = H::material($this->cid);
        foreach ([1, 1, 0] as $kind) {
            DB::connection('main')->table('nuke_downloads_modrequest')->insert(['lid' => $lid, 'brokendownload' => $kind]);
        }
        DB::connection('main')->table('nuke_downloads_modrequest')->insert(['lid' => $keep, 'brokendownload' => 1]);

        $this->delete(route('admin.mobile.broken.destroy-material', $lid))->assertSessionHas('success');

        expect(H::row('nuke_downloads_downloads', 'lid', $lid))->toBeNull()
            ->and(DB::connection('main')->table('nuke_downloads_modrequest')->pluck('lid')->map(fn ($l) => (int) $l)->all())->toBe([$keep]);
    });

    it('ignores the broken-link reports of a material, keeping its correction requests', function () {
        $lid = H::material($this->cid);
        DB::connection('main')->table('nuke_downloads_modrequest')->insert([['lid' => $lid, 'brokendownload' => 1], ['lid' => $lid, 'brokendownload' => 1], ['lid' => $lid, 'brokendownload' => 0]]);

        $this->delete(route('admin.mobile.broken.ignore', $lid))->assertSessionHas('success');
        $this->delete(route('admin.mobile.broken.ignore', $lid))->assertSessionHas('error');

        expect(DB::connection('main')->table('nuke_downloads_modrequest')->pluck('brokendownload')->map(fn ($b) => (int) $b)->all())->toBe([0])
            ->and(H::auditRows())->toHaveCount(1);
    });

    // ---- correction requests ----

    it('accepts a correction: the proposed values replace the material, and the request is removed', function () {
        $to = H::category('هدف');
        $lid = H::material($this->cid, ['title' => 'قديم', 'hits' => 9]);
        $req = (int) DB::connection('main')->table('nuke_downloads_modrequest')->insertGetId([
            'lid' => $lid, 'cid' => $to, 'title' => 'مصحح', 'url' => 'https://archive.org/download/new.sis', 'description' => 'جديد', 'modifysubmitter' => 'عضو', 'brokendownload' => 0,
        ], 'requestid');

        $this->post(route('admin.mobile.corrections.accept', $req))->assertSessionHas('success');

        $m = H::row('nuke_downloads_downloads', 'lid', $lid);
        expect([$m->title, (int) $m->cid, $m->url, (int) $m->hits])->toBe(['مصحح', $to, 'https://archive.org/download/new.sis', 9])
            ->and(DB::connection('main')->table('nuke_downloads_modrequest')->count())->toBe(0);
    });

    it('ignores a correction request; a broken-link report is not a correction', function () {
        $lid = H::material($this->cid);
        $req = (int) DB::connection('main')->table('nuke_downloads_modrequest')->insertGetId(['lid' => $lid, 'brokendownload' => 0, 'title' => 'x'], 'requestid');
        $broken = (int) DB::connection('main')->table('nuke_downloads_modrequest')->insertGetId(['lid' => $lid, 'brokendownload' => 1], 'requestid');

        $this->delete(route('admin.mobile.corrections.ignore', $req))->assertSessionHas('success');
        $this->post(route('admin.mobile.corrections.accept', $broken))->assertNotFound();

        expect(DB::connection('main')->table('nuke_downloads_modrequest')->pluck('requestid')->map(fn ($r) => (int) $r)->all())->toBe([$broken]);
    });

    // ---- ratings / comments ----

    it('deletes a vote and recomputes the material from its remaining votes', function () {
        $lid = H::material($this->cid, ['downloadratingsummary' => 6, 'totalvotes' => 2]);
        $low = H::vote($lid, ['ratinguser' => 'أ', 'rating' => 2]);
        H::vote($lid, ['ratinguser' => 'ب', 'rating' => 10, 'ratingcomments' => 'رائع']);

        $this->delete(route('admin.mobile.votes.destroy', $low))->assertSessionHas('success');

        $m = H::row('nuke_downloads_downloads', 'lid', $lid);
        expect([(int) $m->downloadratingsummary, (int) $m->totalvotes, (int) $m->totalcomments])->toBe([10, 1, 1]);
    });

    it('deletes a comment: the vote stays, totalcomments −1 floored at 0; repeating is a no-op', function () {
        $lid = H::material($this->cid, ['totalcomments' => 0]);
        $vote = H::vote($lid, ['ratingcomments' => 'تعليق']);

        $this->delete(route('admin.mobile.votes.comment.destroy', $vote))->assertSessionHas('success');
        $writes = H::writesDuring(fn () => $this->delete(route('admin.mobile.votes.comment.destroy', $vote)));

        expect(H::row('nuke_downloads_votedata', 'ratingdbid', $vote)->ratingcomments)->toBe('')
            ->and((int) H::row('nuke_downloads_downloads', 'lid', $lid)->totalcomments)->toBe(0)
            ->and($writes)->toBe([])->and(H::auditRows())->toHaveCount(1);
    });

    it('recalculates only materials that have vote rows, auditing only changes (OLD CleanVotes)', function () {
        $voted = H::material($this->cid, ['downloadratingsummary' => 1, 'totalvotes' => 99]);
        $historical = H::material($this->cid, ['downloadratingsummary' => 9, 'totalvotes' => 37]);
        H::vote($voted, ['ratinguser' => 'أ', 'rating' => 6]);

        $this->post(route('admin.mobile.recalculate'))->assertSessionHas('success');
        $n = count(H::auditRows());
        $this->post(route('admin.mobile.recalculate'));

        expect([(int) H::row('nuke_downloads_downloads', 'lid', $voted)->downloadratingsummary, (int) H::row('nuke_downloads_downloads', 'lid', $voted)->totalvotes])->toBe([6, 1])
            ->and((int) H::row('nuke_downloads_downloads', 'lid', $historical)->totalvotes)->toBe(37)
            ->and($n)->toBe(1)->and(H::auditRows())->toHaveCount(1);
    });

    // ---- audit ----

    it('audits with structured metadata — no description text, no markup', function () {
        $this->post(route('admin.mobile.materials.store'), staffFields($this->cid, ['title' => '<b>عنوان</b>', 'description' => 'نص طويل سري']));

        $row = H::auditRows()[0];
        $meta = json_decode($row->metadata, true);
        expect([$row->action, $row->module, $row->entity_type, $row->actor_aid])->toBe(['add_mobile', 'mobile', 'mobile_material', 'mobile-staff'])
            ->and($meta['title'])->toBe(['redacted' => 'markup', 'length' => mb_strlen('<b>عنوان</b>')])
            ->and($meta['description'])->toBe(['sha256' => hash('sha256', 'نص طويل سري'), 'length' => mb_strlen('نص طويل سري')])
            ->and($row->metadata)->not->toContain('نص طويل سري');
    });

    it('writes nothing to nuke_islamic_logs while compatibility is default-OFF', function () {
        $this->post(route('admin.mobile.categories.store'), ['title' => 'س', 'parentid' => 0]);
        $this->post(route('admin.mobile.materials.store'), staffFields($this->cid));

        expect(H::auditRows())->toHaveCount(2)->and(DB::connection('main')->table('nuke_islamic_logs')->count())->toBe(0);
    });

    it('rolls staff mutations back when the audit write fails', function (string $op) {
        $lid = H::material($this->cid);
        $vote = H::vote($lid, ['ratingcomments' => 'c']);
        DB::connection('main')->table('nuke_downloads_modrequest')->insert(['lid' => $lid, 'brokendownload' => 1]);
        DB::connection('main')->unprepared("CREATE TRIGGER fail_audit BEFORE INSERT ON admin_audit_log BEGIN SELECT RAISE(ABORT, 'audit-down'); END");
        $before = H::snapshot();
        $this->withoutExceptionHandling();

        $call = match ($op) {
            'category' => fn () => $this->delete(route('admin.mobile.categories.destroy', $this->cid)),
            'material' => fn () => $this->post(route('admin.mobile.materials.store'), staffFields($this->cid, ['url' => 'https://archive.org/x'])),
            'broken' => fn () => $this->delete(route('admin.mobile.broken.destroy-material', $lid)),
            'vote' => fn () => $this->delete(route('admin.mobile.votes.destroy', $vote)),
            'comment' => fn () => $this->delete(route('admin.mobile.votes.comment.destroy', $vote)),
        };
        expect($call)->toThrow(Exception::class, 'audit-down');

        expect(H::snapshot())->toBe($before);
    })->with(['category', 'material', 'broken', 'vote', 'comment']);
});

<?php

use App\Domain\Admin\Actions\Polls\CreatePollAction;
use App\Domain\Admin\Actions\Polls\DeletePollAction;
use App\Domain\Admin\Actions\Polls\DeletePollCommentAction;
use App\Domain\Admin\Actions\Polls\UpdatePollAction;
use App\Domain\Engagement\Models\Poll;
use App\Domain\Engagement\Models\PollComment;
use App\Support\Permission\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PollAdminHarness as H;

uses(RefreshDatabase::class);

/**
 * OLD Admin Migration Phase 4 — authorization. `polls.managepolls` gates
 * create / edit / remove (OLD's single module-level gate); comment removal
 * is super-admin only (OLD radminsuper). `survey.modsurvey` — the custom
 * survey engine's key — grants nothing here.
 */
beforeEach(function () {
    H::setUp();
    $this->poll = H::poll('Q', ['أ'], [1]);
    $this->comment = H::comment($this->poll);
});

/** @return list<array{0: string, 1: string, 2: string}> */
function pollManageRoutes(): array
{
    return [
        ['get', 'admin.polls.index', ''],
        ['get', 'admin.polls.create', ''],
        ['post', 'admin.polls.store', ''],
        ['get', 'admin.polls.edit', 'poll'],
        ['put', 'admin.polls.update', 'poll'],
        ['delete', 'admin.polls.destroy', 'poll'],
    ];
}

/** @return list<array{0: string, 1: string, 2: string}> */
function pollCommentRoutes(): array
{
    return [
        ['get', 'admin.polls.comments', 'poll'],
        ['delete', 'admin.polls.comments.destroy', 'comment'],
    ];
}

function hitPollRoute(array $route)
{
    [$method, $name, $param] = $route;
    $url = route($name, $param === '' ? [] : [test()->{$param}]);

    return test()->{$method}($url, ['pollTitle' => 'X']);
}

it('covers all eight registered poll routes', function () {
    $registered = collect(app('router')->getRoutes()->getRoutesByName())->keys()
        ->filter(fn ($n) => str_starts_with($n, 'admin.polls.'))->sort()->values()->all();

    expect(collect([...pollManageRoutes(), ...pollCommentRoutes()])->pluck(1)->sort()->values()->all())->toBe($registered)
        ->and($registered)->toHaveCount(8);
});

it('forbids every poll route to an admin without polls.managepolls — survey.modsurvey included', function () {
    $before = H::snapshot();
    $this->actingAs(H::admin(['survey.modsurvey', 'khotab.addcat']), 'admin');

    foreach ([...pollManageRoutes(), ...pollCommentRoutes()] as $route) {
        hitPollRoute($route)->assertForbidden();
    }

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('polls.managepolls opens poll management but not comment removal', function () {
    $this->actingAs(H::admin(['polls.managepolls']), 'admin');

    // Comment routes first: the management loop ends by deleting the poll.
    foreach (pollCommentRoutes() as $route) {
        hitPollRoute($route)->assertForbidden();
    }
    expect(DB::connection('main')->table('nuke_pollcomments')->where('tid', $this->comment)->exists())->toBeTrue();
    foreach (pollManageRoutes() as $route) {
        expect(hitPollRoute($route)->status())->not->toBe(403);
    }
});

it('lets a super-admin do everything without an explicit grant', function () {
    $this->actingAs(H::superAdmin(), 'admin');

    $this->delete(route('admin.polls.comments.destroy', $this->comment))->assertSessionHas('success');
    $this->put(route('admin.polls.update', $this->poll), ['pollTitle' => 'جديد'])->assertSessionHas('success');
    $this->delete(route('admin.polls.destroy', $this->poll))->assertSessionHas('success');
});

it('re-checks inside every action, independent of the route', function () {
    $outsider = H::admin(['survey.modsurvey']);
    $manager = H::admin(['polls.managepolls'], 'poll-manager');
    $poll = Poll::query()->findOrFail($this->poll);
    $before = H::snapshot();

    expect(fn () => app(CreatePollAction::class)->execute($outsider, 'Q', []))->toThrow(AuthorizationException::class);
    expect(fn () => app(UpdatePollAction::class)->execute($outsider, $poll, 'R', []))->toThrow(AuthorizationException::class);
    expect(fn () => app(DeletePollAction::class)->execute($outsider, $poll))->toThrow(AuthorizationException::class);
    expect(fn () => app(DeletePollCommentAction::class)->execute($manager, PollComment::query()->findOrFail($this->comment)))->toThrow(AuthorizationException::class);

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
});

it('shows the sidebar entry to polls.managepolls holders only, separately from custom surveys', function () {
    $this->actingAs(H::admin(['polls.managepolls']), 'admin');
    $this->get(route('admin.polls.index'))->assertOk()
        ->assertSee(route('admin.polls.index'), false)
        ->assertDontSee(route('admin.survey.index'), false)
        ->assertDontSee(route('admin.polls.comments', $this->poll), false);
});

it('mints exactly one poll permission, labelled, granted to no one', function () {
    expect(Permission::where('name', 'like', 'polls.%')->pluck('name')->all())->toBe(['polls.managepolls'])
        ->and(DB::table('model_has_permissions')->count())->toBe(0);
});

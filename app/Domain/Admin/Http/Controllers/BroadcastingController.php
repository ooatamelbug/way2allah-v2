<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Content\Models\Channel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Replaces `admincp/broadcasting/index.php` + `edit_stream.php` —
 * Roadmap task 5.10. Reuses `Channel` directly (Wave 1/3/4's shared
 * model) — no new model needed, `streamcode` is already a documented
 * column there.
 *
 * `index()` was added in the Admin Broadcasting Final Closure task
 * (2026-08-22): legacy `index.php`'s `op=editstream` branch is real,
 * functional source-proven behavior (`WHERE streamcode != '' ORDER BY
 * title ASC`, linking each channel to `edit_stream.php`), so it is
 * reconstructed here — deliberately NOT using `Channel::
 * scopeEligibleForLiveStream()`, which also filters `active = 0`, a
 * condition legacy's own admin list never applied.
 *
 * `broadcasting/delete_stream.php` (top-of-file `die()`, confirmed dead —
 * `admincp.md` §5 Pattern C), `broadcasting/edit_author.php`'s
 * permission-save (commented-out query, confirmed dead — Pattern A), and
 * `index.php`'s `op=addstream` branch (gated by a permanently-false
 * `1!=1` condition AND its own `menu.php` link left commented out —
 * doubly unreachable) are not ported; the permission-editing capability
 * `edit_author.php` duplicated is `PermissionController` (task 5.3), not
 * a per-directory copy.
 */
class BroadcastingController
{
    public function index(): View
    {
        $channels = Channel::where('streamcode', '<>', '')
            ->whereNotNull('streamcode')
            ->orderBy('title')
            ->get();

        return view('admin.broadcasting.index', compact('channels'));
    }

    public function edit(Channel $channel): View
    {
        return view('admin.broadcasting.edit', compact('channel'));
    }

    public function update(Request $request, Channel $channel): RedirectResponse
    {
        $channel->update(['streamcode' => $request->string('streamcode')]);

        return redirect()->route('admin.broadcasting.edit', $channel)->with('success', 'تم حفظ كود البث بنجاح');
    }
}

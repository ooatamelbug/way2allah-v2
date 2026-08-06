<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Content\Models\Channel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Replaces `admincp/broadcasting/edit_stream.php` — Roadmap task 5.10.
 * Reuses `Channel` directly (Wave 1/3/4's shared model) — no new model
 * needed, `streamcode` is already a documented column there.
 *
 * `broadcasting/delete_stream.php` (top-of-file `die()`, confirmed dead —
 * `admincp.md` §5 Pattern C) and `broadcasting/edit_author.php`'s
 * permission-save (commented-out query, confirmed dead — Pattern A) are
 * not ported; the permission-editing capability they duplicated is
 * `PermissionController` (task 5.3), not a per-directory copy.
 */
class BroadcastingController
{
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

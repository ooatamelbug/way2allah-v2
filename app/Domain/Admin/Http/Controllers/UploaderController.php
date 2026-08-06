<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\BackfillUploaderVbulletinIdentityAction;
use App\Domain\Admin\Actions\RecomputeUploaderStatsAction;
use App\Domain\Admin\Models\Uploader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Replaces `admincp/khotab/uploader.php` + `uploaders.php` — Roadmap task
 * 5.9. `index()`/`recompute()`/`backfillVbulletinIdentity()` port confirmed-
 * working legacy behavior. The "add new uploader by forum-member-id" form
 * (`uploader.php`'s own `vbuid` field) is deliberately **not built** — a
 * full read of the legacy file found no server-side handler for it at
 * all, not even a `die()`/commented-out one like this module's other
 * confirmed-dead flows; there is nothing to infer the intended behavior
 * from (Roadmap task 5.9's own scope note).
 */
class UploaderController
{
    public function index(Request $request): View
    {
        $sort = match ($request->query('sort')) {
            'date' => 'last_upload',
            'count' => 'counter',
            'username' => 'username',
            default => 'email',
        };
        $order = $request->query('order') === 'ASC' ? 'asc' : 'desc';

        $uploaders = Uploader::orderBy($sort, $order)->get();

        return view('admin.uploaders.index', compact('uploaders', 'order'));
    }

    public function recompute(RecomputeUploaderStatsAction $action): RedirectResponse
    {
        $action->execute();

        return redirect()->route('admin.uploaders.index')->with('success', 'تم التحديث بنجاح');
    }

    public function backfillVbulletinIdentity(BackfillUploaderVbulletinIdentityAction $action): RedirectResponse
    {
        $action->execute();

        return redirect()->route('admin.uploaders.index')->with('success', 'تم البحث بنجاح');
    }
}

<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Models\SiteOption;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Replaces `admincp/soundcloud/index.php` — Roadmap task 5.4. Confirmed simple, working: a single `nuke_options` row (`soundcloud`), the SoundCloud track id embedded on the public homepage. */
class SoundcloudController
{
    public function edit(): View
    {
        $trackId = SiteOption::get('soundcloud');

        return view('admin.soundcloud.edit', compact('trackId'));
    }

    public function update(Request $request): RedirectResponse
    {
        $trackId = (int) $request->input('soundcloud');

        if ($trackId >= 0) {
            SiteOption::put('soundcloud', (string) $trackId);
        }

        return redirect()->route('admin.soundcloud.edit')->with('success', 'تم التحديث بنجاح');
    }
}

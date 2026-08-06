<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Models\SiteOption;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Replaces `admincp/youtube/index.php` — Roadmap task 5.4. Confirmed
 * working: a `nuke_options` row (`youtube`) storing a serialized array of
 * YouTube video ids, parsed from the full URL's `?v=` query param
 * (`index.php:50-59`) and rendered as embeds on the public homepage.
 */
class YoutubeController
{
    public function edit(): View
    {
        $videoIds = $this->currentIds();

        return view('admin.youtube.edit', compact('videoIds'));
    }

    public function store(Request $request): RedirectResponse
    {
        $url = (string) $request->input('youtube');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (! empty($query['v'])) {
            $ids = $this->currentIds();
            $ids[] = $query['v'];
            SiteOption::put('youtube', serialize($ids));
        }

        return redirect()->route('admin.youtube.edit')->with('success', 'تمت الإضافة بنجاح');
    }

    public function destroy(int $index): RedirectResponse
    {
        $ids = $this->currentIds();

        if (array_key_exists($index, $ids)) {
            unset($ids[$index]);
            SiteOption::put('youtube', serialize(array_values($ids)));
        }

        return redirect()->route('admin.youtube.edit')->with('success', 'تم المسح بنجاح');
    }

    /** @return list<string> */
    private function currentIds(): array
    {
        $stored = SiteOption::get('youtube');

        if ($stored === null || $stored === '') {
            return [];
        }

        $ids = @unserialize($stored);

        return is_array($ids) ? $ids : [];
    }
}

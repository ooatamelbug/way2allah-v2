<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\RecomputeLinkQualityPercentAction;
use App\Domain\Admin\Jobs\RecheckKhotabLinkSizeJob;
use App\Domain\Admin\Jobs\RecheckMirrorLinkSizeJob;
use App\Domain\Admin\Jobs\RecheckTelawahLinkSizeJob;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;
use App\Domain\Content\Models\TelawahItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Replaces `admincp/khotab/stats.php` (`nuke_islamic_mirror`),
 * `admincp/khotab/stats_khotab.php` (`nuke_islamic_khotab`),
 * `admincp/telawah/stats.php` (`nuke_telawah_telawah`), and
 * `admincp/khotab/stats_khotab_200mb.php` — Roadmap task 6.7. Reuses
 * `Content\Models\Mirror`/`KhotabItem`/`TelawahItem` directly (no new
 * models — every needed column already exists on all three).
 *
 * The unconditional `percent` recompute legacy ran on every page load
 * (Pattern E, `admincp.md` §5) is a real, explicit action here instead
 * (`recompute*()`), matching `UploaderController::recompute()`'s own
 * precedent (task 5.9) — not run automatically on every `index`-style
 * request.
 *
 * The three mismatch-repair pages (`mirror`/`khotab`/`telawah`) share
 * an identical shape but differ in real, confirmed ways reproduced here
 * rather than unified: `mirror`'s threshold is `percent < 96 OR > 101`
 * and excludes youtube/soundcloud embeds (not real, checkable download
 * links); `khotab`'s threshold is `percent < 100 OR > 100` and shares
 * the same exclusion; `telawah`'s threshold matches `mirror`'s but has
 * no such exclusion (telawah items are never embeds). `mirror`/`khotab`
 * additionally auto-repair any listed row with `linksize == 0 AND
 * online > 500000` inline, on every render — `telawah/stats.php` has no
 * such block; not added here.
 */
class LinkQualityStatsController
{
    public function mirror(): View
    {
        $mirrors = Mirror::with('khotabItem')
            ->where(function ($query) {
                $query->where('percent', '<', 96)->orWhere('percent', '>', 101);
            })
            ->where('link', 'not like', '%youtube.com%')
            ->where('link', 'not like', '%youtu.be%')
            ->where('link', 'not like', '%soundcloud.com%')
            ->orderByDesc('time')
            ->paginate(100);

        $this->autoRepairZeroLinksize($mirrors->getCollection());

        return view('admin.link-quality.mirror', compact('mirrors'));
    }

    public function khotab(): View
    {
        $khotabItems = KhotabItem::where(function ($query) {
            $query->where('percent', '<', 100)->orWhere('percent', '>', 100);
        })
            ->where('link', 'not like', '%youtube.com%')
            ->where('link', 'not like', '%youtu.be%')
            ->where('link', 'not like', '%soundcloud.com%')
            ->orderByDesc('time')
            ->paginate(100);

        $this->autoRepairZeroLinksize($khotabItems->getCollection());

        return view('admin.link-quality.khotab', compact('khotabItems'));
    }

    public function telawah(): View
    {
        $telawahItems = TelawahItem::where(function ($query) {
            $query->where('percent', '<', 96)->orWhere('percent', '>', 101);
        })
            ->orderByDesc('mytime')
            ->paginate(50);

        return view('admin.link-quality.telawah', compact('telawahItems'));
    }

    /** `stats_khotab_200mb.php` — large-file inventory + bulk link export, no repair actions. */
    public function khotabLargeFiles(): View
    {
        $khotabItems = KhotabItem::where(function ($query) {
            $query->where('linksize', '>', 209715200)->orWhere('online', '>', 209715200);
        })
            ->orderByDesc('time')
            ->paginate(100);

        return view('admin.link-quality.khotab-large-files', compact('khotabItems'));
    }

    public function recomputeMirror(RecomputeLinkQualityPercentAction $action): RedirectResponse
    {
        $action->execute('nuke_islamic_mirror');

        return redirect()->route('admin.link-quality.mirror.index')->with('success', 'تم التحديث بنجاح');
    }

    public function recomputeKhotab(RecomputeLinkQualityPercentAction $action): RedirectResponse
    {
        $action->execute('nuke_islamic_khotab');

        return redirect()->route('admin.link-quality.khotab.index')->with('success', 'تم التحديث بنجاح');
    }

    public function recomputeTelawah(RecomputeLinkQualityPercentAction $action): RedirectResponse
    {
        $action->execute('nuke_telawah_telawah');

        return redirect()->route('admin.link-quality.telawah.index')->with('success', 'تم التحديث بنجاح');
    }

    public function fixSizeMirror(Mirror $mirror): RedirectResponse
    {
        $mirror->update(['linksize' => $mirror->online, 'percent' => 100]);

        return back()->with('success', 'تم تعديل الحجم الأصلي للمادة بنجاح');
    }

    public function fixSizeKhotab(KhotabItem $khotabItem): RedirectResponse
    {
        $khotabItem->update(['linksize' => $khotabItem->online, 'percent' => 100]);

        return back()->with('success', 'تم تعديل الحجم الأصلي للمادة بنجاح');
    }

    public function fixSizeTelawah(TelawahItem $telawahItem): RedirectResponse
    {
        $telawahItem->update(['linksize' => $telawahItem->online, 'percent' => 100]);

        return back()->with('success', 'تم تعديل الحجم الأصلي للمادة بنجاح');
    }

    public function recheckMirror(Mirror $mirror): RedirectResponse
    {
        RecheckMirrorLinkSizeJob::dispatch($mirror->id);

        return back()->with('success', 'تمت جدولة إعادة الفحص');
    }

    public function recheckKhotab(KhotabItem $khotabItem): RedirectResponse
    {
        RecheckKhotabLinkSizeJob::dispatch($khotabItem->id);

        return back()->with('success', 'تمت جدولة إعادة الفحص');
    }

    public function recheckTelawah(TelawahItem $telawahItem): RedirectResponse
    {
        RecheckTelawahLinkSizeJob::dispatch($telawahItem->id);

        return back()->with('success', 'تمت جدولة إعادة الفحص');
    }

    /** `stats.php:176-182`/`stats_khotab.php:157-163` — auto-repairs unrecorded-but-live sizes on every render. */
    private function autoRepairZeroLinksize(iterable $items): void
    {
        foreach ($items as $item) {
            if ((int) $item->linksize === 0 && $item->online > 500000) {
                $item->update(['linksize' => $item->online, 'percent' => 100]);
            }
        }
    }
}

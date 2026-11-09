<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Advfiles\AdvfileState;
use App\Domain\Admin\Actions\Advfiles\AdvfileTransitionRefused;
use App\Domain\Admin\Actions\Advfiles\ApproveAdvfileAction;
use App\Domain\Admin\Actions\Advfiles\RejectAdvfileRequestAction;
use App\Domain\Admin\Actions\Advfiles\RequestAdvfileAction;
use App\Domain\Admin\Actions\Advfiles\UnpublishAdvfileAction;
use App\Domain\Admin\Actions\Advfiles\UpdateAdvfileAction;
use App\Domain\Admin\Actions\Advfiles\UpdateAdvfileCreditsAction;
use App\Domain\Admin\Http\Requests\Advfiles\AdvfileCreditsRequest;
use App\Domain\Admin\Http\Requests\Advfiles\AdvfilePayloadRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Advfile;
use App\Domain\Content\Models\KhotabItem;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * OLD Admin Migration Phase 5 — Featured / Promotional Content (Advfiles).
 *
 * Replaces OLD `KhotabRequestAdvFile` and `modules/advfiles/admin`: the
 * pending queue, approve (add file), reject (Reset), edit, remove, and the
 * credit list. Item CRUD stays out — the item page links to the existing
 * Khotab pages. Thin: every transition is an action that re-checks
 * authorization and audits.
 */
class AdvfileController
{
    public function __construct(private readonly AdminRecordAuthorizer $authorizer) {}

    /** OLD AdvfilesMain: `WHERE advfile = -1 ORDER BY id ASC`. */
    public function queue(): View
    {
        return view('admin.advfiles.queue', [
            'pending' => KhotabItem::query()->where('advfile', AdvfileState::PENDING)->orderBy('id')->get(['id', 'title', 'time']),
        ]);
    }

    /** Any admin: look an item up to see its state and request. */
    public function lookup(Request $request): View|RedirectResponse
    {
        if ($request->filled('item')) {
            $id = (int) $request->input('item');

            return KhotabItem::query()->whereKey($id)->exists()
                ? redirect()->route('admin.advfiles.item', $id)
                : back()->with('error', "لم يتم العثور على المادة #{$id}");
        }

        return view('admin.advfiles.lookup');
    }

    public function item(KhotabItem $khotabItem): View
    {
        $state = (int) $khotabItem->getAttribute('advfile');

        return view('admin.advfiles.item', [
            'item' => $khotabItem,
            'state' => $state,
            'stateLabel' => AdvfileState::label($state),
            'file' => Advfile::query()->find($khotabItem->getKey()),
            'canManage' => $this->authorizer->allows($this->actor(), 'khotab.addadv'),
        ]);
    }

    public function request(Request $request, KhotabItem $khotabItem, RequestAdvfileAction $action): RedirectResponse
    {
        return $this->transition($khotabItem, function () use ($request, $khotabItem, $action) {
            return $action->execute($this->actor(), $khotabItem, $request->ip()) === RequestAdvfileAction::REQUESTED
                ? ['success', 'تم إرسال طلب ملف الرئيسية إلى الفريق.... جزاكم الله خيراً']
                : ['error', 'يوجد طلب قائم لهذه المادة بالفعل — لم يتغير شيء'];
        });
    }

    public function approveForm(KhotabItem $khotabItem): View|RedirectResponse
    {
        if ((int) $khotabItem->getAttribute('advfile') !== AdvfileState::PENDING) {
            return redirect()->route('admin.advfiles.item', $khotabItem)->with('error', 'عفواً ... يجب أن يتم التقدم بطلب ملف دعائي للمادة أولاً');
        }

        return view('admin.advfiles.form', [
            'item' => $khotabItem,
            'file' => Advfile::query()->find($khotabItem->getKey()),
            'action' => route('admin.advfiles.approve', $khotabItem),
            'method' => 'POST',
            'heading' => 'إضافة ملف دعائي: '.$khotabItem->title,
        ]);
    }

    public function approve(AdvfilePayloadRequest $request, KhotabItem $khotabItem, ApproveAdvfileAction $action): RedirectResponse
    {
        return $this->transition($khotabItem, function () use ($request, $khotabItem, $action) {
            $action->execute($this->actor(), $khotabItem, $request->payload(), $request->ip());

            return ['success', 'تمت إضافة الملف الدعائي ونشره'];
        });
    }

    public function reject(Request $request, KhotabItem $khotabItem, RejectAdvfileRequestAction $action): RedirectResponse
    {
        return $this->transition($khotabItem, function () use ($request, $khotabItem, $action) {
            return $action->execute($this->actor(), $khotabItem, $request->ip())
                ? ['success', 'تم رفض الطلب']
                : ['error', 'لا يوجد طلب قائم لهذه المادة — لم يتغير شيء'];
        });
    }

    public function edit(KhotabItem $khotabItem): View|RedirectResponse
    {
        $file = Advfile::query()->find($khotabItem->getKey());
        if (! AdvfileState::isPublished((int) $khotabItem->getAttribute('advfile')) || $file === null) {
            return redirect()->route('admin.advfiles.item', $khotabItem)->with('error', 'لا يوجد ملف دعائي منشور لهذه المادة');
        }

        return view('admin.advfiles.form', [
            'item' => $khotabItem,
            'file' => $file,
            'action' => route('admin.advfiles.update', $khotabItem),
            'method' => 'PUT',
            'heading' => 'تعديل ملف: '.$khotabItem->title,
        ]);
    }

    public function update(AdvfilePayloadRequest $request, KhotabItem $khotabItem, UpdateAdvfileAction $action): RedirectResponse
    {
        return $this->transition($khotabItem, function () use ($request, $khotabItem, $action) {
            return $action->execute($this->actor(), $khotabItem, $request->payload(), $request->ip())
                ? ['success', 'تم حفظ الملف الدعائي']
                : ['error', 'لم يتغير شيء'];
        });
    }

    public function unpublish(Request $request, KhotabItem $khotabItem, UnpublishAdvfileAction $action): RedirectResponse
    {
        return $this->transition($khotabItem, function () use ($request, $khotabItem, $action) {
            $action->execute($this->actor(), $khotabItem, $request->ip());

            return ['success', 'تم مسح الملف الدعائي'];
        });
    }

    /** OLD Credit(): payloads `WHERE credit > 0 ORDER BY credit DESC`. */
    public function credits(): View
    {
        $files = Advfile::query()->where('credit', '>', 0)->orderByDesc('credit')->orderBy('id')->get(['id', 'credit']);

        return view('admin.advfiles.credits', [
            'files' => $files,
            'titles' => KhotabItem::query()->whereKey($files->pluck('id'))->pluck('title', 'id'),
        ]);
    }

    public function saveCredits(AdvfileCreditsRequest $request, UpdateAdvfileCreditsAction $action): RedirectResponse
    {
        $changed = $action->execute($this->actor(), $request->credits(), $request->ip());

        return redirect()->route('admin.advfiles.credits')
            ->with($changed > 0 ? 'success' : 'error', $changed > 0 ? "تم تحديث {$changed} ملف" : 'لم يتغير شيء');
    }

    /** @param Closure(): array{0: string, 1: string} $run */
    private function transition(KhotabItem $item, Closure $run): RedirectResponse
    {
        try {
            [$level, $message] = $run();
        } catch (AdvfileTransitionRefused $refused) {
            [$level, $message] = ['error', $refused->getMessage()];
        }

        return redirect()->route('admin.advfiles.item', $item)->with($level, $message);
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}

<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Khotab\InsertKhotabAuthorAction;
use App\Domain\Admin\Actions\Khotab\KhotabPendingComments;
use App\Domain\Admin\Actions\Khotab\RepairAllKhotabAuthorCountersAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Http\Requests\Khotab\StoreKhotabAuthorRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * OLD public admin parity — Khotab Main (`khotab-video.htm` /
 * `khotab-audio.htm`, OLD `Department2()` + `admincp.htm`). Production = the
 * OLD working copy (owner, 2026-10-21). OLD's fourth link «ملفات مفقودة»
 * (`KhotabListDown`) had no handler anywhere and is not reproduced.
 *
 * `{media}` is the Main page the panel was on (`video` | `audio`); it decides
 * where the admin lands. Every mutation is an action class (authorized,
 * transactional, audited) and a non-GET request.
 */
class KhotabMainAdminController
{
    /** «أضـــف شـــيـــخ» — OLD InsertAuthor() + insert_author.htm (without its never-read image field). */
    public function createAuthor(string $media): View
    {
        return view('admin.khotab.main.author-create', ['media' => $media]);
    }

    /** OLD redirected to the new author's VIDEO page; here, the page of the Main the admin came from. */
    public function storeAuthor(StoreKhotabAuthorRequest $request, string $media, InsertKhotabAuthorAction $action): RedirectResponse
    {
        $id = $action->execute($this->actor(), $request->validated(), $request->ip());

        return redirect()->to('/khotab-'.$media.'-'.$id.'.htm')->with(ContextualReturn::FLASH, 'تمت إضافة الداعية');
    }

    /** «إصــــلاح الـــعــدادات» — every author, server-side (owner decision; OLD walked one author per page). */
    public function repairCounts(Request $request, string $media, RepairAllKhotabAuthorCountersAction $action): RedirectResponse
    {
        // One maintenance request over the whole table: it must not stop half-way.
        ignore_user_abort(true);
        set_time_limit(0);

        $s = $action->execute($this->actor(), $request->ip());

        return redirect()->to('/khotab-'.$media.'.htm')->with(ContextualReturn::FLASH,
            "تم إصلاح العدادات: {$s['authors']} داعية ({$s['authors_changed']} تغيرت عداداتهم)، {$s['series']} سلسلة ({$s['series_changed']} تغيرت)، {$s['groups']} مجموعة ({$s['groups_changed']} تغيرت)");
    }

    /**
     * «تعليقات تنتظر النشر» — OLD WaitingList(0, 1, page): every pending
     * comment, oldest first, 30 a page, with working page links (OLD's held a
     * literal `{PAGE}`). An empty queue sends the admin back to Main, as OLD
     * left its queue when nothing was pending.
     */
    public function comments(Request $request, string $media, AdminRecordAuthorizer $authorizer): View|RedirectResponse
    {
        $total = KhotabPendingComments::count();
        if ($total === 0) {
            return redirect()->to('/khotab-'.$media.'.htm')->with(ContextualReturn::FLASH, 'لا توجد تعليقات تنتظر النشر');
        }

        $perPage = KhotabPendingComments::PER_PAGE;
        $page = min(max(1, (int) $request->query('page', 1)), (int) ceil($total / $perPage));

        $rows = DB::connection('main')->table('nuke_islamic_comments as c')
            ->leftJoin('nuke_islamic_khotab as k', 'k.id', '=', 'c.khid')
            ->where('c.view', 0)
            ->orderBy('c.id')
            ->forPage($page, $perPage)
            ->get(['c.id', 'c.khid', 'c.uid', 'c.uname', 'c.uemail', 'c.comment', 'c.code', 'k.title as item_title']);

        $actor = $this->actor();

        return view('admin.khotab.main.comments', [
            'media' => $media,
            'comments' => new LengthAwarePaginator($rows, $total, $perPage, $page, ['path' => $request->url()]),
            'total' => $total,
            'canPublish' => $authorizer->allows($actor, 'khotab.approvecomment'),
            'canHide' => $authorizer->allows($actor, 'khotab.delcomment'),
        ]);
    }

    /** «موافق» — OLD AddComments(): every row's choice at once; back to the same page of the queue. */
    public function moderateComments(Request $request, string $media, KhotabPendingComments $queue): RedirectResponse
    {
        $data = $request->validate([
            'Comment' => ['nullable', 'array'],
            'Comment.*' => ['in:0,1,2'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $queue->moderate($this->actor(), $data['Comment'] ?? [], $request->ip());

        return redirect()->route('admin.khotab.main.comments', ['media' => $media, 'page' => (int) ($data['page'] ?? 1)])
            ->with('success', "تم نشر {$result['published']} ومسح {$result['hidden']} تعليق");
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}

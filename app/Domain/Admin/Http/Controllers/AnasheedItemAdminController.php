<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Anasheed\AnasheedItemComments;
use App\Domain\Admin\Actions\Anasheed\AnasheedItemGroups;
use App\Domain\Admin\Actions\Anasheed\DeleteAnasheedItemAction;
use App\Domain\Admin\Actions\Anasheed\UpdateAnasheedItemAction;
use App\Domain\Admin\Actions\Anasheed\UploadAnasheedItemFrameAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Http\Requests\Anasheed\PublishAnasheedCommentRequest;
use App\Domain\Admin\Http\Requests\Anasheed\StoreAnasheedModeratorCommentRequest;
use App\Domain\Admin\Http\Requests\Anasheed\UpdateAnasheedItemRequest;
use App\Domain\Admin\Http\Requests\Anasheed\UploadAnasheedItemFrameRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\AnasheedComment;
use App\Domain\Content\Models\AnasheedGroup;
use App\Domain\Content\Models\AnasheedItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * OLD public admin parity — the Anasheed item page's «لوحة تحكم المادة»
 * (`themes/way2allah/templates/Anasheed/item_cp.htm`, rendered by OLD
 * `modules/Anasheed/index.php` `Detailes()`) and its comment controls
 * (`Comments()`), owner-approved scope 2026-10-23: the five working OLD
 * controls not already migrated — «اضافة صورة للمادة», «تعديل المادة»,
 * «حذف المادة», «إضافة تعليق إداري» and «(تعديل التعليق)». The mirror and
 * category controls stay with their Phase 1 / 3 controllers.
 *
 * Every page here is the Laravel counterpart of an OLD `admin.php?op=…`
 * page; every mutation is an action class (authorized, transactional,
 * audited). After a success the admin lands where OLD sent them, with the
 * message in the admin-only public notice.
 */
class AnasheedItemAdminController
{
    /** «اضافة صورة للمادة» — OLD admin `Detailes()`: the item, its current frame, the upload form. */
    public function frame(AnasheedItem $anasheedItem): View
    {
        $hasFrame = (int) $anasheedItem->frame === 1 && is_file(UploadAnasheedItemFrameAction::absolutePath((int) $anasheedItem->id));

        return view('admin.anasheed.items.frame', [
            'item' => $anasheedItem,
            'currentFrame' => $hasFrame ? $anasheedItem->frameThumbUrl() : null,
            'maxKb' => intdiv((int) config('admin_anasheed.frame_max_bytes'), 1000),
        ]);
    }

    /** OLD UploadBanner() then refreshed to `var-item-{id}.htm`. */
    public function uploadFrame(UploadAnasheedItemFrameRequest $request, AnasheedItem $anasheedItem, UploadAnasheedItemFrameAction $action): RedirectResponse
    {
        $file = $request->file('frame');
        abort_unless($file instanceof UploadedFile, 422);
        $action->execute($this->actor(), $anasheedItem, $file, $request->ip());

        return $this->toItem((int) $anasheedItem->id, 'جزاك الله خيرا على إضافة الصورة إلى الموقع.');
    }

    /** «تعديل المادة» — OLD EditAnasheed(). */
    public function edit(AnasheedItem $anasheedItem): View
    {
        $main = DB::connection('main');

        return view('admin.anasheed.items.edit', [
            'item' => $anasheedItem,
            'author' => $main->table('nuke_islamic_authors')->where('id', (int) $anasheedItem->author_id)->first(['prename', 'name']),
            'channels' => $main->table('nuke_sat_channels')->orderByRaw($this->binaryOrder('title'))->get(['id', 'title']),
            'authors' => $main->table('nuke_islamic_authors')->orderByRaw($this->binaryOrder('name'))->get(['id', 'name']),
            'groups' => AnasheedItemGroups::options($anasheedItem),
            'sizeField' => UpdateAnasheedItemAction::sizeField((int) $anasheedItem->linksize),
        ]);
    }

    /**
     * OLD ConfirmEditAnasheed() went to `$_POST['redirct_to']` when the form
     * carried one (only `anasheed_without_authors.htm` sets it), otherwise to
     * the item. Without a valid return_to, the item page as before.
     */
    public function update(UpdateAnasheedItemRequest $request, AnasheedItem $anasheedItem, UpdateAnasheedItemAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $anasheedItem, $request->validated(), $request->ip());

        $target = ContextualReturn::safeTarget($request->input(ContextualReturn::PARAM), $request);
        if ($target !== null) {
            return redirect()->to($target)->with(ContextualReturn::FLASH, 'تم تعديل المادة');
        }

        return $this->toItem((int) $anasheedItem->id, 'تم تعديل المادة');
    }

    /** «حذف المادة» — OLD DeleteAnasheed()'s confirmation page, with what the delete removes. */
    public function confirmDestroy(AnasheedItem $anasheedItem): View
    {
        $main = DB::connection('main');

        return view('admin.anasheed.items.delete', [
            'item' => $anasheedItem,
            'mirrors' => $main->table('nuke_anasheed_mirror')->where('khid', $anasheedItem->id)->count(),
            'comments' => $main->table('nuke_anasheed_comments')->where('khid', $anasheedItem->id)->count(),
        ]);
    }

    /** OLD went to the item's group page; without a group, the home page. */
    public function destroy(Request $request, AnasheedItem $anasheedItem, DeleteAnasheedItemAction $action): RedirectResponse
    {
        $result = $action->execute($this->actor(), $anasheedItem, $request->ip());

        $groupId = $result['group_id'];
        $target = $groupId > 0 && AnasheedGroup::whereKey($groupId)->exists() ? '/var-group-'.$groupId.'.htm' : '/';

        return redirect()->to($target)->with(ContextualReturn::FLASH, "تم حذف المادة '' {$result['title']} ''");
    }

    /** «إضافة تعليق إداري» — OLD AddModComment(). */
    public function storeComment(StoreAnasheedModeratorCommentRequest $request, AnasheedItem $anasheedItem, AnasheedItemComments $comments): RedirectResponse
    {
        $comments->addModerator($this->actor(), $anasheedItem, (string) $request->validated('comment'), (string) $request->ip());

        return $this->toItem((int) $anasheedItem->id, 'تمت إضافة التعليق');
    }

    /** «(تعديل التعليق)» — OLD WaitingList(id): one comment, with publish and hide. */
    public function editComment(AnasheedComment $anasheedComment): View
    {
        return view('admin.anasheed.items.comment', [
            'comment' => $anasheedComment,
            'item' => DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', $anasheedComment->khid)->first(['id', 'title']),
        ]);
    }

    /** «نشر التعليق للمادة» — OLD AddComment(); its «التحويل إلى صفحة المادة» was checked. */
    public function publishComment(PublishAnasheedCommentRequest $request, AnasheedComment $anasheedComment, AnasheedItemComments $comments): RedirectResponse
    {
        $itemId = $comments->publish($this->actor(), $anasheedComment, (string) $request->validated('comment'), $request->ip());

        return $this->toItem($itemId, 'تم نشر التعليق');
    }

    /** «مسح التعليق للمادة» — OLD DelComment(): hidden (`view = 2`), not deleted. */
    public function hideComment(Request $request, AnasheedComment $anasheedComment, AnasheedItemComments $comments): RedirectResponse
    {
        $itemId = $comments->hide($this->actor(), $anasheedComment, $request->ip());

        return $this->toItem($itemId, 'تم مسح التعليق');
    }

    private function toItem(int $itemId, string $message): RedirectResponse
    {
        return redirect()->to('/var-item-'.$itemId.'.htm')->with(ContextualReturn::FLASH, $message);
    }

    /** OLD's `ORDER BY BINARY {column} ASC` on MySQL; plain on the test suite's SQLite. */
    private function binaryOrder(string $column): string
    {
        return DB::connection('main')->getDriverName() === 'mysql' ? "BINARY {$column} ASC" : "{$column} ASC";
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}

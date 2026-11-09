<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Khotab\DeleteKhotabItemAction;
use App\Domain\Admin\Actions\Khotab\KhotabItemComments;
use App\Domain\Admin\Actions\Khotab\KhotabItemFiles;
use App\Domain\Admin\Actions\Khotab\LeechKhotabItemImagesAction;
use App\Domain\Admin\Actions\Khotab\MoveKhotabItemAction;
use App\Domain\Admin\Actions\Khotab\UpdateKhotabItemAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Http\Requests\Khotab\UpdateKhotabItemRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\Comment;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Support\MediaPathResolver;
use App\Domain\Content\Support\MediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * OLD public admin parity — the Khotab item page's «لوحة تحكم المشرفين»
 * (OLD `AdminPanel()`, `modules/Khotab/index.php:784`) and its comment
 * controls (`Comments()`). Production = the OLD working copy (owner,
 * 2026-10-21).
 *
 * Every page here is the Laravel counterpart of an OLD `admin.php?op=…` page;
 * every mutation is an action class (authorized, transactional, audited) and
 * a non-GET request — OLD's GET «نعم» confirmation links became forms. After
 * a mutation the admin lands where OLD sent them, with OLD's message shown in
 * the admin-only public notice.
 */
class KhotabItemAdminController
{
    private const UPLOAD_RULES = [
        'banner' => ['extension' => 'gif', 'mimes' => ['image/gif'], 'max' => 40000],
        'pdf' => ['extension' => 'pdf', 'mimes' => ['application/pdf'], 'max' => 15000000],
        // A .docx is a zip; fileinfo reports one or the other.
        'docx' => ['extension' => 'docx', 'mimes' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'], 'max' => 15000000],
    ];

    /** «تعديل» — OLD EditKhotab(). */
    public function edit(KhotabItem $item): View
    {
        $main = DB::connection('main');
        $free = (int) $item->group_id === 0 && (int) $item->ser_id === 0;

        return view('admin.khotab.items.edit', [
            'item' => $item,
            'author' => $main->table('nuke_islamic_authors')->where('id', $item->author)->first(['prename', 'name']),
            'groupTitle' => (string) $main->table('nuke_islamic_groups')->where('id', $item->group_id)->value('title'),
            'seriesTitle' => (string) $main->table('nuke_islamic_series')->where('id', $item->ser_id)->value('title'),
            'free' => $free,
            'channels' => $free ? $main->table('nuke_sat_channels')->orderByRaw($this->byTitle())->get(['id', 'title']) : collect(),
            'channelTitle' => $free ? '' : (string) $main->table('nuke_sat_channels')->where('id', $item->channel_id)->value('title'),
            'locations' => $main->table('nuke_islamic_locations')->orderByRaw($this->byTitle())->get(['id', 'title']),
            'linkEditable' => UpdateKhotabItemAction::linkEditable($this->actor(), $item),
            'banner' => (int) $item->getAttribute('banner') === 1 ? MediaUrl::asset(KhotabItemFiles::path('banner', (int) $item->id)) : null,
        ]);
    }

    public function update(UpdateKhotabItemRequest $request, KhotabItem $item, UpdateKhotabItemAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $item, $request->validated(), $request->ip());

        return $this->toItem($item, 'تم تعديل المادة');
    }

    /** «نقل» — OLD MoveKhotab() without `confirm`. */
    public function move(KhotabItem $item): View
    {
        $main = DB::connection('main');

        return view('admin.khotab.items.move', [
            'item' => $item,
            'author' => $main->table('nuke_islamic_authors')->where('id', $item->author)->first(['id', 'prename', 'name']),
            'authors' => $main->table('nuke_islamic_authors')->orderBy('name')->get(['id', 'name']),
            'groups' => $main->table('nuke_islamic_groups')->where('author_id', $item->author)->where('vedio', $item->vedio)->orderBy('id')->get(['id', 'title']),
            'series' => $main->table('nuke_islamic_series')->where('author_id', $item->author)->where('vedio', $item->vedio)
                ->where('group_id', $item->group_id)->orderByDesc('id')->get(['id', 'title']),
        ]);
    }

    public function saveMove(Request $request, KhotabItem $item, MoveKhotabItemAction $action): RedirectResponse
    {
        $data = $request->validate([
            'author_id' => ['required', 'integer', 'min:0'],
            'group_id' => ['nullable', 'integer', 'min:0'],
            'ser_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $action->execute($this->actor(), $item, (int) $data['author_id'], (int) ($data['group_id'] ?? 0), (int) ($data['ser_id'] ?? 0), $request->ip());

        return $this->toItem($item, 'تم نقل المادة');
    }

    /** OLD `KhotabAjaxGroupsSeries`: the chosen author's groups, and their series outside any group. */
    public function moveGroups(Request $request): View
    {
        $author = (int) $request->query('author');
        $vedio = (int) $request->query('vedio');
        $main = DB::connection('main');

        return view('admin.khotab.items._move-groups', [
            'groups' => $main->table('nuke_islamic_groups')->where('author_id', $author)->where('vedio', $vedio)->orderByDesc('id')->get(['id', 'title']),
            'series' => $main->table('nuke_islamic_series')->where('author_id', $author)->where('vedio', $vedio)->where('group_id', 0)->orderByDesc('id')->get(['id', 'title']),
        ]);
    }

    /** OLD `KhotabAjaxSeries`: every series of the chosen group (OLD did not filter by author). */
    public function moveSeries(Request $request): View
    {
        return view('admin.khotab.items._move-series', [
            'series' => DB::connection('main')->table('nuke_islamic_series')
                ->where('vedio', (int) $request->query('vedio'))->where('group_id', (int) $request->query('group_id'))
                ->orderByDesc('id')->get(['id', 'title']),
        ]);
    }

    /** «مسح» — OLD DeleteKhotab()'s confirmation page. */
    public function confirmDestroy(KhotabItem $item): View
    {
        return view('admin.khotab.items.delete', [
            'item' => $item,
            'author' => DB::connection('main')->table('nuke_islamic_authors')->where('id', $item->author)->first(['prename', 'name']),
        ]);
    }

    public function destroy(Request $request, KhotabItem $item, DeleteKhotabItemAction $action): RedirectResponse
    {
        $result = $action->execute($this->actor(), $item, $request->ip());

        $target = match (true) {
            $result['ser_id'] > 0 => '/khotab-series-'.$result['ser_id'].'.htm',
            $result['group_id'] > 0 => '/khotab-group-'.$result['group_id'].'.htm',
            default => '/khotab-'.($result['vedio'] === 1 ? 'video' : 'audio').'-'.$result['author_id'].'.htm',
        };

        return redirect()->to($target)->with(ContextualReturn::FLASH, "تم حذف المادة #{$item->id} : {$result['title']}");
    }

    /** «رفع بانر» / «تعديل البانر» — OLD Detailes() (admin). */
    public function banner(KhotabItem $item): View
    {
        return view('admin.khotab.items.banner', ['item' => $item, 'current' => $this->bannerOnDisk($item)]);
    }

    public function uploadBanner(Request $request, KhotabItem $item, KhotabItemFiles $files): RedirectResponse
    {
        $files->upload($this->actor(), $item, 'banner', $this->uploaded($request, 'banner'), $request->ip());

        return $this->toItem($item, 'جزاك الله خيرا على إضافة البانر إلى الموقع.');
    }

    /** «حذف البانر» — OLD KhotabDelete_banner(): the confirmation only for a banner that exists. */
    public function confirmDeleteBanner(KhotabItem $item): View
    {
        return view('admin.khotab.items.banner-delete', ['item' => $item, 'current' => $this->bannerOnDisk($item)]);
    }

    public function deleteBanner(Request $request, KhotabItem $item, KhotabItemFiles $files): RedirectResponse
    {
        // OLD's «نعم» existed only on that page, i.e. for banner = 1 with its file present.
        abort_if($this->bannerOnDisk($item) === null, 404);
        $files->delete($this->actor(), $item, 'banner', $request->ip());

        return $this->toItem($item, 'تم حذف بانر المادة');
    }

    /** «طلب بانر» — OLD RequestBanner(). */
    public function requestBanner(Request $request, KhotabItem $item, KhotabItemFiles $files): View|RedirectResponse
    {
        if (! $files->requestBanner($this->actor(), $item, $request->ip())) {
            return view('admin.khotab.items.refused', [
                'item' => $item,
                'title' => 'طلب بانر',
                'message' => 'عفواً .... هذه المادة لها بانر بالفعل و لا يمكن تقديم طلب تصميم مرة أخرى',
            ]);
        }

        return $this->toItem($item, 'تم إرسال طلب التصميم إلى الفريق.... جزاكم الله خيراً');
    }

    /** «رفع التفريغ» / «تعديل التفريغ», «رفع الوورد» / «تعديل الوورد» — OLD UploadPdf / KhotabEditPdf, Uploaddocx / KhotabEditdocx. */
    public function fileForm(KhotabItem $item, string $kind): View
    {
        return view('admin.khotab.items.file', [
            'item' => $item,
            'kind' => $kind,
            'replacing' => (int) $item->getAttribute($kind) !== 0,
            'author' => DB::connection('main')->table('nuke_islamic_authors')->where('id', $item->author)->first(['prename', 'name']),
        ]);
    }

    public function uploadFile(Request $request, KhotabItem $item, string $kind, KhotabItemFiles $files): RedirectResponse
    {
        $files->upload($this->actor(), $item, $kind, $this->uploaded($request, $kind), $request->ip());

        // OLD's message, the same for both kinds.
        return $this->toItem($item, 'جزاك الله خيرا على إضافة ملف التفريغ إلى الموقع.');
    }

    /** «حذف التفريغ» / «حذف الوورد» — OLD KhotabDeletePdf / KhotabDeletedocx. */
    public function confirmDeleteFile(KhotabItem $item, string $kind): View
    {
        return view('admin.khotab.items.file-delete', ['item' => $item, 'kind' => $kind]);
    }

    public function deleteFile(Request $request, KhotabItem $item, string $kind, KhotabItemFiles $files): RedirectResponse
    {
        $files->delete($this->actor(), $item, $kind, $request->ip());

        return $this->toItem($item, 'تم حذف ملف التفريغ لهذه المادة');
    }

    /** «سحب الصور» — OLD get_gif_jpg.php?id=, shown as OLD's popup showed it. */
    public function leechImages(Request $request, KhotabItem $item, LeechKhotabItemImagesAction $action): View
    {
        $result = $action->execute($this->actor(), $item, $request->ip());
        $version = '?v='.time();

        return view('admin.khotab.items.images', [
            'item' => $item,
            'result' => $result,
            'gif' => $result['gif'] ? MediaUrl::asset(MediaPathResolver::path('khotab_gifs', (int) $item->id, 'gif')).$version : null,
            'frame' => $result['frame'] ? MediaUrl::asset(MediaPathResolver::path('khotab_frames', (int) $item->id, 'jpg')).$version : null,
        ]);
    }

    /** «إضافة تعليق إداري» — OLD AddModComment(). */
    public function storeComment(Request $request, KhotabItem $item, KhotabItemComments $comments): RedirectResponse
    {
        $data = $request->validate(['comment' => ['required', 'string', 'max:65535']]);
        $comments->addModerator($this->actor(), $item, (string) $data['comment'], (string) $request->ip());

        return $this->toItem($item, 'تمت إضافة التعليق');
    }

    /** «(تعديل التعليق)» — OLD WaitingList(id): one comment, with publish and hide. */
    public function editComment(Request $request, Comment $comment): View
    {
        return view('admin.khotab.items.comment', [
            'comment' => $comment,
            'item' => DB::connection('main')->table('nuke_islamic_khotab')->where('id', $comment->khid)->first(['id', 'title']),
            'queue' => $this->queueReturn($request),
        ]);
    }

    /** «نشر التعليـق» — OLD AddComment(). */
    public function publishComment(Request $request, Comment $comment, KhotabItemComments $comments): RedirectResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:65535']]);
        $itemId = $comments->publish($this->actor(), $comment, (string) ($data['comment'] ?? ''), $request->ip());

        return $this->afterComment($request, $itemId, 'تم نشر التعليق');
    }

    /** «مسح التعليـق» — OLD DelComment(). */
    public function hideComment(Request $request, Comment $comment, KhotabItemComments $comments): RedirectResponse
    {
        $itemId = $comments->hide($this->actor(), $comment, $request->ip());

        return $this->afterComment($request, $itemId, 'تم مسح التعليق');
    }

    /**
     * Opened from Khotab Main's pending-comments queue (owner, 2026-10-21),
     * the page returns there: only a media type and a page number are taken
     * from the request, and the URL is built from the route — never a
     * free-form target. Otherwise null: back to the item, as approved.
     *
     * @return array{media: string, page: int}|null
     */
    private function queueReturn(Request $request): ?array
    {
        $media = $request->input('queue');

        return in_array($media, ['video', 'audio'], true) ? ['media' => $media, 'page' => max(1, (int) $request->input('page', 1))] : null;
    }

    private function afterComment(Request $request, int $itemId, string $message): RedirectResponse
    {
        $queue = $this->queueReturn($request);

        return $queue === null
            ? redirect()->to('/khotab-item-'.$itemId.'.htm')->with(ContextualReturn::FLASH, $message)
            : redirect()->route('admin.khotab.main.comments', $queue)->with('success', $message);
    }

    /** The current banner's public URL — OLD showed it only when `banner = 1` and the file exists. */
    private function bannerOnDisk(KhotabItem $item): ?string
    {
        return (int) $item->getAttribute('banner') === 1 && is_file(KhotabItemFiles::absolutePath('banner', (int) $item->id))
            ? MediaUrl::asset(KhotabItemFiles::path('banner', (int) $item->id)).'?v='.(int) filemtime(KhotabItemFiles::absolutePath('banner', (int) $item->id))
            : null;
    }

    /** OLD's upload checks, with OLD's messages: a file, its extension, its size — plus its detected type. */
    private function uploaded(Request $request, string $kind): UploadedFile
    {
        $rule = self::UPLOAD_RULES[$kind];

        $request->validate([
            'file2upl' => ['required', 'file', 'extensions:'.$rule['extension'], 'mimetypes:'.implode(',', $rule['mimes']),
                function (string $attribute, mixed $value, \Closure $fail) use ($rule) {
                    if ($value instanceof UploadedFile && $value->getSize() > $rule['max']) {
                        $fail('حجم الملف أكبر من الحجم المسموح به');
                    }
                }],
        ], [
            'file2upl.required' => 'لم يتم إختيار ملف للتحميل',
            'file2upl.file' => 'لم يتم إختيار ملف للتحميل',
            'file2upl.extensions' => 'هذا الإمتداد غير مسموح به',
            'file2upl.mimetypes' => 'هذا الإمتداد غير مسموح به',
        ]);

        $file = $request->file('file2upl');
        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }

    /** OLD's `ORDER BY BINARY title ASC`; SQLite (tests) has no BINARY, so it falls back to `title ASC`. */
    private function byTitle(): string
    {
        return DB::connection('main')->getDriverName() === 'mysql' ? 'BINARY title ASC' : 'title ASC';
    }

    private function toItem(KhotabItem $item, string $message): RedirectResponse
    {
        return redirect()->to('/khotab-item-'.$item->id.'.htm')->with(ContextualReturn::FLASH, $message);
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}

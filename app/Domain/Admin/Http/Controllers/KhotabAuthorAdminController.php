<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Khotab\DeleteKhotabAuthorAction;
use App\Domain\Admin\Actions\Khotab\InsertKhotabAuthorGroupAction;
use App\Domain\Admin\Actions\Khotab\InsertKhotabAuthorItemAction;
use App\Domain\Admin\Actions\Khotab\InsertKhotabAuthorSeriesAction;
use App\Domain\Admin\Actions\Khotab\RepairKhotabAuthorCountersAction;
use App\Domain\Admin\Actions\Khotab\UpdateKhotabAuthorAction;
use App\Domain\Admin\Actions\Khotab\UploadKhotabAuthorImageAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Http\Requests\Khotab\StoreKhotabAuthorGroupRequest;
use App\Domain\Admin\Http\Requests\Khotab\StoreKhotabAuthorItemRequest;
use App\Domain\Admin\Http\Requests\Khotab\StoreKhotabAuthorSeriesRequest;
use App\Domain\Admin\Http\Requests\Khotab\UpdateKhotabAuthorRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Support\MediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * OLD public admin parity — the Khotab author page's «لوحة تحكم الداعية»
 * (`themes/way2allah/templates/Khotab/author_cp.htm`, rendered by OLD
 * `modules/Khotab/index.php` `Author()` on `khotab-video-{id}.htm` and
 * `khotab-audio-{id}.htm`). Production = the OLD working copy (owner,
 * 2026-10-21).
 *
 * `{media}` is the page the panel was on (`video` | `audio`): OLD's links
 * carried it as `vedio` / `video`, and it decides the media type of what is
 * added and where the admin lands. Every mutation is an action class
 * (authorized, transactional, audited) and a non-GET request.
 */
class KhotabAuthorAdminController
{
    /** «تعديل بيانات الداعية» — OLD EditAuthor(), with the image upload of the same page. */
    public function edit(Author $author, string $media): View
    {
        $image = UploadKhotabAuthorImageAction::absolutePath((int) $author->id);

        return view('admin.khotab.authors.edit', [
            'author' => $author,
            'media' => $media,
            'image' => is_file($image) ? MediaUrl::asset(UploadKhotabAuthorImageAction::path((int) $author->id)).'?v='.(int) filemtime($image) : null,
        ]);
    }

    /**
     * OLD sent the admin to `modules.php?op=Author`, which the site rewrote to
     * the AUDIO page even from the video page; here the admin returns to the
     * page they came from.
     */
    public function update(UpdateKhotabAuthorRequest $request, Author $author, string $media, UpdateKhotabAuthorAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $author, $request->validated(), $request->ip());

        return $this->toAuthor($author, $media, 'تم تعديل بيانات الداعية');
    }

    public function uploadImage(Request $request, Author $author, string $media, UploadKhotabAuthorImageAction $action): RedirectResponse
    {
        // OLD's checks and messages: a file, a .jpg, under 1 MB — plus its detected type.
        $request->validate([
            'photoimg' => ['required', 'file', 'extensions:jpg', 'mimetypes:image/jpeg',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value instanceof UploadedFile && $value->getSize() >= 1024 * 1024) {
                        $fail('حجم الصورة أكبر من واحد ميجا !');
                    }
                }],
        ], [
            'photoimg.required' => 'من فضلك اختر صورة للداعية',
            'photoimg.file' => 'من فضلك اختر صورة للداعية',
            'photoimg.extensions' => 'امتداد الملف غير صحيح',
            'photoimg.mimetypes' => 'امتداد الملف غير صحيح',
        ]);

        $file = $request->file('photoimg');
        abort_unless($file instanceof UploadedFile, 422);
        $action->execute($this->actor(), $author, $file, $request->ip());

        return redirect()->route('admin.khotab.authors.edit', ['author' => $author->id, 'media' => $media])
            ->with('success', 'تم رفع صورة الداعية');
    }

    /** «إضافة مجموعة» — OLD AddGroup(id, vedio). */
    public function createGroup(Author $author, string $media): View
    {
        return view('admin.khotab.authors.group-create', [
            'author' => $author,
            'media' => $media,
            'channels' => DB::connection('main')->table('nuke_sat_channels')->orderByRaw($this->byTitle())->get(['id', 'title']),
        ]);
    }

    public function storeGroup(StoreKhotabAuthorGroupRequest $request, Author $author, string $media, InsertKhotabAuthorGroupAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $author, $this->vedio($media), $request->validated(), $request->ip());

        return $this->toAuthor($author, $media, 'تمت إضافة المجموعة');
    }

    /** «إضافة سلسلة» — OLD InsertSeries(author, 0, vedio): no group, a free channel. */
    public function createSeries(Author $author, string $media): View
    {
        return view('admin.khotab.authors.series-create', [
            'author' => $author,
            'media' => $media,
            'channels' => DB::connection('main')->table('nuke_sat_channels')->orderByRaw($this->byTitle())->get(['id', 'title']),
        ]);
    }

    public function storeSeries(StoreKhotabAuthorSeriesRequest $request, Author $author, string $media, InsertKhotabAuthorSeriesAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $author, $this->vedio($media), $request->validated(), $request->ip());

        return $this->toAuthor($author, $media, 'تمت إضافة السلسلة');
    }

    /** «إضافة مادة» — OLD InsertKhotab(author, 0, 0, vedio): no series, no group, a free channel. */
    public function createItem(Author $author, string $media): View
    {
        $main = DB::connection('main');

        return view('admin.khotab.authors.item-create', [
            'author' => $author,
            'media' => $media,
            'channels' => $main->table('nuke_sat_channels')->orderByRaw($this->byTitle())->get(['id', 'title']),
            'locations' => $main->table('nuke_islamic_locations')->orderByRaw($this->byTitle())->get(['id', 'title']),
        ]);
    }

    public function storeItem(StoreKhotabAuthorItemRequest $request, Author $author, string $media, InsertKhotabAuthorItemAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $author, $this->vedio($media), $request->validated(), $request->ip());

        return $this->toAuthor($author, $media, 'تمت إضافة المادة');
    }

    /** «حذف الداعية» — OLD DeleteAuthor()'s confirmation page. */
    public function confirmDestroy(Author $author, string $media): View
    {
        return view('admin.khotab.authors.delete', ['author' => $author, 'media' => $media]);
    }

    /**
     * OLD refused, on its own error page, while the author had any item; on
     * success it printed an empty page. Here a deleted author leads to the
     * author index of the page's media type.
     */
    public function destroy(Request $request, Author $author, string $media, DeleteKhotabAuthorAction $action): View|RedirectResponse
    {
        $name = trim($author->prename.' '.$author->name);
        $result = $action->execute($this->actor(), $author, $request->ip());

        if (! $result['deleted']) {
            return view('admin.khotab.authors.delete-refused', ['author' => $author, 'media' => $media]);
        }

        return redirect()->to('/khotab-'.$media.'.htm')->with(ContextualReturn::FLASH, "تم حذف الداعية '' {$name} ''");
    }

    /**
     * «مواد لم تصنف» — OLD `KhotabLists&author_id=X&video=V` with no `mode`:
     * every item of the author in that media type, hidden ones included,
     * `ORDER BY id ASC`, 50 a page.
     */
    public function items(Request $request, Author $author, string $media): View
    {
        $main = DB::connection('main');
        $base = fn () => $main->table('nuke_islamic_khotab')->where('vedio', $this->vedio($media))->where('author', $author->id);
        $total = $base()->count();
        $page = max(1, (int) $request->query('page', 1));

        $rows = $main->table('nuke_islamic_khotab as k')
            ->leftJoin('nuke_islamic_authors as a', 'a.id', '=', 'k.author')
            ->leftJoin('nuke_islamic_series as s', 's.id', '=', 'k.ser_id')
            ->leftJoin('nuke_islamic_groups as g', 'g.id', '=', 'k.group_id')
            ->where('k.vedio', $this->vedio($media))->where('k.author', $author->id)
            ->orderBy('k.id')
            ->forPage($page, 50)
            ->get(['k.id', 'k.title', 'k.hidden', 'k.ser_id', 'k.group_id', 'a.prename', 'a.name', 's.title as series_title', 'g.title as group_title']);

        return view('admin.khotab.authors.items', [
            'author' => $author,
            'media' => $media,
            'items' => new LengthAwarePaginator($rows, $total, 50, $page, ['path' => $request->url()]),
            'total' => $total,
        ]);
    }

    /** «صيانة العدادات» — this author only (owner correction of OLD's next-author sweep). */
    public function repairCounts(Request $request, Author $author, string $media, RepairKhotabAuthorCountersAction $action): RedirectResponse
    {
        $result = $action->execute($this->actor(), $author, $request->ip());

        return $this->toAuthor($author, $media, "تمت صيانة العدادات: {$result['vedio']} مرئي، {$result['audio']} صوتي، {$result['series']} سلسلة، {$result['groups']} مجموعة");
    }

    private function vedio(string $media): int
    {
        return $media === 'video' ? 1 : 0;
    }

    /** OLD's `ORDER BY BINARY title ASC`; SQLite (tests) has no BINARY, so it falls back to `title ASC`. */
    private function byTitle(): string
    {
        return DB::connection('main')->getDriverName() === 'mysql' ? 'BINARY title ASC' : 'title ASC';
    }

    private function toAuthor(Author $author, string $media, string $message): RedirectResponse
    {
        return redirect()->to('/khotab-'.$media.'-'.$author->id.'.htm')->with(ContextualReturn::FLASH, $message);
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}

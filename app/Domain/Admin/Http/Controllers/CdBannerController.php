<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Cds\CdBannerFiles;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\W2acdItem;
use App\Domain\Content\Support\CdBanner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * W2ACD public admin parity, Phase 1 — the CD page's banner controls (OLD
 * `Detailes()` `:285-310`, `modules/w2acd/index.php`): «رفع بانر» while
 * `banner = 0`, «تعديل البانر» / «حذف البانر» otherwise. Each page here is the
 * Laravel counterpart of OLD's `admin.php?op=W2ACD…_banner` page; after a
 * change the admin lands on the CD page, as OLD's refresh sent them, with
 * OLD's message in the admin-only public notice.
 */
class CdBannerController
{
    /** «رفع بانر» / «تعديل البانر» — OLD add_banner() / edit_banner() + admin_add_banner.htm / admin_edit_banner.htm. */
    public function edit(W2acdItem $release): View
    {
        return view('admin.cds.banner', ['release' => $release, 'current' => $this->current($release)]);
    }

    /** OLD UploadBanner(). */
    public function upload(Request $request, W2acdItem $release, CdBannerFiles $files): RedirectResponse
    {
        $request->validate([
            'file2upl' => ['required', 'file', 'extensions:gif,jpg,jpeg,png', 'mimetypes:image/gif,image/jpeg,image/png',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value instanceof UploadedFile && $value->getSize() > CdBannerFiles::MAX_BYTES) {
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

        try {
            $files->upload($this->actor(), $release, $file, $request->ip());
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['file2upl' => 'هذا الإمتداد غير مسموح به']);
        }

        return $this->toRelease($release, 'جزاك الله خيرا على إضافة البانر إلى الموقع.');
    }

    /** «حذف البانر» — OLD W2ACDDelete_banner(): the confirmation only for a banner that exists. */
    public function confirmDelete(W2acdItem $release): View
    {
        return view('admin.cds.banner-delete', ['release' => $release, 'current' => $this->current($release)]);
    }

    /** OLD W2ACDConfirmDelete_banner(). */
    public function destroy(Request $request, W2acdItem $release, CdBannerFiles $files): RedirectResponse
    {
        // OLD's «نعم» existed only on that page, i.e. for banner = 1 with its file present.
        abort_if($this->current($release) === null, 404);
        $files->delete($this->actor(), $release, $request->ip());

        return $this->toRelease($release, 'تم حذف بانر المادة');
    }

    /** @return array{url: string, width: int, height: int}|null the current banner — OLD showed it only for `banner = 1` with the file present */
    private function current(W2acdItem $release): ?array
    {
        return (int) $release->getAttribute('banner') === 1 ? CdBanner::onDisk((int) $release->id) : null;
    }

    private function toRelease(W2acdItem $release, string $message): RedirectResponse
    {
        return redirect()->to('/cds-item-'.$release->id.'.htm')->with(ContextualReturn::FLASH, $message);
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}

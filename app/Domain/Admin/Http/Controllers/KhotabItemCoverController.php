<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Khotab\KhotabItemCoverAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Support\KhotabCover;
use App\Domain\Content\Support\MediaPathResolver;
use App\Domain\Content\Support\MediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * F10 / B5 «صورة الغلاف المخصصة» for a Khotab video item (owner-approved 2026-11-16): the page shows the custom
 * cover (if any) beside the Archive.org frame, the upload / replace form and the remove form. `khotab.edititem`
 * (route middleware, re-checked by the action), CSRF, POST / DELETE.
 */
class KhotabItemCoverController
{
    public function show(KhotabItem $item): View
    {
        $frame = MediaPathResolver::path('khotab_frames', (int) $item->id, 'jpg');

        return view('admin.khotab.items.cover', [
            'item' => $item,
            'isVideo' => (int) $item->vedio === 1,
            'cover' => KhotabCover::url((int) $item->id),
            'archiveFrame' => (int) $item->frame === 1 && is_file(MediaPathResolver::absolute($frame)) ? MediaUrl::asset($frame) : null,
            'maxKb' => intdiv(KhotabItemCoverAction::MAX_UPLOAD_BYTES, 1024),
        ]);
    }

    public function store(Request $request, KhotabItem $item, KhotabItemCoverAction $action): RedirectResponse
    {
        $request->validate([
            'cover' => ['required', 'file', 'max:'.intdiv(KhotabItemCoverAction::MAX_UPLOAD_BYTES, 1024),
                'extensions:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp'],
        ], [
            'cover.required' => 'من فضلك اختر صورة',
            'cover.file' => 'من فضلك اختر صورة',
            'cover.max' => 'حجم الصورة أكبر من 2 ميجابايت',
            'cover.extensions' => 'الصيغ المسموح بها: JPG و PNG و WebP',
            'cover.mimetypes' => 'الصيغ المسموح بها: JPG و PNG و WebP',
            // PHP itself refused the file before validation (most often larger than `upload_max_filesize`): without
            // this, Laravel showed only its English default "The cover failed to upload." (manual test, 2026-11-18).
            'cover.uploaded' => 'تعذر رفع الصورة: تأكد أن حجمها لا يتجاوز 2 ميجابايت ثم حاول مرة أخرى',
        ]);
        $file = $request->file('cover');
        abort_unless($file instanceof UploadedFile, 422);

        $result = $action->upload($this->actor(), $item, $file, $request->ip());

        return redirect()->route('admin.khotab.items.cover', ['item' => $item->id])
            ->with(ContextualReturn::FLASH, $result['replaced'] ? 'تم استبدال صورة الغلاف' : 'تم رفع صورة الغلاف');
    }

    public function destroy(Request $request, KhotabItem $item, KhotabItemCoverAction $action): RedirectResponse
    {
        $removed = $action->remove($this->actor(), $item, $request->ip());

        return redirect()->route('admin.khotab.items.cover', ['item' => $item->id])
            ->with(ContextualReturn::FLASH, $removed ? 'تم حذف صورة الغلاف، وعادت صورة الأرشيف' : 'لا توجد صورة غلاف لهذه المادة');
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}

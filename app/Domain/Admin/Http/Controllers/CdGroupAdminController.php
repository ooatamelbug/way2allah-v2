<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Cds\CdGroupIconFiles;
use App\Domain\Admin\Actions\Cds\CdGroupParents;
use App\Domain\Admin\Actions\Cds\DeleteCdGroupAction;
use App\Domain\Admin\Actions\Cds\InsertCdSubGroupAction;
use App\Domain\Admin\Actions\Cds\UpdateCdGroupAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\W2acdGroup;
use App\Domain\Content\Support\CdGroupIcon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * W2ACD public admin parity, Phase 2 — the CD group page's «لوحة تحكم قسم
 * الاسطوانات» (`group_cp_for_cd.htm`, OLD `modules/w2acd/index.php` `Group()`).
 * Each page here is the Laravel counterpart of OLD's `admin.php?op=W2ACD…`
 * page; every mutation is an action class (authorized, transactional,
 * audited). After a success the admin lands where OLD sent them, with the
 * message in the admin-only public notice. OLD's absolute
 * `https://way2allah.com/…` redirects become site-relative.
 */
class CdGroupAdminController
{
    /** G1 «اضافة صورة للقسم» — OLD GroupIcon() + groupicon.htm. */
    public function icon(W2acdGroup $cdGroup): View
    {
        return view('admin.cds.groups.icon', [
            'group' => $cdGroup,
            'currentIcon' => CdGroupIcon::current((int) $cdGroup->id, (int) $cdGroup->icon),
            'maxKb' => intdiv(CdGroupIconFiles::MAX_BYTES, 1000),
        ]);
    }

    /** OLD UploadIcon() then sent the admin to the PARENT group's page (`Group(0)` — cds-main — for a root group). */
    public function uploadIcon(Request $request, W2acdGroup $cdGroup, CdGroupIconFiles $files): RedirectResponse
    {
        $request->validate([
            'file2upl' => ['required', 'file', 'extensions:gif,jpg,jpeg,png', 'mimetypes:image/gif,image/jpeg,image/png',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value instanceof UploadedFile && $value->getSize() > CdGroupIconFiles::MAX_BYTES) {
                        $fail('حجم الملف أكبر من الحد الأقصى المسموح به');
                    }
                }],
        ], [
            'file2upl.required' => 'لم يتم إختيار ملف للتحميل',
            'file2upl.file' => 'لم يتم إختيار ملف للتحميل',
            'file2upl.uploaded' => 'حجم الملف أكبر من الحد الأقصى المسموح به',
            'file2upl.extensions' => 'هذا الإمتداد غير مسموح به',
            'file2upl.mimetypes' => 'هذا الإمتداد غير مسموح به',
        ]);

        $file = $request->file('file2upl');
        abort_unless($file instanceof UploadedFile, 422);

        try {
            $files->upload($this->actor(), $cdGroup, $file, $request->ip());
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['file2upl' => 'هذا الإمتداد غير مسموح به']);
        }

        return redirect()->to($this->parentPage((int) $cdGroup->parent_id))
            ->with(ContextualReturn::FLASH, 'جزاك الله خيرا على إضافة الأيقونة إلى الموقع.');
    }

    /** G2 «تعديل القسم» — OLD EditGroup(). */
    public function edit(W2acdGroup $cdGroup): View
    {
        return view('admin.cds.groups.edit', [
            'group' => $cdGroup,
            'parents' => CdGroupParents::options($cdGroup),
        ]);
    }

    /** OLD ConfirmEditGroup() returned to this group's page. */
    public function update(Request $request, W2acdGroup $cdGroup, UpdateCdGroupAction $action): RedirectResponse
    {
        /** @var array{title: string, parent_id: int|string, Des?: string|null} $data */
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'parent_id' => ['required', 'integer', 'min:0'],
            'Des' => ['nullable', 'string', 'max:65535'],
        ], [
            'title.required' => 'من فضلك أدخل عنوان القسم',
            'title.max' => 'عنوان القسم أطول من الحد المسموح به (100 حرف)',
        ]);

        $action->execute($this->actor(), $cdGroup, $data, $request->ip());

        return $this->toGroup((int) $cdGroup->id, 'تم تعديل القسم');
    }

    /** G4 «إضافة قسم فرعي» — OLD AddGroup(id) + addgroup.htm. */
    public function createSubGroup(W2acdGroup $cdGroup): View
    {
        return view('admin.cds.groups.subgroup-create', ['group' => $cdGroup]);
    }

    /** OLD ConfirmAddGroup() sent the admin to the parent (this) group's page. */
    public function storeSubGroup(Request $request, W2acdGroup $cdGroup, InsertCdSubGroupAction $action): RedirectResponse
    {
        $data = $this->validatedGroup($request);

        $action->execute($this->actor(), $cdGroup, $data, $request->ip());

        return $this->toGroup((int) $cdGroup->id, "تمت إضافة القسم الفرعي '' {$data['Title']} ''");
    }

    /** M4 «إضافة قسم فرعي» on cds-main — OLD AddGroup(0) + addgroup.htm: a top-level group. */
    public function createRootGroup(): View
    {
        return view('admin.cds.groups.subgroup-create', ['group' => null]);
    }

    /** OLD ConfirmAddGroup(…, 0, …) sent the admin to `cds-group-0.htm`, i.e. OLD Group(0) — cds-main. */
    public function storeRootGroup(Request $request, InsertCdSubGroupAction $action): RedirectResponse
    {
        $data = $this->validatedGroup($request);

        $action->executeRoot($this->actor(), $data, $request->ip());

        return redirect()->to('/cds-main.htm')
            ->with(ContextualReturn::FLASH, "تمت إضافة القسم '' {$data['Title']} ''");
    }

    /** G5 «حذف القسم» — OLD DeleteGroup()'s confirmation page (delgroup.htm). */
    public function confirmDestroy(W2acdGroup $cdGroup): View
    {
        return view('admin.cds.groups.delete', ['group' => $cdGroup]);
    }

    /** OLD meant to return to the parent group (cds-main for a root group). */
    public function destroy(Request $request, W2acdGroup $cdGroup, DeleteCdGroupAction $action): RedirectResponse
    {
        $title = (string) $cdGroup->title;
        $result = $action->execute($this->actor(), $cdGroup, $request->ip());

        return redirect()->to($this->parentPage($result['parent_id']))
            ->with(ContextualReturn::FLASH, "تم حذف القسم '' {$title} ''");
    }

    /** @return array{Title: string, Des?: string|null} */
    private function validatedGroup(Request $request): array
    {
        /** @var array{Title: string, Des?: string|null} $data */
        $data = $request->validate([
            'Title' => ['required', 'string', 'max:100'],
            'Des' => ['nullable', 'string', 'max:65535'],
        ], [
            'Title.required' => 'من فضلك أدخل عنوان القسم',
            'Title.max' => 'عنوان القسم أطول من الحد المسموح به (100 حرف)',
        ]);

        return $data;
    }

    private function parentPage(int $parentId): string
    {
        return $parentId > 0 && W2acdGroup::query()->whereKey($parentId)->exists()
            ? '/cds-group-'.$parentId.'.htm'
            : '/cds-main.htm';
    }

    private function toGroup(int $groupId, string $message): RedirectResponse
    {
        return redirect()->to('/cds-group-'.$groupId.'.htm')->with(ContextualReturn::FLASH, $message);
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}

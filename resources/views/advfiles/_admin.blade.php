{{-- OLD ShowAdFile(): for an admin, the remaining credit and «تعديل الملف» /
     «مسح الملف» (Phase 5 routes). Rendered only inside @contextualAdmin. --}}
@php($promotionalControls = \App\Domain\Admin\Contextual\Controls\PromotionalFileControls::forItem((int) $file->id, $returnTo ?? null))
@if (app(\App\Domain\Admin\Contextual\ContextualAdmin::class)->visible($promotionalControls) !== [])
    <div class="text-center" dir="rtl" data-contextual-admin-credit><b>مرات العرض المتبقية لهذا الملف = {{ (int) $file->credit }}</b></div>
    <x-admin.contextual-controls :controls="$promotionalControls" :frame-target="$frameTarget ?? null" />
@endif

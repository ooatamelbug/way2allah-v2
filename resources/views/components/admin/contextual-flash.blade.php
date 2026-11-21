{{-- The success message of an operation an admin started from this public
     page (HonorContextualReturn re-keys it). Only that admin's session can
     hold it, so visitors never see anything here. --}}
@php($contextualMessage = session(\App\Domain\Admin\Contextual\ContextualReturn::FLASH))
@if (is_string($contextualMessage) && $contextualMessage !== '')
    <div class="alert alert-success" dir="rtl" role="status" data-contextual-admin-flash><i class="fa fa-check" aria-hidden="true"></i> {{ $contextualMessage }}</div>
@endif
{{-- B2 / F07-A: the admin's Variety save warning (only an admin's own save can flash it). --}}
@php($linkWarning = session(\App\Domain\Content\Support\VarietyMediaLink::ADMIN_WARNING_FLASH))
@if (is_string($linkWarning) && $linkWarning !== '')
    <div class="alert alert-warning" dir="rtl" role="alert" data-admin-link-warning><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> {{ $linkWarning }}</div>
@endif

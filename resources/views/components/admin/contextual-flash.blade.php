{{-- The success message of an operation an admin started from this public
     page (HonorContextualReturn re-keys it). Only that admin's session can
     hold it, so visitors never see anything here. --}}
@php($contextualMessage = session(\App\Domain\Admin\Contextual\ContextualReturn::FLASH))
@if (is_string($contextualMessage) && $contextualMessage !== '')
    <div class="alert alert-success" dir="rtl" role="status" data-contextual-admin-flash><i class="fa fa-check" aria-hidden="true"></i> {{ $contextualMessage }}</div>
@endif

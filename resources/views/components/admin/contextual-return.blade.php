{{-- Carries a validated return_to from a contextual entry into an AdminCP
     form, so a successful submit lands back on the public page
     (HonorContextualReturn). An absent or invalid value renders nothing, and
     the form keeps its normal AdminCP redirect. --}}
@php($contextualReturn = \App\Domain\Admin\Contextual\ContextualReturn::safeTarget(old(\App\Domain\Admin\Contextual\ContextualReturn::PARAM, request()->query(\App\Domain\Admin\Contextual\ContextualReturn::PARAM)), request()))
@if ($contextualReturn !== null)
    <input type="hidden" name="{{ \App\Domain\Admin\Contextual\ContextualReturn::PARAM }}" value="{{ $contextualReturn }}">
@endif

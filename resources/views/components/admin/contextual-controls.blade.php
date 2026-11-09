@props(['controls' => [], 'variant' => 'toolbar', 'title' => 'أدوات الإدارة', 'frameTarget' => null])

{{--
    OLD Admin Migration — contextual administration surface. Renders the
    controls an authenticated admin may use on this public page, and nothing
    at all otherwise. Each control only points at an existing admin route;
    whether it shows is decided by that route's own authorization middleware
    (ContextualAdmin::visible → AdminRouteAccess). Mutations are forms with
    CSRF and method spoofing, never links. Successful operations come back to
    this page through the validated return_to.

    Always wrap a call in @contextualAdmin so visitors skip building controls.
    `frameTarget` ("_top" or "_parent") is for controls rendered inside an iframe.
--}}
@php
    $contextual = app(\App\Domain\Admin\Contextual\ContextualAdmin::class);
    $visible = $contextual->visible($controls);
    $pageReturn = \App\Domain\Admin\Contextual\ContextualReturn::currentPage(request());
    $frameTarget = in_array($frameTarget, ['_top', '_parent'], true) ? $frameTarget : null;
@endphp
@if ($visible !== [])
    @php($contextual->markRendered())
    @once
        <style>
            .w2a-admin-ctx{direction:rtl;text-align:right;display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin:8px 0;padding:6px 10px;border:1px dashed #c0392b;border-radius:6px;background:#fdf6f5;font-size:13px}
            .w2a-admin-ctx--inline{display:inline-flex;margin:4px 0;padding:2px 6px;font-size:12px}
            .w2a-admin-ctx__title{font-weight:700;color:#c0392b;margin-left:6px}
            .w2a-admin-ctx__form{display:inline;margin:0}
            .w2a-admin-ctx__btn{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border:1px solid #ddd;border-radius:4px;background:#fff;color:#333;line-height:1.6;cursor:pointer;text-decoration:none}
            .w2a-admin-ctx__btn:hover{background:#f3f3f3;color:#c0392b;text-decoration:none}
            .w2a-admin-ctx__btn--danger{color:#c0392b}
        </style>
    @endonce
    <div {{ $attributes->merge(['class' => 'w2a-admin-ctx w2a-admin-ctx--'.$variant]) }} dir="rtl" data-contextual-admin>
        @if ($variant === 'toolbar')
            <span class="w2a-admin-ctx__title"><i class="fa fa-cog" aria-hidden="true"></i> {{ $title }}</span>
        @endif
        @foreach ($visible as $control)
            @php($target = $control->returns ? ($control->returnTo ?? $pageReturn) : null)
            @if ($control->isLink())
                <a href="{{ $control->url($target) }}" class="w2a-admin-ctx__btn" title="{{ $control->label }}" @if ($frameTarget !== null) target="{{ $frameTarget }}" @endif><i class="fa {{ $control->icon }}" aria-hidden="true"></i> {{ $control->label }}</a>
            @else
                <form method="post" action="{{ $control->url(null) }}" class="w2a-admin-ctx__form" @if ($frameTarget !== null) target="{{ $frameTarget }}" @endif
                    @if ($control->confirm !== null) onsubmit="return confirm({{ \Illuminate\Support\Js::from($control->confirm) }})" @endif>
                    @csrf
                    @if ($control->spoofedMethod() !== null)
                        @method($control->spoofedMethod())
                    @endif
                    @foreach ($control->fields as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    @if ($target !== null)
                        <input type="hidden" name="{{ \App\Domain\Admin\Contextual\ContextualReturn::PARAM }}" value="{{ $target }}">
                    @endif
                    <button type="submit" class="w2a-admin-ctx__btn{{ $control->method === 'DELETE' || $control->confirm !== null ? ' w2a-admin-ctx__btn--danger' : '' }}" title="{{ $control->label }}"><i class="fa {{ $control->icon }}" aria-hidden="true"></i> {{ $control->label }}</button>
                </form>
            @endif
        @endforeach
    </div>
@endif

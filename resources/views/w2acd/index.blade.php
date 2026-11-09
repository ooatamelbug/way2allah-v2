@extends('layouts.app')

{{-- Premium CD directory; controller data and pagination behavior remain unchanged. --}}
@section('title', 'قسم الاسطوانات الدعوية')

@push('styles')
    <link href="/assets/frontend/pages/css/gallery.css" rel="stylesheet">
@endpush


@push('page-styles')
    <link href="/assets/frontend/layout/css/content-refresh.css" rel="stylesheet" type="text/css">
@endpush

@section('content')
    <x-page-chrome heading="قائمة الإسطوانات الدعوية" :breadcrumb="[['title' => 'الاسطوانات الدعوية', 'url' => '']]" />
@if (request()->routeIs('w2acd.index.pretty'))
@contextualAdmin
    {{-- W2ACD public admin parity, Phase 3 — OLD Group(0) on cds-main: «لوحة تحكم قسم الاسطوانات» with M4 only. --}}
    <x-admin.contextual-controls title="لوحة تحكم قسم الاسطوانات" :controls="\App\Domain\Admin\Contextual\Controls\CdGroupControls::mainPanel()" />
@endcontextualAdmin
@endif

    <div class="w2a-refresh-page w2a-cds-page">
        {{-- OLD Group(0) → ListGroup(0) (W2ACD visitor parity W3, 2026-11-05): the top-level groups, above the
             general CD list; no panel when there are none. --}}
        @if ($subGroups->isNotEmpty())
            @include('w2acd._subgroup-cards')
        @endif

        <x-content.premium-panel title="قائمة الإسطوانات العامة" icon="fa-dot-circle-o"
            description="مكتبة من الإسطوانات الدعوية المختارة، مرتبة لتصل إلى محتواها بسهولة.">
            @include('w2acd._cd-grid')
            <div class="w2a-refresh-pagination" aria-label="صفحات الإسطوانات">
                {{ $items->onEachSide(1)->links('components.content.premium-pagination') }}
            </div>
        </x-content.premium-panel>
    </div>
@endsection

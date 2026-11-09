@extends('layouts.app')

{{-- W2ACD public admin parity, Phase 2 — OLD Group() (modules/w2acd/index.php): `_Group` «مجموعة» + the group's title. --}}
@section('title', 'مجموعة ' . $groupModel->title)

@push('styles')
    <link href="/assets/frontend/pages/css/gallery.css" rel="stylesheet">
@endpush

@push('page-styles')
    <link href="/assets/frontend/layout/css/content-refresh.css" rel="stylesheet" type="text/css">
@endpush

@section('content')
    {{-- OLD Logo() + listblock.htm: «الاسطوانات الدعوية» → each ancestor (linked) → this group. --}}
    <x-page-chrome :heading="$groupModel->title" :breadcrumb="$breadcrumb" />
@contextualAdmin
    {{-- OLD Group(): «لوحة تحكم قسم الاسطوانات» (group_cp_for_cd.htm), right after the breadcrumb. Admins only. --}}
    <x-admin.contextual-controls title="لوحة تحكم قسم الاسطوانات" :controls="\App\Domain\Admin\Contextual\Controls\CdGroupControls::panel($groupModel)" />
@endcontextualAdmin

    <div class="w2a-refresh-page w2a-cds-page">
        @if ($subGroups->isNotEmpty())
            @include('w2acd._subgroup-cards')
        @endif

        @if ($items->total() > 0)
            {{-- OLD ListW2ACD() + w2acd_head.htm / w2acd_cont.htm, 24 a page. --}}
            <x-content.premium-panel :title="'قائمة الإسطوانات بقسم : ' . $groupModel->title" icon="fa-dot-circle-o">
                @include('w2acd._cd-grid')
                <div class="w2a-refresh-pagination" aria-label="صفحات الإسطوانات">
                    {{ $items->onEachSide(1)->links('components.content.premium-pagination') }}
                </div>
            </x-content.premium-panel>
        @endif
    </div>
@endsection

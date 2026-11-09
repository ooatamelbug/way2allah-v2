@extends('layouts.app')

@section('title', $heading)

{{-- OLD Author() / Group() / Series(): ListGroup, ListSeries and ListKhotab,
     selected and counted by the Phase 5 `advfile` counters. Each item links to
     its normal page, which carries the «الملف الدعائي» button. --}}
@section('content')
    <x-page-chrome :heading="$heading" :breadcrumb="$breadcrumb" />

    @if ($groups->isNotEmpty())
        <x-content.premium-panel title="قائمة المجموعات" icon="fa-folder">
            <table class="table table-striped table-hover">
                <thead><tr><th>م</th><th>اسم المجموعة</th><th>الملفات</th></tr></thead>
                <tbody>
                    @foreach ($groups as $group)
                        <tr><td>{{ $loop->iteration }}</td><td width="100%"><a href="/khotab-advfiles-group-{{ $group->id }}.htm"><b>مجموعة {{ $group->title }}</b></a></td><td>{{ (int) $group->advfile }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr><th>الكل</th><td colspan="2">{{ $groups->count() }} مجموعة</td></tr></tfoot>
            </table>
        </x-content.premium-panel>
    @endif

    @if ($series->isNotEmpty())
        <x-content.premium-panel title="قائمة السلاسل" icon="fa-list-ol">
            <table class="table table-striped table-hover">
                <thead><tr><th>م</th><th>اسم السلسلة</th><th>الملفات</th></tr></thead>
                <tbody>
                    @foreach ($series as $one)
                        <tr><td>{{ $loop->iteration }}</td><td width="100%"><a href="/khotab-advfiles-series-{{ $one->id }}.htm"><b>{{ $one->title }}</b></a></td><td>{{ (int) $one->advfile }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr><th>الكل</th><td colspan="2">{{ $series->count() }} سلسلة</td></tr></tfoot>
            </table>
        </x-content.premium-panel>
    @endif

    @if ($items->isNotEmpty())
        <x-content.premium-panel title="قائمة المواد" icon="fa-file-audio-o">
            <table class="table table-striped table-hover">
                <thead><tr><th>م</th><th>الاسم</th></tr></thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td width="100%">
                                <a href="/khotab-item-{{ $item->id }}.htm"><b>{{ $item->title }}</b></a>
                                {{-- Contextual administration (OLD admin_khotab_cont.htm: delete / edit / view beside each item). --}}
                                @contextualAdmin
                                    @php($rowControls = \App\Domain\Admin\Contextual\Controls\PromotionalFileControls::forItem((int) $item->id))
                                    @if (app(\App\Domain\Admin\Contextual\ContextualAdmin::class)->visible($rowControls) !== [])
                                        <x-admin.contextual-controls variant="inline" :controls="$rowControls" />
                                        <a href="/khotab-advfile-{{ $item->id }}.htm" class="various" data-fancybox-type="iframe" title="مشاهدة الملف الدعائي للمادة"><i class="fa fa-eye" aria-hidden="true"></i></a>
                                    @endif
                                @endcontextualAdmin
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr><th>الكل</th><td>{{ $items->count() }}</td></tr></tfoot>
            </table>
        </x-content.premium-panel>
    @endif

    @if ($groups->isEmpty() && $series->isEmpty() && $items->isEmpty())
        <p>لا توجد ملفات دعائية.</p>
    @endif

    @include('advfiles._popup-script')
@endsection

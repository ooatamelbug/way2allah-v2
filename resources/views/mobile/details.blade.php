@extends('layouts.app')

@section('title', 'الجوال الدعوي - '.$material->title)

@section('content')
    <x-page-chrome :heading="$material->title" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '/mobiles.htm'], ['title' => (string) $path, 'url' => '/mobiles-cat'.$material->cid.'.htm'], ['title' => $material->title, 'url' => '']]" />
    <div class="container-fluid">
        @include('mobile._flash')
        @include('mobile._menu')
        <x-content.premium-panel title="معلومات المادة" icon="fa-info-circle">
            @if (trim($material->description) !== '')
                <div style="white-space:pre-line;margin-bottom:10px">{{ \App\Domain\Content\Services\Mobile\MobileSite::plainText($material->description) }}</div>
            @endif
            <table class="table table-condensed">
                <tr><th>التصنيف</th><td>{{ $path }}</td></tr>
                <tr><th>تاريخ الإضافة</th><td>{{ $material->date }}</td></tr>
                <tr><th>عدد مرات التحميل</th><td>{{ (int) $material->hits }}</td></tr>
                @if ((int) $material->filesize > 0)<tr><th>حجم الملف</th><td>{{ number_format((int) $material->filesize) }} بايت</td></tr>@endif
                @if ($material->version !== '')<tr><th>الإصدار</th><td>{{ $material->version }}</td></tr>@endif
                @if ($material->name !== '')<tr><th>صاحب المادة</th><td>{{ $material->name }}</td></tr>@endif
                @if ($material->homepage !== '' && $material->homepage !== 'http://')<tr><th>صفحته</th><td dir="ltr">{{ $material->homepage }}</td></tr>@endif
                <tr><th>التقييم</th><td>
                    @if ((int) $material->totalvotes > 0) {{ (int) $material->downloadratingsummary }} من 10 ({{ (int) $material->totalvotes }} صوت) @else لم يتم تقييمها بعد @endif
                </td></tr>
                @if ($registered['count'] + $anonymous['count'] > 0)
                    <tr><th>تفاصيل التقييم</th><td>
                        أعضاء مسجلين: {{ $registered['count'] }}{{ $registered['average'] !== null ? ' (متوسط '.$registered['average'].')' : '' }}
                        · زوار: {{ $anonymous['count'] }}{{ $anonymous['average'] !== null ? ' (متوسط '.$anonymous['average'].')' : '' }}
                    </td></tr>
                @endif
            </table>
            <p>
                @if ($available)
                    <a href="/mobile-file-{{ $material->lid }}.htm" class="btn btn-primary">تحميل المادة الآن!</a>
                @else
                    <span class="label label-warning">الملف غير متوفر حالياً</span>
                @endif
            </p>
            <p>
                [ <a href="/ratemobile-{{ $material->lid }}.htm">قيّم المادة</a>
                | <a href="/mobileview-comments-{{ $material->lid }}.htm">تعليقات ({{ (int) $material->totalcomments }})</a>
                @if ($hasEditorial) | <a href="/mobileview-editorial-{{ $material->lid }}.htm">رأي المحررين</a> @endif
                | <a href="/mobile-mod-{{ $material->lid }}.htm">طلب تعديل مادة</a>
                | <a href="/mobile-broken-{{ $material->lid }}.htm">أخبرنا عن وصلة لا تعمل</a> ]
            </p>
        </x-content.premium-panel>
    </div>
@endsection

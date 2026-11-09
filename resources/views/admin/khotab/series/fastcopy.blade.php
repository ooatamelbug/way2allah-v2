@extends('layouts.admin')

@section('title', 'النسخ السريع للتصنيف')

@section('content')
    {{-- OLD cat_fastcopy() from a series page + cat_fastcopy_{head,cont,foot}.htm: rows = the series' items, columns = the series' categories. --}}
    <x-admin-portlet title="النسخ السريع للتصنيف" color="green" icon="fa fa-sitemap">
        @include('admin.mirrors._errors')
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($items->isNotEmpty())
            @if ($columns === [])
                <br>التصنيف السريع غير متاح .... قم بتصنيف أحد المواد تحت هذه المجموعة او السلسلة<br>
            @else
                <style>
                    .w2a-fastcopy p.css-vertical-text{writing-mode:tb-rl;-moz-transform:rotate(270deg);-o-transform:rotate(270deg);white-space:nowrap;display:block;width:20px;top:0}
                    .w2a-fastcopy tr{height:40px}
                </style>
                <center>
                    <font class="title"><b>النسخ السريع للتصنيف</b></font><br>لاحظ ان التصنيفات المتاحة للإختيار محددة تبعاً للمجموعة أو السلسلة الرئيسية<br>
                    لإضافة تصنيف غير متاح يجب أولا أن يتم إضافته إلى أحد المواد الموجودة بالقائمة التالية
                    @if ($series->group !== null)
                        <p><b><a href="/khotab-group-{{ $series->group->id }}.htm">{{ $series->group->title }}</a></b></p>
                    @endif
                    <p><b><a href="/khotab-series-{{ $series->id }}.htm">{{ $series->title }}</a></b></p>
                </center>
                <br>
                <form method="post" action="{{ route('admin.khotab.series.fastcopy.save', $series) }}" class="w2a-fastcopy">
                    @csrf
                    <center><button type="submit" class="btn green">حفظ التصنيف</button></center>
                    <table border="1" cellspacing="1" style="border-collapse: collapse" width="100%">
                        <tr height="100">
                            <td><b>م</b></td>
                            <td height="100" width="100%"><p dir="rtl"><b>&nbsp;عنوان المادة</b></p></td>
                            @foreach ($columns as $catId => $catTitle)
                                <td style="overflow:hidden;" height="300"><p class="css-vertical-text">{{ $catTitle }}</p></td>
                            @endforeach
                        </tr>
                        @foreach ($items as $item)
                            @php($itemCats = explode('|', (string) $item->cat))
                            <tr>
                                <td><b>{{ $loop->iteration }}</b></td>
                                <td width="100%"><p dir="rtl"><a href="/khotab-item-{{ $item->id }}.htm"><b>&nbsp;{{ $item->title }}</b></a></p></td>
                                @foreach ($columns as $catId => $catTitle)
                                    @php($on = in_array((string) $catId, $itemCats, true))
                                    <td title="{{ $catTitle }}" @unless ($on) bgcolor="#FFA4A6" @endunless>
                                        <center><input type="checkbox" name="Khotab[{{ $item->id }}][{{ $catId }}]" value="{{ $catId }}" @checked($on)></center>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>
                    <br><center><button type="submit" class="btn green">حفظ التصنيف</button></center>
                </form>
            @endif
        @endif
        <p><a href="/khotab-series-{{ $series->id }}.htm" class="btn default">رجوع</a></p>
    </x-admin-portlet>
@endsection

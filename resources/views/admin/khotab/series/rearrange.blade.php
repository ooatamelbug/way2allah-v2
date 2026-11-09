@extends('layouts.admin')

@section('title', 'إعادة ترتيب المواد')

@section('content')
    {{-- OLD ReArrange() from a series page + admin_khotab_rearrange_{head,cont,foot}.htm: numbered from N down to 1, digits only. --}}
    <x-admin-portlet title="إعادة ترتيب المواد" color="green" icon="fa fa-sort-numeric-desc">
        @if ($items->isNotEmpty())
            <center><font class="title"><b>قائمة الخطب و الدروس</b></font><br>لاحظ أنه يتم ترتيب المقاطع من الأكبر إلى الأصغر من حيث الرقم<br></center>
            <br>
            <form method="post" action="{{ route('admin.khotab.series.rearrange.save', $series) }}">
                @csrf
                <table border="1" cellpadding="3" cellspacing="5" style="border-collapse: collapse" width="100%">
                    @foreach ($items as $item)
                        <tr>
                            <td width="40">
                                <input type="text" name="Khotab[{{ $item->id }}]" size="3" dir="ltr" value="{{ $items->count() - $loop->index }}" maxlength="3"
                                       style="font-weight: bold;text-align:center" onkeypress="return /\d/.test(String.fromCharCode(event.which || event.keyCode)) || (event.which || event.keyCode) < 32">
                            </td>
                            <td width="100%"><p dir="rtl"><a href="/khotab-item-{{ $item->id }}.htm"><b>&nbsp;{{ $item->title }}</b></a></p></td>
                        </tr>
                    @endforeach
                </table>
                <br><center><button type="submit" class="btn green">أعد الترتيب</button></center>
            </form>
        @endif
        <p><a href="/khotab-series-{{ $series->id }}.htm" class="btn default">رجوع</a></p>
    </x-admin-portlet>
@endsection

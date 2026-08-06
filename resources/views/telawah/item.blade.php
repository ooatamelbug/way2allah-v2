@extends('layouts.app')

@section('title', $telawahItem->title)

@section('content')
    <div class="row service-box margin-bottom-40 sh-w2a-block">
        <div class="col-xs-12 col-sm-12 col-md-9 telawah-item-content nopadding">
            <section aria-label="تفاصيل المادة">
                <table>
                    <tr><th>عنوان المادة</th><td>{{ $telawahItem->title }}</td></tr>
                    <tr><th>تاريخ التحميل</th><td>{{ $telawahItem->mytime ? date('Y-m-d', $telawahItem->mytime) : '' }}</td></tr>
                    <tr><th>عدد الزيارات</th><td>{{ $telawahItem->hits }} زيارة</td></tr>
                    <tr><th>عدد مرات الحفظ</th><td>{{ $telawahItem->downcount }} مرة</td></tr>
                </table>
                <a href="/recite-download-{{ $telawahItem->id }}.htm">حفظ المادة</a>
            </section>
        </div>

        <aside class="col-xs-12 col-sm-12 col-md-3 telawah-item-sidebar nopadding" aria-label="الشريط الجانبي">
            <h3>الأكثر تحميلا</h3>
            <ul>
                @foreach ($mostDownloaded as $item)
                    <li><a href="/recite-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>

            <h3>احدث المواد</h3>
            <ul>
                @foreach ($mostRecent as $item)
                    <li><a href="/recite-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection

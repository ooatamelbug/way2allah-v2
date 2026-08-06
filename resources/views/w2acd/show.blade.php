@extends('layouts.app')

@section('title', $w2acdItem->title)

@section('content')
    <div class="row service-box margin-bottom-40 sh-w2a-block">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <section aria-label="تفاصيل الاسطوانة">
                <table>
                    <tr><th>عنوان الاسطوانة</th><td>{{ $w2acdItem->title }}</td></tr>
                    <tr><th>تاريخ التحميل</th><td>{{ $w2acdItem->mytime ? date('Y-m-d', $w2acdItem->mytime) : '' }}</td></tr>
                    <tr><th>عدد الزيارات</th><td>{{ $w2acdItem->hits }} زيارة</td></tr>
                </table>

                @if($w2acdItem->hidden == 0)
                    <div class="cd-images">
                        @foreach ($w2acdItem->thumbnailFilenames() as $filename)
                            <img src="/images/cds_image2/{{ $filename }}" alt="{{ $w2acdItem->title }}">
                        @endforeach
                    </div>
                @endif
            </section>

            <section aria-label="روابط الاسطوانة">
                <h3>روابط الاسطوانة</h3>
                <table>
                    <tbody>
                    @foreach ($w2acdItem->mirrorLinks() as $index => $mirror)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><a href="{{ $mirror['link'] }}" target="_blank">{{ $mirror['title'] }}</a></td>
                            <td>{{ $mirror['extension'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            <h3>الأكثر تحميلا</h3>
            <ul>
                @foreach ($mostDownloaded as $item)
                    <li><a href="/cds-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>

            <h3>احدث المواد</h3>
            <ul>
                @foreach ($mostRecent as $item)
                    <li><a href="/cds-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection

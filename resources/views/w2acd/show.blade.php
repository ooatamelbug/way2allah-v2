@extends('layouts.app')

@section('title', $w2acdItem->title)

{{--
    G-04 (Migration Gap Register): `w2acd/functions.php:85-131`'s
    `w2acd_details()` image gallery (`thumbnails.php?h=400&w=400&zc=0&
    q=100&src=...`, first image shown larger/distinct from the rest —
    all gated behind `hidden==0`, preserved exactly, not touched) and
    `functions.php:132-180`'s `list_w2acd_mirrors()` (extension icon /
    "سيرفر خاص" classification + the save column, `functions.php:151`).
    Sidebar: same raw (non-thumbnails.php) thumbnail + subtext as
    `w2acd/index.blade.php` — see that file's own docblock.
--}}
@section('content')
    <link href="/assets/frontend/pages/css/gallery.css" rel="stylesheet">
    <div class="row service-box margin-bottom-40 sh-w2a-block">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <section aria-label="تفاصيل الاسطوانة">
                <table>
                    <tr><th>عنوان الاسطوانة</th><td>{{ $w2acdItem->title }}</td></tr>
                    <tr><th>تاريخ التحميل</th><td>{{ $w2acdItem->mytime ? date('Y-m-d', $w2acdItem->mytime) : '' }}</td></tr>
                    <tr><th>عدد الزيارات</th><td>{{ $w2acdItem->hits }} زيارة</td></tr>
                </table>

                @if($w2acdItem->hidden == 0)
                    <div class="row text-center jumbotron-icon">
                        @foreach ($w2acdItem->thumbnailFilenames() as $filename)
                            @php
                                $imgUrl = '/thumbnails.php?h=400&w=400&zc=0&q=100&src=/images/cds_image2/'.$filename;
                            @endphp
                            @if ($loop->first)
                                <div class="cd_first_img">
                                    <img src="{{ $imgUrl }}" height="350" width="400" title="{{ $w2acdItem->title }}" alt="{{ $w2acdItem->title }}">
                                </div>
                            @else
                                <div class="cd_first_img">
                                    <img src="{{ $imgUrl }}" height="400" width="400">
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </section>

            <section aria-label="روابط الاسطوانة">
                <h3>روابط الاسطوانة</h3>
                <table>
                    <thead>
                    <tr>
                        <th width="10%">م</th>
                        <th>الوصف</th>
                        <th>الإمتداد</th>
                        <th>حفظ</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($w2acdItem->mirrorLinks() as $index => $mirror)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><a href="{{ $mirror['link'] }}" target="_blank">{{ $mirror['title'] }}</a></td>
                            <td>
                                @if ($mirror['isPrivateServer'])
                                    سيرفر خاص
                                @else
                                    <img src="/images/ext/{{ $mirror['extension'] }}.gif" alt="نوع الملف {{ $mirror['extension'] }}" border="0">
                                @endif
                            </td>
                            <td>
                                @if ($mirror['extension'] === '')
                                    <img height="20" width="20" src="/images/2.png" title="حفظ" alt="حفظ">
                                @else
                                    <a href="{{ $mirror['link'] }}" target="_blank"><img height="20" width="20" src="/images/save.png" title="حفظ" alt="حفظ"></a>
                                @endif
                            </td>
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
                    @php
                        $sideThumb = $item->firstThumbnailFilename()
                            ? '/images/cds_image2/'.$item->firstThumbnailFilename()
                            : '/images/tvnoise.gif';
                    @endphp
                    <li>
                        <a href="/cds-item-{{ $item->id }}.htm">
                            <img src="{{ $sideThumb }}" alt="{{ $item->title }}">
                            <span>{{ $item->title }}</span>
                            <small>مرات التحميل : {{ $item->hits }} مرة</small>
                        </a>
                    </li>
                @endforeach
            </ul>

            <h3>احدث المواد</h3>
            <ul>
                @foreach ($mostRecent as $item)
                    @php
                        $sideThumb = $item->firstThumbnailFilename()
                            ? '/images/cds_image2/'.$item->firstThumbnailFilename()
                            : '/images/tvnoise.gif';
                    @endphp
                    <li>
                        <a href="/cds-item-{{ $item->id }}.htm">
                            <img src="{{ $sideThumb }}" alt="{{ $item->title }}">
                            <span>{{ $item->title }}</span>
                            {{-- mytime -> plain date(), NOT a CoolShortDate() port, matching this project's established convention. --}}
                            <small>بتاريخ : {{ $item->mytime ? date('Y-m-d', $item->mytime) : '' }}</small>
                        </a>
                    </li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection

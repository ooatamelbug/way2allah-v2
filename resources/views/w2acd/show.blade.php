@extends('layouts.app')

@section('title', $w2acdItem->title)

@push('styles')
    <link href="/assets/frontend/pages/css/gallery.css" rel="stylesheet">
@endpush

@section('content')
    <x-page-chrome :heading="$w2acdItem->title" :breadcrumb="[
        ['title' => 'الاسطوانات الدعوية', 'url' => '/cds-main.htm'],
        ['title' => $w2acdItem->title, 'url' => ''],
    ]" />

    <div class="row service-box margin-bottom-40 sh-w2a-block">
        <div class="col-lg-8 col-md-8 col-sm-12">
            <x-content.premium-panel title="تفاصيل الاسطوانة" icon="fa-desktop">
                <div class="anasheed-details mada-details">
                    <div class="w2a-table-responsive-wrapper">
                        <table class="table table-striped w2a-cd-detail-table">
                            <tr>
                                <th class="w20" style="border-top:0;">عنوان الاسطوانة </th>
                                <td style="border-top:0;">{{ $w2acdItem->title }}</td>
                            </tr>
                            <tr>
                                <th class="w20">تاريخ التحميل</th>
                                <td>{{ $w2acdItem->mytime ? \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $w2acdItem->mytime) : '' }}
                                </td>
                            </tr>
                            <tr>
                                <th class="w20">عدد الزيارات</th>
                                <td>{{ number_format((int) $w2acdItem->hits) }} زيارة</td>
                            </tr>
                        </table>
                    </div>
                    @if ($w2acdItem->hidden == 0 && count($w2acdItem->thumbnailFilenames()) > 0)
                        <div class="text-center w2a-cd-gallery-wrap" style="margin-top: 24px;">
                            @foreach ($w2acdItem->thumbnailFilenames() as $filename)
                                @php
                                    $imgUrl = \App\Domain\Content\Support\MediaUrl::thumbnail(
                                        'h=400&w=400&zc=0&q=100&src=/images/cds_image2/' . $filename,
                                    );
                                @endphp
                                @if ($loop->first)
                                    <div class="cd_first_img col-sm-6 col-xs-12" style="margin-bottom: 16px;">
                                        <div class="w2a-cd-showcase-card">
                                            <img src="{{ $imgUrl }}" height="350" width="400"
                                                title="{{ $w2acdItem->title }}" alt="{{ $w2acdItem->title }}"
                                                class="img-responsive" />
                                            <span class="w2a-cd-img-caption"><i class="fa fa-picture-o"></i> غلاف
                                                الإسطوانة</span>
                                        </div>
                                    </div>
                                @else
                                    <div class="cd_first_img col-sm-6 col-xs-12" style="margin-bottom: 16px;">
                                        <div class="w2a-cd-showcase-card">
                                            <img src="{{ $imgUrl }}" height="400" width="400"
                                                class="img-responsive" alt="{{ $w2acdItem->title }}" />
                                            <span class="w2a-cd-img-caption"><i class="fa fa-dot-circle-o"></i> قرص
                                                الإسطوانة</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-content.premium-panel>

            <div style="margin-top: 24px;">
                <x-content.premium-panel title="روابط الاسطوانة" icon="fa-link">
                    <div class="anasheed-mirrors table-responsive">
                        <div class="w2a-cd-mirrors-list">
                            @foreach ($w2acdItem->mirrorLinks() as $index => $mirror)
                                @php
                                    $isPrivate = $mirror['isPrivateServer'];
                                    $ext = strtolower($mirror['extension']);
                                @endphp
                                <div class="w2a-cd-mirror-item">
                                    <div class="w2a-cd-mirror-item__meta">
                                        <span class="w2a-cd-mirror-item__num">{{ $index + 1 }}</span>
                                        <div
                                            class="w2a-cd-mirror-item__type-icon {{ $isPrivate ? 'w2a-cd-mirror-item__type-icon--server' : '' }}">
                                            @if ($isPrivate)
                                                <i class="fa fa-server" aria-hidden="true"></i>
                                            @elseif ($ext === 'iso')
                                                <i class="fa fa-dot-circle-o" aria-hidden="true"></i>
                                            @elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz']))
                                                <i class="fa fa-file-archive-o" aria-hidden="true"></i>
                                            @else
                                                <i class="fa fa-cloud-download" aria-hidden="true"></i>
                                            @endif
                                        </div>
                                        <div class="w2a-cd-mirror-item__details">
                                            <div class="w2a-cd-mirror-item__title-row">
                                                <h4 class="w2a-cd-mirror-item__title">
                                                    @if ($mirror['link'])
                                                        <a href="{{ $mirror['link'] }}" target="_blank"
                                                            rel="noopener noreferrer">
                                                            {{ $mirror['title'] }}
                                                        </a>
                                                    @else
                                                        <span>{{ $mirror['title'] }}</span>
                                                    @endif
                                                </h4>
                                                @if ($isPrivate)
                                                    <span
                                                        class="w2a-cd-mirror-item__badge w2a-cd-mirror-item__badge--server">
                                                        <i class="fa fa-shield" aria-hidden="true"></i> سيرفر خاص
                                                    </span>
                                                @else
                                                    <span class="w2a-cd-mirror-item__badge w2a-cd-mirror-item__badge--ext">
                                                        {{ strtoupper($mirror['extension'] ?: 'رابط مباشر') }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="w2a-cd-mirror-item__desc">
                                                @if ($isPrivate)
                                                    رابط تحميل بديل ومناقشة موضوع الإسطوانة في المنتدى
                                                @elseif ($ext === 'iso')
                                                    ملف صورة الإسطوانة (ISO) جاهز للحرق أو الاستعراض المباشر
                                                @elseif ($mirror['extension'])
                                                    ملف إسطوانة بصيغة {{ strtoupper($mirror['extension']) }} للتحميل
                                                    المباشر
                                                @else
                                                    رابط إضافي لتحميل محتويات هذه الإسطوانة الدعوية
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    <div class="w2a-cd-mirror-item__actions">
                                        @if ($mirror['link'])
                                            <a href="{{ $mirror['link'] }}" target="_blank" rel="noopener noreferrer"
                                                class="w2a-cd-mirror-btn {{ $isPrivate ? 'w2a-cd-mirror-btn--secondary' : 'w2a-cd-mirror-btn--primary' }}">
                                                <i class="fa {{ $isPrivate ? 'fa-external-link' : 'fa-cloud-download' }}"
                                                    aria-hidden="true"></i>
                                                <span>{{ $isPrivate ? 'زيارة الرابط' : 'تحميل الإسطوانة' }}</span>
                                            </a>
                                        @else
                                            <span class="w2a-cd-mirror-btn w2a-cd-mirror-btn--disabled">
                                                <i class="fa fa-ban" aria-hidden="true"></i>
                                                <span>غير متوفر</span>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-content.premium-panel>
            </div>
        </div>

        <aside class="col-lg-4 col-md-4 col-sm-12" aria-label="الشريط الجانبي">
            @if ($mostDownloaded->isNotEmpty())
                <div style="margin-bottom: 24px;">
                    <x-content.sidebar-ranking title="الأكثر تحميلا" icon="fa-cloud-download" :items="$mostDownloaded"
                        meta="downloads" type="cds" />
                </div>
            @endif

            @if ($mostRecent->isNotEmpty())
                <div>
                    <x-content.sidebar-ranking title="احدث المواد" icon="fa-flash" :items="$mostRecent" meta="recent"
                        type="cds" />
                </div>
            @endif
        </aside>
    </div>
@endsection

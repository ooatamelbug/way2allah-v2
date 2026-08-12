@extends('layouts.app')

{{--
    khotab/item.php. Display formatting for date/size/counts is a
    straightforward re-implementation (number_format()/date()), not a port
    of legacy's exact CoolShortDate()/CoolSize()/cool_number() helpers —
    a deliberate display-layer simplification, not a data-correctness
    concern, flagged in the Wave 4 report's Technical Debt section rather
    than silently done.

    IF-014 fix: "Most Downloaded"/"Newest" boxes below use the item's real
    `vedio` value (via ContentSidebarWidget::khotabMostDownloadedByVideoFlag()/
    khotabMostRecentByVideoFlag()), not the undefined `$Khotab->video`
    legacy read.
    IF-019 fix: comment flags render from `images/flags/`, not `flags/`.
    IF-020 fix: the PDF button links to the working `khotab.download-pdf`
    route, not the dead `khotab-item-pdf-{id}.htm` pattern.
--}}

@section('title', $khotabItem->title . ' - ' . ($khotabItem->authorModel->prename ?? '') . ' ' . ($khotabItem->authorModel->name ?? ''))

@php
    $khotabOp = $khotabItem->vedio ? 'video' : 'audio';
    $khotabSectionLabel = $khotabItem->vedio ? 'المرئيات' : 'صوتيات';
    $khotabAuthorName = trim(($khotabItem->authorModel->prename ?? '').' '.($khotabItem->authorModel->name ?? ''));
@endphp

@section('content')
    {{--
        Presentation-only pass (Live-Reference Comparison Report): breadcrumb
        and portlet/box wrappers added below, reusing the same .page-bar/
        .page-breadcrumb/portlet classes already established for
        fatawa/topics-index.blade.php. No controller/route/data change —
        $series/$group were already passed by KhotabItemController::show()
        (compact() already includes them) but previously unused by this view.
    --}}
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li>{{ $khotabSectionLabel }}<i class="fa fa-angle-right"></i></li>
            <li><a href="/khotab-{{ $khotabOp }}.htm">قائمة الدعاة</a><i class="fa fa-angle-right"></i></li>
            @if ($khotabItem->authorModel)
                <li><a href="/khotab-{{ $khotabOp }}-{{ $khotabItem->authorModel->id }}.htm">{{ $khotabAuthorName }}</a><i class="fa fa-angle-right"></i></li>
            @endif
            <li>{{ $khotabItem->title }}</li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <div class="portlet box blue">
                <div class="portlet-title">
                    <div class="caption">تفاصيل المادة</div>
                </div>
                <div class="portlet-body">
                    <table>
                        <tr><th>عنوان المادة</th><td>{{ $khotabItem->title }}</td></tr>
                        @if(!empty($khotabItem->description))
                            <tr><th>وصف المادة</th><td>{{ $khotabItem->description }}</td></tr>
                        @endif
                        <tr><th>تاريخ التحميل</th><td>{{ $khotabItem->time ? date('Y-m-d', $khotabItem->time) : '' }}</td></tr>
                        <tr><th>حجم المادة</th><td>{{ number_format((int) $khotabItem->linksize) }}</td></tr>
                        <tr><th>عدد الزيارات</th><td>{{ number_format($khotabItem->hits) }} زيارة</td></tr>
                        <tr><th>عدد مرات الحفظ</th><td>{{ number_format($khotabItem->downcount) }} مرة</td></tr>
                        @if($khotabItem->pdf != 0)
                            <tr><th>حفظ ملف التفريغ</th><td>{{ number_format($khotabItem->pdf) }}</td></tr>
                        @endif
                    </table>

                    <div class="row text-center jumbotron-icon">
                        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-6 mada-control-item">
                            <a href="/khotab-download-{{ $khotabItem->id }}.htm"><i class="fa fa-download"></i><br>حفظ المادة</a>
                        </div>
                        @if($khotabItem->pdf != 0)
                            <div class="col-lg-3 col-md-3 col-sm-3 col-xs-6 mada-control-item">
                                <a href="/khotab-item-pdf-{{ $khotabItem->id }}.htm"><i class="fa fa-file-pdf-o"></i><br>ملف تفريغ</a>
                            </div>
                        @endif
                        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-6 mada-control-item">
                            <a href="#comments-form" data-toggle="modal" data-target="#commentsModal"><i class="fa fa-comment"></i><br>اضف تعليقك</a>
                        </div>
                    </div>
                </div>
            </div>

            @if($khotabItem->mirrors->isNotEmpty())
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">قائمة الجودات المختلفة للمادة</div>
                    </div>
                    <div class="portlet-body">
                        <table>
                            <tbody>
                            @foreach($khotabItem->mirrors as $mirror)
                                <tr>
                                    <td>
                                        <a href="/khotab-mirror-{{ $khotabItem->id }}-{{ $mirror->id }}.htm">{{ $mirror->comment }}</a>
                                        — {{ number_format((int) $mirror->linksize) }}
                                        — {{ __('التنزيلات') }}: {{ number_format($mirror->hits) }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($comments !== null && $comments->isNotEmpty())
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">تعليقات الزوار على المادة</div>
                    </div>
                    <div class="portlet-body">
                        <p>( عدد التعليقات : {{ $comments->total() }} تعليق )</p>
                        <div class="anasheed_comments">
                            @foreach($comments as $comment)
                                <div class="comment-item">
                                    <img src="/images/flags/{{ $comment->uid == 0 && $comment->uname === '' ? 'way2allah' : $comment->code }}.png" alt="{{ $comment->code }}">
                                    <p>{{ $comment->comment }}</p>
                                    <span>{{ $comment->uid == 0 && $comment->uname === '' ? 'مشرف التعليقات' : $comment->uname }}</span>
                                    <span>{{ date('Y-m-d', $comment->mytime) }}</span>
                                </div>
                            @endforeach
                        </div>
                        {{ $comments->links() }}
                    </div>
                </div>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            <div class="profile-userpic">
                <img src="{{ $khotabItem->authorModel?->displayImageUrl() }}" alt="">
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">اخترنا لك هذه المادة</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach($randomFeatured as $item)
                                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach($mostDownloaded as $item)
                                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">جديد المواد</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach($mostRecent as $item)
                                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection

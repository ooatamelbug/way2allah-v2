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

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <section aria-label="تفاصيل المادة">
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

                <nav aria-label="إجراءات المادة">
                    <a href="/khotab-download-{{ $khotabItem->id }}.htm">حفظ المادة</a>
                    @if($khotabItem->pdf != 0)
                        <a href="/khotab-item-pdf-{{ $khotabItem->id }}.htm">ملف تفريغ</a>
                    @endif
                    <a href="#comments-form" data-toggle="modal" data-target="#commentsModal">اضف تعليقك</a>
                </nav>
            </section>

            @if($khotabItem->mirrors->isNotEmpty())
                <section aria-label="قائمة الجودات المختلفة للمادة">
                    <h3>قائمة الجودات المختلفة للمادة</h3>
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
                </section>
            @endif

            @if($comments !== null && $comments->isNotEmpty())
                <section aria-label="تعليقات الزوار على المادة">
                    <h3>تعليقات الزوار على المادة</h3>
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
                </section>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            <div class="profile-userpic">
                <img src="{{ $khotabItem->authorModel?->displayImageUrl() }}" alt="">
            </div>

            <h3>اخترنا لك هذه المادة</h3>
            <ul>
                @foreach($randomFeatured as $item)
                    <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>

            <h3>الأكثر تحميلا</h3>
            <ul>
                @foreach($mostDownloaded as $item)
                    <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>

            <h3>جديد المواد</h3>
            <ul>
                @foreach($mostRecent as $item)
                    <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection

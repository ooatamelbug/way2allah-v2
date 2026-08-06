@extends('layouts.app')

{{-- anasheed/item.php. IF-028 fix: comment flags render from images/flags/, not flags/. --}}

@section('title', $anasheedItem->title)

@section('content')
    <div class="row service-box margin-bottom-40 sh-w2a-block">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <section aria-label="تفاصيل المادة">
                <table>
                    <tr><th>عنوان المادة</th><td>{{ $anasheedItem->title }}</td></tr>
                    @if(!empty($anasheedItem->description))
                        <tr><th>وصف المادة</th><td>{{ $anasheedItem->description }}</td></tr>
                    @endif
                    <tr><th>تاريخ التحميل</th><td>{{ $anasheedItem->mytime ? date('Y-m-d', $anasheedItem->mytime) : '' }}</td></tr>
                    <tr><th>عدد الزيارات</th><td>{{ $anasheedItem->hits }} زيارة</td></tr>
                    <tr><th>عدد مرات الحفظ</th><td>{{ $anasheedItem->downcount }} مرة</td></tr>
                </table>

                <nav aria-label="إجراءات المادة">
                    <a href="/var-download-{{ $anasheedItem->id }}.htm">حفظ المادة</a>
                    <a href="#" data-toggle="modal" data-target="#commentsModal">اضف تعليقك</a>
                </nav>
            </section>

            @if($anasheedItem->mirror && $anasheedItem->mirrors->isNotEmpty())
                <section aria-label="قائمة الجودات المختلفة للمادة">
                    <h3>قائمة الجودات المختلفة للمادة</h3>
                    <table>
                        <tbody>
                        @foreach($anasheedItem->mirrors as $mirror)
                            <tr>
                                <td>
                                    <a href="/var-mirror-{{ $anasheedItem->id }}-{{ $mirror->id }}.htm">{{ $mirror->title }}</a>
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
            <h3>الأكثر تحميلا</h3>
            <ul>
                @foreach ($mostDownloaded as $item)
                    <li><a href="/var-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>

            <h3>احدث المواد</h3>
            <ul>
                @foreach ($mostRecent as $item)
                    <li><a href="/var-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection

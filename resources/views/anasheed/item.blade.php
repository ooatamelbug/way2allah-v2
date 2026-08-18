@extends('layouts.app')

{{-- anasheed/item.php. IF-028 fix: comment flags render from images/flags/, not flags/. --}}

@section('title', $anasheedItem->title)

@section('content')
    {{--
        Live-Reference Comparison Report: breadcrumb + portlet wrappers,
        same convention as fatawa/khotab-item/channel. `group` is an
        already-existing model relation (AnasheedItem::group(), belongsTo
        AnasheedGroup) called lazily here — no controller/query change.
    --}}
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li>منوعات<i class="fa fa-angle-right"></i></li>
            @if ($anasheedItem->group)
                <li><a href="/var-group-{{ $anasheedItem->group->id }}.htm">{{ $anasheedItem->group->title }}</a><i class="fa fa-angle-right"></i></li>
            @endif
            <li>{{ $anasheedItem->title }}</li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40 sh-w2a-block">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <div class="portlet box blue">
                <div class="portlet-title">
                    <div class="caption">تفاصيل المادة</div>
                </div>
                <div class="portlet-body">
                    <table>
                        <tr><th>عنوان المادة</th><td>{{ $anasheedItem->title }}</td></tr>
                        @if(!empty($anasheedItem->description))
                            <tr><th>وصف المادة</th><td>{{ $anasheedItem->description }}</td></tr>
                        @endif
                        <tr><th>تاريخ التحميل</th><td>{{ $anasheedItem->mytime ? date('Y-m-d', $anasheedItem->mytime) : '' }}</td></tr>
                        <tr><th>عدد الزيارات</th><td>{{ $anasheedItem->hits }} زيارة</td></tr>
                        <tr><th>عدد مرات الحفظ</th><td>{{ $anasheedItem->downcount }} مرة</td></tr>
                    </table>

                    <div class="row text-center jumbotron-icon">
                        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-6 mada-control-item">
                            <a href="/var-download-{{ $anasheedItem->id }}.htm"><i class="fa fa-download"></i><br>حفظ المادة</a>
                        </div>
                        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-6 mada-control-item">
                            <a href="#" data-toggle="modal" data-target="#commentsModal"><i class="fa fa-comment"></i><br>اضف تعليقك</a>
                        </div>
                    </div>
                </div>
            </div>

            @if($anasheedItem->mirror && $anasheedItem->mirrors->isNotEmpty())
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">قائمة الجودات المختلفة للمادة</div>
                    </div>
                    <div class="portlet-body">
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
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach ($mostDownloaded as $item)
                                <li class="media">
                                    <a class="pull-left" href="/var-item-{{ $item->id }}.htm"><img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 72px; height: 50px;"></a>
                                    <div class="media-body"><a href="/var-item-{{ $item->id }}.htm">{{ $item->title }}</a></div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">احدث المواد</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach ($mostRecent as $item)
                                <li class="media">
                                    <a class="pull-left" href="/var-item-{{ $item->id }}.htm"><img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 72px; height: 50px;"></a>
                                    <div class="media-body"><a href="/var-item-{{ $item->id }}.htm">{{ $item->title }}</a></div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection

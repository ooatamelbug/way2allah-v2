@extends('layouts.app')

{{-- anasheed/item.php. IF-028 fix: comment flags render from images/flags/, not flags/. --}}

{{--
    var-item-17350.htm parity: item.php:6,31-34's $title (`""` then `.=
    ' - '.$Group->title` then `.= ' - '.$Anasheed->title`) feeds BOTH the
    <title> tag and the <h3> heading. $header['title'] = $title.' -
    '.$sitename, and w2a_header()'s own template (header.php:26) appends
    ' - '.$sitename AGAIN — a confirmed, genuine double-suffix verified
    against live production (not a fetch artifact). Reproduced exactly:
    the layout's own single "@yield('title') - {{ config('app.name') }}"
    append becomes the second occurrence once $pageTitle already carries
    the first.
--}}
@section('title', $pageTitle.' - '.config('app.name'))

{{--
    var-item-{id}.htm parity: item.php:5 register_css('css/custom.css')
    (unconditional, same mechanism as var-group-{id}.htm, resolves to
    https://way2allah.com/css/custom.css). item.php:63-64 register_js(
    'scripts/w2a_play.js',2)/register_js('scripts/anasheed_scripts.js',2)
    — $plugin=2 means new_functions.php's register_js() pushes the path
    RAW, unprefixed by $siteurl (same un-prefixed-but-root-page-relative
    shape already established for register_script() elsewhere in this
    project). Both scripts confirmed genuinely LOADED and ACTUALLY_EFFECTIVE
    (not dead): w2a_play.js makes a real AJAX POST to get-mada-player.htm
    (Laravel: /media-player) and injects the response into #w2a_main_player;
    anasheed_scripts.js wires the real comment/send-friend modal forms to
    their own real backend endpoints (already implemented — storeComment()/
    sendToFriend() below — this batch only restores the front-end markup/
    wiring those endpoints were always missing).
--}}
@push('styles')
    <link href="/css/custom.css" rel="stylesheet" type="text/css"/>
@endpush

@push('scripts')
    <script src="/scripts/w2a_play.js" type="text/javascript"></script>
    <script src="/scripts/anasheed_scripts.js" type="text/javascript"></script>
@endpush

@section('content')
    {{--
        var-item-17350.htm parity: item.php:60's title($title) (functions.php:541-543)
        was entirely missing — restored, h3 BEFORE the breadcrumb (item.php:60-62's
        real call order: title() then breadcrumb()). functions.php:541-543's own
        malformed <i class=\fa fa-gift\"> icon is deliberately NOT reproduced —
        same already-established SOURCE_UNRECOVERABLE/dropped decision already
        applied to this identical sitewide title() function elsewhere in this
        project, not re-decided here.
    --}}
    <h3 class="page-title">{{ $pageTitle }}</h3>

    {{--
        item.php:47-58's real breadcrumb: home -> full ancestor chain (walking
        parent_id) -> immediate group -> item title. No "منوعات" label exists
        anywhere in legacy — confirmed absent from both source and live
        production; the previous version here invented it. $breadcrumbTrail
        (AnasheedGroup::breadcrumbTrail(), same shape as Category::breadcrumbTrail())
        already returns [...ancestors, immediate group] in the correct order.
    --}}
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            @foreach ($breadcrumbTrail as $ancestor)
                <li><a href="/var-group-{{ $ancestor->id }}.htm">{{ $ancestor->title }}</a><i class="fa fa-angle-right"></i></li>
            @endforeach
            <li>{{ $anasheedItem->title }}</li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40 sh-w2a-block">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <div class="portlet box blue">
                <div class="portlet-title">
                    <div class="caption"><i class="fa fa-video-camera"></i> تفاصيل المادة</div>
                </div>
                <div class="portlet-body">
                    {{--
                        var-item-17350.htm parity: anasheed_details() wraps
                        this table in <div class="anasheed-details mada-details">
                        — css/custom.css:450,749-758 has real, applicable rules
                        for .mada-details td/th (15px padding) and
                        .anasheed-details.mada-details .col-md-3 (a compound
                        selector requiring BOTH classes together) — previously
                        missing entirely. Date row uses CoolShortDate()
                        (functions.php:391, confirmed via source), not
                        tinydate()'s plain Y-m-d — reused via the same
                        LegacyShortDateFormatter already built for
                        khotab-video-today.htm's "جديد المواد" box.
                    --}}
                    <div class="anasheed-details mada-details">
                    <table class="table table-striped">
                        <tr><th>عنوان المادة</th><td>{{ $anasheedItem->title }}</td></tr>
                        @if(!empty($anasheedItem->description))
                            <tr><th>وصف المادة</th><td>{{ $anasheedItem->description }}</td></tr>
                        @endif
                        <tr><th>تاريخ التحميل</th><td>{{ $anasheedItem->mytime ? \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $anasheedItem->mytime) : '' }}</td></tr>
                        <tr><th>حجم المادة</th><td>{{ \App\Domain\Content\Support\LegacyFileSizeFormatter::format((int) ($anasheedItem->linksize ?? 0)) }}</td></tr>
                        <tr><th>عدد الزيارات</th><td>{{ $anasheedItem->hits }} زيارة</td></tr>
                        <tr><th>عدد مرات الحفظ</th><td>{{ $anasheedItem->downcount }} مرة</td></tr>
                    </table>
                    </div>

                    {{--
                        item.php:85's w2a_player_html() + anasheed_details()'s 4 real
                        action buttons (functions.php:407-446) — watch (onclick=
                        w2a_play(id,'anasheed'), the previously-missing button),
                        download (already present), comment-modal trigger (already
                        present), send-friend-modal trigger (previously missing).
                    --}}
                    <div class="row text-center jumbotron-icon">
                        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-6 mada-control-item">
                            <a onclick="w2a_play({{ $anasheedItem->id }},'anasheed')">
                                <div class="badge blue">
                                    <div class="circle">
                                        <i class="fa fa-youtube-play fa-4 text-blue"></i>
                                    </div>
                                </div>
                                <h5>مشاهدة المادة</h5>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-6 mada-control-item">
                            <a href="/var-download-{{ $anasheedItem->id }}.htm" target="_blank">
                                <div class="badge blue">
                                    <div class="circle">
                                        <i class="fa fa-floppy-o fa-4 text-blue"></i>
                                    </div>
                                </div>
                                <h5>حفظ المادة</h5>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-6 mada-control-item">
                            <a data-toggle="modal" data-target="#commentsModal" href="javascript:;" class="send-comment-btn">
                                <div class="badge blue">
                                    <div class="circle">
                                        <i class="fa fa-commenting fa-4 text-blue"></i>
                                    </div>
                                </div>
                                <h5>اضف تعليقك</h5>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-6 mada-control-item">
                            <a data-toggle="modal" data-target="#sendFriendModal" href="javascript:;" class="send-friend-btn">
                                <div class="badge blue">
                                    <div class="circle">
                                        <i class="fa fa-envelope fa-4 text-blue"></i>
                                    </div>
                                </div>
                                <h5>أرسل لصديق</h5>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- item.php:85's w2a_player_html() (functions.php:780-793) — the empty player container w2a_play()'s AJAX response is injected into. --}}
            <div class="col-md-12 col-sm-12" id="the_main_player">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <span class="clickable" data-effect="fadeOut"><i class="fa fa-times"></i></span>
                    </div>
                    <div class="panel-body" id="w2a_main_player"></div>
                </div>
            </div>

            {{-- item.php:86 post_comment_modal() (functions.php:504-536) — the modal itself, previously entirely absent. --}}
            <div class="modal fade" id="commentsModal" tabindex="-1" role="dialog" aria-labelledby="commentsModalLabel">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title" id="commentsModalLabel">اضافة تعليق على : {{ $anasheedItem->title }}</h4>
                        </div>
                        <div class="modal-body" id="modal-comment-body">
                            <form name="comments_form" id="comments_form" action="" method="post">
                                <input type="hidden" name="anasheed_id" id="anasheed_id" value="{{ $anasheedItem->id }}">
                                <div class="form-group">
                                    <label for="user_nickname">اسمك المستعار</label>
                                    <input name="user_nickname" id="user_nickname" class="form-control" placeholder="اسمك المستعار">
                                    <div class="modal-error alert alert-danger" id="user_nickname_error">يجب عليك ادخال اسمك المستعار</div>
                                </div>
                                <div class="form-group">
                                    <label for="user_comment">نص التعليق</label>
                                    <textarea name="user_comment" id="user_comment" cols="30" rows="10" class="form-control" placeholder="نص التعليق"></textarea>
                                    <div class="modal-error alert alert-danger" id="user_comment_error">يجب عليك ادخال تعليقك</div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="send_comment" data-loading-text="جارى الارسال ......" class="btn btn-primary">ارسل التعليق</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal">اغلاق</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- item.php:87 send_friend_modal() (functions.php:599-646) — the modal itself, previously entirely absent. --}}
            <div class="modal fade" id="sendFriendModal" tabindex="-1" role="dialog" aria-labelledby="sendFriendModalLabel">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title" id="sendFriendModalLabel">ارسل مادة : {{ $anasheedItem->title }}</h4>
                        </div>
                        <div class="modal-body" id="modal-sendFriend-body">
                            <form name="sendFriend_form" id="sendFriend_form" action="" method="post">
                                <div class="alert alert-info">
                                    <p><strong>أرسل هذه المادة لصديق </strong> {{ $anasheedItem->title }}</p>
                                    @if ($anasheedItem->group)
                                        <p><strong>قسم :</strong> {{ $anasheedItem->group->title }}</p>
                                    @endif
                                </div>
                                <input type="hidden" name="anasheed_id" id="anasheed_id" value="{{ $anasheedItem->id }}">
                                <div class="form-group">
                                    <label for="your_name">اسمك </label>
                                    <input type="text" name="your_name" id="your_name" class="form-control" placeholder="اسمك">
                                    <div class="modal-error alert alert-danger" id="your_name_error">يجب عليك ادخال اسمك</div>
                                </div>
                                <div class="form-group">
                                    <label for="your_email">بريدك الالكتروني </label>
                                    <input type="email" name="your_email" id="your_email" class="form-control" placeholder="بريدك الالكتروني">
                                    <div class="modal-error alert alert-danger" id="your_email_error">يجب عليك ادخال بريدك االكتروني بصيغة صحيحة</div>
                                </div>
                                <div class="form-group">
                                    <label for="friend_name">اسم صديقك </label>
                                    <input type="text" name="friend_name" id="friend_name" class="form-control" placeholder="اسم صديقك">
                                    <div class="modal-error alert alert-danger" id="friend_name_error">يجب عليك ادخال اسم صديقك</div>
                                </div>
                                <div class="form-group">
                                    <label for="friend_email">بريد صديقك </label>
                                    <input type="email" name="friend_email" id="friend_email" class="form-control" placeholder="بريد صديقك">
                                    <div class="modal-error alert alert-danger" id="friend_email_error"> يجب عليك ادخال بريد صديقك بصيغة صحيحة </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="send_friend" data-loading-text="جارى الارسال ......" class="btn btn-primary">ارسل لصديقك</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal">اغلاق</button>
                        </div>
                    </div>
                </div>
            </div>

            {{--
                item.php:88 list_anasheed_mirrors() (functions.php:679-787) — real
                numbered rows with a play button (w2a_play(mirror.id,'anasheed_mirror'),
                confirmed a genuinely different type from the main item's 'anasheed'),
                extension icon, file size, download count. Previously a bare
                title+download-count line.
            --}}
            @if($anasheedItem->mirror && $anasheedItem->mirrors->isNotEmpty())
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-clone"></i> قائمة الجودات المختلفة للمادة</div>
                    </div>
                    <div class="portlet-body series-overflow item">
                        <table class="table table-striped table-hover" id="tabelgrp">
                            <tbody>
                                @foreach($anasheedItem->mirrors as $index => $mirror)
                                    <tr><td class="">
                                        <div class="row"><div class="col-lg-12">
                                            <h5>
                                                {{ $index + 1 }} - <a href="/var-mirror-{{ $anasheedItem->id }}-{{ $mirror->id }}.htm">{{ $mirror->title }}</a>
                                            </h5>
                                            <div class="row page-header color_00a">
                                                <div class="col-lg-3 col-xs-6 text-blue">
                                                    <span>
                                                        {{ $mirror->isAudioLike() ? 'إستماع' : 'مشاهدة' }}:
                                                        <a title="{{ $mirror->isAudioLike() ? 'سماع الوصلة' : 'مشاهدة الوصلة' }}" onclick="w2a_play({{ $mirror->id }},'anasheed_mirror')">
                                                            <i class="fa {{ $mirror->isAudioLike() ? 'fa-headphones' : 'fa-youtube-play' }} fa-2"></i>
                                                        </a>
                                                    </span>
                                                </div>
                                                <div class="col-lg-3 col-xs-6 text-blue">
                                                    <span>
                                                        الإمتداد:
                                                        <a href="javascript:void(0)"><img src="/images/ext/{{ $mirror->extensionIconFilename() }}" alt="نوع الملف {{ $mirror->extensionIconFilename() }}" border="0"></a>
                                                    </span>
                                                </div>
                                                <div class="col-lg-3 col-xs-6 text-blue">
                                                    <span><i class="fa fa-file-archive-o"></i> {{ \App\Domain\Content\Support\LegacyFileSizeFormatter::format((int) ($mirror->linksize ?? 0)) }}</span>
                                                </div>
                                                <div class="col-lg-3 col-xs-6 text-blue">
                                                    <span><i class="fa fa-download"></i> التنزيلات: {{ number_format($mirror->hits) }}</span>
                                                </div>
                                            </div>
                                        </div></div>
                                    </td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($comments !== null && $comments->isNotEmpty())
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-comments"></i> تعليقات الزوار على المادة</div>
                    </div>
                    <div class="portlet-body">
                        <p>( عدد التعليقات : {{ $comments->total() }} تعليق )</p>
                        <div class="anasheed_comments">
                            @foreach($comments as $comment)
                                <div class="comment-item">
                                    <img src="/images/flags/{{ $comment->uid == 0 && $comment->uname === '' ? 'way2allah' : $comment->code }}.png" alt="{{ $comment->code }}">
                                    <p>{{ $comment->comment }}</p>
                                    <span>{{ $comment->uid == 0 && $comment->uname === '' ? 'مشرف التعليقات' : $comment->uname }}</span>
                                    {{-- var-item-17350.htm parity: list_anasheed_comments() (functions.php:815) uses CoolShortDate(), not tinydate()'s plain Y-m-d. --}}
                                    <span>{{ \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $comment->mytime) }}</span>
                                </div>
                            @endforeach
                        </div>
                        {{ $comments->links() }}
                    </div>
                </div>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            {{--
                var-item-17350.htm parity: most_recent_html() (functions.php:882-910,
                the shared builder for BOTH sidebar boxes below) — real markup
                is `<ul class='recent_list'><li class='list-group-item
                anasheed-latest-item'>`, not the `<ul class="news"><li
                class="media">` shape previously used here (copied from a
                different, unrelated legacy convention without re-checking
                this module's own function — confirmed by direct re-read).
                Metadata line was entirely missing: "مرات التحميل : X مرة"
                (downcount) for the downloaded box, "بتاريخ : {date}"
                (CoolShortDate(mytime), reusing LegacyShortDateFormatter)
                for the newest box — a real, source-proven distinction, not
                interchangeable.
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-cloud-download"></i> الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="recent_list">
                            @foreach ($mostDownloaded as $item)
                                <li class="list-group-item anasheed-latest-item">
                                    <div class="row">
                                        <div class="col-lg-5 col-md-4 col-sm-3 col-xs-4">
                                            {{-- functions.php:895's literal `"> <img` — a real space inside the inline <a>, not incidental whitespace; confirmed against live production, kept exactly. --}}
                                            <a href="/var-item-{{ $item->id }}.htm"> <img src="{{ $item->thumb }}" class="img-responsive img-thumbnail" alt="{{ $item->title }}"></a>
                                        </div>
                                        <div class="col-lg-7 col-md-8 col-sm-9 col-xs-8" style="padding: 0;">
                                            {{-- functions.php:898's literal `"> <h5` — same real, confirmed space. --}}
                                            <a href="/var-item-{{ $item->id }}.htm"> <h5>{{ $item->title }}</h5></a>
                                            <small>مرات التحميل : {{ $item->downcount }} مرة</small>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-flash"></i> احدث المواد</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="recent_list">
                            @foreach ($mostRecent as $item)
                                <li class="list-group-item anasheed-latest-item">
                                    <div class="row">
                                        <div class="col-lg-5 col-md-4 col-sm-3 col-xs-4">
                                            {{-- functions.php:895's literal `"> <img` — a real space inside the inline <a>, not incidental whitespace; confirmed against live production, kept exactly. --}}
                                            <a href="/var-item-{{ $item->id }}.htm"> <img src="{{ $item->thumb }}" class="img-responsive img-thumbnail" alt="{{ $item->title }}"></a>
                                        </div>
                                        <div class="col-lg-7 col-md-8 col-sm-9 col-xs-8" style="padding: 0;">
                                            {{-- functions.php:898's literal `"> <h5` — same real, confirmed space. --}}
                                            <a href="/var-item-{{ $item->id }}.htm"> <h5>{{ $item->title }}</h5></a>
                                            <small>بتاريخ : {{ \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $item->mytime) }}</small>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection

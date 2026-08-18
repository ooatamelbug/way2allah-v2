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
    // item.php:92,96 — 'الصوتيات' (with the definite article), not 'صوتيات'.
    $khotabSectionLabel = $khotabItem->vedio ? 'المرئيات' : 'الصوتيات';
    $khotabAuthorName = trim(($khotabItem->authorModel->prename ?? '').' '.($khotabItem->authorModel->name ?? ''));
@endphp

@section('content')
    {{--
        Visual parity audit (khotab-item-298784.htm, 2026-08-18): page-title,
        breadcrumb link/final-segment markup, group/series breadcrumb
        segments, and the category-tree breadcrumb extension restored from
        item.php (source read in full). $series/$group were already passed
        by KhotabItemController::show() but previously unused by this view.
        The malformed `<i class=\fa fa-gift\"></i>` icon `title()`
        (functions.php:541-543) emits before the `<h3>` is a legacy
        authoring bug (stray `\f` escape) — not reproduced, same standing
        rule already applied on khotab/authors.blade.php.
    --}}
    <h3 class="page-title">{{ $khotabItem->title }} - {{ $khotabAuthorName }}</h3>
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li><a href="/khotab-{{ $khotabOp }}.htm">{{ $khotabSectionLabel }}</a><i class="fa fa-angle-right"></i></li>
            <li><a href="/khotab-{{ $khotabOp }}.htm">قائمة الدعاة</a><i class="fa fa-angle-right"></i></li>
            @if ($khotabItem->authorModel)
                <li><a href="/khotab-{{ $khotabOp }}-{{ $khotabItem->authorModel->id }}.htm">{{ $khotabAuthorName }}</a><i class="fa fa-angle-right"></i></li>
            @endif
            @if ($group)
                <li><a href="/khotab-group-{{ $group->id }}.htm">مجموعة {{ $group->title }}</a><i class="fa fa-angle-right"></i></li>
            @endif
            @if ($series)
                <li><a href="/khotab-series-{{ $series->id }}.htm">سلسلة {{ $series->title }}</a><i class="fa fa-angle-right"></i></li>
            @endif
            <li><a href=""> {{ $khotabItem->title }}</a><i class=""></i></li>
        </ul>
        @foreach ($categoryChains as $chain)
            @foreach ($chain as $category)<span><img src="/images/arrowbullet.png" alt="" />&nbsp;<b><a href="/category-{{ $category->id }}.htm">{{ $category->title }}</a></b>&nbsp;</span>@if (!$loop->last)&nbsp;&nbsp;&nbsp;@endif @endforeach
        @endforeach
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <div class="portlet box blue">
                <div class="portlet-title">
                    <div class="caption"><i class="fa fa-video-camera"></i> تفاصيل المادة</div>
                </div>
                <div class="portlet-body">
                    <table class="table table-striped">
                        <tr><th class="w20" style="border-top:0;">عنوان المادة</th><td style="border-top:0;">{{ $khotabItem->title }}</td></tr>
                        @if(!empty($khotabItem->description))
                            <tr><th class="w20" style="border-top:0;">وصف المادة</th><td style="border-top:0;">{{ $khotabItem->description }}</td></tr>
                        @endif
                        <tr><th class="w20">تاريخ التحميل</th><td>{{ $khotabItem->time ? date('Y-m-d', $khotabItem->time) : '' }}</td></tr>
                        <tr><th class="w20">حجم المادة</th><td>{{ number_format((int) $khotabItem->linksize) }}</td></tr>
                        <tr><th class="w20">عدد الزيارات</th><td>{{ number_format($khotabItem->hits) }} زيارة</td></tr>
                        <tr><th class="w20">عدد مرات الحفظ</th><td>{{ number_format($khotabItem->downcount) }} مرة</td></tr>
                        @if($khotabItem->pdf != 0)
                            <tr><th class="w20">حفظ ملف التفريغ</th><td>{{ number_format($khotabItem->pdf) }}</td></tr>
                        @endif
                    </table>

                    {{--
                        Visual parity audit (khotab-item-298784.htm, 2026-08-18)
                        Batch 2 / Finding #9: item.php:189-238's exact
                        .badge.blue > .circle > <i> + <h5> button structure,
                        replacing the flat <i><br>text markup. Icons: download
                        fa-floppy-o (was fa-download), comment fa-commenting
                        (was fa-comment) — item.php:203,222 respectively.
                        Comment button's href/class restored to legacy's
                        literal `href="javascript:;" class="send-comment-btn"`
                        (item.php:220) — its target #commentsModal is
                        deferred to Batch 3, unchanged here.

                        Play/Watch button (item.php:191-198) markup restored
                        exactly, including the `onclick="w2a_play(...)"`
                        attribute. Batch 4: `w2a_play()` is now implemented
                        (below, @push('scripts')), backed by
                        MediaPlayerController/MediaPlayerService — see
                        #the_main_player above for the player panel this
                        fills in.

                        "Send to friend" button (item.php:228-237,
                        #sendFriendModal) is NOT part of this batch's
                        approved findings (#8/#9/#13) and is intentionally
                        NOT restored here.
                    --}}
                    <div class="row text-center jumbotron-icon">
                        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-6 mada-control-item">
                            <a onclick="w2a_play({{ $khotabItem->id }},'khotab')">
                                <div class="badge blue">
                                    <div class="circle">
                                        <i class="fa {{ $khotabItem->vedio ? 'fa-youtube-play' : 'fa-headphones' }} fa-4 text-blue"></i>
                                    </div>
                                </div>
                                <h5>{{ $khotabItem->vedio ? 'مشاهدة المادة' : 'إستماع المادة' }}</h5>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-6 mada-control-item">
                            <a href="/khotab-download-{{ $khotabItem->id }}.htm" target="_blank">
                                <div class="badge blue">
                                    <div class="circle">
                                        <i class="fa fa-floppy-o fa-4 text-blue"></i>
                                    </div>
                                </div>
                                <h5>حفظ المادة</h5>
                            </a>
                        </div>
                        @if($khotabItem->pdf != 0)
                            <div class="col-lg-3 col-md-3 col-sm-3 col-xs-6 mada-control-item">
                                <a href="/khotab-item-pdf-{{ $khotabItem->id }}.htm" target="_blank">
                                    <div class="badge blue">
                                        <div class="circle">
                                            <i class="fa fa-file-pdf-o fa-4 text-blue"></i>
                                        </div>
                                    </div>
                                    <h5>ملف تفريغ</h5>
                                </a>
                            </div>
                        @endif
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
                        {{--
                            Visual parity audit (khotab-item-298784.htm,
                            2026-08-18) Batch 3 / Finding #11: item.php:
                            228-237's 5th action button, restored now that
                            its target modal exists below (see
                            KhotabFriendMail's docblock for the backend).
                        --}}
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

            {{--
                Visual parity audit (khotab-item-298784.htm, 2026-08-18)
                Batch 4: w2a_player_html() (item.php:244, functions.php:
                780-793) — the panel #w2a_play (below) fills in via AJAX
                against /media-player (MediaPlayerController), replacing
                get-mada-player.htm. Markup restored verbatim.
            --}}
            {{--
                No inline/CSS `display:none` here — confirmed by both live-
                fetching this exact element (no inline style) and grepping
                every stylesheet this layout actually loads: the only
                `#the_main_player{display:none;}` rules live in
                shams_custom.css (only loaded with `?shams=ok`) and the
                page-specific /css/custom.css (Batch 5, not loaded here
                either) — neither reaches this page on live production.
                The panel is genuinely visible-but-empty on page load in
                real legacy today; not "fixed" here.
            --}}
            <div class="col-md-12 col-sm-12" id="the_main_player">
                <div class="panel panel-default">
                    <div class="panel-heading" style="">
                        <span class="clickable" data-effect="fadeOut"><i class="fa fa-times"></i></span>
                    </div>
                    <div class="panel-body" id="w2a_main_player"></div>
                </div>
            </div>

            {{--
                Visual parity audit (khotab-item-298784.htm, 2026-08-18)
                Batch 3: item.php:246,248's post_comment_modal()/
                send_friend_modal() — restored verbatim from
                khotab/functions.php:1060-1092,1155-1199.
            --}}
            <div class="modal fade" id="commentsModal" tabindex="-1" role="dialog" aria-labelledby="commentsModalLabel">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                            <h4 class="modal-title" id="commentsModalLabel">اضافة تعليق على : {{ $khotabItem->title }}</h4>
                        </div>
                        <div class="modal-body" id="modal-comment-body">
                            <form name="comments_form" id="comments_form" action="" method="post">
                                {{-- Not in legacy — Laravel's CSRF protection applies to this route
                                     (no exemption; bootstrap/app.php only exempts backup.php) and legacy
                                     has no equivalent concept. Read by the AJAX submit below. --}}
                                @csrf
                                <input type="hidden" name="khotab_id" id="khotab_id" value="{{ $khotabItem->id }}" />
                                <div class="form-group">
                                    <label for="user_nickname">اسمك المستعار</label>
                                    <input name="user_nickname" id="user_nickname" class="form-control" placeholder="اسمك المستعار" />
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
                            <button type="button" id="send_comment" data-loading-text="جارى الارسال ......" class="btn btn-primary"> ارسل التعليق </button>
                            <button type="button" class="btn btn-default" data-dismiss="modal"> اغلاق </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="sendFriendModal" tabindex="-1" role="dialog" aria-labelledby="sendFriendModalLabel">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                            <h4 class="modal-title" id="sendFriendModalLabel">ارسل مادة : {{ $khotabItem->title }}</h4>
                        </div>
                        <div class="modal-body" id="modal-sendFriend-body">
                            <form name="sendFriend_form" id="sendFriend_form" action="" method="post">
                                @csrf
                                <div class="alert alert-info">
                                    <p><strong>أرسل هذه المادة لصديق </strong> {{ $khotabItem->title }}</p>
                                </div>
                                {{-- id="khotab_id_friend", not legacy's literal "khotab_id" (send_friend_modal():1170)
                                     — that exact id is already used by #commentsModal's own hidden input above;
                                     legacy has a genuine duplicate-id bug here (two elements, same id, same page).
                                     Not reproduced — this field isn't read by name via #id anywhere. --}}
                                <input type="hidden" name="khotab_id" id="khotab_id_friend" value="{{ $khotabItem->id }}" />
                                <div class="form-group">
                                    <label for="your_name">اسمك </label>
                                    <input type="text" name="your_name" id="your_name" class="form-control" placeholder="اسمك" />
                                    <div class="modal-error alert alert-danger" id="your_name_error">يجب عليك ادخال اسمك</div>
                                </div>
                                <div class="form-group">
                                    <label for="your_email">بريدك الالكتروني </label>
                                    <input type="email" name="your_email" id="your_email" class="form-control" placeholder="بريدك الالكتروني" />
                                    <div class="modal-error alert alert-danger" id="your_email_error">يجب عليك ادخال بريدك االكتروني بصيغة صحيحة</div>
                                </div>
                                <div class="form-group">
                                    <label for="friend_name">اسم صديقك </label>
                                    <input type="text" name="friend_name" id="friend_name" class="form-control" placeholder="اسم صديقك" />
                                    <div class="modal-error alert alert-danger" id="friend_name_error">يجب عليك ادخال اسم صديقك</div>
                                </div>
                                <div class="form-group">
                                    <label for="friend_email">بريد صديقك </label>
                                    <input type="email" name="friend_email" id="friend_email" class="form-control" placeholder="بريد صديقك" />
                                    <div class="modal-error alert alert-danger" id="friend_email_error"> يجب عليك ادخال بريد صديقك بصيغة صحيحة </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="send_friend" data-loading-text="جارى الارسال ......" class="btn btn-primary"> ارسل لصديقك </button>
                            <button type="button" class="btn btn-default" data-dismiss="modal"> اغلاق </button>
                        </div>
                    </div>
                </div>
            </div>

            @if($khotabItem->mirrors->isNotEmpty())
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-clone"></i> قائمة الجودات المختلفة للمادة</div>
                    </div>
                    <div class="portlet-body">
                        {{--
                            Visual parity audit (khotab-item-298784.htm,
                            2026-08-18) Batch 2 / Finding #13: item.php:
                            268-358's exact per-mirror row structure —
                            numbered <h5>/quality_title/download-attribute
                            link, and the 4-column .page-header row (play
                            link, extension icon [G-13-13, unchanged], file
                            size, download count). Play link's
                            `onclick="w2a_play(...)"` uses the same shared
                            `w2a_play()` (Batch 4) as the main play button
                            above — `type='khotab_mirror'` is already
                            supported by MediaPlayerService.
                        --}}
                        <table class="table table-striped table-hover" id="tabelgrp">
                            <tbody>
                            @foreach($khotabItem->mirrors as $mirror)
                                <tr>
                                    <td class="">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <h5>
                                                    {{ $loop->iteration }} - <a class="quality_title" href="/khotab-mirror-{{ $khotabItem->id }}-{{ $mirror->id }}.htm" download>{{ $mirror->comment }}</a>
                                                </h5>
                                                <div class="row page-header color_00a">
                                                    <div class="col-lg-3 col-xs-6 text-blue">
                                                        <span>
                                                            {{ $mirror->vedio == 0 ? 'إستماع' : 'مشاهدة' }}:
                                                        <a title="{{ $mirror->vedio == 0 ? 'سماع الوصلة' : 'مشاهدة الوصلة' }}" onclick="w2a_play({{ $mirror->id }},'khotab_mirror')">
        <i class="fa {{ $mirror->vedio == 0 ? 'fa-headphones' : 'fa-youtube-play' }} fa-2"></i></a>
                                                        </span>
                                                    </div>
                                                    <div class="col-lg-3 col-xs-6 text-blue">
                                                        <span>
                                                        الإمتداد:
                                                            {{-- G-13-13 (media/visual parity phase): item.php:274-308's per-mirror
                                                                 "quality" extension icon — see Mirror::extensionIconFilename(). --}}
                                                            <a href="Javascript:void(0)"><img width="32" src="/images/ext/{{ $mirror->extensionIconFilename() }}" alt="نوع الملف {{ $mirror->extensionIconFilename() }}" border="0"></a>
                                                        </span>
                                                    </div>
                                                    <div class="col-lg-3 col-xs-6 text-blue">
                                                        <span>
                                                        <i class="fa fa-file-archive-o"></i>
                                                        {{ number_format((int) $mirror->linksize) }}
                                                        </span>
                                                    </div>
                                                    <div class="col-lg-3 col-xs-6 text-blue">
                                                        <span>
                                                        <i class="fa fa-download"></i>
                                                        التنزيلات:
                                                            {{ number_format($mirror->hits) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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
                        <div class="caption"><i class="fa fa-child"></i> الملف الشخصي</div>
                    </div>
                    <div class="portlet-body">
                        {{-- G-13-03: item.php:446 calls get_author_img() unconditionally —
                             unlike author.php/authors.php, it never checks the author_image
                             DB column first, so this uses fallbackImageUrl(), not
                             displayImageUrl(). --}}
                        <div class="profile-userpic">
                            <img src="{{ $khotabItem->authorModel?->fallbackImageUrl() }}" alt="">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> اخترنا لك هذه المادة</div>
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
                        <div class="caption"><i class="fa fa-child"></i> الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach($mostDownloaded as $item)
                                <li class="media">
                                    <a class="pull-left" href="/khotab-item-{{ $item->id }}.htm">
                                        <img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 60px; height: 40px;">
                                    </a>
                                    <div class="media-body">
                                        <a href="/khotab-item-{{ $item->id }}.htm"><h5 class="media-heading">{{ $item->title }}</h5></a>
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
                        <div class="caption"><i class="fa fa-child"></i> جديد المواد</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach($mostRecent as $item)
                                <li class="media">
                                    <a class="pull-left" href="/khotab-item-{{ $item->id }}.htm">
                                        <img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 60px; height: 40px;">
                                    </a>
                                    <div class="media-body">
                                        <a href="/khotab-item-{{ $item->id }}.htm"><h5 class="media-heading">{{ $item->title }}</h5></a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    {{--
        Visual parity audit (khotab-item-298784.htm, 2026-08-18) Batch 3:
        required frontend behavior for #commentsModal/#sendFriendModal,
        adapted from scripts/anasheed_scripts.js's proven UX pattern
        (button reset on open, inline keyup error-clearing, disable+relabel
        on submit, response-code-driven message). NOT a reuse of that
        script file verbatim: item.php:7 loads that exact file in
        production, but its hardcoded AJAX URLs
        (`add-anasheed-comment-{id}.htm`, `send-friend-anasheed-{id}.htm`)
        are anasheed's own — a confirmed cross-module bug in legacy itself
        (khotab's comment/send-friend buttons submit to the wrong content
        type's endpoint in production). Posts to this app's own
        khotab.item.store-comment / khotab.item.send-friend routes
        instead, matching the "don't reproduce legacy bugs" standard
        already applied elsewhere in this batch. `@push('scripts')` so
        this runs after jquery.min.js, same load-order discipline as the
        homepage's #pics fix.
    --}}
    @push('scripts')
        <script>
            $(document).ready(function () {
                $('.send-comment-btn').click(function () {
                    $('#comments_form').show();
                    $('#modal-comment-body .sending-result').remove();
                    $('#send_comment').show();
                    $('#send_comment').removeAttr('disabled');
                    $('#send_comment').html(' ارسل التعليق ');
                    $('#user_nickname').val('');
                    $('#user_comment').val('');
                });
                $('#send_comment').click(function () {
                    var userNickname = $('#user_nickname').val();
                    var userComment = $('#user_comment').val();
                    if (!userNickname) {
                        $('#user_nickname_error').show();
                        return false;
                    }
                    if (!userComment) {
                        $('#user_comment_error').show();
                        return false;
                    }
                    var msg = '';
                    $('#send_comment').attr('disabled', 'disabled');
                    $('#send_comment').html('جاري الارسال');
                    $.ajax({
                        url: '{{ route('khotab.item.store-comment', $khotabItem->id) }}',
                        method: 'POST',
                        data: {
                            user_nickname: userNickname,
                            user_comment: userComment,
                            _token: $('#comments_form input[name="_token"]').val()
                        },
                        dataType: 'html',
                        success: function (data) {
                            if (data == 1) {
                                msg = '<div class="sending-result alert alert-success">شكرا لك ، تم اضافة التعليق بنجاح وسوف يتم نشره بعد مراجعته من قبل الادارة</div>';
                            } else if (data == 2) {
                                msg = '<div class="sending-result alert alert-danger">عفوا ، يجب عليك ادخال اسمك المستعار</div>';
                            } else if (data == 3) {
                                msg = '<div class="sending-result alert alert-danger">عفوا ، يجب عليك ادخال التعليق</div>';
                            }
                            $('#comments_form').hide();
                            $('#modal-comment-body').append(msg);
                            $('#send_comment').hide();
                        }
                    });
                });
                $('#user_nickname').keyup(function () {
                    $('#user_nickname_error').hide();
                });
                $('#user_comment').keyup(function () {
                    $('#user_comment_error').hide();
                });

                $('.send-friend-btn').click(function () {
                    $('#sendFriend_form').show();
                    $('#modal-sendFriend-body .sending-result').remove();
                    $('#send_friend').show();
                    $('#send_friend').removeAttr('disabled');
                    $('#send_friend').html(' ارسل لصديقك ');
                    $('#your_name').val('');
                    $('#your_email').val('');
                    $('#friend_name').val('');
                    $('#friend_email').val('');
                });
                $('#your_name').keyup(function () {
                    $('#your_name_error').hide();
                });
                $('#your_email').keyup(function () {
                    $('#your_email_error').hide();
                });
                $('#friend_name').keyup(function () {
                    $('#friend_name_error').hide();
                });
                $('#friend_email').keyup(function () {
                    $('#friend_email_error').hide();
                });
                $('#send_friend').click(function () {
                    var yourName = $('#your_name').val();
                    var yourEmail = $('#your_email').val();
                    var friendName = $('#friend_name').val();
                    var friendEmail = $('#friend_email').val();
                    var emailPattern = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                    if (!yourName) {
                        $('#your_name_error').show();
                        return false;
                    }
                    if (!yourEmail || !emailPattern.test(yourEmail)) {
                        $('#your_email_error').show();
                        return false;
                    }
                    if (!friendName) {
                        $('#friend_name_error').show();
                        return false;
                    }
                    if (!friendEmail || !emailPattern.test(friendEmail)) {
                        $('#friend_email_error').show();
                        return false;
                    }
                    var msg = '';
                    $('#send_friend').attr('disabled', 'disabled');
                    $('#send_friend').html('جاري الارسال');
                    $.ajax({
                        url: '{{ route('khotab.item.send-friend', $khotabItem->id) }}',
                        method: 'POST',
                        data: {
                            your_name: yourName,
                            your_email: yourEmail,
                            friend_name: friendName,
                            friend_email: friendEmail,
                            _token: $('#sendFriend_form input[name="_token"]').val()
                        },
                        dataType: 'html',
                        success: function (data) {
                            if (data == 1) {
                                msg = '<div class="sending-result alert alert-success">شكرا لك ، تم ارسال المادة الى صديقك بنجاح</div>';
                            } else if (data == 2) {
                                msg = '<div class="sending-result alert alert-danger">عفوا ، يجب عليك اكمال البيانات المطلوبة</div>';
                            }
                            $('#sendFriend_form').hide();
                            $('#modal-sendFriend-body').append(msg);
                            $('#send_friend').hide();
                        }
                    });
                });
            });
        </script>

        {{--
            Batch 4 (media player, khotab-item-298784.htm investigation):
            replaces scripts/w2a_play.js's w2a_play() + its close-button
            handler, verbatim behavior — POST to /media-player (not
            get-mada-player.htm — a Laravel-native route, same URL
            adaptation already used for the comment/send-friend endpoints
            above), inject the response into #w2a_main_player, fade in
            #the_main_player. Close handler fades out and clears the
            player's HTML (stopping playback by removing the DOM node —
            same mechanism legacy relies on, no explicit media .pause()
            needed). Same @push('scripts') stack, so this loads after
            jquery.min.js too.
        --}}
        <script>
            function w2a_play(id, type) {
                $.ajax({
                    url: '{{ route('media-player.show') }}',
                    method: 'POST',
                    data: {
                        id: id,
                        type: type,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType: 'html',
                    success: function (data) {
                        $('#w2a_main_player').html(data);
                        $('#the_main_player').fadeIn();
                    }
                });
            }
            $(document).ready(function () {
                $('#the_main_player .clickable').click(function () {
                    $('#the_main_player').fadeOut(350, function () {
                        $('#w2a_main_player').html('');
                    });
                });
            });
        </script>
    @endpush
@endsection

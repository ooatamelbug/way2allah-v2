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
                    <x-content.media-details-card
                        :item="$anasheedItem"
                        module="anasheed"
                        :date="$anasheedItem->mytime ? \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $anasheedItem->mytime) : ''"
                        :size="\App\Domain\Content\Support\LegacyFileSizeFormatter::format((int) ($anasheedItem->linksize ?? 0))"
                        :download-url="'/var-download-'.$anasheedItem->id.'.htm'"
                    />
                </div>
            </div>

            {{-- Shared player chrome; w2a_play() injects the selected media response into #w2a_main_player. --}}
            <x-content.media-player-panel />
            <x-content.media-player-script />

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

            {{-- Mirror routes and the distinct anasheed_mirror player type are preserved by the shared quality component. --}}
            @if($anasheedItem->mirror && $anasheedItem->mirrors->isNotEmpty())
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-clone"></i> قائمة الجودات المختلفة للمادة</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.media-quality-list :items="$anasheedItem->mirrors" module="anasheed" :parent-id="$anasheedItem->id" />
                    </div>
                </div>
            @endif

            @if($comments !== null && $comments->isNotEmpty())
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-comments"></i> تعليقات الزوار على المادة</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.visitor-comments :comments="$comments" />
                    </div>
                </div>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            {{-- Compact sidebar cards retain the source distinction between download-count and date metadata. --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-cloud-download"></i> الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.anasheed-sidebar-list :items="$mostDownloaded" meta="downloads" />
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-flash"></i> احدث المواد</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.anasheed-sidebar-list :items="$mostRecent" meta="date" />
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection

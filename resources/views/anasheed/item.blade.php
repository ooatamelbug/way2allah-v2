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
@section('title', $pageTitle . ' - ' . config('app.name'))

{{-- Retain the page's legacy styles and engagement forms. Playback uses the
     shared media-player component below, without the competing legacy script. --}}
@push('styles')
    <link href="/css/custom.css" rel="stylesheet" type="text/css" />
@endpush

@push('scripts')
    <script src="/scripts/anasheed_scripts.js?v=20261108" type="text/javascript"></script>
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
                <li><a href="/var-group-{{ $ancestor->id }}.htm">{{ $ancestor->title }}</a><i class="fa fa-angle-right"></i>
                </li>
            @endforeach
            <li>{{ $anasheedItem->title }}</li>
        </ul>
    </div>

    {{-- Contextual administration (OLD Detailes()'s «لوحة تحكم المادة», item_cp.htm, and Tree()).
         This page shows no category list publicly, so the item's categories are listed here for admins. --}}
    @contextualAdmin
        <x-admin.contextual-controls title="لوحة تحكم المادة" :controls="\App\Domain\Admin\Contextual\Controls\AnasheedItemControls::toolbar($anasheedItem)" />
        @include('admin.contextual._category-rows', [
            'heading' => 'تصنيفات النشيد',
            'linkPrefix' => '/var-category-',
            'rows' => collect(\App\Domain\Admin\Contextual\Controls\AnasheedItemControls::assignedCategories($anasheedItem))
                ->map(fn ($title, $categoryId) => ['id' => $categoryId, 'title' => $title, 'controls' => [\App\Domain\Admin\Contextual\Controls\AnasheedItemControls::removeCategory($anasheedItem, $categoryId)]])
                ->values()->all(),
        ])
    @endcontextualAdmin

    <div class="row service-box margin-bottom-40 w2a-media-layout sh-w2a-block">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding flex flex-column gap-5">
            {{-- OLD Detailes(): a hidden item (hidden ≠ 0) renders no details, player, actions, mirrors or comments;
                 the chrome, admin panel and sidebar stay (visitor parity A6). F08 / B4: only a signed-in administrator
                 reaches this page for a hidden item (visitors get a 404). --}}
            @if ($isHidden)
                <div class="alert alert-warning w2a-hidden-item-notice" role="status">
                    <i class="fa fa-eye-slash" aria-hidden="true"></i> هذه المادة مخفية عن الزوار
                </div>
            @endif
            @unless ($isHidden)
            <div class="portlet box blue">
                <div class="portlet-title">
                    <div class="caption"><i class="fa fa-video-camera"></i> تفاصيل المادة</div>
                </div>
                <div class="portlet-body">
                    <x-content.media-details-card :item="$anasheedItem" module="anasheed" :date="$anasheedItem->mytime
                        ? \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $anasheedItem->mytime)
                        : ''" :size="\App\Domain\Content\Support\LegacyFileSizeFormatter::format(
                        (int) ($anasheedItem->linksize ?? 0),
                    )"
                        :download-url="\App\Domain\Content\Support\VarietyMediaLink::isDownloadable($anasheedItem->link) ? '/var-download-' . $anasheedItem->id . '.htm' : null"
                        :notes="$anasheedItem->notes" notes-label="تعليق على المادة" />
                </div>
            </div>

            {{-- Shared player chrome; w2a_play() injects the selected media response into #w2a_main_player. --}}
            <x-content.media-player-panel />
            <x-content.media-player-script />

            {{-- item.php:86 post_comment_modal() (functions.php:504-536) — the modal itself, previously entirely absent. --}}
            <div class="modal fade" id="commentsModal" tabindex="-1" role="dialog" aria-modal="true"
                aria-labelledby="commentsModalLabel">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق النافذة"><span
                                    aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title" id="commentsModalLabel">اضافة تعليق على : {{ $anasheedItem->title }}
                            </h4>
                        </div>
                        <div class="modal-body" id="modal-comment-body">
                            <form name="comments_form" id="comments_form" action="" method="post"
                                data-comment-url="{{ route('anasheed.item.store-comment', $anasheedItem->id) }}">
                                {{-- decision-log #105: anasheed_scripts.js posts to data-comment-url with this token
                                     (Laravel CSRF applies; only backup.php is exempt). --}}
                                @csrf
                                <input type="hidden" name="anasheed_id" id="anasheed_id" value="{{ $anasheedItem->id }}">
                                <div class="form-group">
                                    <label for="user_nickname">اسمك المستعار</label>
                                    <input name="user_nickname" id="user_nickname" class="form-control"
                                        placeholder="اسمك المستعار">
                                    <div class="modal-error alert alert-danger" id="user_nickname_error" style="display: none;">يجب عليك ادخال اسمك
                                        المستعار</div>
                                </div>
                                <div class="form-group">
                                    <label for="user_comment">نص التعليق</label>
                                    <textarea name="user_comment" id="user_comment" cols="30" rows="10" class="form-control"
                                        placeholder="نص التعليق"></textarea>
                                    <div class="modal-error alert alert-danger" id="user_comment_error" style="display: none;">يجب عليك ادخال
                                        تعليقك</div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="send_comment" data-loading-text="جارى الارسال ......"
                                class="btn btn-primary">ارسل التعليق</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal">اغلاق</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- item.php:87 send_friend_modal() (functions.php:599-646) — the modal itself, previously entirely absent. --}}
            <div class="modal fade" id="sendFriendModal" tabindex="-1" role="dialog" aria-modal="true"
                aria-labelledby="sendFriendModalLabel">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق النافذة"><span
                                    aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title" id="sendFriendModalLabel">ارسل مادة : {{ $anasheedItem->title }}</h4>
                        </div>
                        <div class="modal-body" id="modal-sendFriend-body">
                            <form name="sendFriend_form" id="sendFriend_form" action="" method="post"
                                data-send-url="{{ route('anasheed.item.send-to-friend', $anasheedItem->id) }}">
                                {{-- decision-log #106: anasheed_scripts.js posts to data-send-url with this token
                                     (Laravel CSRF applies; only backup.php is exempt). --}}
                                @csrf
                                <div class="alert alert-info">
                                    <p><strong>أرسل هذه المادة لصديق </strong> {{ $anasheedItem->title }}</p>
                                    @if ($anasheedItem->group)
                                        <p><strong>قسم :</strong> {{ $anasheedItem->group->title }}</p>
                                    @endif
                                </div>
                                <input type="hidden" name="anasheed_id" id="anasheed_id" value="{{ $anasheedItem->id }}">
                                <div class="form-group">
                                    <label for="your_name">اسمك </label>
                                    <input type="text" name="your_name" id="your_name" class="form-control"
                                        placeholder="اسمك">
                                    <div class="modal-error alert alert-danger" id="your_name_error" style="display: none;">يجب عليك ادخال اسمك
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="your_email">بريدك الالكتروني </label>
                                    <input type="email" name="your_email" id="your_email" class="form-control"
                                        placeholder="بريدك الالكتروني">
                                    <div class="modal-error alert alert-danger" id="your_email_error" style="display: none;">يجب عليك ادخال بريدك
                                        االكتروني بصيغة صحيحة</div>
                                </div>
                                <div class="form-group">
                                    <label for="friend_name">اسم صديقك </label>
                                    <input type="text" name="friend_name" id="friend_name" class="form-control"
                                        placeholder="اسم صديقك">
                                    <div class="modal-error alert alert-danger" id="friend_name_error" style="display: none;">يجب عليك ادخال اسم
                                        صديقك</div>
                                </div>
                                <div class="form-group">
                                    <label for="friend_email">بريد صديقك </label>
                                    <input type="email" name="friend_email" id="friend_email" class="form-control"
                                        placeholder="بريد صديقك">
                                    <div class="modal-error alert alert-danger" id="friend_email_error" style="display: none;"> يجب عليك ادخال
                                        بريد صديقك بصيغة صحيحة </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="send_friend" data-loading-text="جارى الارسال ......"
                                class="btn btn-primary">ارسل لصديقك</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal">اغلاق</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Mirror routes and the distinct anasheed_mirror player type are preserved by the shared quality component. --}}
            @if ($anasheedItem->mirror && $anasheedItem->mirrors->isNotEmpty())
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-clone"></i> قائمة الجودات المختلفة للمادة</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.media-quality-list :items="$anasheedItem->mirrors" module="anasheed" :parent-id="$anasheedItem->id" />
                    </div>
                </div>
            @endif

@php($contextualComments = app(\App\Domain\Admin\Contextual\ContextualAdmin::class))
@php($moderatorForm = $comments !== null && $contextualComments->visible([\App\Domain\Admin\Contextual\Controls\AnasheedItemControls::moderatorComment($anasheedItem)]) !== [])
            @if ($comments !== null && ($comments->isNotEmpty() || $moderatorForm))
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-comments"></i> تعليقات الزوار على المادة</div>
                    </div>
                    <div class="portlet-body">
@if ($moderatorForm)
{{-- OLD Comments(): «إضافة تعليق إداري» (comment_admin.htm) for admins, above the comments; the directives sit at column 0 so a visitor's HTML stays byte-identical. --}}
                        @include('admin.contextual._anasheed-moderator-comment', ['item' => $anasheedItem])
@endif
@if ($comments->isNotEmpty())
                        <x-content.visitor-comments :comments="$comments"
                            :comment-controls="$contextualComments->active() ? fn ($comment) => [\App\Domain\Admin\Contextual\Controls\AnasheedItemControls::forComment((int) $comment->id)] : null" />
@endif
                    </div>
                </div>
            @endif
            @endunless
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding flex flex-column gap-5" aria-label="الشريط الجانبي">
            {{-- Compact sidebar cards retain the source distinction between download-count and date metadata.
                 OLD Top10(): department-scoped, six rows each, the «new» list titled per department (visitor parity A12). --}}
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
                        <div class="caption"><i class="fa fa-flash"></i> {{ $mostRecentTitle }}</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.anasheed-sidebar-list :items="$mostRecent" meta="date" />
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection

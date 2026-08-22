@extends('layouts.app')

{{--
    `fatawa-all-{id}.htm` Owner-Approved `answer2.php` Reconstruction.

    PROVENANCE — do not read this as "answer2.php is the proven historic
    handler": `.htaccess:295` routes this URL to
    `modules.php?name=Fatwa&op=all_questions&g_q_id=$1`, and `modules.php`
    does not exist anywhere in this legacy snapshot. No recoverable
    dispatch evidence proves whether legacy ever served `answer.php` or
    `answer2.php` at this URL — DISPATCH_ORIGIN_UNKNOWN, confirmed in the
    prior "`fatawa-all-{id}.htm` Reconstruction Report". The business
    owner explicitly selected `answer2.php`'s markup as the migration's
    canonical presentation reference (OWNER_APPROVED_CANONICAL_LAYOUT),
    partly because it stylistically matches the confirmed sibling
    `single.php` — a design decision, not a rediscovered historical fact.

    This view reproduces `answer2.php` (re-read in full for this task):
    two-column `<th width="25%" class="w20">` question/answer rows,
    `class="answer-p"`, the icon/action row AFTER the details table, and
    the (in `answer2.php`, uncommented/active) `#the_main_player` CSS.

    Deliberate, classified omissions from a byte-for-byte port (see the
    full report for the complete list):
    - `adminAnswerControls()`/`adminAnswerMoreControls()`: ADMIN_ONLY,
      link to legacy's own `admin.php?op=...` — no migrated admin panel
      exists in this app. UNREACHABLE_IN_CURRENT_MIGRATION, omitted.
    - The `.container-video{i}` hidden `<video>` modal + `playPause{i}()`
      + `close-video{i}`: DEAD_LEGACY — its own click-wiring
      (`$(".watch_video...").click(...)`) is commented out in source, so
      no user interaction ever opens it. Not reproduced.
    - `page_bar_auther()`/`page_bar_channels()` (answer2.php's
      `auther_id`/`channel` GET-param branches): UNREACHABLE_IN_CURRENT_MIGRATION
      — this route/controller has no equivalent parameter. Only the
      default `page_bar($cat_id,$id,$q)` branch is implemented.
    - `fatawa/css/new-style.css` / `fatawa/js/w2a_play.js`:
      ASSET_UNREACHABLE_LOCALLY — no `public/fatawa` symlink exists.
      `/css/custom.css` (a genuinely different, reachable file from the
      globally-loaded `/assets/frontend/layout/css/custom.css`) IS
      pushed. `w2a_play()`'s real behavior is reproduced natively instead
      (see below), not blindly linked to a 404ing script.
    - The "أرسل لصديق" modal's real submit wiring lives in the
      unreachable `w2a_play.js`'s `sendemail()` — exact AJAX contract
      unknown. Wired to a plain, real form POST against the already-
      existing, already-tested `FatwaQuestionController::sendToFriend()`
      route instead of guessing at unrecoverable JS.

    "مشاهدة المادة" (watch): legacy's `w2a_play($k,'fatawa')` addresses
    media by page-ordinal position (matching the `#video_{k}` DOM node
    from the now-omitted dead modal loop), and `get_w2a_mada_player()`'s
    `type=='fatawa'` branch never actually uses `get_w2a_mada()`'s (empty)
    lookup — it trusts client-POSTed title/link outright. Reproduced here
    via the real, shared `/media-player` endpoint instead (same
    Laravel-native pattern already established for khotab/anasheed —
    `MediaPlayerService::fromFatwaQuestion()`), addressed by the answer's
    real `id` rather than by fragile page position — same end-user
    result (click "watch" on this answer, this answer's media plays),
    hardened the same way this service already hardens every other
    branch (parameterized lookup instead of trusting client input).

    "عدد الزيارات" (views): `answer2.php`'s SQL selects
    `general.num_view` AFTER `question.*` in the same query — MySQL/PDO
    object-hydration keeps the LAST column of a repeated name, so
    legacy's rendered value on every row is actually the shared general
    question's view count (post-increment), not each answer row's own
    (uncounted) `nuke_fatwa_questions.num_view` column.
    `ContentListingService::fatwaQuestionsForGeneralQuestion()` doesn't
    alias that column in, so `$generalQuestionModel->num_view` (already
    available, already reflects this request's `recordView()`) is used
    here instead of `$answer->num_view` — reproducing what legacy
    actually renders, not its raw column name.
--}}
@section('title', 'سؤال | '.$generalQuestionModel->question_text)

@push('styles')
    <link rel="stylesheet" href="/css/custom.css">
    <link href="https://fonts.googleapis.com/css?family=Cairo|Reem+Kufi" rel="stylesheet">
    <style>
        .page-header-fixed .header{
            position: relative !important;
        }
        #the_main_player{
            position: fixed;
            z-index: 2147483647;
            width: 875px;
            top: 20%;
            bottom: 10%;
            max-width: 100%;
        }
    </style>
@endpush

@section('content')
    {{--
        page_bar($cat_id, $id, $q) — fatawa/functions.php:239-292, a
        DIFFERENT, hand-rolled chrome mechanism from the shared
        title()/breadcrumb() pair and from <x-page-chrome> (deliberately
        NOT used here). Reproduced exactly as read: empty <h1 style="">,
        Home AND "الفتاوى المرئية" both put their fa-angle-right icon
        AFTER the link, category items do the same except the LAST one
        (no trailing icon), then the topic/question items switch
        convention entirely — icon BEFORE the link, no closing icon —
        matching page_bar()'s own genuinely self-inconsistent source, not
        page_bar_channels()'s uniform icon-before-link convention used
        elsewhere on fatawa-channel-{id}.htm.
    --}}
    <h1 style=""></h1>
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li>
                <i class="fa fa-home"></i>
                <a href="/">الرئيسية</a>
                <i class="fa fa-angle-right"></i>
            </li>
            <li>
                <a href="/fatawa.htm">الفتاوى المرئية </a>
                <i class="fa fa-angle-right"></i>
            </li>
            @foreach ($categoryChain as $category)
                <li>
                    <a href="/fatawa-topics-{{ $category->id }}.htm">{{ $category->title }} </a>
                    @if (!$loop->last)
                        <i class="fa fa-angle-right"></i>
                    @endif
                </li>
            @endforeach
            @if ($topicModel)
                <li> <i class="fa fa-angle-right"></i><a href="/fatawa-group-{{ $topicModel->id }}-{{ $categoryId }}.htm"> موضوع {{ $topicModel->topic_name }} </a></li>
                <li> <i class="fa fa-angle-right"></i><a href="/fatawa-all-{{ $generalQuestionModel->id }}.htm">{{ $generalQuestionModel->question_text }} </a></li>
            @endif
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            {{--
                answer2.php:93's adminAnswerControls($q,$cat_id,$id) —
                ADMIN_ONLY (links to legacy's own admin.php?op=..., which
                has no migrated equivalent here) — UNREACHABLE_IN_CURRENT_MIGRATION,
                intentionally omitted rather than exposed with a broken
                target.
            --}}
            @foreach ($answers as $answer)
                <a id="{{ $answer->id }}"></a>
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption">
                                <a href="/auther-questions-{{ $answer->auther_id }}.htm" class="auther-name">
                                    <i class="fa fa-video-camera"></i>
                                    {{ $answer->author_prename }} : {{ $answer->author_name }}
                                </a>
                            </div>
                        </div>
                        <div class="portlet-body ">
                            <div class="anasheed-details mada-details">
                                <table class="table table-striped">
                                    <tbody>
                                        <tr>
                                            <th width="25%" class="w20" style="border-top:0;">السؤال </th>
                                            <td style="border-top:0;">{{ $answer->question_text }}</td>
                                        </tr>
                                        @if (($answer->answer_text ?? '') !== '' && $answer->answer_text !== '.')
                                            <tr>
                                                <th class="w20" style="border-top:0;">الجواب </th>
                                                <td style="border-top:0;">
                                                    <p class="answer-p" style="line-height: 2.2 !important;">{!! $answer->answer_text !!}</p>
                                                </td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <th class="w20"> تاريخ إصدار الفتوي</th>
                                            <td>{{ \App\Domain\Content\Support\ArabicDateConverter::convert($answer->date_of_fatwa ?? '') }}</td>
                                        </tr>
                                        <tr>
                                            <th class="w20"> مكان إصدار الفتوي</th>
                                            <td>
                                                @if ($channels->get($answer->channel_id))
                                                    <a href="/fatawa-channel-{{ $answer->channel_id }}.htm">{{ $channels->get($answer->channel_id)->title }}</a>
                                                @else
                                                    <a href="/fatawa-channel-0.htm"> بدون قناه </a>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th class="w20"> تاريخ الإضافة </th>
                                            <td>{{ \App\Domain\Content\Support\ArabicDateConverter::convert($answer->db_insertion_date ?? '') }}</td>
                                        </tr>
                                        <tr>
                                            <th class="w20">حجم المادة</th>
                                            <td>{{ $answer->media_size }} ميجا بايت</td>
                                        </tr>
                                        {{-- general.num_view, not this row's own — see this file's top docblock. --}}
                                        <tr>
                                            <th class="w20">عدد الزيارات</th>
                                            <td>{{ $generalQuestionModel->num_view }} زيارة</td>
                                        </tr>
                                        <tr>
                                            <th class="w20">عدد مرات الحفظ</th>
                                            <td id="num_download_{{ $loop->iteration }}">{{ $answer->num_download }} مرة</td>
                                        </tr>
                                        <input type="hidden" value="{{ $answer->num_download }}" id="num_download_hidden_{{ $loop->iteration }}">
                                    </tbody>
                                </table>
                                <br><br>
                                <div class="row text-center jumbotron-icon">
                                    <div class="col-xs-4 text-center mada-control-item watch_video{{ $loop->iteration }}">
                                        <a onclick="w2a_play({{ $answer->id }}, 'fatawa')" style="cursor:pointer;">
                                            <div class="badge blue">
                                                <div class="circle"><i class="fa fa-youtube-play fa-4 text-blue"></i></div>
                                            </div>
                                            <h5>مشاهدة المادة</h5>
                                        </a>
                                    </div>
                                    <div class="col-xs-4 text-center mada-control-item">
                                        <a href="/fatawa-download-{{ $answer->id }}.htm" target="_blank">
                                            <div class="badge blue">
                                                <div class="circle"><i class="fa fa-floppy-o fa-4 text-blue"></i></div>
                                            </div>
                                            <h5>حفظ المادة</h5>
                                        </a>
                                    </div>
                                    <div class="col-xs-4 text-center mada-control-item">
                                        <a data-toggle="modal" data-target="#sendFriendModal{{ $answer->id }}" href="javascript:;" class="send-friend-btn">
                                            <div class="badge blue">
                                                <div class="circle"><i class="fa fa-envelope fa-4 text-blue"></i></div>
                                            </div>
                                            <h5>أرسل لصديق</h5>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{--
                    answer2.php:227-281's sendFriendModal, wired to the
                    already-existing, already-tested
                    FatwaQuestionController::sendToFriend() route (a real
                    form POST) rather than the unreachable w2a_play.js's
                    sendemail() — see this file's top docblock.
                --}}
                <div class="modal fade" id="sendFriendModal{{ $answer->id }}" tabindex="-1" role="dialog" aria-labelledby="sendFriendModalLabel{{ $answer->id }}">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                                <h4 class="modal-title" id="sendFriendModalLabel{{ $answer->id }}">ارسال مادة : {{ $answer->question_text }}</h4>
                            </div>
                            <form action="{{ route('fatawa.question.send-to-friend', $answer->id) }}" method="post">
                                @csrf
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <p><strong>أرسل هذه المادة لصديق </strong>{{ $answer->question_text }}</p>
                                    </div>
                                    <div class="form-group">
                                        <label for="your_name{{ $answer->id }}">اسمك </label>
                                        <input type="text" name="your_name" id="your_name{{ $answer->id }}" class="form-control" placeholder="اسمك">
                                    </div>
                                    <div class="form-group">
                                        <label for="your_email{{ $answer->id }}">بريدك الالكتروني </label>
                                        <input type="email" name="your_email" id="your_email{{ $answer->id }}" class="form-control" placeholder="بريدك الالكتروني">
                                    </div>
                                    <div class="form-group">
                                        <label for="friend_name{{ $answer->id }}">اسم صديقك </label>
                                        <input type="text" name="friend_name" id="friend_name{{ $answer->id }}" class="form-control" placeholder="اسم صديقك">
                                    </div>
                                    <div class="form-group">
                                        <label for="friend_email{{ $answer->id }}">بريد صديقك </label>
                                        <input type="email" name="friend_email" id="friend_email{{ $answer->id }}" class="form-control" placeholder="بريد صديقك">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary"> ارسل لصديقك </button>
                                    <button type="button" class="btn btn-default" data-dismiss="modal"> اغلاق </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach

            {{--
                answer2.php:290-300 — uses the last-iterated $question
                after the loop, which via the JOIN is always the general
                question's own `description` (general.description) —
                $generalQuestionModel->description directly, not a
                per-answer field.
            --}}
            @if (!empty($generalQuestionModel->description))
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-body ">
                            <p>{{ $generalQuestionModel->description }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{--
                w2a_player_html() (functions.php:780-793) — one shared
                panel, placed once (legacy calls this once PER answer row,
                inside the loop, producing duplicate #the_main_player IDs
                — a real but harmless-when-consolidated markup quirk; kept
                to exactly one instance here, matching this project's own
                established khotab-item-298784.htm precedent for this
                exact shared component).
            --}}
            <div class="col-md-12 col-sm-12" id="the_main_player">
                <div class="panel panel-default">
                    <div class="panel-heading" style="">
                        <span class="clickable" data-effect="fadeOut"><i class="fa fa-times"></i></span>
                    </div>
                    <div class="panel-body" id="w2a_main_player"></div>
                </div>
            </div>
        </div>

        @if ($categoryId)
            <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
                <div class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption"><i class="fa fa-download"></i>الأكثر تحميلا </div>
                        </div>
                        <div class="portlet-body ">
                            <ul class="news">
                                @foreach ($mostDownloaded as $item)
                                    <li><a href="/fatawa-all-{{ str_replace('|', '', (string) $item->general_question_id) }}.htm#{{ $item->id }}" class="add">{{ $item->question_text }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption"><i class="fa fa-plus"></i>جديد المواد </div>
                        </div>
                        <div class="portlet-body ">
                            <ul class="news">
                                @foreach ($mostRecent as $item)
                                    <li><a href="/fatawa-all-{{ str_replace('|', '', (string) $item->general_question_id) }}.htm#{{ $item->id }}" class="add">{{ $item->question_text }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </aside>
        @endif
    </div>

    @push('scripts')
        {{--
            Consolidated, real w2a_play(id,'fatawa') — see this file's top
            docblock for why this is addressed by the answer's real `id`
            (not legacy's page-ordinal `$k`) and why one definition
            replaces legacy's N duplicated identical <script> blocks (one
            per answer row, functionally a no-op redeclaration). Same
            Laravel-native /media-player wiring already established for
            khotab-item-298784.htm.
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

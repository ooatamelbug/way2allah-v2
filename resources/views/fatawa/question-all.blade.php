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
    original detail content. Mobile QA now uses the shared player and action controls.

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
@section('title', 'سؤال | ' . $generalQuestionModel->question_text)

@push('styles')
    <link rel="stylesheet" href="/css/custom.css">
    <link href="https://fonts.googleapis.com/css?family=Cairo|Reem+Kufi" rel="stylesheet">
    <style>
        .page-header-fixed .header {
            position: relative !important;
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
            {{--
                Repair Batch 1 (decision-log #52, Sitewide Internal 404
                Audit finding #4): this link was missing the required
                {page} segment — the only registered route for this
                family is /fatawa-topics-{category}-{page}.htm
                (routes/content.php, 'fatawa.topics.show', no 1-segment
                variant exists). Resolved via the named route rather than
                a hardcoded string, matching this project's own
                established preference (FatwaDayController's pageUrl
                closures) — stays correct if the route's own URI ever
                changes. page=1 matches .htaccess:301-302's own "page
                defaults to 1 in the 2-parameter form" note.
            --}}
            @foreach ($categoryChain as $category)
                <li>
                    <a href="{{ route('fatawa.topics.show', ['category' => $category->id, 'page' => 1]) }}">{{ $category->title }}
                    </a>
                    @if (!$loop->last)
                        <i class="fa fa-angle-right"></i>
                    @endif
                </li>
            @endforeach
            @if ($topicModel)
                <li> <i class="fa fa-angle-right"></i><a href="/fatawa-group-{{ $topicModel->id }}-{{ $categoryId }}.htm">
                        موضوع {{ $topicModel->topic_name }} </a></li>
                <li> <i class="fa fa-angle-right"></i><a href="/fatawa-all-{{ $generalQuestionModel->id }}.htm">{{ $generalQuestionModel->question_text }} </a></li>
            @endif
        </ul>
    </div>

    <div class="row service-box margin-bottom-40 w2a-media-layout">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding flex flex-column gap-5">
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
                                <div class="w2a-table-responsive-wrapper">
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
                                                        <p class="answer-p" style="line-height: 2.2 !important;">
                                                            {!! $answer->answer_text !!}</p>
                                                    </td>
                                                </tr>
                                            @endif
                                            <tr>
                                                <th class="w20"> تاريخ إصدار الفتوي</th>
                                                <td>{{ \App\Domain\Content\Support\ArabicDateConverter::convert($answer->date_of_fatwa ?? '') }}
                                                </td>
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
                                                <td>{{ \App\Domain\Content\Support\ArabicDateConverter::convert($answer->db_insertion_date ?? '') }}
                                                </td>
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
                                                <td id="num_download_{{ $loop->iteration }}">{{ $answer->num_download }}
                                                    مرة</td>
                                            </tr>
                                            <input type="hidden" value="{{ $answer->num_download }}"
                                                id="num_download_hidden_{{ $loop->iteration }}">
                                        </tbody>
                                    </table>
                                </div>
                                <div class="w2a-fatwa-actions">
                                    <button type="button" class="w2a-action-btn w2a-action-play"
                                        onclick="w2a_play({{ $answer->id }}, 'fatawa')">
                                        <i class="fa fa-play-circle" aria-hidden="true"></i> مشاهدة المادة
                                    </button>
                                    <a href="/fatawa-download-{{ $answer->id }}.htm" target="_blank" rel="noopener"
                                        class="w2a-action-btn w2a-action-download">
                                        <i class="fa fa-download" aria-hidden="true"></i> حفظ المادة
                                    </a>
                                    <button type="button" data-toggle="modal"
                                        data-target="#sendFriendModal{{ $answer->id }}"
                                        class="w2a-action-btn w2a-action-share send-friend-btn">
                                        <i class="fa fa-paper-plane" aria-hidden="true"></i> أرسل لصديق
                                    </button>
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
                <div class="modal fade" id="sendFriendModal{{ $answer->id }}" tabindex="-1" role="dialog"
                    aria-modal="true" aria-labelledby="sendFriendModalLabel{{ $answer->id }}">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق النافذة"> <span
                                        aria-hidden="true">&times;</span> </button>
                                <h4 class="modal-title" id="sendFriendModalLabel{{ $answer->id }}">ارسال مادة :
                                    {{ $answer->question_text }}</h4>
                            </div>
                            <form action="{{ route('fatawa.question.send-to-friend', $answer->id) }}" method="post">
                                @csrf
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <p><strong>أرسل هذه المادة لصديق </strong>{{ $answer->question_text }}</p>
                                    </div>
                                    <div class="form-group">
                                        <label for="your_name{{ $answer->id }}">اسمك </label>
                                        <input type="text" name="your_name" id="your_name{{ $answer->id }}"
                                            class="form-control" placeholder="اسمك">
                                    </div>
                                    <div class="form-group">
                                        <label for="your_email{{ $answer->id }}">بريدك الالكتروني </label>
                                        <input type="email" name="your_email" id="your_email{{ $answer->id }}"
                                            class="form-control" placeholder="بريدك الالكتروني">
                                    </div>
                                    <div class="form-group">
                                        <label for="friend_name{{ $answer->id }}">اسم صديقك </label>
                                        <input type="text" name="friend_name" id="friend_name{{ $answer->id }}"
                                            class="form-control" placeholder="اسم صديقك">
                                    </div>
                                    <div class="form-group">
                                        <label for="friend_email{{ $answer->id }}">بريد صديقك </label>
                                        <input type="email" name="friend_email" id="friend_email{{ $answer->id }}"
                                            class="form-control" placeholder="بريد صديقك">
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
            <x-content.media-player-panel />
            <x-content.media-player-script />
        </div>

        @if ($categoryId)
            <aside class="col-lg-3 col-md-4 col-sm-5 nopadding flex flex-column gap-5" aria-label="الشريط الجانبي">
                <div class="col-md-12 col-sm-12">
                    <x-content.sidebar-ranking title="الأكثر تحميلا" icon="fa-fire" :items="$mostDownloaded" type="fatawa"
                        meta="downloads" />
                </div>
                <div class="col-md-12 col-sm-12">
                    <x-content.sidebar-ranking title="جديد المواد" icon="fa-clock-o" :items="$mostRecent" type="fatawa"
                        meta="recent" />
                </div>
            </aside>
        @endif
    </div>

@endsection

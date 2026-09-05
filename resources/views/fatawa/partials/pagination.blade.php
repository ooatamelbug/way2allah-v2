{{--
    Fatwa Date Route Completion (decision-log #46), renamed from
    `day-pagination.blade.php` in decision-log #50 once a second real
    caller (`fatawa/author-show.blade.php`) confirmed it isn't
    day-page-specific. Reproduces `fatawa/functions.php:708-751`'s real
    `pagination()` markup/windowing exactly — the SAME function every
    page that calls `pagination($count)` in legacy uses, `auther_profile.php`
    included — including its own `$num = intval($count/$perpage)+1` (a
    genuine legacy off-by-one: always one page higher than the true last
    page, even when $count divides evenly by $perpage; preserved, not
    "fixed") — but generates URLs via the `$pageUrl` closure (route()-based,
    matching the real pretty-URL contract for whichever context this
    partial is included from — `/fatwa-today.htm`/`/fatwa-today-{page}.htm`,
    `/fatwa-date-{d}-{m}-{y}-{page}.htm`, or `/auther-questions-{author}.htm`/
    `/auther-questions-{author}-{page}.htm`) instead of legacy's own
    REQUEST_URI string-splicing, which only worked because it operated on
    the current request's own URL — not portable to a reusable partial.

    The pretty-URL windowing remains the legacy contract. The presentation
    now uses the shared premium pagination system so this paginator matches
    every other public directory while retaining its route closure.

    $perpage is hardcoded to 25 here, matching the same value
    `ContentListingService::fatwaQuestionsByDate()` already paginates
    with (legacy's own `$perpage` global, already established elsewhere
    in this module).
--}}
@php
    $perpage = 25;
    $count = $questions->total();
    $currentPage = $questions->currentPage();
@endphp
@if ($count > $perpage)
    @php
        $num = intval($count / $perpage) + 1;
        $start = 1;
        $end = $num;
        if ($num > 5) {
            $start = $currentPage;
            $end = $start + 5;
        }
        if ($num > 5 && ($num - $currentPage) < 5) {
            $start = $num - 5;
            $end = $start + 5;
        }
    @endphp
    <nav class="w2a-pagination" role="navigation" aria-label="التنقل بين الصفحات">
        <p class="w2a-pagination__summary">
            عرض
            <strong>{{ $questions->firstItem() }}–{{ $questions->lastItem() }}</strong>
            من
            <strong>{{ $count }}</strong>
        </p>
        <ul class="w2a-pagination__list">
            @if ($currentPage > 1)
                <li><a href="{{ $pageUrl(1) }}" class="w2a-pagination__control" aria-label="الصفحة الأولى">الأولى</a></li>
                <li>
                    <a href="{{ $pageUrl($currentPage - 1) }}" class="w2a-pagination__control" rel="prev" aria-label="الصفحة السابقة">
                        <i class="fa fa-angle-right" aria-hidden="true"></i><span>السابق</span>
                    </a>
                </li>
            @endif
            @for ($i = $start; $i <= $end; $i++)
                <li>
                    @if ($currentPage === $i)
                        <span class="w2a-pagination__page is-current" aria-current="page"><span class="sr-only">الصفحة الحالية:</span>{{ $i }}</span>
                    @else
                        <a href="{{ $pageUrl($i) }}" class="w2a-pagination__page" aria-label="الانتقال إلى الصفحة {{ $i }}">{{ $i }}</a>
                    @endif
                </li>
            @endfor
            @if ($count > $currentPage * $perpage)
                <li>
                    <a href="{{ $pageUrl($currentPage + 1) }}" class="w2a-pagination__control" rel="next" aria-label="الصفحة التالية">
                        <span>التالي</span><i class="fa fa-angle-left" aria-hidden="true"></i>
                    </a>
                </li>
            @endif
            @if ($count > (2 * $perpage) && $currentPage < $num)
                <li><a href="{{ $pageUrl($num) }}" class="w2a-pagination__control" aria-label="الصفحة الأخيرة">الأخيرة</a></li>
            @endif
        </ul>
    </nav>
@endif

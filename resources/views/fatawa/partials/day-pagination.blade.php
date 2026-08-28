{{--
    Fatwa Date Route Completion (decision-log #46). Reproduces
    `fatawa/functions.php:708-751`'s real `pagination()` markup/windowing
    exactly — including its own `$num = intval($count/$perpage)+1` (a
    genuine legacy off-by-one: always one page higher than the true last
    page, even when $count divides evenly by $perpage; preserved, not
    "fixed") — but generates URLs via the `$pageUrl` closure (route()-based,
    matching the real pretty-URL contract for whichever context this
    partial is included from — `/fatwa-today.htm`/`/fatwa-today-{page}.htm`
    or `/fatwa-date-{d}-{m}-{y}-{page}.htm`) instead of legacy's own
    REQUEST_URI string-splicing, which only worked because it operated on
    the current request's own URL — not portable to a reusable partial.

    Markup/classes (`.pagination`, `.prevnext`, `.page_link`, `a.active`,
    `.disablelink`) are legacy's own, real, and already styled by the
    `fatawa/css/new-style.css` this page already loads (decision-log #45)
    — not a redesign.

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
    <div class="pagination">
        <ul>
            @if ($currentPage > 1)
                <li><a href="{{ $pageUrl(1) }}" class="prevnext disablelink">الأولى</a></li>
                <li><a href="{{ $pageUrl($currentPage - 1) }}" class="prevnext disablelink">&lt;&lt;</a></li>
            @endif
            @for ($i = $start; $i <= $end; $i++)
                <li><span class="page_link"><a href="{{ $pageUrl($i) }}" class="{{ $currentPage == $i ? 'active' : '' }}">{{ $i }}</a></span></li>
            @endfor
            @if ($count > $currentPage * $perpage)
                <li><a href="{{ $pageUrl($currentPage + 1) }}" class="prevnext">&gt;&gt;</a></li>
            @endif
            @if ($count > (2 * $perpage) && $currentPage < $num)
                <li><a href="{{ $pageUrl($num) }}" class="prevnext">الأخيرة</a></li>
            @endif
        </ul>
    </div>
@endif

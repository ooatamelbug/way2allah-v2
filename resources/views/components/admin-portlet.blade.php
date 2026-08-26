@props(['title' => null, 'color' => 'purple', 'icon' => 'fa fa-comments', 'light' => false, 'width' => 12])

{{--
    AdminCP Final Page-Level Visual-Parity Verification (2026-08-22):
    reconstructs `admincp/admin_functions.php`'s `open_div()`/`close_div()`
    pattern AND the equivalent hand-typed `<div class="portlet box
    {color}">` markup several legacy pages use directly instead of the
    helper — both produce the identical DOM shape, confirmed by reading
    both `admin_functions.php` and several raw-markup pages
    (`broadcasting/index.php`, `survey/index.php`, ...) side by side.

    `color`/`icon` default to 'purple'/'fa fa-comments' — the values every
    raw-markup single-portlet page observed uses. `icon` takes the FULL
    icon class(es), not just a `fa-*` suffix — legacy mixes Font Awesome
    (`fa fa-comments`, `fa fa-gift`) and simple-line-icons (`icon-edit`,
    no `fa` base class) across different portlets, so this component
    can't safely assume a shared prefix. `open_div()`'s own function
    signature defaults to 'blue'/'fa-folder-open' instead; pages that call
    `open_div()` without overriding color (e.g. `chat/edit_room.php`'s
    main portlet) pass `color="blue" icon="fa fa-folder-open"` explicitly
    here rather than relying on this component's own default, since the
    two legacy conventions have different defaults and this component
    can't tell which convention a given page followed.

    Introduced to replace the previous architecture (the layout itself
    auto-wrapping `@yield('content')` in one generic portlet), which was
    a confirmed page-markup gap for every page with more than one real
    legacy portlet (`broadcasting/edit_stream.php`, `survey/add_question.php`,
    `chat/edit_room.php`, `authors/index.php`, `locations/add.php`, ...) —
    see `docs/decision-log.md` entry #25.

    `light` reconstructs legacy's OTHER real portlet variant
    (`class="portlet light"` / `"portlet light bordered"`, no box color) —
    used for per-question stat blocks (`survey/all_stats.php`), per-video
    blocks (`youtube/index.php`), and per-module permission blocks
    (`authors/edit_author.php`). `width` reconstructs `open_div()`'s own
    4th argument for side-by-side portlets (e.g. `locations/add.php`'s
    "بيانات الموقع" width=4 next to "الخريطة" width=8).
--}}
<div class="col-md-{{ $width }}">
    <div class="portlet {{ $light ? 'light bordered' : 'box '.$color }}">
        <div class="portlet-title">
            <div class="caption">
                <i class="{{ $icon }}"></i>{{ $title }}
            </div>
        </div>
        <div class="portlet-body">
            {{ $slot }}
        </div>
    </div>
</div>

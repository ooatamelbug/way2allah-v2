<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>الطريق إلى الله | @yield('title')</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/global/plugins/font-awesome/css/font-awesome.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/global/plugins/simple-line-icons/simple-line-icons.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/global/plugins/bootstrap/css/bootstrap-rtl.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/global/css/components-rtl.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/global/css/plugins-rtl.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/layouts/layout4/css/layout-rtl.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/layouts/layout4/css/themes/light-rtl.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/layouts/layout4/css/custom-rtl.min.css') }}" rel="stylesheet" type="text/css">
    @stack('styles')
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
</head>
{{--
    AdminCP Full Visual/Layout Parity Reconstruction (2026-08-22):
    reconstructs legacy `admincp/header.php` + `sidebar.php` +
    `footer.php`'s REAL shell (Metronic layout4, RTL), separated from
    `admincp/home.php`/`navigation_menu.php`'s demo content per the
    standing `LEGACY_DEMO_DASHBOARD = DO_NOT_REPRODUCE` decision. Every
    element below is either sourced directly from legacy shell markup or
    is real Laravel data (AdminDashboardModules, the authenticated admin) —
    nothing here is fabricated business content. See
    `docs/reviews/admincp-visual-parity-reconstruction.md` for the full
    real-shell-vs-demo-content evidence trail.

    **Corrected 2026-08-26 (owner-supplied real header screenshots,
    decision-log #41's same reversal extended to the header):** the
    header search form and the notification/inbox/todo dropdowns are now
    reproduced verbatim (see the header markup below) — the owner
    flagged the collapsed header bar itself as visibly different from
    production and explicitly asked for exact reproduction, same as the
    dashboard. Their `href="#"`/dead-`.html` targets are unchanged —
    exactly as inert in real legacy as here, not newly broken by this.

    **Corrected again, same day:** the user dropdown's Profile/Calendar/
    Messages/Tasks/Lock-Screen entries ARE now reproduced too (a real
    opened-dropdown screenshot showed them) — see the user-dropdown
    markup below for the full reasoning; their dead `href="#"` targets
    are unchanged from legacy's own equally-dead `extra_profile.html`/
    `page_calendar.html`/etc. `admincp/w2a__lock.php` itself remains
    correctly NOT wired to the reproduced "شاشة القفل" label — legacy's
    own link doesn't point there either (`extra_lock.html`, also dead),
    and that page independently relies on the insecure cookie scheme
    already excluded by `LEGACY_INSECURE_AUTH_BEHAVIOR = DO_NOT_REPRODUCE`.

    Still deliberately NOT reproduced from the legacy header/nav (all
    zero-DB static Metronic demo markup, confirmed by full-file reads,
    and not evidenced as visually different in anything supplied so far):
    - `.page-actions` "Actions" dropdown (New Post/Comment/Share, fake
      badge counts) — not visible in either supplied header screenshot
    - `uniform`/`bootstrap-switch` CSS+JS — nothing in any of the 26
      current admin views uses a uniform-styled checkbox or a switch
      (PRESENT_BUT_UNUSED in the legacy bundle, not loaded here)
    - `demo.min.js`/`quick-sidebar.min.js` — Metronic's own layout-demo
      and quick-sidebar-panel scripts; no quick-sidebar panel or layout
      customizer UI exists here to depend on them (DEMO_ONLY)
    - the page-breadcrumb — legacy's own `<ul class="page-breadcrumb
      breadcrumb hide">` carries a `hide` class in every page that has
      one; it is never visually shown in legacy either (MATCH: omitting
      it here is equivalent to legacy's own permanently-hidden state)
    - the `.page-head`/`.page-title` block — present only in
      `home.php` (whose content is demo-only); every real feature page
      read (`broadcasting/index.php`, `edit_stream.php`) renders straight
      into its portlet with no page-head.

    Correction (AdminCP Final Page-Level Visual-Parity Verification,
    2026-08-22): this layout previously auto-wrapped `@yield('content')`
    in one generic portlet, using `@yield('title')` as its caption. That
    was a confirmed page-markup gap wherever a legacy page had more than
    one real portlet, or where the portlet's real caption differs from
    the page's own `<title>` text (both are true on multiple pages,
    `broadcasting/edit_stream.php` chief among them — see decision-log
    entry #25). The layout now owns only the shell/content container
    (`.page-content-wrapper` > `.page-content` > `.row`); every feature
    view wraps its own content in one or more `<x-admin-portlet>`
    components with its own source-verified caption/color, matching
    legacy's real per-page portlet count exactly where source was read.
--}}
<body class="page-header-fixed page-sidebar-closed-hide-logo">
<div class="page-header navbar navbar-fixed-top">
    <div class="page-header-inner">
        <div class="page-logo">
            <a href="{{ route('admin.entry') }}">
                <img src="{{ asset('logo-light.png') }}" alt="logo" class="logo-default">
            </a>
            <div class="menu-toggler sidebar-toggler"></div>
        </div>
        <a href="javascript:;" class="menu-toggler responsive-toggler" data-toggle="collapse" data-target=".navbar-collapse"></a>
        <div class="page-top">
            {{--
                AdminCP Production-vs-Laravel Screenshot Visual Comparison
                (2026-08-26): the owner flagged the collapsed header bar
                itself as visually different — production shows the real
                notification/inbox/todo dropdown triggers + search box
                `navigation_menu()` (`admincp/header.php`/
                `navigation_menu.php`) always renders; this layout only
                ever had the (already-real) user/logout dropdown. Same
                owner-directed reversal as the dashboard (decision-log
                #41): reproduced verbatim, English lorem-ipsum demo
                content included — every string below is transcribed
                directly from `navigation_menu.php`, not invented. Their
                `href="#"`/`*.html` targets are exactly as dead in real
                legacy as here — not wired to anything real there either.
                The "Actions" dropdown (`header.php`'s `.page-actions`)
                is NOT included — not visible in either supplied
                screenshot, no evidence it renders at this viewport.
            --}}
            <form class="search-form" action="#" method="GET">
                <div class="input-group">
                    <input type="text" class="form-control input-sm" placeholder="بحث..." name="query">
                    <span class="input-group-btn">
                        <a href="javascript:;" class="btn submit"><i class="icon-magnifier"></i></a>
                    </span>
                </div>
            </form>
            <div class="top-menu">
                <ul class="nav navbar-nav pull-right">
                    <li class="dropdown dropdown-extended dropdown-notification dropdown-dark" id="header_notification_bar">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
                            <i class="icon-bell"></i>
                            <span class="badge badge-success">7</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="external">
                                <h3><span class="bold">12 pending</span> notifications</h3>
                                <a href="#">view all</a>
                            </li>
                            <li>
                                <ul class="dropdown-menu-list scroller" style="height: 250px;" data-handle-color="#637283">
                                    <li><a href="javascript:;"><span class="time">just now</span><span class="details"><span class="label label-sm label-icon label-success"><i class="fa fa-plus"></i></span>New user registered.</span></a></li>
                                    <li><a href="javascript:;"><span class="time">3 mins</span><span class="details"><span class="label label-sm label-icon label-danger"><i class="fa fa-bolt"></i></span>Server #12 overloaded.</span></a></li>
                                    <li><a href="javascript:;"><span class="time">10 mins</span><span class="details"><span class="label label-sm label-icon label-warning"><i class="fa fa-bell-o"></i></span>Server #2 not responding.</span></a></li>
                                    <li><a href="javascript:;"><span class="time">14 hrs</span><span class="details"><span class="label label-sm label-icon label-info"><i class="fa fa-bullhorn"></i></span>Application error.</span></a></li>
                                    <li><a href="javascript:;"><span class="time">2 days</span><span class="details"><span class="label label-sm label-icon label-danger"><i class="fa fa-bolt"></i></span>Database overloaded 68%.</span></a></li>
                                    <li><a href="javascript:;"><span class="time">3 days</span><span class="details"><span class="label label-sm label-icon label-danger"><i class="fa fa-bolt"></i></span>A user IP blocked.</span></a></li>
                                    <li><a href="javascript:;"><span class="time">4 days</span><span class="details"><span class="label label-sm label-icon label-warning"><i class="fa fa-bell-o"></i></span>Storage Server #4 not responding dfdfdfd.</span></a></li>
                                    <li><a href="javascript:;"><span class="time">5 days</span><span class="details"><span class="label label-sm label-icon label-info"><i class="fa fa-bullhorn"></i></span>System Error.</span></a></li>
                                    <li><a href="javascript:;"><span class="time">9 days</span><span class="details"><span class="label label-sm label-icon label-danger"><i class="fa fa-bolt"></i></span>Storage server failed.</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <li class="dropdown dropdown-extended dropdown-inbox dropdown-dark" id="header_inbox_bar">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
                            <i class="icon-envelope-open"></i>
                            <span class="badge badge-danger">4</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="external">
                                <h3>You have <span class="bold">7 New</span> Messages</h3>
                                <a href="#">view all</a>
                            </li>
                            <li>
                                <ul class="dropdown-menu-list scroller" style="height: 275px;" data-handle-color="#637283">
                                    <li><a href="#"><span class="photo"><img src="{{ asset('assets/admin/layout4/img/avatar2.jpg') }}" class="img-circle" alt=""></span><span class="subject"><span class="from">Lisa Wong</span><span class="time">Just Now</span></span><span class="message">Vivamus sed auctor nibh congue nibh. auctor nibh auctor nibh...</span></a></li>
                                    <li><a href="#"><span class="photo"><img src="{{ asset('assets/admin/layout4/img/avatar3.jpg') }}" class="img-circle" alt=""></span><span class="subject"><span class="from">Richard Doe</span><span class="time">16 mins</span></span><span class="message">Vivamus sed congue nibh auctor nibh congue nibh. auctor nibh auctor nibh...</span></a></li>
                                    <li><a href="#"><span class="photo"><img src="{{ asset('assets/admin/layout4/img/avatar1.jpg') }}" class="img-circle" alt=""></span><span class="subject"><span class="from">Bob Nilson</span><span class="time">2 hrs</span></span><span class="message">Vivamus sed nibh auctor nibh congue nibh. auctor nibh auctor nibh...</span></a></li>
                                    <li><a href="#"><span class="photo"><img src="{{ asset('assets/admin/layout4/img/avatar2.jpg') }}" class="img-circle" alt=""></span><span class="subject"><span class="from">Lisa Wong</span><span class="time">40 mins</span></span><span class="message">Vivamus sed auctor 40% nibh congue nibh...</span></a></li>
                                    <li><a href="#"><span class="photo"><img src="{{ asset('assets/admin/layout4/img/avatar3.jpg') }}" class="img-circle" alt=""></span><span class="subject"><span class="from">Richard Doe</span><span class="time">46 mins</span></span><span class="message">Vivamus sed congue nibh auctor nibh congue nibh. auctor nibh auctor nibh...</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <li class="dropdown dropdown-extended dropdown-tasks dropdown-dark" id="header_task_bar">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
                            <i class="icon-calendar"></i>
                            <span class="badge badge-primary">3</span>
                        </a>
                        <ul class="dropdown-menu extended tasks">
                            <li class="external">
                                <h3>You have <span class="bold">12 pending</span> tasks</h3>
                                <a href="#">view all</a>
                            </li>
                            <li>
                                <ul class="dropdown-menu-list scroller" style="height: 275px;" data-handle-color="#637283">
                                    <li><a href="javascript:;"><span class="task"><span class="desc">New release v1.2</span><span class="percent">30%</span></span><span class="progress"><span style="width: 40%;" class="progress-bar progress-bar-success" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100"><span class="sr-only">40% Complete</span></span></span></a></li>
                                    <li><a href="javascript:;"><span class="task"><span class="desc">Application deployment</span><span class="percent">65%</span></span><span class="progress"><span style="width: 65%;" class="progress-bar progress-bar-danger" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"><span class="sr-only">65% Complete</span></span></span></a></li>
                                    <li><a href="javascript:;"><span class="task"><span class="desc">Mobile app release</span><span class="percent">98%</span></span><span class="progress"><span style="width: 98%;" class="progress-bar progress-bar-success" aria-valuenow="98" aria-valuemin="0" aria-valuemax="100"><span class="sr-only">98% Complete</span></span></span></a></li>
                                    <li><a href="javascript:;"><span class="task"><span class="desc">Database migration</span><span class="percent">10%</span></span><span class="progress"><span style="width: 10%;" class="progress-bar progress-bar-warning" aria-valuenow="10" aria-valuemin="0" aria-valuemax="100"><span class="sr-only">10% Complete</span></span></span></a></li>
                                    <li><a href="javascript:;"><span class="task"><span class="desc">Web server upgrade</span><span class="percent">58%</span></span><span class="progress"><span style="width: 58%;" class="progress-bar progress-bar-info" aria-valuenow="58" aria-valuemin="0" aria-valuemax="100"><span class="sr-only">58% Complete</span></span></span></a></li>
                                    <li><a href="javascript:;"><span class="task"><span class="desc">Mobile development</span><span class="percent">85%</span></span><span class="progress"><span style="width: 85%;" class="progress-bar progress-bar-success" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100"><span class="sr-only">85% Complete</span></span></span></a></li>
                                    <li><a href="javascript:;"><span class="task"><span class="desc">New UI release</span><span class="percent">38%</span></span><span class="progress progress-striped"><span style="width: 38%;" class="progress-bar progress-bar-important" aria-valuenow="18" aria-valuemin="0" aria-valuemax="100"><span class="sr-only">38% Complete</span></span></span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <li class="dropdown dropdown-user dropdown-dark">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
                            <span class="username username-hide-on-mobile">{{ $adminUser->aid ?? '' }}</span>
                            {{--
                                AdminCP User Dropdown Exact DOM Parity
                                Correction: legacy's trigger really does
                                include `<img alt="" class="img-circle"
                                src="...">` (`navigation_menu.php:316`) —
                                confirmed rendered at 39x39 with
                                `margin-right:5px;margin-top:-8px` via
                                `.dropdown-user .dropdown-toggle>img`'s real
                                CSS (`height:39px`, no explicit width — a
                                square source image lets that height rule
                                size both dimensions identically). Legacy's
                                own src is `$_COOKIE['avatar']`, a cookie no
                                login path ever sets — always broken there
                                too, not a capability to reproduce. A 1x1
                                transparent PNG data URI reproduces the exact
                                geometry with zero network request, so it
                                never 404s merely to imitate a legacy bug.
                            --}}
                            <img alt="" class="img-circle" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=">
                        </a>
                        {{--
                            Corrected 2026-08-26 (owner-supplied real
                            opened-dropdown screenshot, decision-log #41's
                            reversal extended again): the Profile/Calendar/
                            Messages/Tasks/Lock-Screen entries are real,
                            visible content in production — reproduced
                            verbatim from `navigation_menu.php`'s own real
                            "USER LOGIN DROPDOWN" markup, dead `href="#"`
                            targets included (exactly as inert in legacy as
                            here). Only "تسجيل الخروج" below is real —
                            the actual, unchanged, CSRF-protected POST
                            logout, now shaped as a real `.dropdown-menu>li>a`
                            (see the row below) instead of a `<button>` that
                            selector never matched.
                        --}}
                        <ul class="dropdown-menu dropdown-menu-default">
                            <li><a href="#"><i class="icon-user"></i> الملف الشخصي</a></li>
                            <li><a href="#"><i class="icon-calendar"></i> التقويم</a></li>
                            <li><a href="#"><i class="icon-envelope-open"></i> الرسائل الخاصة <span class="badge badge-danger">3</span></a></li>
                            <li><a href="#"><i class="icon-rocket"></i> المهام الوظيفية <span class="badge badge-success">7</span></a></li>
                            <li class="divider"></li>
                            <li><a href="#"><i class="icon-lock"></i> شاشة القفل</a></li>
                            <li>
                                {{--
                                    AdminCP User Dropdown Exact DOM Parity
                                    Correction: the visible element is now a
                                    real `<a>` — a direct `.dropdown-menu>li>a`
                                    child, matching legacy's own
                                    `<a href="index.php?op=logout">` exactly,
                                    so it inherits every real Metronic rule
                                    (padding, font, and `:hover{background-
                                    color:#eee}`) instead of reproducing them
                                    by hand on a `<button>`. It only prevents
                                    its own default `#` navigation and submits
                                    the real hidden POST+CSRF form below —
                                    AdminGuard's logout semantics, CSRF
                                    protection, and POST-only routing are
                                    completely unchanged, never reverted to
                                    legacy's insecure GET.
                                --}}
                                <a href="#" id="admin-logout-link"><i class="icon-key"></i> تسجيل الخروج</a>
                                <form method="POST" action="{{ route('admin.logout') }}" id="admin-logout-form" style="display:none">
                                    @csrf
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
<div class="clearfix"></div>

<div class="page-container">
    <div class="page-sidebar-wrapper">
        <div class="page-sidebar navbar-collapse collapse">
            <ul class="page-sidebar-menu page-sidebar-menu-hover-submenu1" data-keep-expanded="false" data-auto-scroll="true" data-slide-speed="200">
                <li class="heading">
                    <h3>لوحة التحكم</h3>
                </li>
                <li class="{{ request()->routeIs('admin.entry') ? 'active' : '' }}">
                    <a href="{{ route('admin.entry') }}">
                        <i class="icon-home"></i>
                        <span class="title">الرئيسية</span>
                    </a>
                </li>
                @foreach ($sidebarModules ?? [] as $module)
                    @if (isset($module['children']))
                        {{--
                            AdminCP Shared Navbar/Sidebar Final Reconstruction: a
                            group can have multiple children whose routes share
                            the same `beforeLast(route, '.')` prefix (e.g.
                            `admin.link-quality.khotab.index` and
                            `...khotab.large-files` both reduce to
                            `admin.link-quality.khotab`) — the previous
                            prefix-wildcard check then matched EVERY such
                            sibling at once (visiting large-files also lit up
                            khotab.index). When the current route is exactly one
                            of this group's own listed children, only that
                            exact child is considered active — the wildcard
                            fallback (needed so a non-sidebar-listed sub-route
                            like `admin.broadcasting.edit` still highlights its
                            one listed sibling `admin.broadcasting.index`) only
                            applies when the current route isn't already one of
                            this group's own routes.
                        --}}
                        @php
                            $siblingRoutes = collect($module['children'])->pluck('route')->all();
                            $currentRouteName = request()->route()?->getName();
                            $exactSiblingMatch = in_array($currentRouteName, $siblingRoutes, true);
                            $moduleActive = collect($module['children'])->contains(fn ($child) => $currentRouteName === $child['route']
                                || (! $exactSiblingMatch && request()->routeIs(\Illuminate\Support\Str::beforeLast($child['route'], '.').'.*')));
                        @endphp
                        <li class="{{ $moduleActive ? 'active open' : '' }}">
                            <a href="javascript:;">
                                <i class="{{ $module['icon'] }}"></i>
                                <span class="title">{{ $module['label'] }}</span>
                                <span class="arrow "></span>
                            </a>
                            <ul class="sub-menu">
                                @foreach ($module['children'] as $child)
                                    @php
                                        $childActive = $currentRouteName === $child['route']
                                            || (! $exactSiblingMatch && request()->routeIs(\Illuminate\Support\Str::beforeLast($child['route'], '.').'.*'));
                                    @endphp
                                    <li class="{{ $childActive ? 'active' : '' }}">
                                        <a href="{{ route($child['route']) }}">{{ $child['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        @php($activePrefix = \Illuminate\Support\Str::beforeLast($module['route'], '.'))
                        <li class="{{ request()->routeIs($activePrefix.'.*') || request()->routeIs($module['route']) ? 'active' : '' }}">
                            <a href="{{ route($module['route']) }}">
                                <i class="{{ $module['icon'] }}"></i>
                                <span class="title">{{ $module['label'] }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    </div>

    <div class="page-content-wrapper">
        <div class="page-content">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row">
                @yield('content')
            </div>
        </div>
    </div>
</div>

<div class="page-footer">
    <div class="page-footer-inner">
        جميع الحقوق محفوظة لـ شبكة الطريق إلى الله &copy; 2005 - {{ date('Y') }}
    </div>
</div>

<script src="{{ asset('assets/global/plugins/jquery.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/global/plugins/bootstrap/js/bootstrap.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/global/plugins/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/global/scripts/app.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/layouts/layout4/scripts/layout.min.js') }}" type="text/javascript"></script>
{{--
    AdminCP User Dropdown Exact DOM Parity Correction: the visible logout
    row is a real `<a>` (see the user dropdown above); this only prevents
    its dead `#` navigation and submits the real hidden POST+CSRF form —
    AdminGuard's logout/CSRF/POST-only semantics are entirely unchanged.
--}}
<script>
    jQuery(document).ready(function () {
        jQuery('#admin-logout-link').on('click', function (e) {
            e.preventDefault();
            jQuery('#admin-logout-form').trigger('submit');
        });
    });
</script>
@stack('scripts')
{{--
    AdminCP Shared Navbar/Sidebar Final Reconstruction: `layout.min.js`'s
    own last line self-initializes
    (`App.isAngularJsApp()===!1&&jQuery(document).ready(function(){Layout.init()})`)
    — real legacy `footer.php` never calls `Layout.init()` explicitly itself,
    it only wires the dynamic `$jquery_ready_functions` placeholder. This
    layout previously called `Layout.init()` a second time here, which
    double-bound every delegated sidebar click handler
    (`o()`'s `$(".page-sidebar").on("click","li > a",...)`) — two handlers
    firing on one click toggled `open` then immediately back off, so
    clicking a parent module visibly did nothing (confirmed via a live
    handler-count check: 6 bound instead of legacy's real 3). Removed; the
    script's own self-init is sufficient and matches legacy exactly.
--}}
</body>
</html>

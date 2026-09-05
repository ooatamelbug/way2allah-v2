<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Batch 4 (media player, khotab-item-298784.htm): not in legacy —
         legacy has no CSRF concept at all. Laravel's CSRF protection is
         active for the new /media-player POST endpoint (no route
         exemption), so any page's AJAX JS needs a way to read the token;
         a sitewide <meta> tag is the standard Laravel convention for this,
         reused from here rather than duplicated per page. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ config('app.name') }}</title>
    <meta name="description" content="@yield('meta_description', 'شبكة الطريق إلى الله: مكتبة إسلامية مرئية وصوتية شاملة.')">

    {{--
        Business Demo wiring only — reproduces header.php's own stylesheet
        order (lines 86-139) via Laravel-relative paths (/assets/..., /css/...)
        served through the public/assets and public/css symlinks, in place
        of header.php's hardcoded https://way2allah.com/ $siteurl prefix.
        Intentionally NOT reproduced: shams_custom.css, only loaded when
        ?shams=ok (header.php:121-129) —
        custom.css is the default-case stylesheet, used here instead; and
        fetch_css()'s page-specific dynamic CSS (e.g. fatawa/tobics.php's
        own extra stylesheet), a per-page hook this shared layout has no
        equivalent for yet.
    --}}
    <link rel="shortcut icon" href="/css/images/favicon.ico">

    <link href="/assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <link href="/assets/global/plugins/bootstrap/css/bootstrap-rtl.min.css" rel="stylesheet">

    <link href="/assets/global/css/components-rtl.css" rel="stylesheet">
    <link href="/assets/frontend/layout/css/style.css" rel="stylesheet">
    <link href="/assets/frontend/pages/css/style-revolution-slider.css" rel="stylesheet">
    <link href="/assets/frontend/layout/css/style-responsive.css" rel="stylesheet">
    <link href="/assets/frontend/layout/css/themes/blue.css" rel="stylesheet" id="style-color">
    <link href="/assets/frontend/layout/css/custom.css" rel="stylesheet">

    <link href="/assets/layouts/layout/css/layout-rtl.css" rel="stylesheet" type="text/css"/>
    <link href="/assets/global/css/plugins.css" rel="stylesheet" type="text/css"/>
    <link href="/assets/frontend/layout/scripts/w2a/styles.css" rel="stylesheet" />

    {{--
        AddThis widget investigation (visual/CSS parity phase) —
        header.php:147-148's AddThis script: unconditional, sitewide,
        no `if` gate of any kind — confirmed by direct reading. Confirmed
        missing from Laravel entirely prior to this change.
    --}}
    <script type="text/javascript" src="//s7.addthis.com/js/300/addthis_widget.js#pubid=ra-6320ffadd9bb2e6e"></script>

    {{--
        G-13-06 (media/visual parity phase): header.php:103-109's
        `$header['css']['slider']==true` plugin styles — previously
        undocumented as *conditional*, not unconditionally skipped. Only
        `index.php:24` ever sets this flag (confirmed — every other file
        that references it has the assignment commented out), and
        `home.blade.php` is now that page's real Laravel equivalent, so
        this is no longer the "no migrated page sets it" case the layout's
        earlier comment above described. @stack so every other page's
        <head> is unaffected, matching legacy's own per-page conditional.
    --}}
    @stack('styles')
    <link href="/assets/frontend/layout/css/premium-ui.css" rel="stylesheet" type="text/css">
    @stack('page-styles')
</head>
<body class="corporate">
<a class="w2a-skip-link" href="#main-content">انتقل إلى المحتوى الرئيسي</a>
{{--
    Global Chrome step — ported from legacy header.php (pre-header/header/
    nav/search) and footer.php (pre-footer/footer/scripts), reusing the
    same CSS already wired above and the JS below, all served through the
    public/assets, public/css and public/w2a_autocomplete symlinks. GLOBAL
    CHROME ONLY: module-specific breadcrumbs (page_bar()-equivalents),
    per-page portlet wrappers, and page-specific extra CSS are intentionally
    NOT included here — see the investigation report for that scope split.
--}}

<!-- BEGIN TOP BAR (header.php:200-237) -->
<div class="pre-header">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 col-sm-6 additional-shop-info sm-header-list">
                <ul class="list-unstyled list-inline">
                    <li><i class="fa fa-envelope-o"></i><span><a href="https://forums.way2allah.com/" target="_blank">المنتــــدى</a></span></li>
                    <li><i class="fa fa-envelope-o"></i><span><a href="https://way2allah.com/yarb" target="_blank">راجعلك يارب</a></span></li>
                    <li><i class="fa fa-envelope-o"></i><span><a href="https://way2allah.com/english/" target="_blank">English</a></span></li>
                </ul>
            </div>
            <div class="col-md-6 col-sm-6 social-media">
                <ul class="social-footer list-unstyled list-inline pull-right">
                    <li><a href="https://www.facebook.com/Way2allahCom" target="_blank"><i class="fa fa-facebook"></i></a></li>
                    <li><a href="https://twitter.com/way2allahcom" target="_blank"><i class="fa fa-twitter"></i></a></li>
                    <li><a href="https://t.me/way2allahcom" target="_blank"><i class="fa fa-paper-plane"></i></a></li>
                    <li><a href="https://instagram.com/way2allahcom" target="_blank"><i class="fa fa-instagram"></i></a></li>
                    <li><a href="https://www.youtube.com/channel/UC2iUAy5i4kNR2Q9pckZ3pWQ" target="_blank"><i class="fa fa-youtube"></i></a></li>
                    <li><a href="https://soundcloud.com/way2allahcom/" target="_blank"><i class="fa fa-soundcloud"></i></a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- END TOP BAR -->

<!-- BEGIN HEADER (header.php:238-530) -->
<div class="header">
    <div class="container">
        {{-- legacy: href="/index.php" — adapted to "/", Laravel's own root route, not the unported legacy home page --}}
        <a class="site-logo" href="/"><img id="logo-light" src="/logo-light.png" alt="الطريق إلى الله"></a>

        <button type="button" class="mobi-toggler" aria-controls="w2a-primary-navigation" aria-expanded="false" aria-label="فتح القائمة الرئيسية">
            <i class="fa fa-bars" aria-hidden="true"></i>
        </button>

        <!-- BEGIN NAVIGATION -->
        <div class="header-navigation pull-right font-transform-inherit">
            @include('layouts.partials.navigation')
        </div>
        <!-- END NAVIGATION -->
    </div>
</div>
<!-- Header END -->

{{--
    G-13-06 (media/visual parity phase): header.php:532-535's
    `$display_slider` include — the same index.php-only condition as the
    styles/scripts stacks above. @yield so non-home pages render nothing
    here, matching legacy exactly (they never set the flag at all).
--}}
@yield('slider')

<main class="main" id="main-content" tabindex="-1">
    <div class="container">
        {{--
            AddThis widget investigation (visual/CSS parity phase) —
            functions.php:749-757's `share()`, exact markup (including its
            `style=" float: left;"` spacing, byte-matched against live
            production). Confirmed via 7 live page types (homepage,
            khotab listing/detail, fatawa-categories, gallery, anasheed
            group, channels) to render at this exact position — immediately
            inside .main .container, before any page-specific content —
            on every page, not just the homepage. No local caller for
            `share()` was ever found (exhaustive search); rendered here
            unconditionally in the shared layout instead, matching the
            confirmed real position rather than guessing a per-page call.
        --}}
        <div class="row">
            <div class="col-sm-12">
                <div style=" float: left;" class="addthis_inline_share_toolbox addthis_sharing_toolbox"></div>
            </div>
        </div>

        @yield('content')
    </div>
</main>

<div class="w2a-footer"></div>

<!-- BEGIN PRE-FOOTER (footer.php:13-33) -->
<div class="pre-footer">
    <div class="row">
        <div class="footer-container">
            <div class="app-promo">
                <h4>حمل التطبيق الآن</h4>
                <div class="app-buttons">
                    <a href="https://play.google.com/store/apps/details?id=com.way2allah.app" target="_blank">
                        <img src="/google-play.svg" alt="Get it on Google Play" class="app-badge" height="43">
                    </a>
                    <a href="https://apps.apple.com/us/app/%D8%A7%D9%84%D8%B7%D8%B1%D9%8A%D9%82-%D8%A5%D9%84%D9%89-%D8%A7%D9%84%D9%84%D9%87/id6480062523" target="_blank">
                        <img src="/app-store.svg" alt="Download on the App Store" class="app-badge" height="43">
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- END PRE-FOOTER -->

<!-- BEGIN FOOTER (footer.php:35-60) -->
<div class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-sm-8 padding-top-10 copyrights">
                <p>{{ now()->year }} © شبكة الطريق إلى الله. جميع الحقوق محفوظة. </p>
                <a href="#">سياسة الخصوصية</a> | <a href="#">شروط و احكام</a>
            </div>
            <div class="col-md-4 col-sm-4 social-media">
                <ul class="social-footer list-unstyled list-inline pull-right">
                    <li><a href="https://www.facebook.com/Way2allahCom" target="_blank"><i class="fa fa-facebook"></i></a></li>
                    <li><a href="https://twitter.com/way2allahcom" target="_blank"><i class="fa fa-twitter"></i></a></li>
                    <li><a href="https://t.me/way2allahcom" target="_blank"><i class="fa fa-paper-plane"></i></a></li>
                    <li><a href="https://instagram.com/way2allahcom" target="_blank"><i class="fa fa-instagram"></i></a></li>
                    <li><a href="https://www.youtube.com/channel/UC2iUAy5i4kNR2Q9pckZ3pWQ" target="_blank"><i class="fa fa-youtube"></i></a></li>
                    <li><a href="https://soundcloud.com/way2allahcom/" target="_blank"><i class="fa fa-soundcloud"></i></a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- END FOOTER -->

<!-- BEGIN CORE PLUGINS (footer.php:63-121) -->
<script src="/assets/global/plugins/jquery.min.js" type="text/javascript"></script>
<script type="text/javascript" language="javascript" src="/assets/frontend/layout/scripts/inc.js"></script>
<script src="/assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
<script src="/assets/global/plugins/jquery-migrate.min.js" type="text/javascript"></script>
<script src="/assets/frontend/layout/scripts/back-to-top.js" type="text/javascript"></script>
<script src="/assets/global/plugins/fancybox/source/jquery.fancybox.pack.js" type="text/javascript"></script>
<script src="/assets/global/plugins/carousel-owl-carousel/owl-carousel/owl.carousel-rtl.js" type="text/javascript"></script>
<script src="/assets/global/scripts/app.min.js" type="text/javascript"></script>
<script src="/assets/frontend/layout/scripts/layout.js" type="text/javascript"></script>
<script type="text/javascript" src="/assets/frontend/layout/scripts/w2a/jquery.mockjax.js"></script>
<script type="text/javascript" src="/assets/frontend/layout/scripts/w2a/jquery.autocomplete.js"></script>
<script type="text/javascript" src="/assets/frontend/layout/scripts/w2a/demo.js"></script>
<script type="text/javascript">
    jQuery(document).ready(function() {
        Layout.init();
        Layout.initOWL();
        Layout.initTwitter();
        Layout.initNavScrolling();
        Layout.initFixHeaderWithPreHeader();
    });
</script>
<!-- END CORE PLUGINS -->
{{-- G-13-06: footer.php:84-91's `$footer['js']['slider']==true` RevolutionSlider scripts — same index.php-only condition as the styles/slider-markup stacks above. --}}
@stack('scripts')
<script src="/assets/frontend/layout/scripts/premium-ui.js" defer></script>
</body>
</html>

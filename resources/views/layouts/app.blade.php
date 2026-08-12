<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>@yield('title') - {{ config('app.name') }}</title>

    {{--
        Business Demo wiring only — reproduces header.php's own stylesheet
        order (lines 86-139) via Laravel-relative paths (/assets/..., /css/...)
        served through the public/assets and public/css symlinks, in place
        of header.php's hardcoded https://way2allah.com/ $siteurl prefix.
        Intentionally NOT reproduced: the 3 slider/carousel plugin styles
        (fancybox/owl-carousel/revolution-slider, header.php:106-108), gated
        behind $header['css']['slider']==true which no migrated page sets;
        shams_custom.css, only loaded when ?shams=ok (header.php:121-129) —
        custom.css is the default-case stylesheet, used here instead; and
        fetch_css()'s page-specific dynamic CSS (e.g. fatawa/tobics.php's
        own extra stylesheet), a per-page hook this shared layout has no
        equivalent for yet.
    --}}
    <link rel="shortcut icon" href="/css/images/favicon.ico">

    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700|PT+Sans+Narrow|Source+Sans+Pro:200,300,400,600,700,900&amp;subset=all"
          rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">

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
</head>
<body class="corporate">
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

        <a href="javascript:void(0);" class="mobi-toggler"><i class="fa fa-bars"></i></a>

        <!-- BEGIN NAVIGATION -->
        <div class="header-navigation pull-right font-transform-inherit">
            @include('layouts.partials.navigation')
        </div>
        <!-- END NAVIGATION -->
    </div>
</div>
<!-- Header END -->

<div class="main">
    <div class="container">
        @yield('content')
    </div>
</div>

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
</body>
</html>

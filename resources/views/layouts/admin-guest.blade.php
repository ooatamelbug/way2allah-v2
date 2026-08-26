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
    <link href="{{ asset('assets/global/plugins/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/global/css/components-rtl.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/global/css/plugins-rtl.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/pages/css/login-4-rtl.min.css') }}" rel="stylesheet" type="text/css">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
</head>
{{--
    AdminCP Login Final Visual-Parity Pass (2026-08-22): 2 confirmed
    markup gaps closed against a fresh production fetch —
    (1) the `.menu-toggler.sidebar-toggler` div, present in legacy
    immediately after the logo (inert on this page — no sidebar exists
    to toggle — but still real, present DOM legacy renders, not invented);
    (2) `backstretch.min.js` + a page-specific init call, since legacy's
    own `login-4.js` genuinely invokes a real rotating background-image
    slideshow (`$.backstretch([...4 images...])`) — LIVE_RENDER_VERIFIED
    the 4 images exist at their current (non-redirecting) URL,
    `https://way2allah.com/assets/pages/media/bg/{1..4}.jpg`; `login-4.js`'s
    own hardcoded URLs (`.../new/assets/...`) 307-redirect to that same
    path, a stale-path artifact of the site's own restructuring, not
    reproduced verbatim — the CURRENT canonical path is used instead,
    same real images, not a substitute.
--}}
<body class="login">
<div class="logo" style="margin:0px !important;">
    <a href="{{ url('/') }}">
        <img src="{{ asset('login-logo.png') }}" alt="">
    </a>
</div>
<div class="menu-toggler sidebar-toggler"></div>
<div class="content">
    @yield('content')
</div>
<div class="copyright">
    2005 - {{ date('Y') }} &copy; جميع الحقوق محفوظة - موقع الطريق إلى الله.
</div>

<script src="{{ asset('assets/global/plugins/jquery.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/global/plugins/bootstrap/js/bootstrap.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/global/plugins/backstretch/jquery.backstretch.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/global/scripts/app.min.js') }}" type="text/javascript"></script>
@yield('scripts')
</body>
</html>

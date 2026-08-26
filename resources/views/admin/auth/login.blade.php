@extends('layouts.admin-guest')

{{--
    AdminCP Full Visual/Layout Parity Reconstruction (2026-08-22):
    presentation reconstructed from legacy `admincp/w2a__login.php`
    (LIVE_RENDER_VERIFIED against an anonymous GET of the real production
    `/admincp/` — byte-identical). The hardened Laravel auth flow itself
    (POST, CSRF, AdminGuard::attempt(), generic invalid-credential error,
    `old('aid')` without `old('password')`) is unchanged from the prior
    `/admincp/` Login + Dashboard Completion task — only markup/classes
    moved toward legacy.

    Deliberately NOT reproduced from `w2a__login.php`:
    - the "تذكرني" (remember me) checkbox — `admincp/main.php`'s own
      `login()` never reads `$_POST['remember']` anywhere; it was
      dead/cosmetic in legacy too, and adding real remember-me behavior
      here would be new auth surface, out of this task's scope.
    - the hidden `pwd_md5`/`op` inputs — vBulletin client-side MD5-hashing
      artifacts from the old form-submit flow; irrelevant to
      AdminGuard::attempt()'s own server-side verification.
    - the static `display-hide` alert box with a fixed placeholder
      message — replaced with the real, server-rendered validation error
      ($errors->first()), same `alert alert-danger` classes.
    - the stale hardcoded "2005 - 2015" copyright year (legacy bug,
      layout owns the real dynamic year instead) and the "تسجيل دلللخول"
      `<title>` typo (a genuine, LIVE_RENDER_VERIFIED-confirmed legacy
      typo — "تسجيل الدخول" spelled correctly here, not reproduced).

    AdminCP Login Final Visual-Parity Pass (2026-08-22), fixed this pass:
    - the IE8/9-only `<label class="control-label visible-ie8
      visible-ie9">` before each field — present in legacy (invisible in
      any real 2026 browser via its own CSS classes, but real, harmless
      DOM legacy renders — added for markup parity, not functional gain).
    - `class="login-form"` + native `required` attributes removed in
      favor of `login-4.js`'s own real client-side validation
      (`jquery.validate` + `additional-methods`, self-hosted, already
      present locally) — legacy's own inputs carry no `required`
      attribute either, relying entirely on the JS validator; keeping
      both would show duplicate (native + JS) validation UI.

    AdminCP Login Browser-Visual Parity Investigation (2026-08-22, same
    day): the owner's browser observation of a material mismatch was
    real, root-caused via a headless-Chrome screenshot diff, not
    dismissed. `$.backstretch(...)` itself was never the bug (confirmed:
    the exact production `jquery.backstretch.min.js`, byte-identical
    md5, DOES define a static `$.backstretch` alias, contrary to a first
    reading of only its `$.fn.backstretch` definition) — the actual
    root cause was the 4 background images being referenced from the
    live `way2allah.com` origin instead of self-hosted: a real,
    screenshot-reproduced loading delay (a fresh cross-origin fetch of
    4 unbundled images on every page load, no local caching) left the
    page visibly plain/white for long enough to look "materially
    different" from production's own presumably-fast local serving —
    confirmed by re-screenshotting with a longer virtual-time-budget,
    at which point the background renders identically. Fixed by
    self-hosting the 4 images at `public/vendor/login-bg/` (a genuine
    Laravel-only path, same convention as the Leaflet assets) instead
    of pointing at the remote origin.
--}}
@section('title', 'تسجيل الدخول')

@section('content')
    <form method="POST" action="{{ route('admin.login') }}" class="login-form">
        @csrf
        <h3 class="form-title">تسجيل الدخول</h3>

        @if ($errors->any())
            <div class="alert alert-danger">
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="form-group">
            <label class="control-label visible-ie8 visible-ie9">اسم المستخدم</label>
            <div class="input-icon">
                <i class="fa fa-user"></i>
                <input class="form-control placeholder-no-fix" type="text" id="aid" name="aid"
                       value="{{ old('aid') }}" autocomplete="username" placeholder="اسم المستخدم"
                       autofocus>
            </div>
        </div>

        <div class="form-group">
            <label class="control-label visible-ie8 visible-ie9">كلمة المرور</label>
            <div class="input-icon">
                <i class="fa fa-lock"></i>
                <input class="form-control placeholder-no-fix" type="password" id="password" name="password"
                       autocomplete="current-password" placeholder="كلمة المرور">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn blue pull-right">
                تسجيل دخول <i class="m-icon-swapright m-icon-white"></i>
            </button>
        </div>

        <div class="forget-password">
            <h4>هل نسيت كلمة المرور ?</h4>
            <p>لا تقلق يمكنك استعادتها بالضغط <a href="https://forums.way2allah.com/login.php?do=lostpw">هنا</a></p>
        </div>
        <div class="create-account">
            <p>ليس لديك عضوية على موقعنا ?&nbsp; <a href="https://forums.way2allah.com/register.php">إنشاء عضوية جديدة</a></p>
        </div>
    </form>
@endsection

@section('scripts')
    <script>
        jQuery(document).ready(function () {
            $('.login-form').validate({
                errorElement: 'span',
                errorClass: 'help-block',
                focusInvalid: false,
                rules: {
                    aid: { required: true },
                    password: { required: true }
                },
                highlight: function (element) {
                    $(element).closest('.form-group').addClass('has-error');
                },
                success: function (label) {
                    label.closest('.form-group').removeClass('has-error');
                    label.remove();
                },
                errorPlacement: function (error, element) {
                    error.insertAfter(element.closest('.input-icon'));
                }
            });

            $.backstretch([
                "{{ asset('vendor/login-bg/1.jpg') }}",
                "{{ asset('vendor/login-bg/2.jpg') }}",
                "{{ asset('vendor/login-bg/3.jpg') }}",
                "{{ asset('vendor/login-bg/4.jpg') }}"
            ], { fade: 1000, duration: 800 });
        });
    </script>
@endsection

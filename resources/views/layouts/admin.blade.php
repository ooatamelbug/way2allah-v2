<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>@yield('title') - لوحة التحكم</title>
</head>
<body>
{{--
    Wave 5 scope boundary, same shape as layouts/app.blade.php's Wave 2
    note: the legacy Metronic admin theme's chrome (home.php's fake
    dashboard stats, navigation_menu.php's fake notifications) is
    confirmed dead/demo content (admincp.md §5, Blueprint §17 "Can be
    removed") — not reproduced. sidebar.php's real, functional half (real
    permission-gated nav) is represented here as a minimal real nav, not
    the Metronic markup around it.
--}}
{{--
    No dashboard/logout nav here — building a real login/logout flow is
    outside this wave's 10 tasks (AdminGuard, Wave 0, already handles
    authentication itself; nothing in the Roadmap asks for a new UI around
    it this round). Each feature page links back to its own index only.
--}}
<main>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif
    <h1>@yield('title')</h1>
    @yield('content')
</main>
</body>
</html>

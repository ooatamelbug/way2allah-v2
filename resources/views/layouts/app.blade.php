<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>@yield('title') - {{ config('app.name') }}</title>
</head>
<body>
{{--
    Wave 2 scope boundary, not a legacy-behavior decision: the legacy
    w2a_header()/w2a_footer()/breadcrumb() site chrome (navigation, menus,
    session-aware elements) is not reproduced here. Roadmap task 2.1 scopes
    Wave 2 to proving the coexistence routing/content on the simplest
    possible real pages; the shared site chrome is a cross-cutting concern
    for a later wave once more of the site exists to navigate to. This
    layout intentionally renders content only, wrapped in the minimum HTML
    needed for a valid, correctly-directioned (RTL/Arabic) page.
--}}
<main>
    <h1>@yield('title')</h1>
    @yield('content')
</main>
</body>
</html>

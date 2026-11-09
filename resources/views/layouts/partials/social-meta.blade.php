{{--
    IF-056 — Gen 2 `header.php` social-sharing tags, emitted once per page.
    og:title = the page title without the site-name suffix (Gen 2
    `$header['title']`); og:type is always "website" (Gen 2 hard-codes it);
    the image is the page's `social_image` section (Khotab item frame / gif)
    or Gen 2's default `logo-light.png`. Gen 2's non-standard duplicate
    `<meta name="og:image">` is not repeated. Values come from already
    rendered sections and are escaped exactly once here.
--}}
@php
    $socialTitle = \App\Support\Http\SocialMetadata::text($__env->yieldContent('title'));
    $socialDescription = \App\Support\Http\SocialMetadata::text($__env->yieldContent('meta_description', $defaultDescription));
    $socialImage = \App\Support\Http\SocialMetadata::text($__env->yieldContent('social_image'));
    $socialImage = $socialImage !== ''
        ? \App\Support\Http\SocialMetadata::absoluteUrl($socialImage)
        : \App\Support\Http\SocialMetadata::defaultImage();
    $socialUrl = \App\Support\Http\SocialMetadata::currentUrl(request());
@endphp
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:title" content="{{ $socialTitle }}">
    <meta property="og:url" content="{{ $socialUrl }}">
    <meta property="og:description" content="{{ $socialDescription }}">
    <meta property="og:type" content="website">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <link rel="image_src" href="{{ $socialImage }}">
    <link rel="videothumbnail" href="{{ $socialImage }}">

<!DOCTYPE html>
{{-- khotab-advfile-{id}.htm — OLD ShowAdFile($id, 0): the bare promotional file,
     shown in the item page's popup. Rendered through SafeLegacyHtmlRenderer,
     never raw. --}}
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    <title>الملف الدعائي{{ $item ? ' - '.$item->title : '' }}</title>
    <link href="/assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <style>body{direction:rtl;text-align:center;padding:10px;font-family:Tahoma,Arial,sans-serif;background:#fff}</style>
</head>
<body>
    @contextualAdmin
        @include('advfiles._admin', ['returnTo' => '/khotab-item-'.(int) $file->id.'.htm', 'frameTarget' => '_top'])
    @endcontextualAdmin
    <div class="w2a-advfile">{!! $html !!}</div>
</body>
</html>

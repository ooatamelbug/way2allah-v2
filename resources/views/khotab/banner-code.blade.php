{{-- OLD code-html.htm / code-vb.htm / code-bb.htm (Codes(), modules/Khotab/index.php): the banner's
     embed code, opened in the banner's popup. A small standalone page, as OLD's was. Every value is
     escaped; the links point at this site. --}}
@php
    $site = rtrim(url('/'), '/');
    $itemUrl = $site.'/khotab-item-'.$item->id.'.htm';
    $imageUrl = str_starts_with($banner['url'], 'http') ? $banner['url'] : $site.$banner['url'];
    $authorName = trim(($item->authorModel->prename ?? '').' '.($item->authorModel->name ?? ''));
    $caption = $item->title.' لـ'.$authorName.' - موقع '.$siteName;
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $item->title }}</title>
    <style>body{font-family:Tahoma,Arial,sans-serif;background:#fff;color:#222;margin:12px;text-align:center}textarea{width:100%;max-width:560px;direction:ltr}</style>
</head>
<body>
@if ($type === 1)
    <p><font color="#FF0000"><b>قم بنسخ الكود التالي و ضعه في صفحة موقعك بلغة HTML</b></font></p>
    <textarea name="code" id="code" cols="65" rows="7" readonly>{{ '<center><b><a target="_blank" href="'.$itemUrl.'"><img src="'.$imageUrl.'" border="0" Width="'.$banner['width'].'" Height="'.$banner['height'].'" alt="'.e($caption).'" /><br />'.e($caption).'</a></b></center>' }}</textarea>
@else
    <p><font color="#0000FF"><b>قم بنسخ الكود التالي و ضعه في توقيعك أو مشاركاتك بمنتديات {{ $type === 2 ? 'VBulletin' : 'phpBB' }}</b></font></p>
    <textarea name="code" id="code" cols="65" rows="7" readonly>{{ "[CENTER][URL=\"{$itemUrl}\"][IMG]{$imageUrl} [/IMG][/URL]\n[B][URL=\"{$itemUrl}\"]{$item->title} [/URL][/B]\n[B][URL=\"{$itemUrl}\"]{$authorName} - موقع {$siteName} [/URL][/B][/CENTER]" }}</textarea>
@endif
</body>
</html>

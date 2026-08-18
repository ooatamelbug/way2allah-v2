{{--
    Verbatim translation of anasheed/functions.php:662-668's HTML body
    (anasheed_send_friend()). Two links, both site-relative in legacy
    ($siteurl.'var-item-'.$id.'.htm' / $siteurl.'var-group-'.$id.'.htm'):
    the item itself, and its group ("السلسلة") — group's title comes from
    AnasheedItemController::sendToFriend()'s own eager-loaded relation,
    matching legacy's fresh SELECT id,title FROM nuke_anasheed_groups.
--}}
<div dir="rtl">
<p>السلام عليكم / {{ $friendName }}</p>
<p>صديقك <strong>{{ $yourName }}</strong> قام بارسال المادة التالية من موقع الطريق الى الله ، </p>
<p>عنوان المادة : <a href="{{ url('/var-item-'.$anasheedItem->id.'.htm') }}">{{ $anasheedItem->title }}</a></p>
@if ($anasheedItem->group)
<p>السلسلة : <a href="{{ url('/var-group-'.$anasheedItem->group->id.'.htm') }}">{{ $anasheedItem->group->title }}</a></p>
@endif
<p>نتمنى ان تحوز على اعجابكم</p>
</div>

{{--
    Verbatim translation of khotab/functions.php:1216-1221's HTML body
    (khotab_send_friend()), except the item link — see KhotabFriendMail's
    own docblock for why `khotab-item-{id}.htm` is used instead of
    legacy's own `var-item-{id}.htm` (a confirmed copy-paste bug).
--}}
<div dir="rtl">
<p>السلام عليكم / {{ $friendName }}</p>
<p>صديقك <strong>{{ $yourName }}</strong> قام بارسال المادة التالية من موقع الطريق الى الله ، </p>
<p>عنوان المادة : <a href="{{ url('/khotab-item-'.$khotabItem->id.'.htm') }}">{{ $khotabItem->title }}</a></p>
<p>نتمنى ان تحوز على اعجابكم</p>
</div>

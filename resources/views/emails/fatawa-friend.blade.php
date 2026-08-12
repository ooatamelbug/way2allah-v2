{{--
    Verbatim translation of sendemail.php:63-92's HTML body. The "الغرفة
    الصوتية" (Voice Room) link below points at /chat, the retired
    live-room feature (Business Confirmation #4 — no replacement, no
    Zoom). Preserved as legacy's own copy, not scrubbed or updated —
    rewriting old email marketing text is a content decision outside this
    task's scope, flagged here rather than silently changed.
--}}
<html dir="rtl">
<body>
<p align="center"><b>بسم الله الرحمن الرحيم</b></p>
<p align="right"><b>السلام عليكم يا / {{ $friendName }}</b></p>
<p align="right"><b>صديقك {{ $yourName }} قام بإختيار الفتوى التالية من موقع الطريق إلى الله</b></p>
<p align="right"><b>عنوان الفتوى : <font color="#FF0000"><span lang="en-us">{{ $fatwaQuestion->question_text }}</span></font></b></p>
<p align="right"><b>رابط الفتوى :<span lang="en-us"> <font color="#FF0000">
<a href="{{ url('/fatawa-'.$fatwaQuestion->id.'.htm') }}">{{ $fatwaQuestion->question_text }}</a></font></span></b></p>
<p align="right"><b>و تتشرف إدارة الموقع بإهدائها إليك عبر بريدك الإلكتروني كما تدعوك إلي زيارة أقسام الموقع المختلفة</b></p>
<p align="right"><b>&nbsp;(
<a href="{{ url('/khotab-audio.htm') }}">الصوتيات</a> -
<a href="{{ url('/khotab-video.htm') }}">المرئيات</a> -
<a href="{{ url('/live-stream.htm') }}">البث المباشر</a> -
<a href="{{ url('/var-group-98.htm') }}">الأناشيد و المقاطع المؤثرة</a> -
<a href="{{ url('/var-group-12.htm') }}">الأفلام الوثائقية</a> -
<a href="{{ url('/var-group-57.htm') }}">الكارتون الإسلامي</a> -
<a href="{{ url('/chat') }}">الغرفة الصوتية</a>)</b></p>
<p align="right"><b>و يمكنك أيضاً الإشتراك معنا مجاناً بالمنتدى الدعوي الخاص بالموقع لتشاركنا المشاريع الدعوية و للتعرف على صحبة صالحة على الرابط التالي</b></p>
<p align="center" dir="ltr"><span lang="en-us"><b>
<a href="http://forums.way2allah.com/">منتدى الطريق الى الله</a></b></span></p>
<p align="center"><b>نحن بإنتظار زيارتك لموقعنا</b></p>
<p align="left"><b>إدارة موقع الطريق إلى الله</b></p>
<p align="left"><span lang="en-us"><b><a href="https://way2allah.com">www.way2allah.com</a></b></span></p>
</body>
</html>

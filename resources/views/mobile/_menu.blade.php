{{-- Phase 7 — OLD menu(): search plus the section's fixed links. --}}
<form method="get" action="{{ route('mobile.search') }}" class="form-inline" style="margin-bottom:10px">
    <input type="text" name="query" value="{{ $query ?? '' }}" class="form-control" placeholder="بحث في القسم">
    <button type="submit" class="btn btn-default">بحث</button>
</form>
<p>
    [ <a href="/mobiles.htm">صفحة الموبايل الرئيسية</a>
    | <a href="/mobiles-admobile.htm">أضف المادة</a>
    | <a href="/mobiles-newmobiles.htm">الجديد</a>
    | <a href="/mobiles-mostpopular.htm">الأكثر تحميلا</a>
    | <a href="/mobiles-toprated.htm">الأعلى تقييما</a> ]
</p>

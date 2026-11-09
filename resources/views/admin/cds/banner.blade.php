@extends('layouts.admin')

@section('title', $release->title)

@section('content')
    {{-- OLD add_banner() / edit_banner() + admin_add_banner.htm / admin_edit_banner.htm
         (modules/w2acd/admin/index.php): the banner upload form; for a CD that has a banner, OLD's
         warning, the current banner and banner.htm's three embed-code links (cds-code-{id}-{type}.htm). --}}
    <x-admin-portlet :title="$release->title" color="green" icon="fa fa-picture-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.cds.banner.upload', ['release' => $release->id]) }}" enctype="multipart/form-data" dir="rtl">
            @csrf
            <font class="title"><b>اختر ملف البانر من جهازك</b></font><br>
            <p><b>ملف البانر : </b>
                <input type="file" name="file2upl" accept=".gif,.jpg,.jpeg,.png,image/gif,image/jpeg,image/png" required>
            </p>
            <center>
                امتداد الملفات المسموح بها : gif - jpg - png <br>
                أقصى حجم مسموح به 40 كيلو بايت
            </center>
            <br>
            <button type="submit" class="btn green">اضف هذا الملف</button>
        </form>
        @if ((int) $release->banner === 1)
            <p><b><font color="#FF0000">رفع أي بانر جديد سيؤدي إلى مسح البانر الحالي</font></b></p>
            @if ($current !== null)
                <p align="center"><b>بانر الاسطوانة</b><br>
                    <img src="{{ $current['url'] }}" border="0" @if ($current['width'] > 0) width="{{ $current['width'] }}" height="{{ $current['height'] }}" @endif alt="{{ $release->title }}">
                </p>
                <p align="center" dir="rtl"><b>شارك معنا و قم بوضع رابط لهذه الاسطوانة في موقعك أو توقيعك</b><br>
                    ( <a href="{{ route('w2acd.banner-code', ['khid' => $release->id, 'type' => 1]) }}" target="_blank" rel="noopener">كود HTML</a>
                    - <a href="{{ route('w2acd.banner-code', ['khid' => $release->id, 'type' => 2]) }}" target="_blank" rel="noopener">كود منتديات VBulletin</a>
                    - <a href="{{ route('w2acd.banner-code', ['khid' => $release->id, 'type' => 3]) }}" target="_blank" rel="noopener">كود منتديات phpBB</a> )
                </p>
            @endif
        @endif
        <center><a href="/cds-item-{{ $release->id }}.htm">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection

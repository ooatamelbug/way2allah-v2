@extends('layouts.admin')

@section('title', 'رفع صورة المادة')

@section('content')
    {{-- OLD admin Detailes() (modules/Anasheed/admin/index.php): the item, its current frame, then «رفع صورة المادة». --}}
    <x-admin-portlet :title="$item->title" color="green" icon="fa fa-picture-o">
        @include('admin.mirrors._errors')
        @if ($currentFrame !== null)
            <p><b>صورة المادة</b><br><img src="{{ $currentFrame }}" alt="{{ $item->title }}" style="max-width:240px"></p>
        @endif
        <form method="post" action="{{ route('admin.anasheed.items.frame.store', $item) }}" enctype="multipart/form-data">
            @csrf
            <p><b>اختر ملف البانر من جهازك</b></p>
            <p dir="rtl"><b>ملف البانر : </b><input type="file" name="frame" accept=".jpg,image/jpeg" required></p>
            <p>امتداد الملفات المسموح بها : jpg<br>أقصى حجم مسموح به {{ $maxKb }} كيلو بايت</p>
            <button type="submit" class="btn green">اضف هذا الملف</button>
            <a href="/var-item-{{ $item->id }}.htm" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection

@extends('layouts.admin')

@section('title', 'صورة الغلاف')

@section('content')
    {{-- F10 / B5: the custom cover of a Khotab video item. The Archive.org frame is never changed here. --}}
    <x-admin-portlet :title="'صورة الغلاف: '.$item->title" color="green" icon="fa fa-picture-o">
        @include('admin.mirrors._errors')
        @if (session(\App\Domain\Admin\Contextual\ContextualReturn::FLASH))
            <div class="alert alert-success" role="status">{{ session(\App\Domain\Admin\Contextual\ContextualReturn::FLASH) }}</div>
        @endif

        <div class="row">
            <div class="col-md-6 text-center">
                <h4>الصورة المخصصة</h4>
                @if ($cover !== null)
                    <img src="{{ $cover }}" alt="{{ $item->title }}" style="max-width:100%" data-cover-preview>
                    <p class="help-block">تُعرض هذه الصورة بدلاً من صورة الأرشيف.</p>
                @else
                    <p>لا توجد صورة مخصصة — تُعرض صورة الأرشيف.</p>
                @endif
            </div>
            <div class="col-md-6 text-center">
                <h4>صورة الأرشيف (archive.org)</h4>
                @if ($archiveFrame !== null)
                    <img src="{{ $archiveFrame }}" alt="" style="max-width:100%" data-archive-preview>
                @else
                    <p>لا توجد صورة من الأرشيف لهذه المادة.</p>
                @endif
            </div>
        </div>
        <hr>

        @if ($isVideo)
            <form method="post" action="{{ route('admin.khotab.items.cover.store', ['item' => $item->id]) }}" enctype="multipart/form-data">
                @csrf
                <p><b>{{ $cover !== null ? 'استبدال الصورة المخصصة' : 'رفع صورة مخصصة' }}:</b>
                    <input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                </p>
                <p class="help-block">
                    JPG أو PNG أو WebP، بحد أقصى {{ $maxKb }} كيلوبايت، وأبعاد بين
                    {{ \App\Domain\Admin\Actions\Khotab\KhotabItemCoverAction::MIN_WIDTH }}×{{ \App\Domain\Admin\Actions\Khotab\KhotabItemCoverAction::MIN_HEIGHT }}
                    و{{ \App\Domain\Admin\Actions\Khotab\KhotabItemCoverAction::MAX_SOURCE_WIDTH }}×{{ \App\Domain\Admin\Actions\Khotab\KhotabItemCoverAction::MAX_SOURCE_HEIGHT }}.
                    تُحفظ الصورة بصيغة JPEG، وتُصغَّر إن زادت عن {{ \App\Domain\Admin\Actions\Khotab\KhotabItemCoverAction::MAX_WIDTH }} بكسل.
                </p>
                <button type="submit" class="btn green">{{ $cover !== null ? 'استبدال الصورة' : 'رفع الصورة' }}</button>
            </form>
        @else
            <p class="alert alert-info">الصورة المخصصة متاحة لمواد الفيديو فقط.</p>
        @endif

        @if ($cover !== null)
            <form method="post" action="{{ route('admin.khotab.items.cover.destroy', ['item' => $item->id]) }}" style="margin-top:15px"
                onsubmit="return confirm('حذف الصورة المخصصة والعودة إلى صورة الأرشيف؟')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn red">حذف الصورة المخصصة (العودة إلى صورة الأرشيف)</button>
            </form>
        @endif

        <br><center><a href="/khotab-item-{{ $item->id }}.htm">( الرجوع إلى صفحة المادة )</a></center>
    </x-admin-portlet>
@endsection

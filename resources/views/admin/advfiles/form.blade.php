@extends('layouts.admin')

@section('title', $heading)

@section('content')
    {{-- OLD Admin Migration Phase 5 — OLD w2a_edit_advfile(): credit, code and
         audio URL. `code` is the raw HTML OLD's editor produced; it is edited
         as source here and stored verbatim. --}}
    <x-admin-portlet :title="$heading" color="green" icon="fa fa-pencil">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ $action }}">
            @csrf
            <x-admin.contextual-return />
            @if ($method !== 'POST')
                @method($method)
            @endif
            <label>مرات العرض المتبقية لهذا الملف
                <input type="number" name="credit" min="0" value="{{ old('credit', (int) ($file->credit ?? 0)) }}" class="form-control" dir="ltr">
            </label>
            <label>رابط الملف الصوتي
                <input type="text" name="audio_url" value="{{ old('audio_url', $file->audio_url ?? '') }}" class="form-control" dir="ltr">
            </label>
            <label style="display:block">كود الملف (HTML)
                <textarea name="code" rows="14" class="form-control" dir="ltr">{{ old('code', $file->code ?? '') }}</textarea>
            </label>
            <button type="submit" class="btn green">حفظ</button>
            <a href="{{ route('admin.advfiles.item', $item->id) }}" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection

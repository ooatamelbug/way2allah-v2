@extends('layouts.admin')

@section('title', $fatwa === null ? 'إضافة فتوى الى السؤال' : 'تعديل الفتوى')

@section('content')
    {{-- Q1 «اضافة فتوى للسؤال» / A4 «تعديل» — OLD fatwa_question_add.htm / fatwa_question_edit.htm: the same fields,
         labels and order. «مكان إصدار الفتوى» is OLD's channel select. The size (MB) can be typed, or filled from the
         link (X2) — the lookup is never required. --}}
    <x-admin-portlet :title="$fatwa === null ? 'إضافة فتوى الى السؤال' : 'تعديل الفتوى '" color="green" :icon="$fatwa === null ? 'fa fa-plus' : 'fa fa-pencil-square-o'">
        @include('admin.mirrors._errors')
        @if ($question !== null)
            <p><b>السؤال : </b>{{ $question->question_text }}</p>
        @endif
        <form method="post" action="{{ $fatwa === null ? route('admin.fatwa.questions.fatwas.store', $question) : route('admin.fatwa.fatwas.update', $fatwa) }}" dir="rtl">
            @csrf
            @if ($fatwa !== null)
                @method('PUT')
            @endif
            <x-admin.contextual-return />
            @php($author = (string) old('authers', $fatwa?->auther_id ?? '0'))
            @php($type = (string) old('audio_or_video', $fatwa?->media_type ?? 'audio'))
            @php($channel = (string) old('channel', (string) (int) ($fatwa?->channel_id ?? 0)))
            <table class="table table-bordered">
                <tr><td>اسم الشيخ</td><td>
                    <select name="authers" required>
                        <option value="0" @selected($author === '0')>أختر اسم الشيخ</option>
                        @foreach ($authors as $option)
                            <option value="{{ $option->id }}" @selected($author === (string) $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </td></tr>
                <tr><td>نص الفتوى</td><td><textarea name="fatwa_text" class="form-control" rows="6" required>{{ old('fatwa_text', $fatwa?->question_text) }}</textarea></td></tr>
                <tr><td>نص الرد على الفتوى</td><td><textarea name="fatwa_answer_text" class="form-control" rows="6">{{ old('fatwa_answer_text', $fatwa?->answer_text) }}</textarea></td></tr>
                <tr><td>نوع المادة المراد رفعها</td><td>
                    <label><input type="radio" name="audio_or_video" value="audio" @checked($type !== 'video')> ملف صوت</label>&nbsp;&nbsp;&nbsp;&nbsp;
                    <label><input type="radio" name="audio_or_video" value="video" @checked($type === 'video')> ملف فيديو</label>
                </td></tr>
                <tr><td>رابط المادة</td><td>
                    <input type="text" name="media_link" id="fatwa-media-link" value="{{ old('media_link', $fatwa?->media_link) }}" size="50" maxlength="255" dir="ltr"
                        data-size-url="{{ route('admin.fatwa.media-size') }}">
                    <span id="fatwa-media-size-status" style="display:none;color:#EB3131"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i> جاري سحب حجم المادة</span>
                </td></tr>
                <tr><td>حجم المادة</td><td><input type="text" name="media_size" id="fatwa-media-size" value="{{ old('media_size', $sizeField ?? '') }}" size="10" inputmode="decimal" @required($fatwa === null)> ميجا بايت</td></tr>
                <tr><td>مكان إصدار الفتوى</td><td>
                    <select name="channel">
                        <option value="0" @selected($channel === '0')>بدون قناة</option>
                        @foreach ($channels as $option)
                            <option value="{{ $option->id }}" @selected($channel === (string) $option->id)>{{ $option->title }}</option>
                        @endforeach
                    </select>
                </td></tr>
                <tr><td>تاريخ إصدار الفتوى</td><td><input type="date" name="fatwa_date" value="{{ old('fatwa_date', $dateField ?? '') }}"></td></tr>
                <tr><td>تاريخ إضافة الفتوى</td><td><input type="text" value="{{ $today }}" readonly disabled></td></tr>
            </table>
            <button type="submit" class="btn green">{{ $fatwa === null ? 'إضافة الفتوى' : 'حفظ التعديلات للفتوى' }}</button>
            @if ($question !== null)
                <a href="/fatawa-all-{{ $question->id }}.htm{{ $fatwa !== null ? '#'.$fatwa->id : '' }}" class="btn default">( الرجوع للخلف )</a>
            @endif
        </form>
    </x-admin-portlet>
@endsection

@push('scripts')
    {{-- X2 — OLD get_filesize() (js/inc.js): on leaving the link field, ask for the size and fill it in MB.
         A failed lookup leaves the typed value as it was; the field is never locked. --}}
    <script>
        (function () {
            var link = document.getElementById('fatwa-media-link');
            var size = document.getElementById('fatwa-media-size');
            var status = document.getElementById('fatwa-media-size-status');
            if (!link || !size || !window.fetch) { return; }
            link.addEventListener('blur', function () {
                var url = link.value.trim();
                if (url === '') { return; }
                status.style.display = 'inline';
                fetch(link.dataset.sizeUrl + '?url=' + encodeURIComponent(url), {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
                    .then(function (r) { return r.ok ? r.json() : {size: null}; })
                    .then(function (data) { if (data && typeof data.size === 'string' && data.size !== '') { size.value = data.size; } })
                    .catch(function () {})
                    .then(function () { status.style.display = 'none'; });
            });
        })();
    </script>
@endpush

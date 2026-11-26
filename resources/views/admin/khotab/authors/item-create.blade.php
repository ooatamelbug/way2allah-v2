@extends('layouts.admin')

@section('title', 'إضافة درس')

@section('content')
    {{--
        OLD InsertKhotab(author, 0, 0, vedio) + admin_add_khotab.htm from an
        author page: the link first, then «التالي» reads archive.org (OLD
        KhotabLeechImages) and reveals the rest of the form. No group, no
        series, and — as OLD rendered it here — a free channel select.
    --}}
    <x-admin-portlet :title="'إضافة درس لـ'.trim(($author->prename ?? '').' '.($author->name ?? ''))" color="green" icon="fa fa-plus">
        @include('admin.mirrors._errors')
        <table width="100%" cellpadding="20" cellspacing="20">
            <tr>
                <td width="700" valign="top">
                    <form method="post" action="{{ route('admin.khotab.authors.items.store', ['author' => $author->id, 'media' => $media]) }}" id="w2a-add-item">
                        @csrf
                        <fieldset><legend>رابط تحميل المادة</legend>
                            <div class="required">
                                <label>رابط المادة :</label>
                                <input class="inputText form-control" type="text" id="khotab_url" name="link" size="100" dir="ltr" value="{{ old('link') }}"
                                       onkeypress="return (event.which || event.keyCode) !== 13">
                                <input id="next_button" type="button" class="btn default" value="التالي" @if ($errors->any()) style="display:none" @endif>
                            </div>
                        </fieldset>

                        <div id="more_data" @unless ($errors->any()) style="display:none;" @endunless>
                            <fieldset><legend>بيانات المادة</legend>
                                <div class="required">
                                    <label>عنوان المادة :</label>
                                    <input class="inputText form-control" type="text" name="title" size="48" maxlength="100" value="{{ old('title') }}">
                                </div>
                                <div class="optional">
                                    <label>الوصف :</label>
                                    <textarea name="description" class="inputTextarea form-control" rows="3" cols="60">{{ old('description') }}</textarea>
                                </div>
                                <div id="more_info">
                                    <div class="required">
                                        <label> حجم المادة :</label>
                                        <input class="inputText" type="text" name="size" size="8" value="{{ old('size') }}"><br>
                                        <small>الحجم التقريبي للمادة بالميجابايت</small>
                                    </div>
                                </div>
                                <div class="required">
                                    <label>التعليق على المادة :</label>
                                    <textarea name="MoreInfo" class="inputTextarea form-control" rows="3" cols="60">{{ old('MoreInfo') }}</textarea>
                                </div>
                                <small>لا تزيد عن 250 حرف على الأكثر.</small>
                            </fieldset>

                            <fieldset><legend>خصائص المادة</legend>
                                <div class="optional"><label>اسم المجموعة :</label> بدون مجموعة</div>
                                <div class="optional"><label>اسم السلسلة :</label> بدون سلسلة</div>
                                <div class="optional"><label>اسم القناة :</label>
                                    <select name="Channel" size="1">
                                        <option value="0">بدون قناة</option>
                                        @foreach ($channels as $channel)
                                            <option value="{{ $channel->id }}" @selected((int) old('Channel') === (int) $channel->id)>{{ $channel->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="optional"><label>موقع إلقاء المادة :</label>
                                    <select name="Location" size="1">
                                        <option value="0">غير معلوم</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}" @selected((int) old('Location') === (int) $location->id)>{{ $location->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <b>
                                    <label><input type="checkbox" name="newslist" value="1" @checked(old('newslist', ! $errors->any()))>الظهور في قائمة الجديد برئيسية الموقع</label>
                                    <br><label><input type="checkbox" name="fixed" @checked(old('fixed'))>التثبيت بقائمة المواد المثبتة</label>
                                </b>
                            </fieldset>

                            <fieldset><legend>SEO</legend>
                                <div class="optional"><label>Meta Keywords:</label><textarea name="meta_keywords" class="inputTextarea form-control">{{ old('meta_keywords') }}</textarea></div>
                                <div class="optional"><label>Meta Description:</label><textarea name="meta_description" class="inputTextarea form-control">{{ old('meta_description') }}</textarea></div>
                                <br><label><input type="checkbox" name="meta_index" value="1" @checked(old('meta_index', ! $errors->any()))> Index</label><br>
                                <label><input type="checkbox" name="meta_follow" value="1" @checked(old('meta_follow', ! $errors->any()))> Follow</label>
                            </fieldset>

                            {{-- Multi-author creation (2026-11-25): optional additional sheikhs, and the submit protection. --}}
                            @include('admin.khotab._additional-authors', ['primaryAuthorId' => $author->id, 'media' => $media, 'selected' => $additionalAuthors ?? []])

                            <br><br>
                            <center><button type="submit" class="btn green">أضف</button></center>
                        </div>
                    </form>
                </td>
                <td width="200" valign="top">
                    <div id="archive_feed" style="width:190px; max-height:770px; overflow:auto;float:left;"></div>
                </td>
            </tr>
        </table>
        <p><a href="/khotab-{{ $media }}-{{ $author->id }}.htm" class="btn default">رجوع</a></p>
    </x-admin-portlet>

    <script>
        // OLD js/inc.js getarchivedata() / setframe(): the archive fragment fills the side
        // column, its size block replaces #more_info, and its hidden inputs join the form.
        // Since 2026-11-26 (owner-approved): «أضف» waits for the lookup (w2a:archive-start / -end, see
        // _additional-authors); each lookup is numbered, so an answer for a link that has since been edited is
        // ignored; editing the link discards everything archive.org supplied (images, frame, size) and offers
        // «التالي» again; a lookup that never answers is abandoned after the server's own bound plus a margin.
        (function () {
            var next = document.getElementById('next_button');
            var form = document.getElementById('w2a-add-item');
            var feed = document.getElementById('archive_feed');
            var link = document.getElementById('khotab_url');
            var moreInfo = document.getElementById('more_info');
            var manualInfo = moreInfo.innerHTML;
            var limit = {{ (2 * max(1, (int) config('admin_khotab.archive_timeout', 20)) + 15) * 1000 }};
            var lookup = 0, pending = false, loaded = false;
            function settle() {
                if (! pending) { return; }
                pending = false;
                form.dispatchEvent(new CustomEvent('w2a:archive-end'));
            }
            next.addEventListener('click', function () {
                var id = ++lookup;
                next.style.display = 'none';
                document.getElementById('more_data').style.display = 'block';
                pending = true;
                form.dispatchEvent(new CustomEvent('w2a:archive-start'));
                var controller = typeof AbortController === 'function' ? new AbortController() : null;
                var timer = controller ? setTimeout(function () { controller.abort(); }, limit) : null;
                var url = {{ \Illuminate\Support\Js::from(route('admin.khotab.archive')) }} + '?url=' + encodeURIComponent(link.value);
                fetch(url, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}, signal: controller ? controller.signal : undefined})
                    .then(function (r) { if (! r.ok) { throw new Error(); } return r.text(); })
                    .then(function (html) {
                        if (id !== lookup) { return; }          // the link was edited meanwhile: stale answer
                        feed.innerHTML = html;
                        var info = feed.querySelector('#more_info_feed');
                        if (info) {
                            moreInfo.innerHTML = info.innerHTML;
                            info.innerHTML = '';
                        }
                        feed.querySelectorAll('input[type=hidden]').forEach(function (input) {
                            input.setAttribute('data-w2a-archive', '');
                            form.appendChild(input);
                        });
                        loaded = true;
                    })
                    .catch(function () { if (id === lookup) { alert('error'); } })
                    .then(function () { clearTimeout(timer); if (id === lookup) { settle(); } });
            });
            link.addEventListener('input', function () {
                lookup++;                                       // any answer still on its way is now stale
                if (loaded || pending) {
                    feed.innerHTML = '';
                    moreInfo.innerHTML = manualInfo;
                    form.querySelectorAll('[data-w2a-archive]').forEach(function (input) { input.remove(); });
                    loaded = false;
                }
                next.style.display = '';
                settle();
            });
            feed.addEventListener('click', function (event) {
                var img = event.target.closest('img.archive_frame');
                if (! img) { return; }
                var frame = form.querySelector('#archive_frame');
                if (frame) { frame.value = img.getAttribute('src'); }
                var preview = document.getElementById('archive_frame_img');
                if (preview) { preview.setAttribute('src', img.getAttribute('src')); }
            });
        })();
    </script>
@endsection

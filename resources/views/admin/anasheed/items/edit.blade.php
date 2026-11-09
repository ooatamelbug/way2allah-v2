@extends('layouts.admin')

@section('title', 'تعديل نشيد')

@section('content')
    {{-- OLD EditAnasheed() (modules/Anasheed/admin/index.php, production working copy): the same fields, labels, defaults and group choices. --}}
    <x-admin-portlet :title="'تعديل نشيد '.$item->title" color="green" icon="fa fa-pencil-square-o">
        @include('admin.mirrors._errors')
        <p><b>تعديل نشيد لـ{{ trim(($author->prename ?? '').' '.($author->name ?? '')) }}</b></p>
        <form method="post" action="{{ route('admin.anasheed.items.update', $item) }}">
            @csrf
            @method('PUT')
            {{-- Opt-in, as OLD's redirct_to: only an entry that carried a valid return_to (anasheed_without_authors.htm). --}}
            <x-admin.contextual-return />
            <p dir="rtl"><b>عنوان النشيد : </b><input type="text" name="title" value="{{ old('title', $item->title) }}" size="48" maxlength="250" required></p>
            <p dir="rtl"><b>نوع المادة : &nbsp;&nbsp;</b>
                <label><input type="radio" value="0" name="vedio" @checked((string) old('vedio', (int) $item->vedio === 1 ? '1' : '0') === '0')>مادة صوتية</label>&nbsp;&nbsp;&nbsp;&nbsp;
                <label><input type="radio" value="1" name="vedio" @checked((string) old('vedio', (int) $item->vedio === 1 ? '1' : '0') === '1')>مادة مرئية</label>
            </p>
            <p dir="rtl"><b>التثبيت في قائمة المزيد: &nbsp;&nbsp;</b>
                <label><input type="radio" value="0" name="fixed" @checked((string) old('fixed', (int) $item->fixed === 1 ? '1' : '0') === '0')>غير مثبتة</label>&nbsp;&nbsp;&nbsp;&nbsp;
                <label><input type="radio" value="1" name="fixed" @checked((string) old('fixed', (int) $item->fixed === 1 ? '1' : '0') === '1')>مثبتة</label>
            </p>
            <p dir="rtl"><b>النشر علي المنصة؟: &nbsp;&nbsp;</b>
                <label><input type="radio" value="0" name="is_published" @checked((string) old('is_published', (int) $item->is_published === 1 ? '1' : '0') === '0')>غير قابلة للنشر</label>&nbsp;&nbsp;&nbsp;&nbsp;
                <label><input type="radio" value="1" name="is_published" @checked((string) old('is_published', (int) $item->is_published === 1 ? '1' : '0') === '1')>منشوره علي المصة</label>
            </p>
            <p dir="rtl"><b>وصلة المادة: </b><input type="text" name="Anasheed_link" value="{{ old('Anasheed_link', $item->link) }}" size="100" dir="ltr" required></p>
            @php($channel = (string) old('Channel', $item->channel_id === null ? '' : (string) $item->channel_id))
            <p dir="rtl"><b>اسم القناة : </b>
                <select name="Channel" size="1">
                    <option value="" @selected($channel === '')>غير مصنف</option>
                    <option value="0" @selected($channel === '0')>بدون قناة</option>
                    @foreach ($channels as $row)
                        <option value="{{ $row->id }}" @selected($channel === (string) $row->id)>{{ $row->title }}</option>
                    @endforeach
                </select>
            </p>
            @php($authorId = (string) old('Author', $item->author_id === null ? '' : (string) $item->author_id))
            <p dir="rtl"><b>إختر الشيخ : </b>
                <select name="Author" size="1">
                    <option value="" @selected($authorId === '')>غير مصنف</option>
                    <option value="0" @selected($authorId === '0')>بدون شيخ</option>
                    @foreach ($authors as $row)
                        <option value="{{ $row->id }}" @selected($authorId === (string) $row->id)>{{ $row->name }}</option>
                    @endforeach
                </select>
            </p>
            <p dir="rtl"><b>الحجم بالميجا بايت : </b><input type="text" value="{{ old('Anasheedsize', $sizeField) }}" name="Anasheedsize" size="8"></p>
            <p dir="rtl"><b>القسم: </b>
                <select name="group">
                    @foreach ($groups as $option)
                        <option value="{{ $option['id'] }}" @selected(old('group') !== null ? (int) old('group') === $option['id'] : $option['selected'])>{{ $option['title'] }}</option>
                    @endforeach
                </select>
            </p>
            <p>التعليق على النشيد</p>
            <textarea cols="68" rows="7" name="Des" maxlength="255" class="form-control">{{ old('Des', $item->notes) }}</textarea>
            <p><b>وصف التصنيف :</b></p>
            <textarea cols="68" rows="10" name="description" class="form-control">{{ old('description', $item->description) }}</textarea>
            <hr>
            <p><b>SEO :</b></p>
            <textarea cols="68" rows="2" name="meta_keywords" placeholder="Meta Keywords" class="form-control">{{ old('meta_keywords', $item->meta_keywords) }}</textarea>
            <br>
            <textarea cols="68" rows="5" name="meta_description" placeholder="Meta Description" class="form-control">{{ old('meta_description', $item->meta_description) }}</textarea>
            <br>
            <label><input type="checkbox" name="meta_index" value="1" @checked(old('meta_index', ! empty($item->meta_index)))> Index</label><br>
            <label><input type="checkbox" name="meta_follow" value="1" @checked(old('meta_follow', ! empty($item->meta_follow)))> Follow</label>
            <br><br>
            <button type="submit" class="btn green">تعديل النشيد</button>
            <a href="/var-item-{{ $item->id }}.htm" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection

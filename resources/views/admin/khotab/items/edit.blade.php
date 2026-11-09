@extends('layouts.admin')

@section('title', 'تعديل '.$item->title)

@section('content')
    {{-- OLD EditKhotab() + admin_edit_khotab.htm (modules/Khotab/admin/index.php:1530): the same fields,
         labels and conditions. OLD's «نسخ مادة» box (CloneKhotab → KhotabCopyKhotab) called a function
         that does not exist, so it is not reproduced. --}}
    <x-admin-portlet :title="'تعديل '.$item->title" color="green" icon="fa fa-pencil">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.khotab.items.update', ['item' => $item->id]) }}">
            @csrf
            @method('PUT')
            <fieldset><legend>بيانات المادة</legend>
                <div class="note note-info">
                    <p>هذا النموذج يمكنك من تعديل العنوان ، الرابط ، الحجم و التعليق على المادة فقط و لتعديل السلسلة او المجموعة اضغط
                        @if (app(\App\Domain\Admin\Contextual\ContextualAdmin::class)->visible([\App\Domain\Admin\Contextual\ContextualControl::link('نقل', 'fa-arrows', 'admin.khotab.items.move', ['item' => $item->id])]) !== [])
                            <a href="{{ route('admin.khotab.items.move', ['item' => $item->id]) }}">هنا</a>.
                        @else
                            هنا.
                        @endif
                    </p>
                    <p>يمكنك العودة إلى صفحة المادة بالضغط <a href="/khotab-item-{{ $item->id }}.htm">هنا</a>.</p>
                </div>

                <div class="form-group">
                    <label>عنوان المادة :</label>
                    <input name="title" class="form-control" value="{{ old('title', $item->title) }}" type="text" maxlength="250">
                </div>
                <div class="form-group">
                    <label>الوصف :</label>
                    <textarea name="description" class="form-control" rows="3" cols="60">{{ old('description', $item->description) }}</textarea>
                </div>
                <div class="form-group">
                    <label>{{ $author->prename ?? '' }} :</label>
                    <input class="form-control" value="{{ $author->name ?? '' }}" type="text" disabled>
                </div>
                <div class="form-group">
                    <label>اسم المجموعة :</label>
                    <input class="form-control" value="{{ $groupTitle }}" type="text" disabled>
                </div>
                <div class="form-group">
                    <label>اسم السلسلة :</label>
                    <input class="form-control" value="{{ $seriesTitle }}" type="text" disabled>
                </div>
                <div class="form-group">
                    <label>اسم القناة :</label>
                    @if ($free)
                        <select name="Channel" size="1">
                            <option value="0">بدون قناة</option>
                            @foreach ($channels as $channel)
                                <option value="{{ $channel->id }}" @selected((int) old('Channel', $item->channel_id) === (int) $channel->id)>{{ $channel->title }}</option>
                            @endforeach
                        </select>
                    @else
                        {{ $channelTitle }}
                    @endif
                    <br>*لا يمكنك تغيير القناة إلا في المواد الفردية
                </div>
                <div class="form-group">
                    <label>موقع إلقاء المادة :</label>
                    <select name="Location" size="1">
                        <option value="0">غير معلوم</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((int) old('Location', $item->location_id) === (int) $location->id)>{{ $location->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label> حجم المادة :</label>
                    <input name="khotabsize" class="form-control" value="{{ old('khotabsize', sprintf('%01.2f', ((float) $item->linksize) / 1048576)) }}" type="text" @disabled(! $linkEditable)>
                </div>
                <div class="form-group">
                    <label><a href="{{ $item->link }}" target="_blank" rel="noopener">رابط المادة</a> :</label>
                    <input style="direction:ltr;" name="khotab_link" class="form-control" value="{{ old('khotab_link', $item->link) }}" type="text" @disabled(! $linkEditable)>
                </div>
                <div class="form-group">
                    <label>التعليق على المادة :</label>
                    <textarea name="Des" class="form-control" rows="3" cols="60" maxlength="255">{{ old('Des', $item->notes) }}</textarea>
                    <small>لا تزيد عن 250 حرف على الأكثر.</small>
                </div>
            </fieldset>
            <fieldset><legend>خصائص المادة</legend>
                <label><input type="checkbox" name="newslist" value="1" @checked(old('newslist', (int) $item->newslist === 1))>الظهور في قائمة الجديد برئيسية الموقع</label><br>
                <label><input type="checkbox" name="fixed" value="1" @checked(old('fixed', (int) $item->fixed === 1))>التثبيت بقائمة المواد المثبتة الموقع</label><br>
                <label><input type="checkbox" name="hidden" value="1" @checked(old('hidden', (int) $item->hidden === 1))>مادة مخفية عن الزوار</label>
            </fieldset>
            <fieldset><legend>خصائص البانر</legend>
                <center>
                    <font color="#FF0000"><b>تحت الإنشاء</b></font><br><br>
                    <label><input type="radio" disabled checked> درس فردي</label>
                    &nbsp;&nbsp;<label><input type="radio" disabled> سلسلة</label>
                    &nbsp;&nbsp;<label><input type="radio" disabled> مجموعة</label>
                    @if ($banner !== null)
                        <br><br><img src="{{ $banner }}" border="0" alt="">
                    @endif
                </center>
            </fieldset>
            <fieldset><legend>SEO</legend>
                <div class="form-group">
                    <label>Meta Keywords:</label>
                    <textarea name="meta_keywords" class="form-control">{{ old('meta_keywords', $item->meta_keywords) }}</textarea>
                </div>
                <div class="form-group">
                    <label>Meta Description:</label>
                    <textarea name="meta_description" class="form-control">{{ old('meta_description', $item->meta_description) }}</textarea>
                </div>
                <label><input type="checkbox" name="meta_index" value="1" @checked(old('meta_index', ! empty($item->meta_index)))> Index</label><br>
                <label><input type="checkbox" name="meta_follow" value="1" @checked(old('meta_follow', ! empty($item->meta_follow)))> Follow</label>
            </fieldset>
            <br>
            <button type="submit" class="btn green">تعديل الدرس</button>
            <a href="/khotab-item-{{ $item->id }}.htm" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection

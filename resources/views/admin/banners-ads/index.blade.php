@extends('layouts.admin')

@section('title', 'قائمة إعلانات البنر')

@section('content')
    {{-- F01 / B6b — OLD w2a_banners_ads_main(): «المواد المختارة» (the current nuke_banners_ads set) and
         «المواد المقترحة» (Khotab items with banner = 1), one «حفظ التعديلات». The saved set = the ticked current
         items + the ticked suggestions. Every title is escaped (OLD printed them raw into <option>). --}}
    <x-admin-portlet title="قائمة إعلانات البنر" color="blue" icon="fa fa-picture-o">
        @include('admin.mirrors._errors')

        <form method="get" action="{{ route('admin.banners_ads.index') }}" class="form-inline" style="margin-bottom:15px">
            <label>التصنيف :
                <select name="category" class="form-control" style="max-width:360px">
                    <option value="">كل التصنيفات</option>
                    @foreach ($categories as $id => $path)
                        <option value="{{ $id }}" @selected($filters['category'] === $id)>{{ $path }}</option>
                    @endforeach
                </select>
            </label>
            <label>اسم الشيخ :
                <select name="author" class="form-control" style="max-width:260px">
                    <option value="">كل الشيوخ</option>
                    @foreach ($authors as $author)
                        <option value="{{ $author->id }}" @selected($filters['author'] === (int) $author->id)>{{ $author->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>اسم المادة : <input type="text" name="q" value="{{ $filters['q'] }}" maxlength="100" class="form-control"></label>
            <button type="submit" class="btn blue">بـــحــث</button>
            <a href="{{ route('admin.banners_ads.index') }}" class="btn default">عرض الكل</a>
        </form>

        <form method="post" action="{{ route('admin.banners_ads.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="set_version" value="{{ $setVersion }}">
            <div class="row">
                <div class="col-md-6">
                    <h4>المواد المختارة ({{ count($current) }})</h4>
                    <p class="help-block">ألغِ التحديد لإزالة المادة من إعلانات البنر.</p>
                    <table class="table table-striped table-light">
                        <thead><tr><th></th><th>#</th><th>المادة</th><th>الشيخ</th><th>تاريخ الإضافة</th></tr></thead>
                        <tbody>
                            @forelse ($current as $row)
                                <tr>
                                    <td><input type="checkbox" name="keep[]" value="{{ (int) $row->media_id }}" checked></td>
                                    <td>{{ (int) $row->media_id }}</td>
                                    <td>
                                        {{ $row->title ?? 'لا يوجد خطبة' }}
                                        @if ($row->item_id === null)
                                            <small class="text-danger">(المادة غير موجودة)</small>
                                        @elseif ((int) $row->banner !== 1)
                                            <small class="text-warning">(لم تعد تحتوي على بنر)</small>
                                        @endif
                                        @if ((int) $row->hidden === 1)
                                            <small class="text-muted">(مخفية)</small>
                                        @endif
                                    </td>
                                    <td>{{ $row->author_name }}</td>
                                    <td dir="ltr">{{ $row->creation_date }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">لا يوجد مواد مضافة حاليا</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h4>المواد المقترحة</h4>
                    <p class="help-block">حدد المواد التي تريد إضافتها (المواد التي تحتوي على بنر فقط).</p>
                    <table class="table table-striped table-light">
                        <thead><tr><th></th><th>#</th><th>المادة</th><th>الشيخ</th></tr></thead>
                        <tbody>
                            @forelse ($candidates as $item)
                                <tr>
                                    <td>
                                        @if (in_array((int) $item->id, $currentIds, true))
                                            <small class="text-muted">مضافة</small>
                                        @else
                                            <input type="checkbox" name="add[]" value="{{ (int) $item->id }}">
                                        @endif
                                    </td>
                                    <td>{{ (int) $item->id }}</td>
                                    <td>{{ $item->title }} @if ((int) $item->hidden === 1)<small class="text-muted">(مخفية)</small>@endif</td>
                                    <td>{{ $item->author_name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4">لا يوجد خطب تحتوى على بنرات</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $candidates->links() }}
                </div>
            </div>
            <p style="text-align:center">
                <button type="submit" class="btn green" onclick="return confirm('حفظ التعديلات على قائمة إعلانات البنر؟')">حفظ التعديلات</button>
            </p>
        </form>
    </x-admin-portlet>
@endsection

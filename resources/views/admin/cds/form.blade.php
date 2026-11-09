@extends('layouts.admin')

@section('title', $release ? 'تعديل اسطوانة: '.$release->title : 'اضافة اسطوانة جديدة')

@section('content')
    {{-- OLD Admin Migration Phase 6 — OLD insertcd.htm / editcd.htm: title,
         images (at most five, the first is the cover) and download links
         (a title and a URL each). --}}
    <x-admin-portlet :title="$release ? 'تعديل اسطوانة #'.$release->id : 'اضافة اسطوانة جديدة'" color="green" icon="fa fa-dot-circle-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ $release ? route('admin.cds.update', $release) : route('admin.cds.store') }}" enctype="multipart/form-data">
            @csrf
            <x-admin.contextual-return />
            @if ($release)
                @method('PUT')
            @endif
            <label style="display:block">عنوان الإسطوانة
                <input type="text" name="title" value="{{ old('title', $release->title ?? '') }}" maxlength="100" required class="form-control">
            </label>
            @if ($groups->isNotEmpty())
                <label>القسم
                    <select name="group_id" class="form-control">
                        <option value="0" @selected((int) old('group_id', $groupId) === 0)>القائمة الرئيسية</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected((int) old('group_id', $groupId) === (int) $group->id)>{{ $group->title }}</option>
                        @endforeach
                    </select>
                </label>
            @else
                <input type="hidden" name="group_id" value="{{ $groupId }}">
            @endif

            <h4>الروابط <small>(عنوان الرابط ثم الرابط — بدون فواصل)</small></h4>
            @php
                $rows = old('links', $links);
                $rows = array_values(array_pad($rows, max(count($rows) + 2, 3), ['title' => '', 'url' => '']));
            @endphp
            <table class="table table-light">
                @foreach ($rows as $i => $row)
                    <tr>
                        <td><input type="text" name="links[{{ $i }}][title]" value="{{ $row['title'] ?? '' }}" placeholder="عنوان الرابط" class="form-control"></td>
                        <td><input type="text" name="links[{{ $i }}][url]" value="{{ $row['url'] ?? '' }}" placeholder="الرابط" class="form-control" dir="ltr"></td>
                    </tr>
                @endforeach
            </table>

            <h4>الصور <small>(jpg / png / gif — خمس صور على الأكثر، الأولى هي الغلاف)</small></h4>
            @if ($images !== [])
                <table class="table table-light">
                    @foreach ($images as $i => $name)
                        <tr>
                            <td><img src="/images/cds_image2/{{ $name }}" alt="" style="max-height:60px"></td>
                            <td dir="ltr">{{ $name }}</td>
                            <td>
                                <input type="hidden" name="existing[{{ $i }}][name]" value="{{ $name }}">
                                <input type="hidden" name="existing[{{ $i }}][keep]" value="0">
                                <label><input type="checkbox" name="existing[{{ $i }}][keep]" value="1" checked> إبقاء</label>
                            </td>
                            <td><label>استبدال <input type="file" name="existing[{{ $i }}][file]" accept=".jpg,.jpeg,.png,.gif"></label></td>
                        </tr>
                    @endforeach
                </table>
            @endif
            @for ($i = 0; $i < max(0, 5 - count($images)); $i++)
                <p><input type="file" name="images[]" accept=".jpg,.jpeg,.png,.gif"></p>
            @endfor

            <button type="submit" class="btn green">{{ $release ? 'حفظ التعديلات' : 'أضف' }}</button>
            <a href="{{ route('admin.cds.index', ['group' => $groupId]) }}" class="btn default">رجوع</a>
        </form>

        @if ($release)
            <hr>
            <a href="/cds-item-{{ $release->id }}.htm" target="_blank" class="btn default">عرض في الموقع</a>
            <form method="post" action="{{ route('admin.cds.destroy', $release) }}" style="display:inline"
                  onsubmit="return confirm('حذف الإسطوانة #{{ $release->id }}؟')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn red"><i class="fa fa-trash-o"></i> حذف الإسطوانة</button>
            </form>
        @endif
    </x-admin-portlet>
@endsection

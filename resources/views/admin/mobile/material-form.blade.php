@extends('layouts.admin')

@section('title', $material ? 'تعديل مادة #'.$material->lid : 'إضافة مادة')

@section('content')
    <x-admin-portlet :title="$material ? 'تعديل مادة #'.$material->lid : 'إضافة مادة'" color="green" icon="fa fa-mobile">
        @include('admin.mirrors._errors')
        @include('admin.mobile._nav')
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        <form method="post" action="{{ $material ? route('admin.mobile.materials.update', $material->lid) : route('admin.mobile.materials.store') }}">
            @csrf
            <x-admin.contextual-return />
            @if ($material) @method('PUT') @endif
            @include('admin.mobile._material-fields', ['values' => $material ? $material->only(['cid', 'title', 'url', 'description', 'name', 'email', 'filesize', 'version', 'homepage', 'hits']) : []])
            <button type="submit" class="btn green">حفظ</button>
        </form>
        @if ($material)
            <hr>
            <a href="/mobileview-details-{{ $material->lid }}.htm" target="_blank" class="btn default">عرض في الموقع</a>
            <form method="post" action="{{ route('admin.mobile.materials.destroy', $material->lid) }}" style="display:inline" onsubmit="return confirm('حذف المادة #{{ $material->lid }}؟')">
                @csrf
                <x-admin.contextual-return />
                @method('DELETE')
                <button type="submit" class="btn red">حذف المادة</button>
            </form>
        @endif
    </x-admin-portlet>

    @if ($material)
        <x-admin-portlet title="رأي المحررين" color="blue" icon="fa fa-pencil">
            <form method="post" action="{{ route('admin.mobile.materials.editorial', $material->lid) }}">
                @csrf
                @method('PUT')
                <label style="display:block">العنوان <input type="text" name="editorialtitle" value="{{ old('editorialtitle', $editorial?->editorialtitle) }}" maxlength="100" required class="form-control"></label>
                <label style="display:block">النص <textarea name="editorialtext" rows="5" required class="form-control">{{ old('editorialtext', $editorial?->editorialtext) }}</textarea></label>
                <button type="submit" class="btn blue">{{ $editorial ? 'حفظ' : 'أضف' }}</button>
            </form>
            @if ($editorial)
                <form method="post" action="{{ route('admin.mobile.materials.editorial.destroy', $material->lid) }}" style="margin-top:6px" onsubmit="return confirm('حذف رأي المحررين؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm red">حذف رأي المحررين</button>
                </form>
            @endif
        </x-admin-portlet>

        <x-admin-portlet title="التقييمات والتعليقات" color="blue" icon="fa fa-star">
            <p>التقييم: {{ (int) $material->downloadratingsummary }} · الأصوات: {{ (int) $material->totalvotes }} · التعليقات: {{ (int) $material->totalcomments }}</p>
            <table class="table table-striped table-light">
                <thead><tr><th>المستخدم</th><th>IP</th><th>التقييم</th><th>التعليق</th><th>التاريخ</th><th></th></tr></thead>
                <tbody>
                    @forelse ($votes as $vote)
                        <tr>
                            <td>{{ $vote->ratinguser }}</td>
                            <td dir="ltr">{{ $vote->ratinghostname }}</td>
                            <td>{{ (int) $vote->rating }}</td>
                            <td style="white-space:pre-line">{{ $vote->ratingcomments }}</td>
                            <td dir="ltr">{{ $vote->ratingtimestamp }}</td>
                            <td style="white-space:nowrap">
                                @if (trim((string) $vote->ratingcomments) !== '')
                                    <form method="post" action="{{ route('admin.mobile.votes.comment.destroy', $vote->ratingdbid) }}" style="display:inline">@csrf @method('DELETE')<button type="submit" class="btn btn-sm default">حذف التعليق</button></form>
                                @endif
                                <form method="post" action="{{ route('admin.mobile.votes.destroy', $vote->ratingdbid) }}" style="display:inline" onsubmit="return confirm('حذف التقييم؟')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm red">حذف التقييم</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">لا توجد تقييمات مسجلة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-admin-portlet>
    @endif
@endsection

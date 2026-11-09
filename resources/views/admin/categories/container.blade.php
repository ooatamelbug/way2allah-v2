@extends('layouts.admin')

@section('title', 'التصنيفات: '.$heading)

@section('content')
    {{-- OLD Admin Migration Phase 3 — a Khotab series or group. As in OLD,
         the bulk buttons are offered per category already on the container. --}}
    <x-admin-portlet :title="$heading" color="blue" icon="fa fa-tags">
        @include('admin.mirrors._errors')
        <p>{{ $scopeNote }}</p>
        <p class="text-muted">القيمة المخزنة: <code dir="ltr">{{ $raw === '' ? '(فارغة)' : $raw }}</code></p>
        <table class="table table-striped table-hover table-light">
            <thead><tr><th>#</th><th>التصنيف</th><th></th></tr></thead>
            <tbody>
                @forelse ($assigned as $category)
                    <tr>
                        <td>{{ $category['id'] }}</td>
                        <td>{{ $category['title'] ?? 'تصنيف غير معروف' }}</td>
                        <td style="white-space:nowrap">
                            @if ($category['title'] !== null)
                                <form method="post" action="{{ route('admin.categories.khotab.'.$type.'.add-all', $id) }}" style="display:inline">
                                    @csrf
                                    <input type="hidden" name="category_id" value="{{ $category['id'] }}">
                                    <button type="submit" class="btn btn-sm green">اضف للكل</button>
                                </form>
                                <form method="post" action="{{ route('admin.categories.khotab.'.$type.'.remove-all', $id) }}" style="display:inline"
                                      onsubmit="return confirm('حذف التصنيف #{{ $category['id'] }} من جميع المواد؟')">
                                    @csrf
                                    <input type="hidden" name="category_id" value="{{ $category['id'] }}">
                                    <button type="submit" class="btn btn-sm red">امسح من الكل</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3">لا توجد تصنيفات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-admin-portlet>
@endsection

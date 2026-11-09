@extends('layouts.admin')

@section('title', 'التصنيفات: '.$heading)

@section('content')
    {{-- OLD Admin Migration Phase 3 — one Khotab or Anasheed item. --}}
    <x-admin-portlet :title="$heading" color="blue" icon="fa fa-tags">
        @include('admin.mirrors._errors')
        <p class="text-muted">القيمة المخزنة: <code dir="ltr">{{ $raw === '' ? '(فارغة)' : $raw }}</code></p>
        <table class="table table-striped table-hover table-light">
            <thead><tr><th>#</th><th>التصنيف</th><th></th></tr></thead>
            <tbody>
                @forelse ($assigned as $category)
                    <tr>
                        <td>{{ $category['id'] }}</td>
                        <td>{{ $category['title'] ?? 'تصنيف غير معروف' }}</td>
                        <td>
                            @if ($category['title'] !== null)
                                <form method="post" action="{{ $removeRoute }}" style="display:inline"
                                      onsubmit="return confirm('حذف التصنيف #{{ $category['id'] }} من المادة؟')">
                                    @csrf
                                    <x-admin.contextual-return />
                                    <input type="hidden" name="category_id" value="{{ $category['id'] }}">
                                    <button type="submit" class="btn btn-sm red"><i class="fa fa-trash-o"></i> حذف</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3">لا توجد تصنيفات لهذه المادة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-admin-portlet>

    <x-admin-portlet title="إضافة تصنيف" color="green" icon="fa fa-plus">
        <form method="post" action="{{ $addRoute }}" class="form-inline">
            @csrf
            <x-admin.contextual-return />
            <select name="category_id" class="form-control" required>
                @foreach ($options as $option)
                    <option value="{{ $option['id'] }}" @selected((string) old('category_id') === (string) $option['id'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn green">أضف</button>
        </form>
    </x-admin-portlet>
@endsection

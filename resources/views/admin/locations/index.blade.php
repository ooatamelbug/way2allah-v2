@extends('layouts.admin')

@section('title', 'قائمة المساجد و الاماكن الدعوية')

@section('content')
    {{-- `open_div('قائمة المساجد و الاماكن الدعوية', 'blue', '', 12)` — legacy explicitly passes 'blue' (AdminCP Final Page-Level Visual-Parity Verification, 2026-08-22). Empty icon arg falls back to open_div()'s own default, fa-folder-open. --}}
    <x-admin-portlet title="قائمة المساجد و الاماكن الدعوية" color="blue" icon="fa fa-folder-open">
        <a href="{{ route('admin.locations.create') }}">إضافة مكان جديد</a>
        <table class="table table-striped table-hover table-light">
            <thead>
                <tr>
                    <th>#</th>
                    <th>المكان</th>
                    <th>العنوان</th>
                    <th>الدولة</th>
                    <th>المقاطع</th>
                    <th>حذف</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($locations as $index => $location)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td><a href="{{ route('admin.locations.edit', $location) }}">{{ $location->title }}</a></td>
                        <td>{{ $location->address }}</td>
                        <td>{{ $location->country }}</td>
                        <td>{{ $location->count }}</td>
                        <td>
                            @if ($location->count === 0)
                                <form method="post" action="{{ route('admin.locations.destroy', $location) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm red"><i class="fa fa-trash-o"></i> حذف</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-admin-portlet>
@endsection

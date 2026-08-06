@extends('layouts.admin')

@section('title', 'قائمة المساجد و الاماكن الدعوية')

@section('content')
    <a href="{{ route('admin.locations.create') }}">إضافة مكان جديد</a>
    <table>
        <thead>
            <tr>
                <th>المكان</th>
                <th>العنوان</th>
                <th>الدولة</th>
                <th>المقاطع</th>
                <th>حذف</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($locations as $location)
                <tr>
                    <td><a href="{{ route('admin.locations.edit', $location) }}">{{ $location->title }}</a></td>
                    <td>{{ $location->address }}</td>
                    <td>{{ $location->country }}</td>
                    <td>{{ $location->count }}</td>
                    <td>
                        @if ($location->count === 0)
                            <form method="post" action="{{ route('admin.locations.destroy', $location) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit">حذف</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

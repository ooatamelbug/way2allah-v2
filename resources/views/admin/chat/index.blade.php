@extends('layouts.admin')

@section('title', 'قائمة الغرف الصوتية')

@section('content')
    <h2>قائمة الغرف المفتوحة</h2>
    <table>
        <thead><tr><th>الغرفة</th><th>المتحدثين</th><th>المشرفين</th></tr></thead>
        <tbody>
            @foreach ($rooms->where('enable', 1) as $room)
                <tr>
                    <td><a href="{{ route('admin.chat.edit', $room) }}">{{ $room->name }}</a></td>
                    <td>{{ $room->speaker }}</td>
                    <td>{{ $room->owner }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>قائمة الغرف المغلقة</h2>
    <table>
        <thead><tr><th>الغرفة</th><th>المتحدثين</th><th>المشرفين</th></tr></thead>
        <tbody>
            @foreach ($rooms->where('enable', 0) as $room)
                <tr>
                    <td><a href="{{ route('admin.chat.edit', $room) }}">{{ $room->name }}</a></td>
                    <td>{{ $room->speaker }}</td>
                    <td>{{ $room->owner }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

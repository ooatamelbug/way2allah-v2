@extends('layouts.admin')

@section('title', 'اليوتيوب الرئيسية')

@section('content')
    <form method="post" action="{{ route('admin.youtube.store') }}">
        @csrf
        <label>رابط المقطع باليوتيوب <input type="text" name="youtube"></label>
        <button type="submit">اضافة</button>
    </form>

    @foreach ($videoIds as $index => $videoId)
        <div>
            الفيديو رقم {{ $index + 1 }}: {{ $videoId }}
            <form method="post" action="{{ route('admin.youtube.destroy', $index) }}" style="display:inline">
                @csrf
                @method('DELETE')
                <button type="submit">مسح الفيديو</button>
            </form>
        </div>
    @endforeach
@endsection

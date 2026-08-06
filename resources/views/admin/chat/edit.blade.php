@extends('layouts.admin')

{{-- IF-034: the legacy edit_room.php form and delowner/delspeaker links
     render but have no backend anywhere in the source — this page's
     form/delete actions are real, working implementations built fresh
     (ChatRoomAdminController's own docblock), not a port. --}}

@section('title', 'تعديل غرفة: '.$room->name)

@section('content')
    <form method="post" action="{{ route('admin.chat.update', $room) }}">
        @csrf
        @method('PUT')
        <label>اسم الغرفة <input type="text" name="name" value="{{ $room->name }}"></label>
        <label><input type="radio" name="enable" value="1" @checked($room->enable == 1)> مفتوحة</label>
        <label><input type="radio" name="enable" value="0" @checked($room->enable != 1)> مغلقة</label>
        <label>الرسالة الترحيبية <input type="text" name="welcome" value="{{ $room->welcome }}"></label>
        <label>كلمة المرور <input type="text" name="password" value="{{ $room->password }}"></label>
        <label>الحد الأقصى للأعضاء <input type="text" name="max_user" value="{{ $room->max_user }}"></label>
        <label>التعليق <textarea name="des">{{ $room->des }}</textarea></label>
        <label><input type="checkbox" name="member_only" value="1" @checked($room->member_only == 1)> للأعضاء فقط</label>
        <label><input type="checkbox" name="enable_audio" value="1" @checked($room->enable_audio == 1)> بث صوتي</label>
        <label><input type="checkbox" name="enable_video" value="1" @checked($room->enable_video == 1)> بث مرئي</label>
        <label><input type="checkbox" name="enable_white_board" value="1" @checked($room->enable_white_board == 1)> لوحة الكتابة</label>
        <button type="submit">Submit</button>
    </form>

    <section aria-label="قائمة المشرفين">
        <h2>قائمة المشرفين</h2>
        <table>
            <thead><tr><th>اسم المشرف</th><th>المشاركات</th><th>حذف</th></tr></thead>
            <tbody>
                @foreach ($owners as $owner)
                    <tr>
                        <td>{{ $owner->username ?? '' }}</td>
                        <td>{{ $owner->posts ?? '' }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.chat.owner.destroy', [$room, $owner->username ?? '']) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit">حذف</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section aria-label="قائمة المحاضرين">
        <h2>قائمة المحاضرين</h2>
        <table>
            <thead><tr><th>اسم المحاضر</th><th>المشاركات</th><th>حذف</th></tr></thead>
            <tbody>
                @foreach ($speakers as $speaker)
                    <tr>
                        <td>{{ $speaker->username ?? '' }}</td>
                        <td>{{ $speaker->posts ?? '' }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.chat.speaker.destroy', [$room, $speaker->username ?? '']) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit">حذف</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection

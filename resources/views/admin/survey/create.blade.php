@extends('layouts.admin')

@section('title', 'اضافة استبيان جديد')

@section('content')
    <form method="post" action="{{ route('admin.survey.store') }}">
        @csrf
        <label>عنوان الاستبيان <input type="text" name="title" required></label>
        <label>رسالة افتتاحية <textarea name="openning"></textarea></label>
        <label>رسالة نهائية <textarea name="finish"></textarea></label>
        <label>تاريخ البداية <input type="date" name="start_date"></label>
        <label>تاريخ النهاية <input type="date" name="end_date"></label>
        <label><input type="checkbox" name="users_only" value="1"> للأعضاء فقط</label>
        <label><input type="checkbox" name="ip_restriction" value="1"> تصويت واحد فقط لكل آي بي</label>
        <label><input type="checkbox" name="anonymous" value="1"> بيانات المستخدم سرية</label>
        <label><input type="checkbox" name="published" value="1"> متاح</label>

        <fieldset>
            <legend>المشرفين على الاستبيان</legend>
            @foreach ($moderators as $moderator)
                <label><input type="checkbox" name="editors[]" value="{{ $moderator->id }}"> {{ $moderator->aid }}</label>
            @endforeach
        </fieldset>

        <fieldset>
            <legend>المجموعات المشاركة في الإستبيان</legend>
            @foreach ($groups as $group)
                <label><input type="checkbox" name="groups[]" value="{{ $group->usergroupid }}"> {{ $group->title }}</label>
            @endforeach
        </fieldset>

        <button type="submit">أضف الاستبيان</button>
    </form>
@endsection

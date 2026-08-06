@extends('layouts.admin')

@section('title', 'قائمة المشاركين باستبيان: '.$survey->title)

@section('content')
    <table>
        <thead>
            <tr>
                <th>الاسم</th>
                <th>الآي بي</th>
                <th>تاريخ المشاركة</th>
                <th>مشاهدة</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($answers as $answer)
                <tr>
                    <td>{{ $answer->isGuest() ? "زائر ({$answer->id})" : "عضو #{$answer->user_id}" }}</td>
                    <td>{{ $answer->ip }}</td>
                    <td>{{ $answer->mytime ? date('Y-m-d H:i', $answer->mytime) : '' }}</td>
                    <td><a href="{{ route('admin.survey.answer.show', [$survey, $answer]) }}">عرض</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <a href="{{ route('admin.survey.all-stats', $survey) }}">الاحصائية الكاملة</a>
@endsection

@extends('layouts.admin')

@section('title', 'الاستبيانات')

@section('content')
    <a href="{{ route('admin.survey.create') }}">أضف استبيان جديد</a>
    <table>
        <thead>
            <tr>
                <th>الاسم</th>
                <th>الأسئلة</th>
                <th>المشاركين</th>
                <th>الإحصائيات</th>
                <th>تاريخ البداية</th>
                <th>تاريخ النهاية</th>
                <th>حذف</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($surveys as $survey)
                <tr>
                    <td><a href="{{ route('admin.survey.questions.index', $survey) }}">{{ $survey->title }}</a></td>
                    <td>{{ $survey->questions }}</td>
                    <td>{{ $survey->submits }}</td>
                    <td><a href="{{ route('admin.survey.stats', $survey) }}">إحصائيات</a></td>
                    <td>{{ $survey->start_date }}</td>
                    <td>{{ $survey->end_date }}</td>
                    <td>
                        <form method="post" action="{{ route('admin.survey.destroy', $survey) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit">حذف</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

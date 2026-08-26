@extends('layouts.admin')

@section('title', 'الاستبيانات')

@section('content')
    {{--
        CONFIRMED_PAGE_MARKUP_GAP, fixed (AdminCP Final Page-Level
        Visual-Parity Verification, 2026-08-22): legacy `survey/index.php`'s
        table has a real "#" index column and a real "مشاهدة" (view) column
        linking to the public survey page — both existed in source and were
        missing here, not decorative additions.
    --}}
    <x-admin-portlet title="قائمة الاستبيانات">
        <table class="table table-striped table-hover table-light">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم</th>
                    <th>الأسئلة</th>
                    <th>المشاركين</th>
                    <th>الإحصائيات</th>
                    <th>تاريخ البداية</th>
                    <th>تاريخ النهاية</th>
                    <th>مشاهدة</th>
                    <th>حذف</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($surveys as $index => $survey)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td><a href="{{ route('admin.survey.questions.index', $survey) }}">{{ $survey->title }}</a></td>
                        <td>{{ $survey->questions }}</td>
                        <td>{{ $survey->submits }}</td>
                        <td><a href="{{ route('admin.survey.stats', $survey) }}">إحصائيات</a></td>
                        <td>{{ $survey->start_date }}</td>
                        <td>{{ $survey->end_date }}</td>
                        <td><a target="_blank" href="https://way2allah.com/survey/?id={{ $survey->id }}">مشاهدة</a></td>
                        <td>
                            <form method="post" action="{{ route('admin.survey.destroy', $survey) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm red"><i class="fa fa-trash-o"></i> حذف</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p><a href="{{ route('admin.survey.create') }}">أضف استبيان جديد</a></p>
    </x-admin-portlet>
@endsection

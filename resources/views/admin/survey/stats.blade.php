@extends('layouts.admin')

@section('title', 'قائمة المشاركين باستبيان: '.$survey->title)

@section('content')
    {{-- Portlet caption from legacy `survey/stats.php`'s own portlet caption (AdminCP Final Page-Level Visual-Parity Verification, 2026-08-22). --}}
    <x-admin-portlet :title="'قائمة المشاركين باستبيان: '.$survey->title">
        <table class="table table-striped table-hover table-light">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم</th>
                    <th>الآي بي</th>
                    <th>تاريخ المشاركة</th>
                    <th>مشاهدة</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($answers as $index => $answer)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            @if ($answer->isGuest())
                                زائر ({{ $answer->id }})
                            @else
                                <a href="https://forums.way2allah.com/member.php?u={{ $answer->user_id }}">{{ $usernames[$answer->user_id] ?? 'عضو #'.$answer->user_id }}</a>
                            @endif
                        </td>
                        <td>{{ $answer->ip }}</td>
                        <td>{{ $answer->mytime ? date('Y-m-d H:i', $answer->mytime) : '' }}</td>
                        <td><a href="{{ route('admin.survey.answer.show', [$survey, $answer]) }}"><span class="badge badge-info"><i class="fa fa-bar-chart"></i></span></a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p><a href="{{ route('admin.survey.all-stats', $survey) }}">الاحصائية الكاملة</a></p>
    </x-admin-portlet>
@endsection

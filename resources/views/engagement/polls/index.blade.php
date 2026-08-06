@extends('layouts.app')

@section('title', 'الإستفتاءات')

@section('content')
    <table>
        <thead>
            <tr>
                <th>عنوان الإستفتاء</th>
                <th>الأصوات</th>
                <th>النتائج</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($polls as $poll)
                <tr>
                    <td><a href="{{ route('engagement.polls.show', $poll) }}">{{ $poll->pollTitle }}</a></td>
                    <td>{{ $poll->totalVotes() }} صوت</td>
                    <td><a href="{{ route('engagement.polls.results', $poll) }}">نتائج</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

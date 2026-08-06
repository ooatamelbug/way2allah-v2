@extends('layouts.admin')

@section('title', 'قائمة المشاركين بالاستبيان')

@section('content')
    <table>
        <thead>
            <tr>
                <th>الاسم</th>
                <th>رقم الهاتف</th>
                <th>البريد الألكتروني</th>
                <th>الفيسبوك</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($responses as $response)
                <tr>
                    <td><a href="{{ route('admin.questionnaire.show', $response) }}">{{ $response->username }}</a></td>
                    <td>{{ $response->mobile }}</td>
                    <td><a href="mailto:{{ $response->email }}">{{ $response->email }}</a></td>
                    <td>
                        @if (trim((string) $response->facebook) !== '')
                            <a href="{{ $response->facebook }}" target="_blank">فيسبوك الداعية</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

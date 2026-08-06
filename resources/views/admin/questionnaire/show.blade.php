@extends('layouts.admin')

@section('title', 'بيانات شخصية: '.$response->username)

@section('content')
    <dl>
        <dt>الاسم</dt><dd>{{ $response->username }}</dd>
        <dt>الهاتف</dt><dd>{{ $response->mobile }}</dd>
        <dt>البريد الالكتروني</dt><dd>{{ $response->email }}</dd>
        <dt>الفيسبوك</dt><dd>{{ $response->facebook }}</dd>
    </dl>
    <dl>
        @foreach (\App\Domain\Admin\Models\QuestionnaireResponse::REMARK_LABELS as $field => $label)
            <dt>{{ $label }}</dt>
            <dd>{{ $response->$field }}</dd>
        @endforeach
    </dl>
@endsection

@extends('layouts.admin')

@section('title', 'بيانات شخصية: '.$response->username)

@section('content')
    {{--
        AdminCP Final Untested-Page Closure Pass: both portlet captions now
        re-read directly from `questionnaire/index.php` (its detail view has
        exactly 2 portlets, lines 42-100 and 101-209 — the page's 3rd
        `portlet box` at line 210 is the separate list/index state, not part
        of this detail view). 2nd portlet's real caption is "بيانات
        الاستبيان" ("questionnaire data"), not the previous placeholder
        "ملاحظات الاستبيان" ("questionnaire remarks") — corrected.
    --}}
    <x-admin-portlet title="بيانات شخصية" icon="fa fa-gift">
        <dl>
            <dt>الاسم</dt><dd>{{ $response->username }}</dd>
            <dt>الهاتف</dt><dd>{{ $response->mobile }}</dd>
            <dt>البريد الالكتروني</dt><dd>{{ $response->email }}</dd>
            <dt>الفيسبوك</dt><dd>{{ $response->facebook }}</dd>
        </dl>
    </x-admin-portlet>

    <x-admin-portlet title="بيانات الاستبيان" icon="fa fa-gift">
        <dl>
            @foreach (\App\Domain\Admin\Models\QuestionnaireResponse::REMARK_LABELS as $field => $label)
                <dt>{{ $label }}</dt>
                <dd>{{ $response->$field }}</dd>
            @endforeach
        </dl>
    </x-admin-portlet>
@endsection

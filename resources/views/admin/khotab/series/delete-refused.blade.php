@extends('layouts.admin')

@section('title', 'حذف سلسلة')

@section('content')
    {{-- OLD ConfirmDeleteSeries()'s refusal while items still have ser_id = X (modules/Khotab/admin/index.php:2263). --}}
    <x-admin-portlet title="حذف سلسلة" color="red" icon="fa fa-exclamation-triangle">
        <center>
            <font class="title">عفواً .... لن تتمكن من مسح هذه السلسلة من قاعدة بيانات الموقع
                <br>يجب عليك أولا مسح جميع الخطب و الدروس التى تندرج تحتها أولا من الموقع
            </font>
        </center>
        <center><a href="javascript:history.go(-1)">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection

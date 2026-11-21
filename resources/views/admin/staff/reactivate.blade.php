@extends('layouts.admin')

@section('title', 'إعادة تفعيل مشرف')

@section('content')
    <x-admin-portlet :title="'إعادة تفعيل المشرف: '.$target->aid" color="green" icon="fa fa-check">
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif
        <p>لا يمكن استعادة كلمة المرور القديمة؛ عيّن كلمة مرور جديدة وأبلغها للمشرف بطريقة آمنة. مفتاح برنامج النسخ الاحتياطي لا يُستعاد تلقائياً.</p>
        <form method="post" action="{{ route('admin.staff.reactivate', $target) }}" autocomplete="off">
            @csrf
            <label>كلمة المرور الجديدة <input type="password" name="new_password" minlength="8" maxlength="72" required class="form-control" autocomplete="new-password"></label>
            <label>تأكيد كلمة المرور <input type="password" name="new_password_confirmation" minlength="8" maxlength="72" required class="form-control" autocomplete="new-password"></label>
            <button type="submit" class="btn green">إعادة تفعيل</button>
            <a href="{{ route('admin.staff.index') }}" class="btn default">إلغاء</a>
        </form>
    </x-admin-portlet>
@endsection

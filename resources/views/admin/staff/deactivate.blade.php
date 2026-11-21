@extends('layouts.admin')

@section('title', 'تعطيل مشرف')

@section('content')
    <x-admin-portlet :title="'تعطيل المشرف: '.$target->aid" color="red" icon="fa fa-ban">
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif
        @if ($isSelf)
            <div class="alert alert-danger">لا يمكنك تعطيل حسابك.</div>
        @else
            <ul>
                <li>لن يُحذف الحساب: يبقى رقم العضوية بالمنتدى والاسم وسجل العمليات والصلاحيات كما هي.</li>
                <li>يُمنع الدخول إلى لوحة التحكم الجديدة فوراً (حتى الجلسات المفتوحة)، وتُبطَل كلمات المرور ومفتاح برنامج النسخ الاحتياطي.</li>
                <li><strong>تنبيه:</strong> لوحة التحكم القديمة (OLD) وجسر الدخول وبعض صفحات Gen 2 العامة لا تقرأ حالة التعطيل بعد؛ قد يبقى وصول قائم عبرها حتى تُطبَّق تحديثات الأنظمة القديمة.</li>
                <li>إعادة التفعيل تتطلب تعيين كلمة مرور جديدة.</li>
            </ul>
            <form method="post" action="{{ route('admin.staff.deactivate', $target) }}">
                @csrf
                <label>سبب التعطيل <input type="text" name="reason" value="{{ old('reason') }}" maxlength="255" required class="form-control"></label>
                <label><input type="checkbox" name="confirm" value="1" required> أؤكد تعطيل هذا المشرف</label>
                <button type="submit" class="btn red">تعطيل</button>
                <a href="{{ route('admin.staff.index') }}" class="btn default">إلغاء</a>
            </form>
        @endif
    </x-admin-portlet>
@endsection

@extends('layouts.admin')

@section('title', 'إضافة مشرف جديد')

@section('content')
    {{--
        REAL PRESENTATION AROUND FUNCTIONAL CONTENT (AdminCP Final
        Page-Level Visual-Parity Verification, 2026-08-22): legacy
        `authors/index.php`'s `op=addstuff` branch is DEAD (a top-level
        `die('hhhh')` makes the actual add-by-forum-id logic unreachable),
        so this Laravel page is `OWNER_APPROVED_REBUILT_VARIANT` for the
        FORM LOGIC (already established in an earlier wave, a real,
        working implementation, not a port of the dead one). Its portlet
        CHROME is still real and recoverable — same caption ("إضافة مشرف
        جديد"), color, and icon (fa-gift) as the dead branch's own markup,
        which is separable from whether that branch's logic ever worked.
    --}}
    <x-admin-portlet title="إضافة مشرف جديد" icon="fa fa-gift">
        {{--
            Administrator management (OLD mod_authors → AddAuthor parity, 2026-11-17): besides the forum member id,
            OLD's add form took the nickname («الكنية», the login name), name («الإسم»), email, URL («رابط»), a
            password, module permissions and «مشرف عام». All are optional here and default to the forum account;
            the permissions grid is shown to super-admins only, «مشرف عام» to owner accounts only (the controller
            enforces both again).
        --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </div>
        @endif
        <form method="post" action="{{ route('admin.staff.store') }}" autocomplete="off">
            @csrf
            <div class="form-group">
                <label>رقم العضو بالمنتدى <input type="text" name="vbuid" required class="form-control" value="{{ old('vbuid') }}"></label>
            </div>
            <div class="form-group">
                <label>الكنية (اسم الدخول) <input type="text" name="aid" maxlength="25" class="form-control" value="{{ old('aid') }}"></label>
                <span class="help-block">اتركها فارغة لاستخدام اسم العضو بالمنتدى.</span>
            </div>
            <div class="form-group">
                <label>الإسم <input type="text" name="name" maxlength="50" class="form-control" value="{{ old('name') }}"></label>
                <span class="help-block">اتركه فارغًا لاستخدام اسم العضو بالمنتدى. لا يمكن تعديله بعد الإضافة.</span>
            </div>
            <div class="form-group">
                <label>البريد الالكتروني <input type="email" name="email" maxlength="255" class="form-control" value="{{ old('email') }}"></label>
                <span class="help-block">اتركه فارغًا لاستخدام بريد العضو بالمنتدى.</span>
            </div>
            <div class="form-group">
                <label>الرابط <input type="url" name="url" maxlength="255" class="form-control" value="{{ old('url') }}"></label>
            </div>
            <div class="form-group">
                <label>كلمة المرور <input type="password" name="new_password" minlength="8" maxlength="72" class="form-control" autocomplete="new-password"></label>
                <label>تأكيد كلمة المرور <input type="password" name="new_password_confirmation" minlength="8" maxlength="72" class="form-control" autocomplete="new-password"></label>
                <span class="help-block">اختيارية. إن تُركت فارغة يُنشأ الحساب بكلمة مرور عشوائية لا يعرفها أحد، وتُعيَّن لاحقًا من صفحة الصلاحيات.</span>
            </div>
            @if ($canGrantSuperAdmin)
                <div class="form-group">
                    <label><input type="checkbox" name="make_super_admin" value="1" @checked(old('make_super_admin'))> مشرف عام (لوحة التحكم الجديدة)</label>
                </div>
            @endif
            @if ($canAssignPermissions)
                @foreach ($permissionsByModule as $module => $permissions)
                    <div class="form-group">
                        <strong>{{ \App\Domain\Admin\Support\PermissionLabels::moduleName($module) }}</strong>
                        <div>
                            @foreach ($permissions as $permission)
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="permissions[{{ $permission->name }}]" value="1" @checked(old('permissions.'.$permission->name))>
                                    {{ \App\Domain\Admin\Support\PermissionLabels::permissionLabel($permission->name) }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
            <button type="submit" class="btn green">اضف</button>
        </form>
    </x-admin-portlet>
@endsection

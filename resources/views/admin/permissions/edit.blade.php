@extends('layouts.admin')

@section('title', 'صلاحيات: '.$admin->aid)

@section('content')
    <form method="post" action="{{ route('admin.permissions.update', $admin) }}">
        @csrf
        @method('PUT')
        @foreach ($permissionsByModule as $module => $permissions)
            <fieldset>
                <legend>{{ $module }}</legend>
                @foreach ($permissions as $permission)
                    <label>
                        <input type="checkbox" name="permissions[{{ $permission->name }}]" value="1"
                            @checked($assigned->contains($permission->name))>
                        {{ $permission->name }}
                    </label>
                @endforeach
            </fieldset>
        @endforeach
        <button type="submit">حفظ التعديلات</button>
    </form>

    <form method="post" action="{{ route('admin.permissions.password', $admin) }}">
        @csrf
        @method('PUT')
        <label>كلمة المرور الجديدة <input type="password" name="new_password" required></label>
        <button type="submit">تعيين كلمة مرور</button>
    </form>
@endsection

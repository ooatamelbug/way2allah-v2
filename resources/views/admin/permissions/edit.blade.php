@extends('layouts.admin')

@section('title', 'صلاحيات: '.$admin->aid)

@section('content')
    {{--
        CONFIRMED_PAGE_MARKUP_GAP, fixed (AdminCP Final Page-Level
        Visual-Parity Closure, 2026-08-22): legacy `authors/edit_author.php`
        is a real multi-portlet page — (1) a profile-sidebar portlet
        (avatar, name, vBulletin usertitle, a real forum-PM link — the
        legacy "حذف كمشرف" button in the same sidebar is a plain
        `<button>` with no handler anywhere, confirmed dead, not
        reproduced), (2) a profile-content portlet with real vBulletin
        member stats (post count/rate, last activity/post, join date —
        queried live from the forum `user` table by uid, via the
        existing read-only `VbUser` model), (3) one "portlet light" per
        admincp module for its permission checkboxes, (4) a
        password-change MODAL (kept as this page's existing inline form
        instead — same fields, a UI-pattern simplification, not missing
        content). Portlets (1)-(2) render only when `$vbUser` resolves
        (an admin's `uid` might not correspond to a live vBulletin
        account) — legacy's own code has no such guard and would emit
        PHP warnings/blank fields in that case; failing closed here
        instead is a hardening, not a fidelity loss, since there is no
        real data to show either way.
    --}}
    @if ($vbUser)
        <x-admin-portlet title="بيانات العضو" icon="fa fa-user" width="4">
            <p><img src="{{ $admin->thumb }}" alt="" style="max-width:100%"></p>
            <p><a href="https://forums.way2allah.com/member.php?u={{ $admin->uid }}" target="_blank">{{ $admin->aid }}</a></p>
            <p>{{ $vbUser->usertitle }}</p>
            <p><a href="https://forums.way2allah.com/private.php?do=newpm&u={{ $admin->uid }}" target="_blank">رسالة خاصة</a></p>
        </x-admin-portlet>

        <x-admin-portlet title="الملف الشخصي" light icon="icon-globe" width="8">
            <table class="table table-striped table-hover table-light">
                <tbody>
                    <tr>
                        <td>رقم العضوية : {{ $vbUser->userid }}</td>
                        <td>البريد الالكتروني : {{ $vbUser->email }}</td>
                    </tr>
                    <tr>
                        <td>عدد المشاركات : {{ number_format($vbUser->posts) }} مشاركة</td>
                        <td>
                            @php $postRate = $vbUser->joindate && time() > $vbUser->joindate ? round($vbUser->posts * 86400 / (time() - $vbUser->joindate), 2) : 0; @endphp
                            معدل المشاركات : {{ $postRate }} مشاركة/اليوم
                        </td>
                    </tr>
                    <tr>
                        <td>آخر نشاط : {{ $vbUser->lastactivity ? date('Y-m-d H:i', $vbUser->lastactivity) : '' }}</td>
                        <td>آخر مشاركة : {{ $vbUser->lastpost ? date('Y-m-d H:i', $vbUser->lastpost) : '' }}</td>
                    </tr>
                    <tr>
                        <td>تاريخ التسجيل : {{ $vbUser->joindate ? date('Y-m-d', $vbUser->joindate) : '' }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </x-admin-portlet>
    @endif

    {{--
        AdminCP Authenticated Design/CSS Parity (2026-08-23): the previous
        version of this block rendered each module's title as the raw
        internal permission-group key (e.g. "survey") and each checkbox as
        a bare, unstyled `<input type="checkbox">` with the raw
        `{module}.{key}` permission name as its label — CSS files were all
        loading correctly, but nothing on this page used any of Metronic's
        form/checkbox selectors, so none of them had anything to style
        (`DOM_SELECTOR_MISMATCH`, not a missing-asset problem). Rebuilt to
        match `authors/edit_author.php:206-263`'s real markup exactly:
        `portlet light` (no color, not the shared `<x-admin-portlet>`
        component's `light bordered` variant — this page's own caption
        uses a different real class set, `caption-subject bold uppercase`
        + `font-red-sunglo`, not reproduced by that component), real
        `md-checkbox`/`md-check` inputs (`has-success`/`has-error` per
        checked state, matching legacy's own conditional class exactly),
        and each module's real Arabic name/icon and each permission's real
        Arabic label (`App\Domain\Admin\Support\PermissionLabels` —
        display-only, sourced from each real `admincp/*/menu.php`; see its
        own docblock for the 2 documented non-legacy-sourced exceptions).
        Grants/authorization logic in `PermissionController` is unchanged.
    --}}
    <form method="post" action="{{ route('admin.permissions.update', $admin) }}">
        @csrf
        @method('PUT')
        @foreach ($permissionsByModule as $module => $permissions)
            <div class="col-md-12">
                <div class="portlet light">
                    <div class="portlet-title">
                        <div class="caption font-red-sunglo">
                            <i class="{{ \App\Domain\Admin\Support\PermissionLabels::moduleIcon($module) }} font-red-sunglo"></i>
                            <span class="caption-subject bold uppercase"> {{ \App\Domain\Admin\Support\PermissionLabels::moduleName($module) }}</span>
                        </div>
                    </div>
                    <div class="portlet-body form">
                        <div class="form-group form-md-checkboxes">
                            <div class="md-checkbox-inline">
                                @foreach ($permissions as $permission)
                                    @php($isChecked = $assigned->contains($permission->name))
                                    <div class="md-checkbox {{ $isChecked ? 'has-success' : 'has-error' }}">
                                        <input name="permissions[{{ $permission->name }}]" id="perm-{{ $permission->name }}" class="md-check" type="checkbox" value="1" @checked($isChecked)>
                                        <label for="perm-{{ $permission->name }}">
                                            <span></span>
                                            <span class="check"></span>
                                            <span class="box"></span>
                                            {{ \App\Domain\Admin\Support\PermissionLabels::permissionLabel($permission->name) }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
        <div class="col-md-12">
            <button type="submit" class="btn green-haze">حفظ التعديلات</button>
        </div>
    </form>

    <div class="col-md-12">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <i class="icon-lock"></i>
                    <span class="caption-subject bold uppercase"> تعيين كلمة مرور جديدة</span>
                </div>
            </div>
            <div class="portlet-body form">
                <form method="post" action="{{ route('admin.permissions.password', $admin) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label>كلمة المرور</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </form>
            </div>
        </div>
    </div>
@endsection

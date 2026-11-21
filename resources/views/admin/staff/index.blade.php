@extends('layouts.admin')

@section('title', 'قائمة المشرفين')

@section('content')
    {{--
        CONFIRMED_PAGE_MARKUP_GAP, fixed (AdminCP Final Page-Level
        Visual-Parity Verification, 2026-08-22): legacy `authors/index.php`
        splits staff into 2 real portlets by `radminsuper` — "قائمة
        الإدارة العليا للموقع" (super-admins) and "قائمة المشرفين"
        (regular admins) — not one flat table with a synthesized rank
        column. The rank grouping itself replaces the old "الرتبة" column
        (redundant once grouped). `#` per portlet is added (was missing
        entirely).

        Legacy's `رقم الهاتف`/`الفيسبوك` columns are deliberately NOT
        reproduced — confirmed `LEGACY_DEAD_OR_NONCANONICAL` (AdminCP
        Staff Phone/Facebook Final Closure, 2026-08-22), not merely a
        deferred schema gap as an earlier pass in this series classified
        it. Re-read fresh: `authors/index.php`'s own staff-list query is
        `SELECT id, uid, aid, email, thumb, radminsuper FROM nuke_authors
        ...` — it never selects `mobile`/`facebook` at all, so those two
        columns in legacy's own table markup always render blank in
        production regardless of what's stored in the database. No
        `edit_author.php` copy (4 checked: `authors/`, `broadcasting/`,
        `khotab/`, `chat/`) has any field to edit either — there is no
        write path anywhere in `admincp/` for `nuke_authors.mobile`/
        `.facebook`. Reproducing them here would show real data legacy
        itself never actually displayed — not recovering real content.
    --}}
    {{-- Administrator management (2026-11-17): the two portlets keep legacy's grouping by `radminsuper` (the
         legacy «الإدارة العليا» flag, which Gen 2 / OLD still read). «مشرف عام» marks Laravel's super-admin role
         (the one Laravel authorises with), «مالك» the accounts OLD's mod_authors rule authorises (config/admin_owners.php), «الحساب الرئيسي» OLD's protected `Good` account. --}}
    {{-- F02-UI (2026-11-14): controls appear only for viewers the route would allow
         (AdminStaffController::index computes both flags; the middleware still enforces them). --}}
    @if ($canAddStaff)
        <p><a href="{{ route('admin.staff.create') }}">إضافة مشرف</a></p>
    @endif

    @php $superAdmins = $staff->where('radminsuper', true)->values(); @endphp
    @if ($superAdmins->isNotEmpty())
        <x-admin-portlet title="قائمة الإدارة العليا للموقع">
            <table class="table table-striped table-hover table-light">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>البريد الألكتروني</th>
                        @if ($canEditPermissions)
                            <th>الصلاحيات</th>
                        @endif
                        @if ($canManageStatus)
                            <th>الحالة</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($superAdmins as $index => $admin)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><img class="user-pic" src="{{ $admin->thumb }}">{{ $admin->aid }}
                                @if (in_array((int) $admin->id, $ownerIds ?? [], true))
                                    <span class="label label-primary">مالك</span>
                                @endif
                                @if (in_array((int) $admin->id, $protectedIds ?? [], true))
                                    <span class="label label-warning">الحساب الرئيسي</span>
                                @endif
                                @if (in_array((int) $admin->id, $laravelSuperAdminIds ?? [], true))
                                    <span class="label label-success">مشرف عام</span>
                                @endif
                                @if (in_array((int) $admin->id, $deactivatedIds, true))
                                    {{-- F02: kept in the list, never deleted. --}}
                                    <span class="label label-danger">معطّل</span>
                                @endif
                            </td>
                            <td>{{ $admin->email }}</td>
                            @if ($canEditPermissions)
                                <td><a href="{{ route('admin.permissions.edit', $admin) }}">تعديل الصلاحيات</a></td>
                            @endif
                            @if ($canManageStatus)
                                <td>
                                    @if (in_array((int) $admin->id, $deactivatedIds, true) && in_array((int) $admin->id, $manageableIds ?? [], true))
                                        <a href="{{ route('admin.staff.reactivate.confirm', $admin) }}">إعادة تفعيل</a>
                                    @elseif ((int) $admin->id !== $viewerId && in_array((int) $admin->id, $manageableIds ?? [], true) && ! in_array((int) $admin->id, $protectedIds ?? [], true))
                                        <a href="{{ route('admin.staff.deactivate.confirm', $admin) }}" class="font-red">تعطيل</a>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-admin-portlet>
    @endif

    @php $regularAdmins = $staff->where('radminsuper', false)->values(); @endphp
    @if ($regularAdmins->isNotEmpty())
        <x-admin-portlet title="قائمة المشرفين">
            <table class="table table-striped table-hover table-light">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>البريد الألكتروني</th>
                        @if ($canEditPermissions)
                            <th>الصلاحيات</th>
                        @endif
                        @if ($canManageStatus)
                            <th>الحالة</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($regularAdmins as $index => $admin)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><img class="user-pic" src="{{ $admin->thumb }}">{{ $admin->aid }}
                                @if (in_array((int) $admin->id, $ownerIds ?? [], true))
                                    <span class="label label-primary">مالك</span>
                                @endif
                                @if (in_array((int) $admin->id, $protectedIds ?? [], true))
                                    <span class="label label-warning">الحساب الرئيسي</span>
                                @endif
                                @if (in_array((int) $admin->id, $laravelSuperAdminIds ?? [], true))
                                    <span class="label label-success">مشرف عام</span>
                                @endif
                                @if (in_array((int) $admin->id, $deactivatedIds, true))
                                    {{-- F02: kept in the list, never deleted. --}}
                                    <span class="label label-danger">معطّل</span>
                                @endif
                            </td>
                            <td>{{ $admin->email }}</td>
                            @if ($canEditPermissions)
                                <td><a href="{{ route('admin.permissions.edit', $admin) }}">تعديل الصلاحيات</a></td>
                            @endif
                            @if ($canManageStatus)
                                <td>
                                    @if (in_array((int) $admin->id, $deactivatedIds, true) && in_array((int) $admin->id, $manageableIds ?? [], true))
                                        <a href="{{ route('admin.staff.reactivate.confirm', $admin) }}">إعادة تفعيل</a>
                                    @elseif ((int) $admin->id !== $viewerId && in_array((int) $admin->id, $manageableIds ?? [], true) && ! in_array((int) $admin->id, $protectedIds ?? [], true))
                                        <a href="{{ route('admin.staff.deactivate.confirm', $admin) }}" class="font-red">تعطيل</a>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-admin-portlet>
    @endif
@endsection

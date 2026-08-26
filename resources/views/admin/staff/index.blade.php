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
    <p><a href="{{ route('admin.staff.create') }}">إضافة مشرف</a></p>

    @php $superAdmins = $staff->where('radminsuper', true)->values(); @endphp
    @if ($superAdmins->isNotEmpty())
        <x-admin-portlet title="قائمة الإدارة العليا للموقع">
            <table class="table table-striped table-hover table-light">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>البريد الألكتروني</th>
                        <th>الصلاحيات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($superAdmins as $index => $admin)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><img class="user-pic" src="{{ $admin->thumb }}">{{ $admin->aid }}</td>
                            <td>{{ $admin->email }}</td>
                            <td><a href="{{ route('admin.permissions.edit', $admin) }}">تعديل الصلاحيات</a></td>
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
                        <th>الصلاحيات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($regularAdmins as $index => $admin)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><img class="user-pic" src="{{ $admin->thumb }}">{{ $admin->aid }}</td>
                            <td>{{ $admin->email }}</td>
                            <td><a href="{{ route('admin.permissions.edit', $admin) }}">تعديل الصلاحيات</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-admin-portlet>
    @endif
@endsection

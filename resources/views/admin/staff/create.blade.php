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
        <form method="post" action="{{ route('admin.staff.store') }}">
            @csrf
            <label>رقم العضو بالمنتدى <input type="text" name="vbuid" required class="form-control"></label>
            <button type="submit" class="btn green">اضف</button>
        </form>
    </x-admin-portlet>
@endsection

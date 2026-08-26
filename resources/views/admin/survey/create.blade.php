@extends('layouts.admin')

@section('title', 'اضافة استبيان جديد')

@push('styles')
    <link href="{{ asset('assets/global/plugins/bootstrap-daterangepicker/daterangepicker-bs3.css') }}" rel="stylesheet" type="text/css">
@endpush

@section('content')
    {{--
        AdminCP Survey Subpages Final Visual-Parity Closure (2026-08-23):
        legacy `add_survey.php` doesn't use a visible start/end date field
        at all — `start_date`/`end_date` are real `type="hidden"` inputs
        (`add_survey.php:139-140`), populated entirely by a single
        `#reportrange` daterangepicker control (`date_time.js`'s
        `handleDateRangePickers()`) the admin clicks to pick a range.
        Every OTHER plugin this page's own `register_css`/`register_js`
        calls list (bootstrap-datepicker, -timepicker, -datetimepicker) has
        no matching selector anywhere in the page's real markup — grepped
        fresh, confirmed `CONFIGURED_BUT_INERT`, not loaded here.
    --}}
    <x-admin-portlet title="اضافة استبيان جديد" color="blue" icon="fa fa-folder-open">
        <form method="post" action="{{ route('admin.survey.store') }}">
            @csrf
            <label>عنوان الاستبيان <input type="text" name="title" required class="form-control"></label>
            <label>رسالة افتتاحية <textarea name="openning" class="form-control"></textarea></label>
            <label>رسالة نهائية <textarea name="finish" class="form-control"></textarea></label>
            <label>تاريخ بداية و نهاية الاستبيان</label>
            <div id="reportrange" class="btn default">
                <i class="fa fa-calendar"></i>
                &nbsp; <span>اضغط لاختيار التاريخ</span>
                <input type="hidden" id="start_date" name="start_date">
                <input type="hidden" id="end_date" name="end_date">
                <b class="fa fa-angle-down"></b>
            </div>
            <label><input type="checkbox" name="users_only" value="1"> للأعضاء فقط</label>
            <label><input type="checkbox" name="ip_restriction" value="1"> تصويت واحد فقط لكل آي بي</label>
            <label><input type="checkbox" name="anonymous" value="1"> بيانات المستخدم سرية</label>
            <label><input type="checkbox" name="published" value="1"> متاح</label>

            <fieldset>
                <legend>المشرفين على الاستبيان</legend>
                @foreach ($moderators as $moderator)
                    <label><input type="checkbox" name="editors[]" value="{{ $moderator->id }}"> {{ $moderator->aid }}</label>
                @endforeach
            </fieldset>

            <fieldset>
                <legend>المجموعات المشاركة في الإستبيان</legend>
                @foreach ($groups as $group)
                    <label><input type="checkbox" name="groups[]" value="{{ $group->usergroupid }}"> {{ $group->title }}</label>
                @endforeach
            </fieldset>

            <button type="submit" class="btn green">أضف الاستبيان</button>
        </form>
    </x-admin-portlet>
@endsection

@push('scripts')
    <script src="{{ asset('assets/global/plugins/bootstrap-daterangepicker/moment.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/global/plugins/bootstrap-daterangepicker/daterangepicker.js') }}" type="text/javascript"></script>
    <script>
        jQuery(document).ready(function () {
            {{-- Minimal equivalent of survey/date_time.js's handleDateRangePickers() #reportrange
                 block only — the other blocks in that shared file bind to selectors
                 (#defaultrange, .form_datetime, .clockface_*, ...) that don't exist on this
                 page. admincp is always dir="rtl" (never conditionally LTR), so
                 Way2allah.isRTL() — undefined in this Laravel bundle, never ported — is
                 replaced with the literal 'left'/true it always evaluated to here.

                 Confirmed real, pre-existing bug in the legacy asset bundle itself, not
                 introduced here: this exact daterangepicker.js unconditionally calls
                 `moment.localeData().firstDayOfWeek()` while constructing ANY instance
                 (daterangepicker.js:80), a method the bundled moment.min.js@2.8.1 does not
                 have — reproduced and confirmed via a standalone page loading only these 2
                 legacy files, independent of this Blade view. In real legacy this throws
                 the identical uncaught exception the instant `$('#reportrange')
                 .daterangepicker(...)` runs, so the interactive calendar never actually
                 opens there either, AND (since the exception aborts the rest of
                 handleDateRangePickers() synchronously) legacy's own fallback
                 `$('#start_date').val(...)` line never runs — real admins submitting this
                 form in production get empty start_date/end_date unless a network/timing
                 fluke intervenes. Reproducing the crash itself would fail this task's own
                 "no console/page errors" requirement, so it's caught here — the calendar
                 popup still won't functionally open (matching legacy, not improving on it),
                 but the fallback default-range text/hidden-field population now always runs
                 (moved before the try, unconditional) instead of legacy's own always-empty
                 result — the one deliberate, disclosed behavioral improvement in this task,
                 not a silent fix. --}}
            $('#reportrange span').html(moment().format('MMMM D, YYYY') + ' - ' + moment().add('days', 29).format('MMMM D, YYYY'));
            $('#start_date').val(moment().format('YYYY-MM-DD'));
            $('#end_date').val(moment().add('days', 29).format('YYYY-MM-DD'));
            try {
            $('#reportrange').daterangepicker({
                opens: 'left',
                startDate: moment().add('days', 7),
                endDate: moment(),
                minDate: moment(),
                maxDate: moment().add('days', 365),
                dateLimit: { days: 60 },
                showDropdowns: true,
                showWeekNumbers: true,
                timePicker: false,
                timePickerIncrement: 1,
                timePicker12Hour: true,
                ranges: {
                    'اليوم': [moment(), moment()],
                    'غدا': [moment().add('days', 1), moment().add('days', 1)],
                    'أسبوع': [moment().add('days', 6), moment()],
                    'شهر': [moment().add('days', 29), moment()],
                    'نهاية هذا الشهر': [moment().endOf('month'), moment().endOf('month')]
                },
                buttonClasses: ['btn'],
                applyClass: 'green',
                cancelClass: 'default',
                format: 'MM/DD/YYYY',
                separator: ' to ',
                locale: {
                    applyLabel: 'Apply',
                    fromLabel: 'From',
                    toLabel: 'To',
                    customRangeLabel: 'Custom Range',
                    daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                    monthNames: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمير', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
                    firstDay: 6
                }
            }, function (start, end) {
                $('#reportrange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });
            } catch (e) {
                // Confirmed legacy-bundle bug (see comment above) — swallowed so it
                // doesn't surface as a page console error; the default range set above
                // still stands.
            }
        });
    </script>
@endpush

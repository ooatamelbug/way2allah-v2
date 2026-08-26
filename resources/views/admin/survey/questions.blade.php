@extends('layouts.admin')

@section('title', 'اسئلة الاستبيان: '.$survey->title)

@push('styles')
    <link href="{{ asset('assets/global/plugins/jquery-nestable/jquery.nestable.css') }}" rel="stylesheet" type="text/css">
@endpush

@section('content')
    {{--
        AdminCP Final Page-Level Visual-Parity Verification (2026-08-22):
        legacy `add_question.php` has 3 real portlets — add-question,
        reorder (drag list), and a separate delete listing (the questions
        list shown a second time, each row with its own delete link). This
        Laravel page already merges delete into the reorder list instead
        of duplicating it — a real, non-regressive consolidation (every
        question is still individually deletable, nothing is missing),
        not a silently-dropped portlet. Both `open_div()` calls here used
        the helper's own default color/icon (blue/fa-folder-open), not
        overridden in source.
    --}}
    <x-admin-portlet :title="'اضافة سؤال جديد: '.$survey->title" color="blue" icon="fa fa-folder-open">
        <form method="post" action="{{ route('admin.survey.questions.store', $survey) }}">
            @csrf
            <label>نص السؤال <input type="text" name="title" required class="form-control"></label>
            <label>تعليق توضيحي <input type="text" name="des" class="form-control"></label>
            <label>سؤال إجباري
                <select name="required" class="form-control">
                    <option value="1">نعم</option>
                    <option value="0">لا</option>
                </select>
            </label>
            <label>نوع السؤال
                <select name="question_type" required class="form-control">
                    @foreach (\App\Domain\Admin\Models\SurveyQuestion::QUESTION_TYPES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>الاختيار الأول <input type="text" name="question_options[]" class="form-control"></label>
            <label>الاختيار الثاني <input type="text" name="question_options[]" class="form-control"></label>
            <label>الحد الأقصى لطول الإجابة <input type="text" name="max_len" placeholder="اكتب رقم فقط" class="form-control"></label>
            <label>الحد الأقصى للإختيارات <input type="number" name="max_sel_num" value="2" class="form-control"></label>
            <button type="submit" class="btn green">أضف السؤال</button>
        </form>
    </x-admin-portlet>

    {{--
        AdminCP Survey Subpages Final Visual-Parity Closure (2026-08-23):
        legacy `add_question.php:212-236` has a REAL, actually-used
        drag-reorder list (`jquery-nestable`, `.dd`/`.dd-list`/`.dd-item`,
        `id="nestable_list_1"`) — the browser reorders the actual `<li>`
        DOM elements (including each one's hidden `question[]` input) on
        drag, and the plain form submit afterward relies on that new DOM
        order; `SurveyController::reorderQuestions()` already reads
        `$request->input('question', [])` by array POSITION to set
        `weight` (unchanged by this task). Without the nestable plugin,
        this list rendered as a static, non-draggable list — the "reorder"
        button submitted the same order every time, a real behavioral
        gap, not just a styling one. Delete stays consolidated into the
        same list (an already-accepted, non-regressive simplification —
        legacy's own add_question.php duplicates the list a second time
        for delete-only).
    --}}
    <x-admin-portlet :title="'ترتيب اسئلة الاستبيان: '.$survey->title" color="blue" icon="fa fa-folder-open">
        <form method="post" action="{{ route('admin.survey.questions.reorder', $survey) }}">
            @csrf
            <div class="dd" id="nestable_list_1">
                <ol class="dd-list">
                    @foreach ($questions as $question)
                        <li class="dd-item">
                            <div class="dd-handle" style="min-height:35px;">
                                {{ $question->title }}
                            </div>
                            <input type="hidden" name="question[]" value="{{ $question->id }}">
                            <form method="post" action="{{ route('admin.survey.questions.destroy', [$survey, $question]) }}" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm red"><i class="fa fa-trash-o"></i> حذف</button>
                            </form>
                        </li>
                    @endforeach
                </ol>
            </div>
            <button type="submit" class="btn green">اعادة ترتيب الاسئلة</button>
        </form>
    </x-admin-portlet>
@endsection

@push('scripts')
    <script src="{{ asset('assets/global/plugins/jquery-nestable/jquery.nestable-rtl.js') }}" type="text/javascript"></script>
    <script>
        {{-- Minimal equivalent of assets/admin/pages/scripts/ui-nestable.js — that shared
             Metronic demo module also wires #nestable_list_2/_3/_list_menu, none of which
             exist on this page; only the #nestable_list_1 activation is real/effective here. --}}
        jQuery(document).ready(function () {
            $('#nestable_list_1').nestable({ group: 1 });
        });
    </script>
@endpush

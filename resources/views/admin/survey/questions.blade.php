@extends('layouts.admin')

@section('title', 'اسئلة الاستبيان: '.$survey->title)

@section('content')
    <section aria-label="اضافة سؤال جديد">
        <form method="post" action="{{ route('admin.survey.questions.store', $survey) }}">
            @csrf
            <label>نص السؤال <input type="text" name="title" required></label>
            <label>تعليق توضيحي <input type="text" name="des"></label>
            <label>سؤال إجباري
                <select name="required">
                    <option value="1">نعم</option>
                    <option value="0">لا</option>
                </select>
            </label>
            <label>نوع السؤال
                <select name="question_type" required>
                    @foreach (\App\Domain\Admin\Models\SurveyQuestion::QUESTION_TYPES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>الاختيار الأول <input type="text" name="question_options[]"></label>
            <label>الاختيار الثاني <input type="text" name="question_options[]"></label>
            <label>الحد الأقصى للطول (للنص القصير فقط) <input type="text" name="max_len"></label>
            <label>الحد الأقصى للإختيارات <input type="number" name="max_sel_num" value="2"></label>
            <button type="submit">أضف السؤال</button>
        </form>
    </section>

    <section aria-label="ترتيب اسئلة الاستبيان">
        <form method="post" action="{{ route('admin.survey.questions.reorder', $survey) }}">
            @csrf
            <ol>
                @foreach ($questions as $question)
                    <li>
                        {{ $question->title }}
                        <input type="hidden" name="question[]" value="{{ $question->id }}">
                        <form method="post" action="{{ route('admin.survey.questions.destroy', [$survey, $question]) }}" style="display:inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit">حذف</button>
                        </form>
                    </li>
                @endforeach
            </ol>
            <button type="submit">اعادة ترتيب الاسئلة</button>
        </form>
    </section>
@endsection

@extends('layouts.admin')

@section('title', 'حذف الفتوى')

@section('content')
    {{-- A5 «حذف» — OLD w2a_fatwa_del_question()'s confirmation page (fatwa_question_del.htm). It deletes the fatwa itself;
         to take it off one question only, use «حذف الفتوى من على سؤال». --}}
    <x-admin-portlet title="حذف الفتوى" color="red" icon="fa fa-trash-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.fatwa.fatwas.destroy', $fatwa) }}" dir="rtl">
            @csrf
            @method('DELETE')
            <x-admin.contextual-return />
            <center>
                <p><font size="5"><b>هل ترغب في حذف هذا الفتوى {{ $fatwa->question_text }} ??</b></font></p>
                @if (count($questions) > 1)
                    <p>هذه الفتوى مرتبطة بأكثر من سؤال وسيتم حذفها من كل الأسئلة:</p>
                @endif
                <ul class="list-unstyled">
                    @foreach ($questions as $linked)
                        <li>{{ $linked['title'] }}</li>
                    @endforeach
                </ul>
                <button type="submit" class="btn red">نعم متأكد</button>
            </center>
        </form>
        <center><a href="{{ $backUrl }}">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection

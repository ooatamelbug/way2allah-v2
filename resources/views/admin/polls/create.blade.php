@extends('layouts.admin')

@section('title', 'أنشئ استفتاءا جديدا')

@section('content')
    {{-- OLD Admin Migration Phase 4 — OLD poll_createPoll(): a title and twelve
         choice slots. OLD's optional "announce poll" News story is not
         reproduced (plan §23.3). --}}
    <x-admin-portlet title="أنشئ استفتاءا جديدا" color="green" icon="fa fa-plus">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.polls.store') }}">
            @csrf
            <x-admin.contextual-return />
            <label>عنوان الإستفتاء <input type="text" name="pollTitle" value="{{ old('pollTitle') }}" maxlength="100" required class="form-control"></label>
            <p class="text-muted">الرجاء ادخال كل خيار في حقل واحد</p>
            <table class="table table-light">
                @for ($i = 1; $i <= $slots; $i++)
                    <tr>
                        <td>إختيار {{ $i }}:</td>
                        <td><input type="text" name="options[{{ $i }}]" value="{{ old('options.'.$i) }}" maxlength="50" class="form-control"></td>
                    </tr>
                @endfor
            </table>
            <button type="submit" class="btn green">أنشئ الإستفتاء</button>
            <a href="{{ route('admin.polls.index') }}" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection

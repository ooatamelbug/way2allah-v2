@extends('layouts.admin')

@php
    $titles = [
        'topics' => ['add' => 'اضافة السؤال لأكثر من موضوع', 'move' => 'نقل السؤال من موضوع لأخر', 'remove' => 'حذف السؤال من موضوع'],
        'questions' => ['add' => 'اضافة الفتوى لأكثر من سؤال', 'move' => 'نقل الفتوى من سؤال لأخر', 'remove' => 'حذف الفتوى من سؤال'],
    ];
    $noun = $kind === 'topics' ? 'المواضيع' : 'الاسئلة';
    $linkedIds = array_column($linked, 'id');
    $choices = array_values(array_filter($options, fn ($o) => ! in_array($o['id'], $linkedIds, true)));
@endphp

@section('title', $titles[$kind][$mode])

@section('content')
    {{-- Q4 / Q5 / Q6 (a question's topics) and A1 / A2 / A3 (a fatwa's questions) — OLD add_question_many_topics.htm,
         move_question_many_topics.htm and delete_question_many_topics.htm, each with OLD's confirmation for a move or a
         removal. «اضافة» appends to the stored list (its order, and so the first item, is kept); a removal always
         leaves at least one. --}}
    <x-admin-portlet :title="$titles[$kind][$mode]" color="green" icon="fa fa-sitemap">
        @include('admin.mirrors._errors')
        <p><b>{{ $kind === 'topics' ? 'السؤال' : 'الفتوى' }} : </b>{{ \Illuminate\Support\Str::limit((string) $subject, 300) }}</p>
        <p><b>{{ $noun }} المختارة حاليا : </b></p>
        <ol data-fatwa-linked>
            @foreach ($linked as $item)
                <li>{{ $item['title'] }}</li>
            @endforeach
        </ol>
        @if ($blocked !== null)
            <div class="alert alert-danger" data-fatwa-blocked>{{ $blocked }}</div>
            <p><a href="{{ $backUrl }}" class="btn default">( الرجوع للخلف )</a></p>
        @else
        <form method="post" action="{{ $action }}" dir="rtl"
            @if ($mode !== 'add') onsubmit="return confirm({{ \Illuminate\Support\Js::from($mode === 'move' ? 'هل أنت متأكد من النقل؟' : 'هل أنت متأكد من الحذف؟') }})" @endif>
            @csrf
            @method('PUT')
            <x-admin.contextual-return />
            @if ($mode === 'remove')
                <p><b>{{ $kind === 'topics' ? 'اختر مواضيع لتحذف من السؤال' : 'اختر اسئلة لتحذف من الفتوى' }}</b> (يجب أن يبقى واحد على الأقل)</p>
                @foreach ($linked as $item)
                    <p><label><input type="checkbox" name="ids[]" value="{{ $item['id'] }}" @checked(in_array((string) $item['id'], (array) old('ids', []), true))> {{ $item['title'] }}</label></p>
                @endforeach
            @else
                <p><input type="search" placeholder="بحث" size="40" oninput="(function(q,s){q=q.trim();for(var i=0;i<s.options.length;i++){s.options[i].hidden=q!==''&&s.options[i].text.indexOf(q)===-1;}})(this.value,document.getElementById('fatwa-relation-choices'))"></p>
                <p><b>{{ $mode === 'add' ? 'كل '.$noun : ($kind === 'topics' ? 'الموضوع الجديد' : 'السؤال الجديد') }}</b></p>
                <select id="fatwa-relation-choices" name="{{ $mode === 'add' ? 'ids[]' : 'id' }}" @if ($mode === 'add') multiple @endif size="15" style="width:100%" required>
                    @foreach ($choices as $option)
                        <option value="{{ $option['id'] }}" @selected(in_array((string) $option['id'], array_map('strval', (array) old($mode === 'add' ? 'ids' : 'id', [])), true))>{{ $option['title'] }}</option>
                    @endforeach
                </select>
            @endif
            <p><button type="submit" class="btn green">{{ ['add' => 'اضافة', 'move' => 'نقل', 'remove' => 'حذف'][$mode] }}</button>
                <a href="{{ $backUrl }}" class="btn default">( الرجوع للخلف )</a></p>
        </form>
        @endif
    </x-admin-portlet>
@endsection

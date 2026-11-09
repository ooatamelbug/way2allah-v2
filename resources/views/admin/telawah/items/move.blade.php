@extends('layouts.admin')

@section('title', 'نقل التلاوة')

@section('content')
    {{-- OLD TelawahmoveTelawah() + move_telawa_form.htm: choosing a reciter reloads its sub-groups (OLD load_sub_categories). --}}
    <x-admin-portlet title="نقل التلاوة" color="green" icon="fa fa-arrows">
        @include('admin.mirrors._errors')
        <p><b>عنوان التلاوة : </b>{{ $item->title }}</p>
        <form method="post" action="{{ route('admin.telawah.items.move.save', $item) }}" dir="rtl">
            @csrf
            @method('PUT')
            <p><b>إختر الشيخ : </b>
                <select name="group_id" id="w2a-move-reciter">
                    <option value="0">إختر الشيخ</option>
                    @foreach ($reciters as $row)
                        <option value="{{ $row->id }}" @selected((int) old('group_id', $reciterId) === (int) $row->id)>{{ $row->title }}</option>
                    @endforeach
                </select>
            </p>
            <p><b>إختر القسم التابع لهذا الشيخ : </b>
                <select name="sub_group_id" id="w2a-move-sub-group">
                    @include('admin.telawah.items._move-sub-groups', ['selected' => (int) old('sub_group_id', $subGroupId)])
                </select>
            </p>
            <button type="submit" class="btn green">نـقـل</button>
            <a href="/recite-item-{{ $item->id }}.htm" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection

@push('scripts')
    <script>
        $(function () {
            $('#w2a-move-reciter').on('change', function () {
                $.get(@json(route('admin.telawah.move-options.sub-groups')), {reciter: this.value}, function (html) {
                    $('#w2a-move-sub-group').html(html);
                });
            });
        });
    </script>
@endpush

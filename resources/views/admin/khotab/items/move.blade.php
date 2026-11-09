@extends('layouts.admin')

@section('title', 'نقل مادة')

@section('content')
    {{-- OLD MoveKhotab() + move_khotab.htm (modules/Khotab/admin/index.php:1284): choosing an author
         reloads its groups and series (OLD KhotabAjaxGroupsSeries); choosing a group reloads that
         group's series (OLD KhotabAjaxSeries). --}}
    <x-admin-portlet :title="'نقل مادة : '.trim(($author->prename ?? '').' '.($author->name ?? ''))" color="green" icon="fa fa-arrows">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.khotab.items.move.save', ['item' => $item->id]) }}" dir="rtl">
            @csrf
            @method('PUT')
            <b>عنوان المادة : </b> {{ $item->title }}<br>
            <div id="Authors">
                <br><b>الداعية : </b>
                <select name="author_id" id="w2a-move-author">
                    @foreach ($authors as $row)
                        <option value="{{ $row->id }}" @selected((int) $row->id === (int) $item->author)>{{ $row->name }}</option>
                    @endforeach
                </select>
            </div>
            <div id="GroupsSeries">
                <div id="Groups">
                    <br><b>المجموعة : </b>
                    <select name="group_id" id="group_id">
                        <option value="0">بدون مجموعة</option>
                        @foreach ($groups as $row)
                            <option value="{{ $row->id }}" @selected((int) $row->id === (int) $item->group_id)>{{ $row->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="Series">
                    <br><b>السلسلة : </b>
                    <select name="ser_id" id="ser_id">
                        <option value="0">بدون سلسلة</option>
                        @foreach ($series as $row)
                            <option value="{{ $row->id }}" @selected((int) $row->id === (int) $item->ser_id)>{{ $row->title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <br><br>
            <button type="submit" class="btn green">نقل المادة</button>
            <a href="/khotab-item-{{ $item->id }}.htm" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection

@push('scripts')
    <script>
        $(function () {
            var vedio = @json((int) $item->vedio);
            $('#w2a-move-author').on('change', function () {
                $.get(@json(route('admin.khotab.move-options.groups')), {vedio: vedio, author: this.value}, function (html) {
                    $('#GroupsSeries').html(html);
                });
            });
            $('#GroupsSeries').on('change', '#group_id', function () {
                $.get(@json(route('admin.khotab.move-options.series')), {vedio: vedio, group_id: this.value}, function (html) {
                    $('#Series').html(html);
                });
            });
        });
    </script>
@endpush

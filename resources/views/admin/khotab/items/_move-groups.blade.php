{{-- OLD AjaxGroupsSeries(): the author's groups (newest first) and the author's series outside any group. --}}
<div id="Groups">
    <br><b>المجموعة : </b>
    <select name="group_id" id="group_id">
        <option value="0">بدون مجموعة</option>
        @foreach ($groups as $row)
            <option value="{{ $row->id }}">{{ $row->title }}</option>
        @endforeach
    </select>
</div>
<div id="Series">
    <br><b>السلسلة : </b>
    <select name="ser_id" id="ser_id">
        <option value="0">بدون سلسلة</option>
        @foreach ($series as $row)
            <option value="{{ $row->id }}">{{ $row->title }}</option>
        @endforeach
    </select>
</div>

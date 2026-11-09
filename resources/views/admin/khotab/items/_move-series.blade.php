{{-- OLD AjaxSeries(): the group's series (newest first); disabled — so not submitted — when there are none. --}}
<b>ضمن سلسلة : </b><select id="ser_id" name="ser_id" @disabled($series->isEmpty())>
    <option value="0">بدون سلسلة</option>
    @foreach ($series as $row)
        <option value="{{ $row->id }}">{{ $row->title }}</option>
    @endforeach
</select>

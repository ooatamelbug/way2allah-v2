{{-- OLD load_sub_categories(): «إختر القسم» (= the reciter itself), then the reciter's direct sub-groups. --}}
<option value="0">إختر القسم</option>
@foreach ($subGroups as $row)
    <option value="{{ $row->id }}" @selected((int) ($selected ?? 0) === (int) $row->id)>{{ $row->title }}</option>
@endforeach

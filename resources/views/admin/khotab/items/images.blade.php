@extends('layouts.admin')

@section('title', 'سحب الصور')

@section('content')
    {{-- What OLD's get_gif_jpg.php?id= printed in its popup: the item, then its animated and still images. --}}
    <x-admin-portlet title="سحب الصور" color="green" icon="fa fa-picture-o">
        <center>
            <b>{{ $item->title }}</b><br>
            @if (! $result['archive'])
                <br>رابط هذه المادة ليس على archive.org ، لم يتم سحب أي صور<br>
            @else
                @if ($gif !== null)
                    <hr>الصورة المتحركة للمادة : <br><img src="{{ $gif }}" alt=""><br>
                @else
                    <br>لا يوجد صور متحركة لهذه المادة<br>
                @endif
                @if ($frame !== null)
                    <hr>الصورة الثابتة للمادة : <br><img src="{{ $frame }}" alt="" style="max-width:100%"><br>
                    {{-- F10 / B5: the archive frame is stored even when a custom cover exists; the site shows the cover. --}}
                    @if (\App\Domain\Content\Support\KhotabCover::url((int) $item->id) !== null)
                        <p class="help-block">لهذه المادة صورة غلاف مخصصة، والموقع يعرضها بدلاً من صورة الأرشيف.</p>
                    @endif
                @else
                    <br>لا يوجد صور ثابتة لهذه المادة<br>
                @endif
            @endif
        </center>
        <center><a href="/khotab-item-{{ $item->id }}.htm">( الرجوع إلى صفحة المادة )</a></center>
    </x-admin-portlet>
@endsection

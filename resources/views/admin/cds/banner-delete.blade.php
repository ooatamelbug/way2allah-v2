@extends('layouts.admin')

@section('title', $release->title)

@section('content')
    {{-- OLD W2ACDDelete_banner() + delete_banner.htm (modules/w2acd/admin/index.php): the confirmation
         exists only for a CD with `banner = 1` whose file is present — for any other CD OLD's page was
         empty, and so is this one. --}}
    <x-admin-portlet :title="$release->title" color="red" icon="fa fa-picture-o">
        @if ($current !== null)
            <p align="center"><img src="{{ $current['url'] }}" border="0" alt="{{ $release->title }}"></p>
            <form method="post" action="{{ route('admin.cds.banner.destroy', ['release' => $release->id]) }}">
                @csrf
                @method('DELETE')
                <center><span style="font-weight:bold; color:#936">هل أنت متأكد من أنك تريد حذف هذا البانر؟</span></center><br>
                <div align="center">
                    <button type="submit" class="btn red">نعم</button>
                    <a href="/cds-item-{{ $release->id }}.htm" class="btn default">لا</a>
                </div>
            </form>
        @endif
    </x-admin-portlet>
@endsection

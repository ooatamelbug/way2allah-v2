@extends('layouts.admin')

@section('title', $item->title)

@section('content')
    {{-- OLD KhotabDelete_banner() + delete_banner.htm (modules/Khotab/admin/index.php:5033): the
         confirmation exists only for an item with `banner = 1` whose file is present — for any other
         item (a requested banner, -1, included) OLD's page was empty, and so is this one. --}}
    <x-admin-portlet :title="$item->title" color="red" icon="fa fa-picture-o">
        @if ($current !== null)
            <p align="center"><img src="{{ $current }}" border="0" alt="{{ $item->title }}"></p>
            <form method="post" action="{{ route('admin.khotab.items.banner.destroy', ['item' => $item->id]) }}">
                @csrf
                @method('DELETE')
                <center><span style="font-weight:bold; color:#936">هل أنت متأكد من أنك تريد حذف هذا البانر؟</span></center><br>
                <div align="center">
                    <button type="submit" class="btn red">نعم</button>
                    <a href="/khotab-item-{{ $item->id }}.htm" class="btn default">لا</a>
                </div>
            </form>
        @endif
    </x-admin-portlet>
@endsection

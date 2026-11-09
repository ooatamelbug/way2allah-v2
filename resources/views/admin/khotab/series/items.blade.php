@extends('layouts.admin')

@section('title', 'قائمة المواد')

@section('content')
    {{-- OLD Lists() with ser_id and no mode + lists_khotab_{head,cont,foot}.htm: every item of the series, 50 a page. --}}
    <x-admin-portlet :title="'قائمة المواد ( '.$total.' مادة )'" color="green" icon="fa fa-list">
        {{ $items->links() }}
        <table class="w2atable table table-light" cellspacing="0">
            <thead>
                <tr>
                    <th scope="col">م</th>
                    <th scope="col">عنوان المادة</th>
                    <th scope="col">السلسلة</th>
                    <th scope="col">المجموعة</th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <th scope="row">الكل</th>
                    <td colspan="3">{{ $total }} مادة</td>
                </tr>
            </tfoot>
            <tbody>
                @foreach ($items as $item)
                    <tr @if ($loop->odd) class="odd" @endif>
                        <td>{{ $loop->iteration }}</td>
                        <td width="100%">
                            <a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a>&nbsp;<a href="/khotab-group-{{ $item->group_id }}.htm">{{ $item->group_title }}</a>&nbsp;-&nbsp;{{ $item->prename }} {{ $item->name }}
                        </td>
                        <td>@if ((int) $item->ser_id > 0 && $item->series_title !== null)<a href="/khotab-series-{{ $item->ser_id }}.htm">{{ $item->series_title }}</a>@endif</td>
                        <td><a href="/khotab-group-{{ $item->group_id }}.htm">{{ $item->group_title }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $items->links() }}
        <p><a href="/khotab-series-{{ $series->id }}.htm" class="btn default">رجوع</a></p>
    </x-admin-portlet>
@endsection

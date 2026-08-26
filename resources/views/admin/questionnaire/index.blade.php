@extends('layouts.admin')

@section('title', 'قائمة المشاركين بالاستبيان')

@section('content')
    {{-- Portlet color/icon use the shared purple/fa-comments convention observed across the module's other pages; the exact legacy list-view portlet caption for this specific branch of questionnaire/index.php was not independently re-verified this pass (SOURCE_DOM_PARITY unverified for this one caption). --}}
    <x-admin-portlet title="قائمة المشاركين بالاستبيان">
        <table class="table table-striped table-hover table-light">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>رقم الهاتف</th>
                    <th>البريد الألكتروني</th>
                    <th>الفيسبوك</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($responses as $response)
                    <tr>
                        <td><a href="{{ route('admin.questionnaire.show', $response) }}">{{ $response->username }}</a></td>
                        <td>{{ $response->mobile }}</td>
                        <td><a href="mailto:{{ $response->email }}">{{ $response->email }}</a></td>
                        <td>
                            @if (trim((string) $response->facebook) !== '')
                                <a href="{{ $response->facebook }}" target="_blank">فيسبوك الداعية</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-admin-portlet>
@endsection

@extends('layouts.admin')

@section('title', 'تقارير الإعلانات')

@section('content')
    {{-- F01 / B6a — OLD view_report(): type, period (required) and one position; every ENABLED ad overlapping the
         period, grouped into «المجموعة N» rows of non-overlapping ads, with OLD's count / share summary.
         A table instead of OLD's bar chart (see App\Domain\Admin\Support\AdsReport). --}}
    @php($ads = \App\Domain\Admin\Support\LegacyAds::class)
    <x-admin-portlet title="بيانات التقرير المطلوبة" color="blue" icon="fa fa-bar-chart">
        @include('admin.mirrors._errors')
        <p><a href="{{ route('admin.ads.index') }}">عودة إلى قائمة الإعلانات</a></p>
        <form method="get" action="{{ route('admin.ads.reports') }}" class="form-inline">
            <input type="hidden" name="search" value="1">
            <label>نوع الإعلان :
                <select name="type" class="form-control">
                    <option value="2" @selected($input['type'] === 2)>الكل</option>
                    <option value="1" @selected($input['type'] === 1)>إعلان مصور</option>
                    <option value="0" @selected($input['type'] === 0)>جوجل ادسنس</option>
                </select>
            </label>
            <label>من : <input type="date" name="from" value="{{ $input['from'] }}" required class="form-control"></label>
            <label>إلى : <input type="date" name="to" value="{{ $input['to'] }}" required class="form-control"></label>
            <label>مكان الإعلان في الصفحة :
                <select name="position" class="form-control">
                    @foreach ($ads::POSITIONS as $id => $label)
                        <option value="{{ $id }}" @selected($input['position'] === $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="btn blue">بــحــث</button>
        </form>
    </x-admin-portlet>

    @if ($report !== null)
        <x-admin-portlet title="كل الإعلانات السارية والمعروضة" color="green" icon="fa fa-list">
            <p>
                من : <span dir="ltr">{{ $input['from'] }}</span> &nbsp; إلى : <span dir="ltr">{{ $input['to'] }}</span>
                &nbsp;·&nbsp; في المكان : {{ $ads::positionLabel($input['position']) }}
                @if ($input['type'] !== 2)
                    &nbsp;·&nbsp; نوع الإعلان : {{ $ads::typeLabel($input['type']) }}
                @endif
            </p>
            <p>عدد الإعلانات: {{ $report['count'] }} &nbsp;·&nbsp; مجموع النسب: {{ $report['percentage_sum'] }}% &nbsp;·&nbsp; عدد المجموعات: {{ $report['lanes'] }}</p>
            <table class="table table-striped table-hover table-light">
                <thead>
                    <tr><th>المجموعة</th><th>إسم الإعلان</th><th>تاريخ بداية الإعلان</th><th>تاريخ نهاية الإعلان</th><th>نوع الإعلان</th><th>نسبة الإعلان</th><th>أيام داخل المدة</th></tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            <td>المجموعة {{ $row['lane'] }}</td>
                            <td>
                                @if ($row['type'] !== $ads::TYPE_CODE || $isSuperAdmin)
                                    <a href="{{ route('admin.ads.edit', $row['id']) }}">{{ $row['name'] }}</a>
                                @else
                                    {{ $row['name'] }}
                                @endif
                            </td>
                            <td dir="ltr">{{ $row['startdate'] }}</td>
                            <td dir="ltr">{{ $row['enddate'] }}</td>
                            <td>{{ $ads::typeLabel($row['type']) }}</td>
                            <td>{{ $row['percentage'] }}%</td>
                            <td>{{ $row['days_in_range'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">لا توجد إعلانات معروضة في هذه المدة لهذا المكان.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-admin-portlet>
    @endif
@endsection
